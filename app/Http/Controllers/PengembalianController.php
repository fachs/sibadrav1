<?php

namespace App\Http\Controllers;

use App\Models\Pengembalian;
use App\Models\Akun;
use App\Models\Penerimaan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengembalianController extends Controller
{
    public function index(Request $request)
    {
        $selectedPeriode = $request->get('periode');
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $query = Pengembalian::with(['penerimaan'])->whereHas('penerimaan', fn ($query) => $query->where('tahun_anggaran', \App\Support\TahunAnggaran::selected()));

        // Query untuk opsi select penerimaan
        $penerimaanOptionsQuery = Penerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $query->whereRaw('1 = 0');
                $penerimaanOptionsQuery->whereRaw('1 = 0');
            } else {
                $query->where('unit_blud_id', $unitBlud->id);
                $penerimaanOptionsQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        if ($selectedPeriode) {
            $query->whereHas('penerimaan', function ($q) use ($selectedPeriode) {
                $q->where('periode', $selectedPeriode);
            });

            $penerimaanOptionsQuery->where('periode', $selectedPeriode);
        }

        $pengembalians = $query
            ->orderByDesc('tanggal')
            ->paginate(6)
            ->withQueryString();

        $penerimaanOptions = $penerimaanOptionsQuery
            ->orderByDesc('tanggal_setor')
            ->get();

        return view('pengembalian', compact('pengembalians', 'selectedPeriode', 'penerimaanOptions'));
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'jenis_bukti' => ['required', 'string'],
            'pihak_ketiga' => ['required', 'string'],
            'jumlah' => ['required', 'integer'],
            'penerimaan_id' => ['required', 'integer', Rule::exists('penerimaans', 'id')->whereNull('deleted_at')],
        ]);

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if (!$unitBlud) {
                return back()->withErrors(['unit_blud' => 'Unit BLUD tidak ditemukan.'])->withInput();
            }

            $penerimaan = Penerimaan::where('id', $validated['penerimaan_id'])
                ->where('unit_blud_id', $unitBlud->id)
                ->first();

            if (!$penerimaan) {
                return back()->withErrors(['penerimaan_id' => 'Penerimaan tidak sesuai unit BLUD Anda.'])->withInput();
            }



            $validated['unit_blud_id'] = $unitBlud->id;
        } else {
            $penerimaan = Penerimaan::findOrFail($validated['penerimaan_id']);



            $validated['unit_blud_id'] = $penerimaan->unit_blud_id;
        }

        $validated['akun_id'] = $user->id;

        Pengembalian::create($validated);

        return redirect()->route('pengembalian.index')
            ->with('success', 'Pengembalian berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $pengembalian = Pengembalian::findOrFail($id);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'jenis_bukti' => ['required', 'string'],
            'pihak_ketiga' => ['required', 'string'],
            'jumlah' => ['required', 'integer'],
            'penerimaan_id' => ['required', 'integer', Rule::exists('penerimaans', 'id')->whereNull('deleted_at')],
        ]);

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if (!$unitBlud) {
                return back()->withErrors(['unit_blud' => 'Unit BLUD tidak ditemukan.'])->withInput();
            }

            if ((int) $pengembalian->unit_blud_id !== (int) $unitBlud->id) {
                return back()->withErrors([
                    'pengembalian' => 'Data pengembalian tidak sesuai unit BLUD Anda.',
                ])->withInput();
            }

            $penerimaan = Penerimaan::where('id', $validated['penerimaan_id'])
                ->where('unit_blud_id', $unitBlud->id)
                ->first();

            if (!$penerimaan) {
                return back()->withErrors(['penerimaan_id' => 'Penerimaan tidak sesuai unit BLUD Anda.'])->withInput();
            }



            $validated['unit_blud_id'] = $unitBlud->id;
        } else {
            $penerimaan = Penerimaan::findOrFail($validated['penerimaan_id']);



            $validated['unit_blud_id'] = $penerimaan->unit_blud_id;
        }

        $validated['akun_id'] = $user->id;

        $pengembalian->update($validated);

        return redirect()->route('pengembalian.index')
            ->with('success', 'Pengembalian berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $pengembalian = Pengembalian::with('penerimaan')->findOrFail($id);
        $periode = optional($pengembalian->penerimaan)->periode;

        $pengembalian->delete();

        return redirect()->route('pengembalian.index', ['periode' => $periode])
            ->with('success', 'Pengembalian berhasil dihapus');
    }

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
