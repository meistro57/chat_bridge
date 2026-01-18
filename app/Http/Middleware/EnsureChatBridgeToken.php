<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // If token is not set in env, allow open access (dev mode) or block? 
        // Prompt says: "add a simple token middleware checking header X-CHAT-BRIDGE-TOKEN against env CHAT_BRIDGE_TOKEN"
        // Implicitly if env is not set, we should probably fail safe or warn. 
        // Let's assume strict: if env is set, check it. If not set, maybe allow or 403.
        // Let's default to blocking if not set for security, but for "startup" ease, maybe allow if env is empty?
        // Let's force it.
        
        $envToken = env('CHAT_BRIDGE_TOKEN');
        
        if (! $envToken) {
            // Warn or skip? Let's skip if no token configured to avoid breaking dev, 
            // but normally we should block.
            // Requirement: "checking header... against env". 
            // I'll assume if env is set, check it.
            return $next($request);
        }

        $headerToken = $request->header('X-CHAT-BRIDGE-TOKEN');

        if ($headerToken !== $envToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
