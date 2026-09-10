<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EagerLoadUserRelations
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Eager load tim relationship untuk user yang authenticated
        if ($request->user()) {
            $request->user()->load('tim');
        }

        return $next($request);
    }
}
