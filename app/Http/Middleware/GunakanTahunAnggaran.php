<?php

namespace App\Http\Middleware;

use App\Support\TahunAnggaran;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GunakanTahunAnggaran
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('tahun-anggaran.update')) return $next($request);

        $active = TahunAnggaran::saved($request);
        if ($active !== null) {
            $yearBound = $request->routeIs('rba*', 'renbut*', 'rencana-kebutuhan*', 'prakiraan-maju.*', 'angkas*', 'bku*',
                'penerimaan*', 'penyetoran*', 'transaksi-penerimaan*', 'pengembalian*', 'ppd-*', 'opd-*', 'spd-*');
            $submittedYear = $request->input('_tahun_aktif') ?? $request->header('X-Tahun-Anggaran');
            if ($submittedYear === null && !$request->routeIs('rba.ambang-batas.*')) {
                $submittedYear = $request->request->get('tahun');
            }
            if ($yearBound && !$request->isMethod('GET') && $submittedYear !== null
                && (string) $submittedYear !== (string) $active) {
                throw ValidationException::withMessages(['tahun' => 'Tahun anggaran aktif sudah berubah. Muat ulang halaman sebelum menyimpan data.']);
            }
            $request->query->set('tahun', $active);
            if ($request->filled('minggu') && !str_starts_with((string) $request->input('minggu'), $active.'-')) {
                $request->query->remove('minggu');
            }
        }

        return $next($request);
    }
}
