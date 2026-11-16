<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperuser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || !$user->hasRole('Superuser')) {
            abort(403, 'Akses ditolak. Hanya Superuser yang dapat melakukan aksi ini.');
        }

        return $next($request);
    }
}
