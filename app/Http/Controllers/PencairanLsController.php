<?php

namespace App\Http\Controllers;

use App\Models\OpdLs;
use App\Models\PpdLs;
use App\Models\SpdLs;
use App\Models\UnitBlud;

class PencairanLsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        // Query utama untuk tabel
        $ppdLsQuery = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');
        $opdLsQuery = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'ppdLs'])->orderByDesc('id');
        $spdLsQuery = SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'opdLs'])->orderByDesc('id');

        if (!$isDinkes) {
            if (!$unitBlud) {
                $ppdLsQuery->whereRaw('1 = 0');
                $opdLsQuery->whereRaw('1 = 0');
                $spdLsQuery->whereRaw('1 = 0');
            } else {
                $ppdLsQuery->where('unit_blud_id', $unitBlud->id);
                $opdLsQuery->where('unit_blud_id', $unitBlud->id);
                $spdLsQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        // Fetch RBA untuk LS
        $rbaQuery = \App\Models\RencanaKebutuhan::with('akuns')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->whereNotNull('akun_id');
        if (!$isDinkes) {
            $rbaQuery->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim((string) $user->upt))]);
        }
        $rbasRaw = $rbaQuery->get();
        $rbas = $rbasRaw->groupBy('akun_id')->map(function ($items) {
            $first = $items->first();
            $first->total_anggaran = $items->sum('active_jumlah_harga');
            return $first;
        })->filter(function($item) {
            return $item->akuns && $item->akuns->kode_rek;
        })->values();

        return view('pencairan-ls', [
            'ppdLss' => $ppdLsQuery->get(),
            'opdLss' => $opdLsQuery->get(),
            'spdLss' => $spdLsQuery->get(),
            'rbas'   => $rbas,
        ]);
    }
}
