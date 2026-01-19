<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\EmbeddingService;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'mcp_mode' => 'laravel-native',
            'version' => '1.0.0',
            'vector_search' => true,
        ]);
    }

    public function stats()
    {
        return response()->json([
            'conversations_count' => Conversation::count(),
            'messages_count' => Message::count(),
            'embeddings_count' => Message::whereNotNull('embedding')->count(),
        ]);
    }

    public function recentChats(Request $request)
    {
        $limit = $request->query('limit', 10);

        return Conversation::latest()->limit($limit)->get();
    }

    public function conversation(Conversation $conversation)
    {
        return $conversation->load('messages');
    }

    public function search(Request $request)
    {
        $keyword = $request->query('keyword');

        return Message::where('content', 'like', "%{$keyword}%")
            ->with(['conversation', 'persona'])
            ->latest()
            ->limit(10)
            ->get();
    }

    public function contextualMemory(Request $request, EmbeddingService $embeddingService)
    {
        $topic = $request->query('topic');
        $limit = (int) $request->query('limit', 5);

        if (! $topic) {
            return response()->json([]);
        }

        try {
            // Get query embedding
            $queryEmbedding = $embeddingService->getEmbedding($topic);

            // Fetch messages with embeddings (limited set for performance in PHP)
            $messages = Message::whereNotNull('embedding')
                ->where('role', 'assistant')
                ->with(['conversation', 'persona'])
                ->latest()
                ->limit(100)
                ->get();

            if ($messages->isEmpty()) {
                // Fallback to keyword search
                return Message::where('role', 'assistant')
                    ->where('content', 'like', "%{$topic}%")
                    ->with(['conversation', 'persona'])
                    ->latest()
                    ->limit($limit)
                    ->get();
            }

            // Calculate cosine similarity
            $results = $messages->map(function ($message) use ($queryEmbedding) {
                $message->similarity = $this->cosineSimilarity($queryEmbedding, $message->embedding);

                return $message;
            })
                ->filter(fn ($m) => $m->similarity > 0.3)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();

            return response()->json($results);
        } catch (\Exception $e) {
            // Fallback to keyword search on error (e.g. OpenAI down)
            return Message::where('role', 'assistant')
                ->where('content', 'like', "%{$topic}%")
                ->with(['conversation', 'persona'])
                ->latest()
                ->limit($limit)
                ->get();
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        foreach ($vec1 as $i => $val) {
            $dotProduct += $val * ($vec2[$i] ?? 0);
            $norm1 += $val * $val;
            $norm2 += ($vec2[$i] ?? 0) * ($vec2[$i] ?? 0);
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }
}
