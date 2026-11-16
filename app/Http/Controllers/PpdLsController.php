<?php

namespace App\Http\Controllers;

use App\Models\PpdLs;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PpdLsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $query = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('unit_blud_id', $unitBlud->id);
            }
        }

        $ppdLss = $query->get();

        return view('ppd-ls', compact('ppdLss'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('PPTK')) {
            return back()->withErrors(['Akses ditolak: Hanya PPTK yang dapat membuat pengajuan PPD LS.']);
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
                Rule::unique('ppd_lss', 'nomor')->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'integer'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'kode_rekening' => ['nullable', 'string', 'max:100'],
            'uraian' => ['nullable', 'string'],
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

        $validated['tahun_anggaran'] = \App\Support\TahunAnggaran::selected();
        PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);

        return redirect()->route('pencairan-ls.index', ['tab' => 'ppd'])->with('success', 'PPD-LS berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('PPTK')) {
            return back()->withErrors(['Akses ditolak: Hanya PPTK yang dapat mengubah PPD LS.']);
        }

        $ppdLs = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('ppd_ls_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke OPD LS.']);
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
                Rule::unique('ppd_lss', 'nomor')
                    ->ignore($ppdLs->id)
                    ->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'integer'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'kode_rekening' => ['nullable', 'string', 'max:100'],
            'uraian' => ['nullable', 'string'],
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

        $ppdLs->update($validated);

        return redirect()->route('pencairan-ls.index', ['tab' => 'ppd'])->with('success', 'PPD-LS berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('PPTK')) {
            return back()->withErrors(['Akses ditolak: Hanya PPTK yang dapat menghapus PPD LS.']);
        }

        $ppdLs = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if (\App\Models\OpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->where('ppd_ls_id', $id)->exists()) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke OPD LS.']);
        }

        $ppdLs->delete();

        return redirect()->route('pencairan-ls.index', ['tab' => 'ppd'])->with('success', 'PPD-LS berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $ppdLs = PpdLs::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $ppdLs->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $ppdLs,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.ppd-ls', $data)->setPaper('a4', 'portrait');
        $filename = 'PPD-LS-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}
