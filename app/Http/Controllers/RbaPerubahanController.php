<?php

namespace App\Http\Controllers;

use App\Models\RencanaKebutuhan;
use App\Models\Akun;
use Illuminate\Http\Request;

class RbaPerubahanController extends Controller
{
    private function isPrivilegedRole(\App\Models\User $user): bool
    {
        return $user->hasAnyRole(['superuser', 'kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
    }

    public function index()
    {
        $user = auth()->user();

        $query = RencanaKebutuhan::with('akuns')->where('tahun_anggaran', \App\Support\TahunRba::selected());

        if (!$this->isPrivilegedRole($user)) {
            $query->where('nama_blud', $user->upt)
                ->whereIn('nama_unit', $user->roleNames());
        } elseif (!$user->hasRole('Superuser')) {
            $query->where('nama_blud', $user->upt);
        }

        $renbut = $query->get()
            ->filter(fn ($item) => !empty(optional($item->akuns)->kode_rek));

        $grouped = $renbut->groupBy('akun_id');

        $akuns = Akun::all();

        return view('rba-perubahan', compact('grouped', 'akuns'));
    }
}
