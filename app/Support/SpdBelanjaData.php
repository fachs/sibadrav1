<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpdBelanjaData
{
    public function entries(?int $unitId, bool $allUnits = false, ?string $before = null, ?int $year = null): Collection
    {
        $rows = collect();
        $date = 'COALESCE(s.tanggal_dba, DATE(s.created_at))';
        foreach (['ls', 'gu'] as $kind) {
            $query = DB::table('spd_'.$kind.'s as s')->whereNull('s.deleted_at')
                ->whereRaw($date.' IS NOT NULL')
                ->when(!$allUnits, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('s.unit_blud_id', $unitId))
                ->when($before !== null, fn ($query) => $query->whereRaw($date.' < ?', [$before]))
                ->when($year !== null, fn ($query) => $query->where('s.tahun_anggaran', (string) $year)
                    ->whereRaw($date.' >= ? AND '.$date.' < ?', [$year.'-01-01', ($year + 1).'-01-01']))
                ->select(['s.id', 's.unit_blud_id', 's.nomor as no_transaksi', 's.jumlah_akhir as pengeluaran'])
                ->selectRaw($date.' AS tanggal');

            if ($kind === 'ls') {
                $query->leftJoin('opd_lss as o', function ($join) {
                    $join->on('o.id', '=', 's.opd_ls_id')->on('o.unit_blud_id', '=', 's.unit_blud_id')->whereNull('o.deleted_at');
                })->leftJoin('ppd_lss as p', function ($join) {
                    $join->on('p.id', '=', 'o.ppd_ls_id')->on('p.unit_blud_id', '=', 's.unit_blud_id')->whereNull('p.deleted_at');
                })->addSelect(['p.kode_rekening', 'p.uraian']);
            } else {
                // GU currently has no account breakdown; retain it as unclassified spending.
                $query->selectRaw('NULL AS kode_rekening, NULL AS uraian');
            }

            $rows = $rows->concat($query->get()->map(function ($row) use ($kind) {
                $row->id = 'spd_'.$kind.'_'.$row->id;
                $row->uraian = 'Pencairan SPD '.strtoupper($kind).($row->uraian ? ' - '.$row->uraian : '');
                $row->penerimaan = 0;
                $row->giro = 0;
                $row->saldo = 0;
                $row->is_saldo_awal = false;
                $row->is_penyetoran = false;
                $row->is_spd = true;
                $row->jenis_spd = strtoupper($kind);

                return $row;
            }));
        }

        return $rows->sortBy([['tanggal', 'asc'], ['id', 'asc']])->values();
    }
}
