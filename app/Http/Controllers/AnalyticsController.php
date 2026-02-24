<?php

namespace App\Http\Controllers;

use App\Exports\ConversationsExport;
use App\Models\Message;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
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
}
