<?php

namespace App\Http\Controllers;

use App\Models\RencanaKebutuhan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;

class PerkiraanHargaController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'puskesmas' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim($filters['search'] ?? '');
        $puskesmas = $filters['puskesmas'] ?? '';
        $isSuperuser = $request->user()->hasRole('Superuser');
        $query = RencanaKebutuhan::query()->select('id', 'uraian', 'harga_satuan', 'satuan', 'keterangan')
            ->where('tahun_anggaran', \App\Support\TahunRba::selected($request));

        if (!$isSuperuser) {
            $query->where('nama_blud', $request->user()->upt);
        } elseif ($puskesmas !== '') {
            $query->where('nama_blud', $puskesmas);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('uraian', 'like', '%'.$search.'%')
                    ->orWhere('satuan', 'like', '%'.$search.'%');
            });
        }

        $perkiraanHarga = $query->orderBy('uraian')->orderBy('id')
            ->paginate(15)->withQueryString();
        $puskesmasList = $isSuperuser ? UnitBlud::orderBy('nama')->get() : collect();

        return view('perkiraan-harga', compact(
            'perkiraanHarga', 'search', 'puskesmas', 'isSuperuser', 'puskesmasList'
        ));
    }
}
