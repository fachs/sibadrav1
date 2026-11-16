<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        if (!auth()->user()->hasAnyRole($roles)) {
            abort(403, 'Anda tidak memiliki akses');
        }

        return $next($request);
    }
}
