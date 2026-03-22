<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the Accept header to application/json on all /api/* routes.
 *
 * Without this, unauthenticated requests to protected endpoints return
 * an HTML redirect to /login instead of a 401 JSON response.
 *
 * Register in bootstrap/app.php:
 *   ->withMiddleware(fn (Middleware $m) => $m->api(append: [ForceJsonResponse::class]))
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
