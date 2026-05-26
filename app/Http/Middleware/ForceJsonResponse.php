<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force Accept: application/json on API routes so errors (e.g. 429) return JSON, not HTML.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && !$request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
