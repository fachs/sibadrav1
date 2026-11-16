<?php

namespace App\Http\Controllers;

// use App\Models\Penyetoran;
use App\Models\Akun;
use App\Models\Penerimaan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PenyetoranController extends Controller
{
    public function index(Request $request)
    {
        $selectedPeriode = $request->get('periode');
        $user = auth()->user();

        $query = Penerimaan::with('akuns');

        if ($selectedPeriode) {
            $query->where('periode', $selectedPeriode);
        }

        $penerimaans = $query
            ->orderByDesc('tanggal_terima')
            ->paginate(6)
            ->withQueryString();

        return view('penerimaan', compact(
            'penerimaans',
            'selectedPeriode'
        ));
    }


    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //     'tanggal'       => ['required', 'date'],
    //     'nomor_bukti'   => [
    //         'required','string','max:100',
    //         Rule::unique('penyetorans', 'nomor_bukti')->whereNull('deleted_at'),
    //     ],
    //     'jumlah'        => ['required', 'numeric', 'min:0'],
    //     'keterangan'    => ['nullable', 'string'],
    //     'penerimaan_id' => ['required', 'integer', Rule::exists('penerimaans', 'id')->whereNull('deleted_at')],
    // ]);

    // $user = auth()->user();
    // $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

    // if (!$isDinkes) {
    //     $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
    //     if (!$unitBlud) {
    //         return back()->withErrors(['unit_blud' => 'Unit BLUD tidak ditemukan.'])->withInput();
    //     }

    //     $penerimaan = Penerimaan::where('id', $validated['penerimaan_id'])
    //         ->where('unit_blud_id', $unitBlud->id)
    //         ->first();

    //     if (!$penerimaan) {
    //         return back()->withErrors(['penerimaan_id' => 'Penerimaan tidak sesuai unit BLUD Anda.'])->withInput();
    //     }

    //     $validated['unit_blud_id'] = $unitBlud->id;
    // } else {
    //     $penerimaan = Penerimaan::findOrFail($validated['penerimaan_id']);
    //     $validated['unit_blud_id'] = $penerimaan->unit_blud_id;
    // }

    // // Pastikan ini memang id akun pada tabel akuns
    // $validated['akun_id'] = $user->id;

    // Penyetoran::create($validated);

    //     return redirect()->route('penyetoran.index')
    //         ->with('success', 'Penyetoran berhasil ditambahkan.');
    // }


    // public function update(Request $request, $id)
    // {
    //     $penyetoran = Penyetoran::findOrFail($id);

    //     $validated = $request->validate([
    //         'tanggal'       => ['required', 'date'],
    //         'nomor_bukti'   => [
    //             'required','string','max:100',
    //             Rule::unique('penyetorans', 'nomor_bukti')
    //                 ->ignore($penyetoran->id)
    //                 ->whereNull('deleted_at'),
    //         ],
    //         'jumlah'        => ['required', 'numeric', 'min:0'],
    //         'keterangan'    => ['nullable', 'string'],
    //         'penerimaan_id' => ['required', 'integer', Rule::exists('penerimaans', 'id')->whereNull('deleted_at')],
    //     ]);

    //     // Validasi ulang hak akses unit_blud sama seperti store
    //     // lalu:
    //     $penyetoran->update($validated);

    //     return redirect()->route('penyetoran.index')
    //         ->with('success', 'Penyetoran berhasil diperbarui.');
    // }

    // public function destroy($id)
    // {
    //     $penyetoran = Penyetoran::with('penerimaan')->findOrFail($id);
    //     $periode = optional($penyetoran->penerimaan)->periode;

    //     $penyetoran->delete();

    //     return redirect()->route('penyetoran.index', ['periode' => $periode])
    //         ->with('success', 'Penyetoran berhasil dihapus');
    // }

    public function search(Request $request)
    {
        $search = $request->q;

        $data = Akun::where('nama', 'like', "%$search%")
            ->limit(20)
            ->get();

        return response()->json(
            $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->nama,
                ];
            })
        );
    }
}
