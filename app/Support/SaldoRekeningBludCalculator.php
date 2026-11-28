<?php

namespace App\Support;

use App\Models\BkuBendaharaPengeluaran;
use App\Models\Penerimaan;
use App\Models\Pengembalian;
use App\Models\RekeningBlud;
use App\Models\SpdGu;
use App\Models\SpdLs;
use App\Models\Up;
use Illuminate\Support\Facades\DB;

class SaldoRekeningBludCalculator
{
    /**
     * Hitung mutasi penerimaan dan pengeluaran untuk masing-masing jenis rekening BLUD
     * pada unit BLUD dan tahun anggaran terpilih.
     *
     * @return array<string, array{masuk: float, keluar: float}>
     */
    public function mutationsForUnit(int $unitBludId, int $tahun): array
    {
        // 1. Mutasi Rek. Bendahara Penerimaan
        $penerimaanIds = Penerimaan::query()
            ->where('unit_blud_id', $unitBludId)
            ->where('tahun_anggaran', $tahun)
            ->pluck('id');

        $totalDiterima = $penerimaanIds->isEmpty()
            ? 0.0
            : (float) DB::table('transaksi_penerimaans')
                ->whereIn('penerimaan_id', $penerimaanIds)
                ->sum('total_biaya');

        $disetorkanIds = Penerimaan::query()
            ->where('unit_blud_id', $unitBludId)
            ->where('tahun_anggaran', $tahun)
            ->whereNotNull('tanggal_setor')
            ->pluck('id');

        $totalDisetorkan = $disetorkanIds->isEmpty()
            ? 0.0
            : (float) DB::table('transaksi_penerimaans')
                ->whereIn('penerimaan_id', $disetorkanIds)
                ->sum('total_biaya');

        $totalPengembalian = (float) Pengembalian::query()
            ->where('unit_blud_id', $unitBludId)
            ->whereHas('penerimaan', fn ($q) => $q->where('tahun_anggaran', $tahun))
            ->sum('jumlah');

        // 2. Pencairan SPD UP, GU, LS (Keluar dari Rekening BLUD -> Masuk ke Rek. Bendahara Pengeluaran)
        $totalSpdUp = (float) Up::query()
            ->where('unit_blud_id', $unitBludId)
            ->where('tahun_anggaran', $tahun)
            ->whereNotNull('no_spd')
            ->where('no_spd', '<>', '')
            ->get(['jumlah', 'potongan'])
            ->sum(fn ($up) => max(0, (float) $up->jumlah - (float) $up->potongan));

        $totalSpdGu = (float) SpdGu::query()
            ->where('unit_blud_id', $unitBludId)
            ->where('tahun_anggaran', $tahun)
            ->sum('jumlah_akhir');

        $totalSpdLs = (float) SpdLs::query()
            ->where('unit_blud_id', $unitBludId)
            ->where('tahun_anggaran', $tahun)
            ->sum('jumlah_akhir');

        $totalPencairanSpd = $totalSpdUp + $totalSpdGu + $totalSpdLs;

        // 3. Mutasi BKU Bendahara Pengeluaran (di luar saldo awal)
        $bkuBendaharaQuery = TahunAnggaran::dates(BkuBendaharaPengeluaran::query(), 'tanggal', $tahun)
            ->where('unit_blud_id', $unitBludId)
            ->where('is_saldo_awal', false);

        $bkuBendaharaMasuk = (float) (clone $bkuBendaharaQuery)->sum('penerimaan');
        $bkuBendaharaKeluar = (float) (clone $bkuBendaharaQuery)->sum('pengeluaran');

        // 4. Mutasi manual BKU Pejabat Keuangan / Kas BLUD (di luar saldo awal)
        $bkuPkQuery = TahunAnggaran::dates(DB::table('bku_pejabat_keuangans'), 'tanggal', $tahun)
            ->where('unit_blud_id', $unitBludId)
            ->where('is_saldo_awal', false);

        $bkuPkMasukManual = (float) (clone $bkuPkQuery)->sum('penerimaan');
        $bkuPkKeluarManual = (float) (clone $bkuPkQuery)->sum('pengeluaran');

        return [
            RekeningBlud::JENIS_PENERIMAAN => [
                'masuk' => $totalDiterima,
                'keluar' => $totalDisetorkan + $totalPengembalian,
            ],
            RekeningBlud::JENIS_PENGELUARAN => [
                'masuk' => $totalPencairanSpd + $bkuBendaharaMasuk,
                'keluar' => $bkuBendaharaKeluar,
            ],
            RekeningBlud::JENIS_BLUD => [
                'masuk' => $totalDisetorkan + $bkuPkMasukManual,
                'keluar' => $totalPencairanSpd + $bkuPkKeluarManual,
            ],
        ];
    }

    public function syncRekeningSaldo(RekeningBlud $rekening, array $mutation): float
    {
        $saldoAwal = (float) ($rekening->saldo_awal ?? 0);
        $masuk = (float) ($mutation['masuk'] ?? 0);
        $keluar = (float) ($mutation['keluar'] ?? 0);
        $saldoAkhir = round($saldoAwal + $masuk - $keluar, 2);

        if (abs(((float) $rekening->saldo) - $saldoAkhir) >= 0.01) {
            $rekening->forceFill(['saldo' => $saldoAkhir])->saveQuietly();
        }

        return $saldoAkhir;
    }
}
