<?php

namespace Allandereal\FilamentApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    /**
     * Render every error (401, 403, 404, 422...) as JSON, even if the client didn't send an `Accept` header.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
