<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventWritesInReadOnlyMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('safety.read_only_mode', false)) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowedRoutes = config('safety.allowed_mutating_routes', []);
        $allowedPaths = config('safety.allowed_mutating_paths', []);

        if (is_string($routeName) && in_array($routeName, $allowedRoutes, true)) {
            return $next($request);
        }

        foreach ($allowedPaths as $pattern) {
            if (is_string($pattern) && $request->is($pattern)) {
                return $next($request);
            }
        }

        $message = 'Application is in read-only mode. Data changes are disabled.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 423);
        }

        abort(423, $message);
    }
}
