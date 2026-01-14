<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'mcp_mode' => 'laravel-native',
            'version' => '1.0.0',
        ]);
    }

    public function stats()
    {
        return response()->json([
            'conversations_count' => Conversation::count(),
            'messages_count' => \App\Models\Message::count(),
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
        
        return \App\Models\Message::where('content', 'like', "%{$keyword}%")
            ->with(['conversation', 'persona'])
            ->latest()
            ->limit(10)
            ->get();
    }

    public function contextualMemory(Request $request)
    {
        $topic = $request->query('topic');
        $limit = $request->query('limit', 5);

        // Simple topic-based retrieval via keyword search for now
        return \App\Models\Message::where('role', 'assistant')
            ->where('content', 'like', "%{$topic}%")
            ->with(['conversation', 'persona'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
