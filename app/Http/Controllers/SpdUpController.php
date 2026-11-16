<?php

namespace App\Http\Controllers;

use App\Models\Up;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpdUpController extends Controller
{
    public function index()
    {

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $spdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->whereNotNull('no_spd')->orderByDesc('id');
        $opdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->whereNotNull('no_dba')->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $spdUpsQuery->whereRaw('1 = 0');
                $opdUpsQuery->whereRaw('1 = 0');
            } else {
                $spdUpsQuery->where('unit_blud_id', $unitBlud->id);
                $opdUpsQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $spdUps = $spdUpsQuery->get();
        $opdUps = $opdUpsQuery->get();

        return view('spd-up', compact('spdUps', 'opdUps'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($request->opd_up_id);
        
        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('ups', 'no_spd')->whereNull('deleted_at'),
            ],
            'opd_up_id' => ['required', 'integer', Rule::exists('ups', 'id')->whereNull('deleted_at')],
        ]);
        
        $validated['no_spd'] = $validated['nomor'];
        unset($validated['nomor']);
        unset($validated['opd_up_id']);
        
        $up->update($validated);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'spd'])->with('success', 'SPD-UP berhasil ditambahkan.');
    }



    private function resolveUnitBludIdFromUser(): int
    {
        $user = auth()->user();
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        if (!$unitBlud) {
            abort(422, 'Unit BLUD tidak ditemukan berdasarkan UPT user.');
        }

        return (int) $unitBlud->id;
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('ups', 'no_spd')->ignore($up->id)->whereNull('deleted_at'),
            ],
        ]);
        
        $validated['no_spd'] = $validated['nomor'];
        unset($validated['nomor']);
        
        $up->update($validated);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'spd'])->with('success', 'SPD-UP berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $up->update([
            'no_spd' => null,
        ]);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'spd'])->with('success', 'SPD-UP berhasil dihapus (dikembalikan ke OPD-UP).');
    }

    public function unduhSurat($id)
    {
        $spdUp = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $spdUp->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $spdUp,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.spd-up', $data)->setPaper('a4', 'portrait');
        $filename = 'SPD-UP-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}