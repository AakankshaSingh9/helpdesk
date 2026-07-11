<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route to admins only.
 *
 * `role` is a privilege boundary (see App\Models\User) — anything behind this
 * middleware is off-limits to agents. Assumes an authenticated request, so
 * chain it after `auth:sanctum`.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'This action is restricted to administrators.');
        }

        return $next($request);
    }
}
