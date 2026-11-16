<?php

namespace App\Http\Controllers;

use App\Models\Up;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpdUpController extends Controller
{
    public function index()
    {
        $opdUps = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->whereNotNull('no_dba')->orderByDesc('id')->get();
        $ppdUps = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id')->get();

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $opdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->whereNotNull('no_dba')->orderByDesc('id');
        $ppdUpsQuery = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $opdUpsQuery->whereRaw('1 = 0');
                $ppdUpsQuery->whereRaw('1 = 0');
            } else {
                $opdUpsQuery->where('unit_blud_id', $unitBlud->id);
                $ppdUpsQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $opdUps = $opdUpsQuery->get();
        $ppdUps = $ppdUpsQuery->get();

        return view('opd-up', compact('opdUps', 'ppdUps'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($request->ppd_up_id);
        
        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('ups', 'no_opd')->whereNull('deleted_at'),
            ],
            'no_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
            'ppd_up_id' => ['required', 'integer', Rule::exists('ups', 'id')->whereNull('deleted_at')],
        ]);

        $validated['no_opd'] = $validated['nomor'];
        unset($validated['nomor']);
        unset($validated['ppd_up_id']);
        
        $up->update($validated);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'opd'])->with('success', 'OPD-UP berhasil ditambahkan.');
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
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if ($up->no_spd) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke SPD UP.']);
        }

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('ups', 'no_opd')->ignore($up->id)->whereNull('deleted_at'),
            ],
            'no_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
        ]);

        $validated['no_opd'] = $validated['nomor'];
        unset($validated['nomor']);
        
        $up->update($validated);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'opd'])->with('success', 'OPD-UP berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD UP.']);
        }

        $up = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if ($up->no_spd) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke SPD UP.']);
        }

        $up->update([
            'no_opd' => null,
            'no_dba' => null,
            'tanggal_dba' => null,
        ]);
        
        return redirect()->route('pencairan-up.index', ['tab' => 'opd'])->with('success', 'OPD-UP berhasil dihapus (dikembalikan ke PPD-UP).');
    }

    public function unduhSurat($id)
    {
        $opdUp = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $opdUp->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $opdUp,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.opd-up', $data)->setPaper('a4', 'portrait');
        $filename = 'OPD-UP-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}