<?php

namespace App\Http\Controllers;

use App\Models\OpdGu;
use App\Models\SpdGu;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpdGuController extends Controller
{
    public function index()
    {

        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $spdGusQuery = SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with(['unitBlud', 'opdGu'])->orderByDesc('id');
        $opdGusQuery = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $spdGusQuery->whereRaw('1 = 0');
                $opdGusQuery->whereRaw('1 = 0');
            } else {
                $spdGusQuery->where('unit_blud_id', $unitBlud->id);
                $opdGusQuery->where('unit_blud_id', $unitBlud->id);
            }
        }

        $spdGus = $spdGusQuery->get();
        $opdGus = $opdGusQuery->get();

        return view('spd-gu', compact('spdGus', 'opdGus'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD GU.']);
        }

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('spd_gus', 'nomor')->whereNull('deleted_at'),
            ],
            'opd_gu_id' => ['required', 'integer', Rule::exists('opd_gus', 'id')->whereNull('deleted_at')],
        ]);

        $opdGu = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['opd_gu_id']);
        
        $validated['unit_blud_id'] = $this->resolveUnitBludIdFromUser();
        $validated['tahun_anggaran'] = (string) \App\Support\TahunAnggaran::selected();
        $validated['nomor_dba'] = $opdGu->nomor_dba;
        $validated['tanggal_dba'] = $opdGu->tanggal_dba;
        $validated['bank_asal'] = $opdGu->bank_asal;
        $validated['kepada'] = $opdGu->kepada;
        $validated['npwp'] = $opdGu->npwp;
        $validated['no_rek_tujuan'] = $opdGu->no_rek_tujuan;
        $validated['bank_tujuan'] = $opdGu->bank_tujuan;
        $validated['jumlah'] = $opdGu->jumlah;
        $validated['potongan'] = $opdGu->potongan;
        $validated['jumlah_akhir'] = $opdGu->jumlah_akhir;
        
        SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);
        
        return redirect()->route('pencairan-gu.index', ['tab' => 'spd'])->with('success', 'SPD-GU berhasil ditambahkan.');
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
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD GU.']);
        }

        $spdGu = SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        $validated = $request->validate([
            'nomor' => [
                'required', 'string', 'max:100',
                Rule::unique('spd_gus', 'nomor')->ignore($spdGu->id)->whereNull('deleted_at'),
            ],
            'opd_gu_id' => ['required', 'integer', Rule::exists('opd_gus', 'id')->whereNull('deleted_at')],
        ]);

        $opdGu = OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($validated['opd_gu_id']);
        
        $validated['nomor_dba'] = $opdGu->nomor_dba;
        $validated['tanggal_dba'] = $opdGu->tanggal_dba;
        $validated['bank_asal'] = $opdGu->bank_asal;
        $validated['kepada'] = $opdGu->kepada;
        $validated['npwp'] = $opdGu->npwp;
        $validated['no_rek_tujuan'] = $opdGu->no_rek_tujuan;
        $validated['bank_tujuan'] = $opdGu->bank_tujuan;
        $validated['jumlah'] = $opdGu->jumlah;
        $validated['potongan'] = $opdGu->potongan;
        $validated['jumlah_akhir'] = $opdGu->jumlah_akhir;

        $spdGu->update($validated);

        return redirect()->route('pencairan-gu.index', ['tab' => 'spd'])->with('success', 'SPD-GU berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Kepala Puskesmas')) {
            return back()->withErrors(['Akses ditolak: Hanya Kepala Puskesmas yang dapat memproses SPD GU.']);
        }

        $spdGu = SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);
        $spdGu->delete();

        return redirect()->route('pencairan-gu.index', ['tab' => 'spd'])->with('success', 'SPD-GU berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $spdGu = SpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $spdGu->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $spdGu,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.spd-gu', $data)->setPaper('a4', 'portrait');
        $filename = 'SPD-GU-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}