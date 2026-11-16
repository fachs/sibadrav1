<?php

namespace App\Http\Controllers;

use App\Models\OpdGu;
use App\Models\PpdGu;
use App\Models\SpdGu;
use App\Models\UnitBlud;

class PencairanGuController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        // Query utama untuk tabel
        $ppdGusQuery = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');
        $opdGusQuery = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'ppdGu'])->orderByDesc('id');
        $spdGusQuery = SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'opdGu'])->orderByDesc('id');

        // Query untuk dropdown di form
        $ppdGusDropdownQuery = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');
        $opdGusDropdownQuery = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            if (!$unitBlud) {
                $ppdGusQuery->whereRaw('1 = 0');
                $opdGusQuery->whereRaw('1 = 0');
                $spdGusQuery->whereRaw('1 = 0');
                $ppdGusDropdownQuery->whereRaw('1 = 0');
                $opdGusDropdownQuery->whereRaw('1 = 0');
            } else {
                $ppdGusQuery->where('unit_blud_id', $unitBlud->id);
                $opdGusQuery->where('unit_blud_id', $unitBlud->id);
                $spdGusQuery->where('unit_blud_id', $unitBlud->id);
                $ppdGusDropdownQuery->where('unit_blud_id', $unitBlud->id);
                $opdGusDropdownQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $sisaUp = 0;
        if ($unitBlud) {
            $totalUp = \App\Models\Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
            $totalGuSebelumnya = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
            $sisaUp = max(0, $totalUp - $totalGuSebelumnya);
        }

        return view('pencairan-gu', [
            'ppdGus' => $ppdGusQuery->get(),
            'opdGus' => $opdGusQuery->get(),
            'spdGus' => $spdGusQuery->get(),
            'ppdGusDropdown' => $ppdGusDropdownQuery->get(),
            'opdGusDropdown' => $opdGusDropdownQuery->get(),
            'sisaUp' => $sisaUp,
        ]);
    }
}
