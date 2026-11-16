<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKapusKtu
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Akses ditolak.');
        }

        $allowedRoles = ['kepala puskesmas', 'klaster 1', 'superuser','kasubbag tu', 'kepala labkes'];
        if (!$user->hasAnyRole($allowedRoles)) {
            abort(403, 'Akses ditolak. Hanya Kepala Puskesmas dan Klaster 1.');
        }

        return $next($request);
    }
}
