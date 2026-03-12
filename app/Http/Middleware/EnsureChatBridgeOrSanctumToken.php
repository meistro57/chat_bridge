<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatBridgeOrSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Strategy 1: existing shared env token (backward compat)
        $headerToken = $request->header('X-CHAT-BRIDGE-TOKEN');
        if ($headerToken) {
            $envToken = config('services.chat_bridge.token');
            if (! $envToken || $headerToken === $envToken) {
                return $next($request);
            }
        }

        // Strategy 2: Sanctum personal access token
        if (auth('sanctum')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
