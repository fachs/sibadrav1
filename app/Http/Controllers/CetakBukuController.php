<?php

namespace App\Http\Controllers;

use App\Models\Penerimaan;
use App\Models\UnitBlud;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Pengembalian; // pastikan ada di atas

class CetakBukuController extends Controller
{


    public function index(Request $request)
    {
        $selectedPeriode = $request->get('periode');
        $selectedUnitBludId = $request->get('unit_blud_id');
        $user = auth()->user();

        $bulanList = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $unitBludId = null;

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if (!$unitBlud) {
                return back()->with('error', 'Unit BLUD tidak ditemukan berdasarkan UPT user.');
            }
            $unitBludId = $unitBlud->id;
        }

        // TOTAL penerimaan: tanpa filter verifikasi
        // TOTAL penerimaan: tanpa filter verifikasi
        $totalPenerimaanQuery = Penerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());

        // Data penerimaan/penyetoran buku
        $penerimaanQuery = Penerimaan::with('transaksis')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());

        if (!$isDinkes && $unitBludId) {
            $totalPenerimaanQuery->where('unit_blud_id', $unitBludId);
            $penerimaanQuery->where('unit_blud_id', $unitBludId);
        }

        // Khusus Dinkes: boleh filter per unit BLUD
        if ($isDinkes && !empty($selectedUnitBludId)) {
            $totalPenerimaanQuery->where('unit_blud_id', $selectedUnitBludId);
            $penerimaanQuery->where('unit_blud_id', $selectedUnitBludId);
        }

        if ($selectedPeriode) {
            $totalPenerimaanQuery->where('periode', $selectedPeriode);
            $penerimaanQuery->where('periode', $selectedPeriode);
        }

        $penerimaans = $penerimaanQuery->orderBy('tanggal_terima')->get();

        $penyetorans = $penerimaans
            ->filter(fn ($p) => !empty($p->tanggal_setor))
            ->map(fn ($p) => (object) [
                'tanggal' => $p->tanggal_setor,
                'jumlah' => $p->jumlah,
                'nomor' => $p->nomor_tbp,
                'penerimaan' => $p,
                'unit_blud_id' => $p->unit_blud_id,
            ])
            ->values();

        $totalPenerimaan = (float) $totalPenerimaanQuery
            ->join('transaksi_penerimaans', 'penerimaans.id', '=', 'transaksi_penerimaans.penerimaan_id')
            ->sum('transaksi_penerimaans.total_biaya');
        $totalPenyetoran = (float) $penyetorans->sum('jumlah');
        $selisih = $totalPenerimaan - $totalPenyetoran;

        $unitBluds = $isDinkes ? UnitBlud::orderBy('nama')->get() : collect();

        return view('buku-penerimaan-penyetoran', compact(
            'penerimaans',
            'penyetorans',
            'selectedPeriode',
            'selectedUnitBludId',
            'unitBluds',
            'bulanList',
            'totalPenerimaan',
            'totalPenyetoran',
            'selisih'
        ));
    }

    public function pdf(Request $request)
    {
        $selectedPeriode = $request->get('periode');
        $selectedUnitBludId = $request->get('unit_blud_id');
        $user = auth()->user();

        $isDinkes = strtolower(trim((string) $user->upt)) === 'dinas kesehatan';
        $unitBludId = null;

        if (!$isDinkes) {
            $unitBlud = UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $user->upt))])->first();
            if (!$unitBlud) {
                return back()->with('error', 'Unit BLUD tidak ditemukan berdasarkan UPT user.');
            }
            $unitBludId = $unitBlud->id;
        }

        // TOTAL penerimaan PDF: tanpa filter verifikasi
        // TOTAL penerimaan PDF: tanpa filter verifikasi
        $totalPenerimaanQuery = Penerimaan::query()->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());
        // Total pengembalian (unit/periode mengikuti filter aktif)
        $totalPengembalianQuery = Pengembalian::with('penerimaan.transaksis')->whereHas('penerimaan', fn ($query) => $query->where('tahun_anggaran', \App\Support\TahunAnggaran::selected()));
        // Data buku PDF
        $penerimaanQuery = Penerimaan::with('transaksis')->where('tahun_anggaran', \App\Support\TahunAnggaran::selected());

        if (!$isDinkes && $unitBludId) {
            $totalPenerimaanQuery->where('unit_blud_id', $unitBludId);
            $totalPengembalianQuery->where('unit_blud_id', $unitBludId);
            $penerimaanQuery->where('unit_blud_id', $unitBludId);
        }

        if ($isDinkes && !empty($selectedUnitBludId)) {
            $totalPenerimaanQuery->where('unit_blud_id', $selectedUnitBludId);
            $totalPengembalianQuery->where('unit_blud_id', $selectedUnitBludId);
            $penerimaanQuery->where('unit_blud_id', $selectedUnitBludId);
        }

        if ($selectedPeriode) {
            $totalPenerimaanQuery->where('periode', $selectedPeriode);
            $penerimaanQuery->where('periode', $selectedPeriode);
            $totalPengembalianQuery->whereHas('penerimaan', function ($q) use ($selectedPeriode) {
                $q->where('periode', $selectedPeriode);
            });
        }

        $penerimaans = $penerimaanQuery->orderBy('tanggal_terima')->get();

        $penyetorans = $penerimaans
            ->filter(fn ($p) => !empty($p->tanggal_setor))
            ->map(fn ($p) => (object) [
                'tanggal' => $p->tanggal_setor,
                'jumlah' => $p->jumlah,
                'nomor' => $p->nomor_tbp,
                'penerimaan' => $p,
                'unit_blud_id' => $p->unit_blud_id,
            ])
            ->values();

        $totalPenerimaan = (float) $totalPenerimaanQuery
            ->join('transaksi_penerimaans', 'penerimaans.id', '=', 'transaksi_penerimaans.penerimaan_id')
            ->sum('transaksi_penerimaans.total_biaya');
        $totalPenyetoran = (float) $penyetorans->sum('jumlah');
        $totalPengembalian = (float) $totalPengembalianQuery->get()->sum(function ($p) {
            return $p->penerimaan ? $p->penerimaan->jumlah : 0;
        });
        $selectedUnitBludName = null;
        if ($isDinkes && !empty($selectedUnitBludId)) {
            $selectedUnitBludName = optional(UnitBlud::find($selectedUnitBludId))->nama;
        }

        $data = [
            'user' => $user, 'tahun' => \App\Support\TahunAnggaran::selected(),
            'selectedPeriode' => $selectedPeriode,
            'selectedUnitBludId' => $selectedUnitBludId,
            'penerimaans' => $penerimaans,
            'penyetorans' => $penyetorans,
            'totalPenerimaan' => $totalPenerimaan,
            'totalPenyetoran' => $totalPenyetoran,
            'totalPengembalian' => $totalPengembalian,
            'selectedUnitBludName' => $selectedUnitBludName,
            'selisih' => $totalPenerimaan - $totalPenyetoran,
            'selisih2' => $totalPenyetoran - $totalPengembalian,
            'tanggalCetak' => now()->format('d-m-Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.buku-penerimaan-penyetoran', $data)->setPaper('a4', 'landscape');

        $filename = 'BKU-' . $selectedUnitBludName . '-' . now()->format('Ymd-His') . '.pdf';
        return $pdf->download($filename);
    }
}