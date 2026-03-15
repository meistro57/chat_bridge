<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() || auth('sanctum')->check()) {
            return $next($request);
        }

        $bearerToken = $request->bearerToken();

        if (is_string($bearerToken) && $bearerToken !== '') {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken !== null && ($accessToken->expires_at === null || $accessToken->expires_at->isFuture())) {
                $tokenable = $accessToken->tokenable;

                if ($tokenable !== null) {
                    auth()->setUser($tokenable);
                    $request->setUserResolver(static fn () => $tokenable);

                    return $next($request);
                }
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
