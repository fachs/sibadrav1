<?php

namespace App\Http\Controllers;

use App\Models\Up;
use App\Models\UnitBlud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PpdUpController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        $query = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->orderByDesc('id');

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('unit_blud_id', $unitBlud->id);
            }
        }

        $ppdUps = $query->get();

        return view('ppd-up', compact('ppdUps'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat membuat pengajuan PPD UP.']);
        }

        $request->merge([
            'jumlah' => str_replace('.', '', $request->jumlah),
            'potongan' => str_replace('.', '', $request->potongan),
        ]);

        $validated = $request->validate([
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ups', 'nomor')->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'bank_asal' => ['required', 'string', 'max:100'],
            'kepada' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'no_rek_tujuan' => ['required', 'string', 'max:100'],
            'bank_tujuan' => ['required', 'string', 'max:100'],
            'potongan' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        if (!$unitBlud) {
            return back()->withErrors([
                'unit_blud_id' => 'Unit BLUD tidak ditemukan berdasarkan UPT user.',
            ])->withInput();
        }

        $bendaharaUser = \App\Models\User::whereRaw('LOWER(upt) = ?', [strtolower(trim((string) $user->upt))])
            ->withRole('Bendahara Pengeluaran')
            ->first();

        $validated['nama_bendahara'] = $bendaharaUser ? $bendaharaUser->name : '-';
        $validated['unit_blud_id'] = $unitBlud->id;

        $validated['tahun_anggaran'] = \App\Support\TahunAnggaran::selected();
        Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->create($validated);

        return redirect()->route('pencairan-up.index', ['tab' => 'ppd'])->with('success', 'PPD-UP berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat mengubah PPD UP.']);
        }

        $ppdUp = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);
        
        if ($ppdUp->no_opd) {
            return back()->withErrors(['Data tidak dapat diubah karena sudah diverifikasi ke OPD UP.']);
        }

        $request->merge([
            'jumlah' => str_replace('.', '', $request->jumlah),
            'potongan' => str_replace('.', '', $request->potongan),
        ]);

        $validated = $request->validate([
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ups', 'nomor')
                    ->ignore($ppdUp->id)
                    ->whereNull('deleted_at'),
            ],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'no_rek' => ['required', 'string', 'max:100'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'bank_asal' => ['required', 'string', 'max:100'],
            'kepada' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'no_rek_tujuan' => ['required', 'string', 'max:100'],
            'bank_tujuan' => ['required', 'string', 'max:100'],
            'potongan' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

        if (!$unitBlud) {
            return back()->withErrors([
                'unit_blud_id' => 'Unit BLUD tidak ditemukan berdasarkan UPT user.',
            ])->withInput();
        }

        $bendaharaUser = \App\Models\User::whereRaw('LOWER(upt) = ?', [strtolower(trim((string) $user->upt))])
            ->withRole('Bendahara Pengeluaran')
            ->first();

        $validated['nama_bendahara'] = $bendaharaUser ? $bendaharaUser->name : '-';
        $validated['unit_blud_id'] = $unitBlud->id;

        $ppdUp->update($validated);

        return redirect()->route('pencairan-up.index', ['tab' => 'ppd'])->with('success', 'PPD-UP berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Bendahara Pengeluaran')) {
            return back()->withErrors(['Akses ditolak: Hanya Bendahara Pengeluaran yang dapat menghapus PPD UP.']);
        }

        $ppdUp = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->findOrFail($id);

        if ($ppdUp->no_opd) {
            return back()->withErrors(['Data tidak dapat dihapus karena sudah diverifikasi ke OPD UP.']);
        }

        $ppdUp->delete();

        return redirect()->route('pencairan-up.index', ['tab' => 'ppd'])->with('success', 'PPD-UP berhasil dihapus.');
    }

    public function unduhSurat($id)
    {
        $ppdUp = Up::where('tahun_anggaran', \App\Support\TahunAnggaran::selected())->with('unitBlud')->findOrFail($id);
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();

            if (!$unitBlud || (int) $ppdUp->unit_blud_id !== (int) $unitBlud->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengunduh surat ini.');
            }
        }

        $data = [
            'surat' => $ppdUp,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('pdf.ppd-up', $data)->setPaper('a4', 'portrait');
        $filename = 'PPD-UP-' . $user->upt . '-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}