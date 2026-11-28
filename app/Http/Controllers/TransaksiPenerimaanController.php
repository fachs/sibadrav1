<?php

namespace App\Http\Controllers;

use App\Models\Penerimaan;
use App\Models\TransaksiPenerimaan;
use App\Imports\TransaksiPenerimaanImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class TransaksiPenerimaanController extends Controller
{
    private function ensureSchema(): void
    {
        if (Schema::hasTable('transaksi_penerimaans') && ! Schema::hasColumn('transaksi_penerimaans', 'nomor_tbp')) {
            Schema::table('transaksi_penerimaans', function (Blueprint $table) {
                $table->string('nomor_tbp', 100)->nullable()->after('nomor_transaksi');
            });
        }
    }

    private function checkUptAuthorization(Penerimaan $penerimaan)
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        if (! $isDinkes) {
            $unitBlud = \App\Models\UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if ($unitBlud && $penerimaan->unit_blud_id !== $unitBlud->id) {
                abort(403, 'Akses ditolak.');
            }
        }

        return $isDinkes;
    }

    public function show($uuid)
    {
        $this->ensureSchema();

        $penerimaan = Penerimaan::with('transaksis')->where('uuid', $uuid)->firstOrFail();
        $isDinkes = $this->checkUptAuthorization($penerimaan);

        $transaksis = $penerimaan->transaksis()->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->paginate(10);
        $totalBiaya = $penerimaan->transaksis()->sum('total_biaya');

        return view('penerimaan-transaksi', compact('penerimaan', 'transaksis', 'totalBiaya', 'isDinkes'));
    }

    public function storeManual(Request $request, $uuid)
    {
        $this->ensureSchema();

        $penerimaan = Penerimaan::where('uuid', $uuid)->firstOrFail();
        $isDinkes = $this->checkUptAuthorization($penerimaan);
        if ($isDinkes) {
            abort(403, 'Akses ditolak.');
        }

        if ($request->has('total_biaya')) {
            $request->merge([
                'total_biaya' => (int) preg_replace('/[^0-9]/', '', $request->total_biaya),
            ]);
        }

        $isTbpPerTransaksi = $penerimaan->isTbpPerTransaksi();

        $rules = [
            'tanggal' => 'required|date',
            'nomor_transaksi' => 'required|string|max:100',
            'nama_penjamin' => 'required|string|max:150',
            'metode_pembayaran' => 'required|string|max:100',
            'total_biaya' => 'required|numeric|min:0',
            'nomor_tbp' => $isTbpPerTransaksi
                ? ['required', 'string', 'max:100', Rule::unique('transaksi_penerimaans', 'nomor_tbp')]
                : ['nullable', 'string', 'max:100'],
        ];

        $validated = $request->validate($rules, [
            'nomor_tbp.required' => 'Nomor TBP wajib diisi untuk setiap transaksi pada cara pembayaran '.$penerimaan->cara_pembayaran.'.',
            'nomor_tbp.unique' => 'Nomor TBP tersebut sudah digunakan pada transaksi lain.',
        ]);

        if (! $isTbpPerTransaksi) {
            $validated['nomor_tbp'] = null;
        } else {
            $validated['nomor_tbp'] = trim((string) $validated['nomor_tbp']);
        }

        $validated['status'] = 'Selesai';

        $penerimaan->transaksis()->create($validated);

        return back()->with('success', 'Data transaksi manual berhasil ditambahkan.');
    }

    public function updateNomorTbp(Request $request, $id)
    {
        $this->ensureSchema();

        $transaksi = TransaksiPenerimaan::with('penerimaan')->findOrFail($id);
        abort_if(! $transaksi->penerimaan, 404);

        $isDinkes = $this->checkUptAuthorization($transaksi->penerimaan);
        if ($isDinkes) {
            abort(403, 'Akses ditolak.');
        }

        abort_unless(
            $transaksi->penerimaan->isTbpPerTransaksi(),
            422,
            'Nomor TBP per transaksi hanya berlaku untuk cara pembayaran Kas Penerimaan BLUD dan Kas BLUD.'
        );

        $validated = $request->validate([
            'nomor_tbp' => [
                'required',
                'string',
                'max:100',
                Rule::unique('transaksi_penerimaans', 'nomor_tbp')->ignore($transaksi->id),
            ],
        ], [
            'nomor_tbp.required' => 'Nomor TBP tidak boleh kosong.',
            'nomor_tbp.unique' => 'Nomor TBP tersebut sudah digunakan pada transaksi lain.',
        ]);

        $transaksi->update([
            'nomor_tbp' => trim($validated['nomor_tbp']),
        ]);

        return back()->with('success', 'Nomor TBP transaksi berhasil disimpan.');
    }

    public function importExcel(Request $request, $uuid)
    {
        $this->ensureSchema();

        $penerimaan = Penerimaan::where('uuid', $uuid)->firstOrFail();
        $isDinkes = $this->checkUptAuthorization($penerimaan);
        if ($isDinkes) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'file_excel' => 'required|mimes:xls,xlsx,csv',
        ]);

        try {
            DB::transaction(function () use ($penerimaan, $request) {
                $penerimaan->transaksis()->delete();
                Excel::import(new TransaksiPenerimaanImport($penerimaan->id), $request->file('file_excel'));
            });

            return back()->with('success', 'Data transaksi lama berhasil direset dan data baru berhasil diimport.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat import: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $transaksi = TransaksiPenerimaan::with('penerimaan')->findOrFail($id);
        if ($transaksi->penerimaan) {
            $isDinkes = $this->checkUptAuthorization($transaksi->penerimaan);
            if ($isDinkes) {
                abort(403, 'Akses ditolak.');
            }
        }
        $transaksi->delete();

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }
}
