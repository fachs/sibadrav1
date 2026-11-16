<?php

namespace App\Http\Controllers;

use App\Models\Up;
use App\Models\UnitBlud;

class PencairanUpController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        // Query utama untuk tabel (dari tabel ups)
        $ppdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');
        // OPD adalah UP yang sudah diproses di tahap OPD (misal no_opd tidak null)
        $opdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->whereNotNull('no_opd')->orderByDesc('id');
        // SPD adalah UP yang sudah diproses di tahap SPD (misal no_spd tidak null)
        $spdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->whereNotNull('no_spd')->orderByDesc('id');

        if (!$isDinkes) {
            if (!$unitBlud) {
                $ppdUpsQuery->whereRaw('1 = 0');
                $opdUpsQuery->whereRaw('1 = 0');
                $spdUpsQuery->whereRaw('1 = 0');
            } else {
                $ppdUpsQuery->where('unit_blud_id', $unitBlud->id);
                $opdUpsQuery->where('unit_blud_id', $unitBlud->id);
                $spdUpsQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $bendaharaUser = \App\Models\User::whereRaw('LOWER(upt) = ?', [strtolower(trim((string) $user->upt))])
            ->withRole('Bendahara Pengeluaran')
            ->first();

        return view('pencairan-up', [
            'ppdUps' => $ppdUpsQuery->get(),
            'opdUps' => $opdUpsQuery->get(),
            'spdUps' => $spdUpsQuery->get(),
            'bendaharaUser' => $bendaharaUser,
        ]);
    }
}
