<?php

namespace App\Http\Controllers;

use App\Models\PpdGu;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PpdGuController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $query = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');

        $sisaUp = 0;
        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('unit_blud_id', $unitBlud->id);
                $totalUp = \App\Models\Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
                $totalGuSebelumnya = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
                $sisaUp = max(0, $totalUp - $totalGuSebelumnya);
            }
        }

        $ppdGus = $query->get();

        return view('ppd-gu', compact('ppdGus', 'sisaUp'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat membuat pengajuan PPD GU.']);
        }

        foreach (['jumlah', 'potongan', 'jumlah_akhir'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => str_replace('.', '', $request->$field)]);
            }
        }

        $validated = $request->validate([
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ppd_gus', 'nomor')->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'integer'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'bank_asal' => ['required', 'string', 'max:100'],
            'kepada' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'bank_tujuan' => ['required', 'string', 'max:100'],
            'no_rek_tujuan' => ['required', 'string', 'max:100'],
            'potongan' => ['required', 'integer'],
            'jumlah_akhir' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        if (!$unitBlud) {
            return back()->withErrors([
                'unit_blud_id' => 'Unit BLUD tidak ditemukan berdasarkan UPT user.',
            ])->withInput();
        }

        $validated['nama_bendahara'] = (string) $unitBlud->b_pengeluaran;
        $validated['unit_blud_id'] = $unitBlud->id;

        $totalUp = \App\Models\Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
        $totalGuSebelumnya = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');

        if (($totalGuSebelumnya + $validated['jumlah']) > $totalUp) {
            $sisa = $totalUp - $totalGuSebelumnya;
            return back()->withErrors(['Jumlah pengajuan GU melebihi batas UP. Sisa batas pengajuan GU Anda adalah Rp ' . number_format($sisa, 0, ',', '.')])->withInput();
        }

        $validated['tahun_anggaran'] = \App\Support\TahunAnggaran::selected();
        PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);

        return redirect()->route('pencairan-gu.index', ['tab' => 'ppd'])->with('success', 'PPD-GU berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat mengubah PPD GU.']);
        }

        $ppdGu = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('ppd_gu_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke OPD GU.']);
        }

        foreach (['jumlah', 'potongan', 'jumlah_akhir'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => str_replace('.', '', $request->$field)]);
            }
        }

        $validated = $request->validate([
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ppd_gus', 'nomor')
                    ->ignore($ppdGu->id)
                    ->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'integer'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'bank_asal' => ['required', 'string', 'max:100'],
            'kepada' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'bank_tujuan' => ['required', 'string', 'max:100'],
            'no_rek_tujuan' => ['required', 'string', 'max:100'],
            'potongan' => ['required', 'integer'],
            'jumlah_akhir' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        if (!$unitBlud) {
            return back()->withErrors([
                'unit_blud_id' => 'Unit BLUD tidak ditemukan berdasarkan UPT user.',
            ])->withInput();
        }

        $validated['nama_bendahara'] = (string) $unitBlud->b_pengeluaran;
        $validated['unit_blud_id'] = $unitBlud->id;

        $totalUp = \App\Models\Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)->sum('jumlah');
        $totalGuSebelumnya = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('unit_blud_id', $unitBlud->id)
                                ->where('id', '!=', $ppdGu->id)
                                ->sum('jumlah');

        if (($totalGuSebelumnya + $validated['jumlah']) > $totalUp) {
            $sisa = $totalUp - $totalGuSebelumnya;
            return back()->withErrors(['Jumlah pengajuan GU melebihi batas UP. Sisa batas pengajuan GU Anda adalah Rp ' . number_format($sisa, 0, ',', '.')])->withInput();
        }

        $ppdGu->update($validated);

        return redirect()->route('pencairan-gu.index', ['tab' => 'ppd'])->with('success', 'PPD-GU berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat menghapus PPD GU.']);
        }

        $ppdGu = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\OpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('ppd_gu_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke OPD GU.']);
        }

        $ppdGu->delete();

        return redirect()->route('pencairan-gu.index', ['tab' => 'ppd'])->with('success', 'PPD-GU berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $ppdGu = PpdGu::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $ppdGu->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $ppdGu,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.ppd-gu', $data)->setPaper('a4', 'portrait');
        $filename = 'PPD-GU-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}