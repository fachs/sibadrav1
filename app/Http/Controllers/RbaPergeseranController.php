<?php

namespace App\Http\Controllers;

use App\Models\RencanaKebutuhan;
use App\Models\Akun;
use Illuminate\Http\Request;

class RbaPergeseranController extends Controller
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

        return view('rba-pergeseran', compact('grouped', 'akuns'));
    }

    /**
     * Soft-zero: set jumlah, harga_satuan, jumlah_harga to 0
     * (not actually deleted, just zeroed out)
     */
    public function zeroOut($id)
    {
        $renbut = RencanaKebutuhan::where('tahun_anggaran', \App\Support\TahunRba::selected())->findOrFail($id);
        $renbut->update([
            'jumlah' => 0,
            'harga_satuan' => 0,
            'jumlah_harga' => 0,
        ]);

        return redirect()->route('rba-pergeseran.index')
            ->with('success', 'Rencana kebutuhan berhasil dihapus (nilai diubah menjadi 0).');
    }

    /**
     * Store new rencana kebutuhan from RBA Pergeseran page
     */
    public function store(Request $request)
    {
        $request->merge(['tahun_anggaran' => (string) \App\Support\TahunRba::selected($request)]);
        $validated = $request->validateWithBag('createRenbutPergeseran', [
            'nama_blud' => ['required', 'string', 'max:255'],
            'tahun_anggaran' => ['required', 'digits:4'],
            'nama_unit' => ['required', 'string', 'max:255'],
            'program' => ['required', 'string'],
            'kegiatan' => ['required', 'string'],
            'subkegiatan' => ['required', 'string'],
            'uraian' => ['required', 'string', 'max:255'],
            'spesifikasi' => ['required', 'string', 'max:255'],
            'ruang' => ['required', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:255'],
            'keterangan' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'min:1'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'akun_id' => ['required', 'exists:akuns,id'],
            'sumber_dana' => ['nullable', 'string'],
        ]);

        $validated['jumlah_harga'] = $validated['jumlah'] * $validated['harga_satuan'];

        RencanaKebutuhan::create($validated);

        return redirect()->route('rba-pergeseran.index')
            ->with('success', 'Rencana kebutuhan baru berhasil ditambahkan.');
    }
}
