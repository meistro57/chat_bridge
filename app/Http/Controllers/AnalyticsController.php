<?php

namespace App\Http\Controllers;

use App\Exports\ConversationsExport;
use App\Http\Requests\RunAnalyticsSqlRequest;
use App\Models\Message;
use App\Services\AnalyticsService;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function index(): Response
    {
        $user = auth()->user();

        $overview = $this->analyticsService->getOverviewStats($user);
        $metrics = $this->analyticsService->getConversationMetrics($user);
        $tokenUsageByProvider = $this->analyticsService->getTokenUsageByProvider($user);
        $providerUsage = $this->analyticsService->getProviderUsage($user);
        $personaStats = $this->analyticsService->getPersonaPopularity($user);
        $trendData = $this->analyticsService->getTrendData($user, 30);
        $recentConversations = $this->analyticsService->getRecentConversations($user);
        $costEstimation = $this->analyticsService->getCostEstimation($user);

        return Inertia::render('Analytics/Index', [
            'overview' => $overview,
            'metrics' => $metrics,
            'tokenUsageByProvider' => $tokenUsageByProvider,
            'providerUsage' => $providerUsage,
            'personaStats' => $personaStats,
            'trendData' => $trendData,
            'recentConversations' => $recentConversations,
            'costByProvider' => $costEstimation['by_provider'],
        ]);
    }

    public function metrics(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'overview' => $this->analyticsService->getOverviewStats($user),
            'metrics' => $this->analyticsService->getConversationMetrics($user),
            'tokenUsageByProvider' => $this->analyticsService->getTokenUsageByProvider($user),
            'providerUsage' => $this->analyticsService->getProviderUsage($user),
            'personaStats' => $this->analyticsService->getPersonaPopularity($user),
            'trendData' => $this->analyticsService->getTrendData($user, 30),
            'recentConversations' => $this->analyticsService->getRecentConversations($user),
            'costByProvider' => $this->analyticsService->getCostEstimation($user)['by_provider'],
        ]);
    }

    public function query(Request $request): Response
    {
        $user = auth()->user();

        $query = Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['conversation', 'persona']);

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where('content', 'like', "%{$keyword}%");
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->input('persona_id'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->whereHas('conversation', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            });
        }

        $query->orderBy('created_at', $request->input('sort_order', 'desc'));

        $results = $query->paginate($request->input('per_page', 20))
            ->withQueryString();

        return Inertia::render('Analytics/Query', [
            'results' => $results,
            'filters' => $request->only(['keyword', 'date_from', 'date_to', 'persona_id', 'role', 'status', 'sort_order', 'per_page', 'format']),
            'personas' => $this->analyticsService->getPersonas($user),
            'sqlPlayground' => [
                'defaultLimit' => 100,
                'currentUserId' => $user->id,
                'schema' => $this->getSqlSchema(),
                'examples' => $this->getSqlExamples(),
                'keywords' => $this->getSqlKeywords(),
            ],
        ]);
    }

    public function runSql(RunAnalyticsSqlRequest $request): JsonResponse
    {
        $sql = trim((string) $request->input('sql'));
        $limit = (int) $request->integer('limit', 100);

        try {
            $query = $this->prepareReadOnlyQuery($sql, $limit);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $startedAt = microtime(true);

        try {
            $rows = DB::select($query['sql']);
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'SQL error: '.$exception->getMessage(),
            ], 422);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $normalizedRows = array_map(fn (object|array $row) => $this->normalizeRow($row), $rows);
        $truncated = false;

        if ($query['can_truncate'] && count($normalizedRows) > $limit) {
            $normalizedRows = array_slice($normalizedRows, 0, $limit);
            $truncated = true;
        }

        return response()->json([
            'columns' => array_keys($normalizedRows[0] ?? []),
            'rows' => $normalizedRows,
            'row_count' => count($normalizedRows),
            'truncated' => $truncated,
            'limit' => $limit,
            'execution_ms' => $durationMs,
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');
        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $writerType = $format === 'xlsx' ? Excel::XLSX : Excel::CSV;
        $filename = 'chat-analytics-export-'.now()->format('Y-m-d-His').'.'.$extension;

        $export = new ConversationsExport(auth()->user(), $request->only([
            'keyword',
            'date_from',
            'date_to',
            'persona_id',
            'role',
            'status',
        ]));

        return $export->download($filename, $writerType);
    }

    public function clearHistory(Request $request): RedirectResponse
    {
        $user = $request->user();
        $conversationIds = $user->conversations()->pluck('id');

        DB::transaction(function () use ($user, $conversationIds) {
            foreach ($conversationIds as $conversationId) {
                Cache::forget("conversation.stop.{$conversationId}");
            }

            $user->conversations()->delete();
        });

        $this->analyticsService->invalidateUserCache($user);

        return redirect()
            ->route('analytics.index')
            ->with('success', 'Conversation history cleared. Personas and API keys were not changed.');
    }

    /**
     * @return array{sql:string,can_truncate:bool}
     */
    private function prepareReadOnlyQuery(string $sql, int $limit): array
    {
        $statement = rtrim(trim($sql), ';');

        if ($statement === '') {
            throw new InvalidArgumentException('Enter a SQL query to run.');
        }

        if (! preg_match('/^(select|with)\b/i', $statement)) {
            throw new InvalidArgumentException('Only SELECT and WITH queries are allowed.');
        }

        if (str_contains($statement, ';')) {
            throw new InvalidArgumentException('Only a single SQL statement is allowed.');
        }

        $blockedKeywords = [
            'insert',
            'update',
            'delete',
            'drop',
            'alter',
            'truncate',
            'create',
            'replace',
            'attach',
            'detach',
            'vacuum',
            'reindex',
            'pragma',
        ];

        foreach ($blockedKeywords as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $statement)) {
                throw new InvalidArgumentException('Only read-only SQL is allowed in this runner.');
            }
        }

        $hasExplicitLimit = preg_match('/\blimit\s+\d+(\s*,\s*\d+)?(\s+offset\s+\d+)?\s*$/i', $statement) === 1;

        if ($hasExplicitLimit) {
            return [
                'sql' => $statement,
                'can_truncate' => false,
            ];
        }

        return [
            'sql' => $statement.' LIMIT '.($limit + 1),
            'can_truncate' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRow(object|array $row): array
    {
        $values = is_array($row) ? $row : (array) $row;

        foreach ($values as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $values[$key] = $value->format(DATE_ATOM);

                continue;
            }

            if (is_resource($value)) {
                $values[$key] = null;
            }
        }

        return $values;
    }

    /**
     * @return array<int, array{name:string, columns:array<int, array{name:string,type:string}>}>
     */
    private function getSqlSchema(): array
    {
        $availableTables = collect(Schema::getTableListing(schemaQualified: false))
            ->map(fn (string $table): string => str_contains($table, '.') ? explode('.', $table, 2)[1] : $table)
            ->values();

        $analyticsTables = collect([
            'conversations',
            'messages',
            'personas',
            'transmissions',
            'conversation_templates',
            'model_prices',
            'users',
        ])->filter(fn (string $table): bool => $availableTables->contains($table));

        return $analyticsTables->map(function (string $table): array {
            $columns = collect(Schema::getColumns($table))
                ->map(function (array $column): array {
                    return [
                        'name' => (string) ($column['name'] ?? $column['column_name'] ?? ''),
                        'type' => (string) ($column['type_name'] ?? $column['type'] ?? 'unknown'),
                    ];
                })
                ->filter(fn (array $column): bool => $column['name'] !== '')
                ->values()
                ->all();

            return [
                'name' => $table,
                'columns' => $columns,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{id:string,title:string,description:string,sql:string}>
     */
    private function getSqlExamples(): array
    {
        return [
            [
                'id' => 'recent-conversations',
                'title' => 'Recent Conversations',
                'description' => 'Latest 25 conversations for your account.',
                'sql' => "SELECT id, status, provider_a, provider_b, created_at\nFROM conversations\nWHERE user_id = {{auth_user_id}}\nORDER BY created_at DESC\nLIMIT 25",
            ],
            [
                'id' => 'messages-by-role',
                'title' => 'Messages by Role',
                'description' => 'Count user vs assistant messages.',
                'sql' => "SELECT m.role, COUNT(*) AS total_messages\nFROM messages m\nINNER JOIN conversations c ON c.id = m.conversation_id\nWHERE c.user_id = {{auth_user_id}}\nGROUP BY m.role\nORDER BY total_messages DESC",
            ],
            [
                'id' => 'persona-activity',
                'title' => 'Persona Activity',
                'description' => 'Top personas by message volume.',
                'sql' => "SELECT COALESCE(p.name, 'Unknown Persona') AS persona_name, COUNT(*) AS total_messages\nFROM messages m\nINNER JOIN conversations c ON c.id = m.conversation_id\nLEFT JOIN personas p ON p.id = m.persona_id\nWHERE c.user_id = {{auth_user_id}}\nGROUP BY COALESCE(p.name, 'Unknown Persona')\nORDER BY total_messages DESC\nLIMIT 20",
            ],
            [
                'id' => 'daily-volume',
                'title' => 'Daily Volume',
                'description' => 'Daily conversation + message count over the last 30 days.',
                'sql' => "SELECT DATE(c.created_at) AS day, COUNT(DISTINCT c.id) AS conversations, COUNT(m.id) AS messages\nFROM conversations c\nLEFT JOIN messages m ON m.conversation_id = c.id\nWHERE c.user_id = {{auth_user_id}}\nGROUP BY DATE(c.created_at)\nORDER BY day DESC\nLIMIT 30",
            ],
            [
                'id' => 'failed-conversations',
                'title' => 'Failed Conversations',
                'description' => 'Most recent failed conversations and metadata.',
                'sql' => "SELECT id, status, provider_a, provider_b, metadata, updated_at\nFROM conversations\nWHERE user_id = {{auth_user_id}} AND status = 'failed'\nORDER BY updated_at DESC\nLIMIT 50",
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getSqlKeywords(): array
    {
        return [
            'SELECT',
            'FROM',
            'WHERE',
            'GROUP BY',
            'ORDER BY',
            'LIMIT',
            'OFFSET',
            'HAVING',
            'INNER JOIN',
            'LEFT JOIN',
            'RIGHT JOIN',
            'COUNT',
            'SUM',
            'AVG',
            'MIN',
            'MAX',
            'DISTINCT',
            'CASE',
            'WHEN',
            'THEN',
            'ELSE',
            'END',
            'DATE',
            'COALESCE',
            'NULLIF',
            'LIKE',
            'IN',
            'NOT IN',
            'BETWEEN',
            'ASC',
            'DESC',
        ];
    }
}
