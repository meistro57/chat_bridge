<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $envToken = config('services.chat_bridge.token');

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
