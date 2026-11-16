<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BkuPejabatKeuanganData
{
    public const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public function entries(?int $unitId, bool $allUnits = false, ?string $before = null, ?int $year = null): Collection
    {
        $manual = DB::table('bku_pejabat_keuangans')
            ->when($year !== null, fn ($query) => TahunAnggaran::dates($query, 'tanggal', $year))
            ->when(!$allUnits, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('unit_blud_id', $unitId))
            ->when($before !== null, fn ($query) => $query->whereNotNull('tanggal')->where('tanggal', '<', $before))
            ->get()->map(function ($row) {
                $row->is_penyetoran = false;
                $row->is_spd = false;

                return $row;
            });

        $amounts = DB::table('transaksi_penerimaans')->selectRaw('penerimaan_id, SUM(total_biaya) AS jumlah')->groupBy('penerimaan_id');
        $deposits = DB::table('penerimaans as p')->joinSub($amounts, 't', 't.penerimaan_id', '=', 'p.id')
            ->when($year !== null, fn ($query) => TahunAnggaran::dates($query, 'p.tanggal_setor', $year))
            ->whereNull('p.deleted_at')->whereNotNull('p.tanggal_setor')
            ->when(!$allUnits, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('p.unit_blud_id', $unitId))
            ->when($before !== null, fn ($query) => $query->where('p.tanggal_setor', '<', $before))
            ->select(['p.id', 'p.unit_blud_id', 'p.kode_rekening', 'p.uraian', 'p.tanggal_setor as tanggal'])
            ->selectRaw("COALESCE(NULLIF(p.nomor_tbp, ''), p.nomor_terima) AS no_transaksi, t.jumlah AS penerimaan, 0 AS pengeluaran, 0 AS giro, 0 AS saldo, 0 AS is_saldo_awal")
            ->get()->map(function ($row) {
                $row->is_penyetoran = true;
                $row->is_spd = false;

                return $row;
            });

        // Opening balances precede transactions on the same date.
        $payments = app(SpdBelanjaData::class)->entries($unitId, $allUnits, $before, $year);

        return $manual->concat($deposits)->concat($payments)->sortBy([
            ['tanggal', 'asc'], ['is_saldo_awal', 'desc'], ['is_penyetoran', 'asc'], ['id', 'asc'],
        ])->values();
    }

    public function report(?int $unitId, ?string $bulan = null, ?int $year = null): array
    {
        $rows = $this->entries($unitId, false, null, $year);
        $balance = BigDecimal::zero();
        foreach ($rows as $row) {
            $balance = $row->is_saldo_awal ? BigDecimal::of((string) $row->saldo)
                : $balance->plus((string) $row->penerimaan)->minus((string) $row->pengeluaran);
            $row->saldo = (string) $balance;
        }

        $monthIndex = array_search($bulan, self::BULAN, true);
        $month = $monthIndex === false ? null : sprintf('%02d', $monthIndex + 1);
        $bkuList = $month === null ? $rows : $rows->filter(fn ($row) => substr((string) $row->tanggal, 5, 2) === $month)->values();

        return [
            'bkuList' => $bkuList,
            'totalPenerimaan' => $bkuList->where('is_saldo_awal', false)->sum('penerimaan'),
            'totalPengeluaran' => $bkuList->where('is_saldo_awal', false)->sum('pengeluaran'),
            'totalGiro' => $bkuList->sum('giro'),
            'saldoAkhir' => $bkuList->last()?->saldo ?? 0,
            'bkuSaldoAwal' => $rows->first(fn ($row) => $row->is_saldo_awal),
            'jumlahSdTanggalLalu' => $month === null ? 0 : $rows->filter(fn ($row) => !$row->is_saldo_awal && substr((string) $row->tanggal, 5, 2) < $month)->sum('penerimaan'),
        ];
    }
}
