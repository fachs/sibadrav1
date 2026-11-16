<?php

namespace App\Http\Controllers;

use App\Models\OpdLs;
use App\Models\SpdLs;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpdLsController extends Controller
{
    public function index()
    {

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $SpdLssQuery = SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'OpdLs'])->orderByDesc('id');
        $OpdLssQuery = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $SpdLssQuery->whereRaw('1 = 0');
                $OpdLssQuery->whereRaw('1 = 0');
            } else {
                $SpdLssQuery->where('unit_blud_id', $unitBlud->id);
                $OpdLssQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $SpdLss = $SpdLssQuery->get();
        $OpdLss = $OpdLssQuery->get();

        return view('SPD-LS', compact('SpdLss', 'OpdLss'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD LS.']);
        }

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('spd_lss', 'nomor')->whereNull('deleted_at'),
            ],
            'opd_ls_id' => ['required', 'integer', Rule::exists('opd_lss', 'id')->whereNull('deleted_at')],
        ]);

        $OpdLs = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['opd_ls_id']);
        
        $validated['unit_blud_id'] = $this->resolveUnitBludIdFromUser();
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        $validated['nomor_dba'] = $OpdLs->nomor_dba;
        $validated['tanggal_dba'] = $OpdLs->tanggal_dba;
        $validated['bank_asal'] = $OpdLs->bank_asal;
        $validated['kepada'] = $OpdLs->kepada;
        $validated['npwp'] = $OpdLs->npwp;
        $validated['no_rek_tujuan'] = $OpdLs->no_rek_tujuan;
        $validated['bank_tujuan'] = $OpdLs->bank_tujuan;
        $validated['jumlah'] = $OpdLs->jumlah;
        $validated['potongan'] = $OpdLs->potongan;
        $validated['jumlah_akhir'] = $OpdLs->jumlah_akhir;
        
        SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);
        
        return redirect()->route('pencairan-ls.index', ['tab' => 'spd'])->with('success', 'SPD-LS berhasil ditambahkan.');
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
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD LS.']);
        }

        $SpdLs = SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('spd_lss', 'nomor')->ignore($SpdLs->id)->whereNull('deleted_at'),
            ],
            'opd_ls_id' => ['required', 'integer', Rule::exists('opd_lss', 'id')->whereNull('deleted_at')],
        ]);

        $OpdLs = OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['opd_ls_id']);
        
        $validated['nomor_dba'] = $OpdLs->nomor_dba;
        $validated['tanggal_dba'] = $OpdLs->tanggal_dba;
        $validated['bank_asal'] = $OpdLs->bank_asal;
        $validated['kepada'] = $OpdLs->kepada;
        $validated['npwp'] = $OpdLs->npwp;
        $validated['no_rek_tujuan'] = $OpdLs->no_rek_tujuan;
        $validated['bank_tujuan'] = $OpdLs->bank_tujuan;
        $validated['jumlah'] = $OpdLs->jumlah;
        $validated['potongan'] = $OpdLs->potongan;
        $validated['jumlah_akhir'] = $OpdLs->jumlah_akhir;

        $SpdLs->update($validated);

        return redirect()->route('pencairan-ls.index', ['tab' => 'spd'])->with('success', 'SPD-LS berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD LS.']);
        }

        $SpdLs = SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);
        $SpdLs->delete();

        return redirect()->route('pencairan-ls.index', ['tab' => 'spd'])->with('success', 'SPD-LS berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $SpdLs = SpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $SpdLs->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $SpdLs,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.SPD-LS', $data)->setPaper('a4', 'portrait');
        $filename = 'SPD-LS-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}
