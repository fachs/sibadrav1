<?php

namespace App\Http\Controllers;

use App\Models\OpdLs;
use App\Models\PpdLs;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpdLsController extends Controller
{
    public function index()
    {

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $OpdLssQuery = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'PpdLs'])->orderByDesc('id');
        $PpdLssQuery = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $OpdLssQuery->whereRaw('1 = 0');
                $PpdLssQuery->whereRaw('1 = 0');
            } else {
                $OpdLssQuery->where('unit_blud_id', $unitBlud->id);
                $PpdLssQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $OpdLss = $OpdLssQuery->get();
        $PpdLss = $PpdLssQuery->get();

        return view('OPD-LS', compact('OpdLss', 'PpdLss'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD LS.']);
        }

        $validated = $this->validateRequest($request);
        
        $PpdLs = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['ppd_ls_id']);

        $validated['unit_blud_id'] = $this->resolveUnitBludIdFromUser();
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        
        // Copy fields from PpdLs
        $validated['bank_asal'] = $PpdLs->bank_asal;
        $validated['jumlah'] = $PpdLs->jumlah;
        $validated['kepada'] = $PpdLs->kepada;
        $validated['npwp'] = $PpdLs->npwp;
        $validated['no_rek_tujuan'] = $PpdLs->no_rek_tujuan;
        $validated['bank_tujuan'] = $PpdLs->bank_tujuan;
        $validated['potongan'] = $PpdLs->potongan;
        $validated['jumlah_akhir'] = $PpdLs->jumlah_akhir;
        
        OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);
        
        return redirect()->route('pencairan-ls.index', ['tab' => 'opd'])->with('success', 'OPD-LS berhasil ditambahkan.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('opd_lss', 'nomor')->whereNull('deleted_at'),
            ],
            'nomor_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
            'ppd_ls_id' => ['required', 'integer', Rule::exists('ppd_lss', 'id')->whereNull('deleted_at')],
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
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD LS.']);
        }

        $OpdLs = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('opd_ls_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke SPD LS.']);
        }

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('opd_lss', 'nomor')->ignore($OpdLs->id)->whereNull('deleted_at'),
            ],
            'nomor_dba' => ['required', 'string', 'max:100'],
            'tanggal_dba' => \App\Support\TahunAnggaran::dateRules(),
            'ppd_ls_id' => ['required', 'integer', Rule::exists('ppd_lss', 'id')->whereNull('deleted_at')],
        ]);

        $PpdLs = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['ppd_ls_id']);
        
        $validated['bank_asal'] = $PpdLs->bank_asal;
        $validated['jumlah'] = $PpdLs->jumlah;
        $validated['kepada'] = $PpdLs->kepada;
        $validated['npwp'] = $PpdLs->npwp;
        $validated['no_rek_tujuan'] = $PpdLs->no_rek_tujuan;
        $validated['bank_tujuan'] = $PpdLs->bank_tujuan;
        $validated['potongan'] = $PpdLs->potongan;
        $validated['jumlah_akhir'] = $PpdLs->jumlah_akhir;

        $OpdLs->update($validated);

        return redirect()->route('pencairan-ls.index', ['tab' => 'opd'])->with('success', 'OPD-LS berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Pejabat Keuangan')) {
            return back()->withErrors(['Akses ditolak: Hanya Pejabat Keuangan yang dapat memproses OPD LS.']);
        }

        $OpdLs = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('opd_ls_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke SPD LS.']);
        }

        $OpdLs->delete();

        return redirect()->route('pencairan-ls.index', ['tab' => 'opd'])->with('success', 'OPD-LS berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $OpdLs = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $OpdLs->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $OpdLs,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.OPD-LS', $data)->setPaper('a4', 'portrait');
        $filename = 'OPD-LS-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}
