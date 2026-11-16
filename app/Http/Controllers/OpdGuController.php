<?php

namespace App\Http\Controllers;

use App\Models\OpdGu;
use App\Models\PpdGu;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpdGuController extends Controller
{
    public function index()
    {

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $opdGusQuery = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'ppdGu'])->orderByDesc('id');
        $ppdGusQuery = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $opdGusQuery->whereRaw('1 = 0');
                $ppdGusQuery->whereRaw('1 = 0');
            } else {
                $opdGusQuery->where('unit_blud_id', $unitBlud->id);
                $ppdGusQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $opdGus = $opdGusQuery->get();
        $ppdGus = $ppdGusQuery->get();

        return view('opd-gu', compact('opdGus', 'ppdGus'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD GU.']);
        }

        $validated = $this->validateRequest($request);
        
        $ppdGu = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['ppd_gu_id']);

        $validated['unit_blud_id'] = $this->resolveUnitBludIdFromUser();
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        
        // Copy fields from PpdGu
        $validated['bank_asal'] = $ppdGu->bank_asal;
        $validated['jumlah'] = $ppdGu->jumlah;
        $validated['kepada'] = $ppdGu->kepada;
        $validated['npwp'] = $ppdGu->npwp;
        $validated['no_rek_tujuan'] = $ppdGu->no_rek_tujuan;
        $validated['bank_tujuan'] = $ppdGu->bank_tujuan;
        $validated['potongan'] = $ppdGu->potongan;
        $validated['jumlah_akhir'] = $ppdGu->jumlah_akhir;
        
        OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);
        
        return redirect()->route('pencairan-gu.index', ['tab' => 'opd'])->with('success', 'OPD-GU berhasil ditambahkan.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('opd_gus', 'nomor')->whereNull('deleted_at'),
            ],
            'nomor_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
            'ppd_gu_id' => ['required', 'integer', Rule::exists('ppd_gus', 'id')->whereNull('deleted_at')],
        ]);
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
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD GU.']);
        }

        $opdGu = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('opd_gu_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke SPD GU.']);
        }

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('opd_gus', 'nomor')->ignore($opdGu->id)->whereNull('deleted_at'),
            ],
            'nomor_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
            'ppd_gu_id' => ['required', 'integer', Rule::exists('ppd_gus', 'id')->whereNull('deleted_at')],
        ]);

        $ppdGu = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['ppd_gu_id']);
        
        $validated['bank_asal'] = $ppdGu->bank_asal;
        $validated['jumlah'] = $ppdGu->jumlah;
        $validated['kepada'] = $ppdGu->kepada;
        $validated['npwp'] = $ppdGu->npwp;
        $validated['no_rek_tujuan'] = $ppdGu->no_rek_tujuan;
        $validated['bank_tujuan'] = $ppdGu->bank_tujuan;
        $validated['potongan'] = $ppdGu->potongan;
        $validated['jumlah_akhir'] = $ppdGu->jumlah_akhir;

        $opdGu->update($validated);

        return redirect()->route('pencairan-gu.index', ['tab' => 'opd'])->with('success', 'OPD-GU berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD GU.']);
        }

        $opdGu = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('opd_gu_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke SPD GU.']);
        }

        $opdGu->delete();

        return redirect()->route('pencairan-gu.index', ['tab' => 'opd'])->with('success', 'OPD-GU berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $opdGu = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $opdGu->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $opdGu,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.opd-gu', $data)->setPaper('a4', 'portrait');
        $filename = 'OPD-GU-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}