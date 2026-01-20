<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Get statistics
        $stats = [
            'total_conversations' => $user->conversations()->count(),
            'total_messages' => Message::whereHas('conversation', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),
            'active_conversations' => $user->conversations()->where('status', 'active')->count(),
            'completed_conversations' => $user->conversations()->where('status', 'completed')->count(),
        ];

        // Get recent activity (last 7 days)
        $recentActivity = Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get persona usage stats
        $personaStats = Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->whereNotNull('persona_id')
            ->select('persona_id', DB::raw('COUNT(*) as message_count'))
            ->groupBy('persona_id')
            ->with('persona')
            ->orderByDesc('message_count')
            ->limit(10)
            ->get()
            ->map(function ($stat) {
                return [
                    'persona_name' => $stat->persona->name ?? 'Unknown',
                    'count' => $stat->message_count,
                ];
            });

        return Inertia::render('Analytics/Index', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'personaStats' => $personaStats,
            'personas' => Persona::orderBy('name')->get(['id', 'name', 'provider']),
        ]);
    }

    public function query(Request $request)
    {
        $user = auth()->user();

        $query = Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['conversation', 'persona']);

        // Filter by keyword
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where('content', 'like', "%{$keyword}%");
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // Filter by persona
        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->input('persona_id'));
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Filter by conversation status
        if ($request->filled('status')) {
            $query->whereHas('conversation', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            });
        }

        // Order
        $query->orderBy('created_at', $request->input('sort_order', 'desc'));

        // Paginate
        $results = $query->paginate($request->input('per_page', 20))
            ->withQueryString();

        return Inertia::render('Analytics/Query', [
            'results' => $results,
            'filters' => $request->only(['keyword', 'date_from', 'date_to', 'persona_id', 'role', 'status', 'sort_order', 'per_page']),
            'personas' => Persona::orderBy('name')->get(['id', 'name', 'provider']),
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();

        $query = Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['conversation', 'persona']);

        // Apply same filters as query method
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where('content', 'like', "%{$keyword}%");
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }
        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->input('persona_id'));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $messages = $query->orderBy('created_at', 'desc')->limit(1000)->get();

        // Create CSV
        $filename = 'chat-export-' . now()->format('Y-m-d-His') . '.csv';
        $filepath = storage_path('app/' . $filename);

        $file = fopen($filepath, 'w');

        // Headers
        fputcsv($file, ['Date', 'Time', 'Conversation ID', 'Persona', 'Role', 'Content', 'Tokens Used']);

        // Data
        foreach ($messages as $message) {
            fputcsv($file, [
                $message->created_at->format('Y-m-d'),
                $message->created_at->format('H:i:s'),
                $message->conversation_id,
                $message->persona->name ?? 'N/A',
                $message->role,
                $message->content,
                $message->tokens_used ?? 0,
            ]);
        }

        fclose($file);

        return response()->download($filepath, $filename)->deleteFileAfterSend();
    }
}
