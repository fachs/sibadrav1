<?php

namespace App\Http\Controllers;

use App\Models\BuktiDukungPenerimaan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuktiDukungPenerimaanController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['tahun_anggaran' => (string) \App\Support\TahunAnggaran::selected($request)]);
        $validated = $request->validate([
            'periode' => ['required', 'string', 'max:20'],
            'tahun_anggaran' => ['required', 'string', 'max:4'],
            'link' => ['required', 'url'],
            'unit_blud_id' => ['nullable', 'integer', Rule::exists('unit_bluds', 'id')],
        ]);

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                return back()->withErrors(['unit_blud_id' => 'Unit BLUD tidak ditemukan.'])->withInput();
            }

            // Paksa unit_blud_id sesuai user non-Dinkes
            $validated['unit_blud_id'] = $unitBlud->id;
        } else {
            // Dinkes wajib kirim unit_blud_id
            if (empty($validated['unit_blud_id'])) {
                return back()->withErrors(['unit_blud_id' => 'Unit BLUD wajib dipilih.'])->withInput();
            }
        }

        $validated['nama_blud'] = (string) $user->upt;

        BuktiDukungPenerimaan::create($validated);

        return back()->with('success', 'Link bukti dukung berhasil disimpan.');
    }
}