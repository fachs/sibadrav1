<?php

namespace App\Http\Controllers;

use App\Models\Penerimaan;
use App\Models\TransaksiPenerimaan;
use App\Imports\TransaksiPenerimaanImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TransaksiPenerimaanController extends Controller
{
    private function checkUptAuthorization(Penerimaan $penerimaan)
    {
        $user = auth()->user();
        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        // Authorization: ensure this belongs to the user's UPT
        if (!$isDinkes) {
            $unitBlud = \App\Models\UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if ($unitBlud && $penerimaan->unit_blud_id !== $unitBlud->id) {
                abort(403, 'Akses ditolak.');
            }
        }
        return $isDinkes;
    }

    public function show($uuid)
    {
        $penerimaan = Penerimaan::with('transaksis')->where('uuid', $uuid)->firstOrFail();
        
        $isDinkes = $this->checkUptAuthorization($penerimaan);

        $transaksis = $penerimaan->transaksis()->orderBy('tanggal', 'desc')->paginate(10);
        $totalBiaya = $penerimaan->transaksis()->sum('total_biaya');

        return view('penerimaan-transaksi', compact('penerimaan', 'transaksis', 'totalBiaya', 'isDinkes'));
    }

    public function storeManual(Request $request, $uuid)
    {
        $penerimaan = Penerimaan::where('uuid', $uuid)->firstOrFail();
        $isDinkes = $this->checkUptAuthorization($penerimaan);
        if ($isDinkes) {
            abort(403, 'Akses ditolak.');
        }

        if ($request->has('total_biaya')) {
            $request->merge([
                'total_biaya' => (int) preg_replace('/[^0-9]/', '', $request->total_biaya)
            ]);
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'nomor_transaksi' => 'required|string',
            'nama_penjamin' => 'required|string',
            'metode_pembayaran' => 'required|string',
            'total_biaya' => 'required|numeric|min:0',
        ]);

        $validated['status'] = 'Selesai';

        $penerimaan->transaksis()->create($validated);

        return back()->with('success', 'Data transaksi manual berhasil ditambahkan.');
    }

    public function importExcel(Request $request, $uuid)
    {
        $penerimaan = Penerimaan::where('uuid', $uuid)->firstOrFail();
        $isDinkes = $this->checkUptAuthorization($penerimaan);
        if ($isDinkes) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'file_excel' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            DB::transaction(function () use ($penerimaan, $request) {
                $penerimaan->transaksis()->delete();
                Excel::import(new TransaksiPenerimaanImport($penerimaan->id), $request->file('file_excel'));
            });

            return back()->with('success', 'Data transaksi lama berhasil direset dan data baru berhasil diimport.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
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
