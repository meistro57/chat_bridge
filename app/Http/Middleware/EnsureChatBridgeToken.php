<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerToken = $request->header('X-CHAT-BRIDGE-TOKEN');

        if (! $headerToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $envToken = config('services.chat_bridge.token');

        if ($envToken && $headerToken !== $envToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
