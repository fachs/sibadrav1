<?php

namespace App\Support;

use App\Models\BkuBendaharaPengeluaran;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LpjReportData
{
    private const GROUPS = [
        '5.1' => 'BELANJA OPERASI',
        '5.1.01' => 'Belanja Pegawai',
        '5.1.02' => 'Belanja Barang dan Jasa',
        '5.2' => 'BELANJA MODAL',
        '5.2.01' => 'Belanja Modal Tanah',
        '5.2.02' => 'Belanja Modal Peralatan dan Mesin',
        '5.2.03' => 'Belanja Modal Gedung dan Bangunan',
        '5.2.04' => 'Belanja Modal Jalan, Jaringan, dan Irigasi',
        '5.2.05' => 'Belanja Modal Aset Tetap Lainnya',
    ];

    private function add(string $first, string $second): string
    {
        return (string) BigDecimal::of($first)->plus($second)->toScale(2);
    }

    private function emptyRow(string $code, string $label): array
    {
        return ['code' => $code, 'label' => $label, 'budget' => '0.00',
            'gaji_lalu' => '0.00', 'gaji_ini' => '0.00',
            'barang_lalu' => '0.00', 'barang_ini' => '0.00',
            'up_lalu' => '0.00', 'up_ini' => '0.00'];
    }

    private function addAtLevels(array &$rows, array $labels, string $code, string $label, string $field, string $amount): void
    {
        if (! str_starts_with($code, '5.')) {
            $code = '5.1.02';
        }
        $parts = explode('.', $code);
        for ($length = 2; $length <= count($parts); $length++) {
            $key = implode('.', array_slice($parts, 0, $length));
            $rows[$key] ??= $this->emptyRow($key, $labels[$key] ?? self::GROUPS[$key] ?? ($length === count($parts) ? $label : $key));
            $rows[$key][$field] = $this->add($rows[$key][$field], $amount);
        }
    }

    public function build(int $year, int $month, ?string $unit, ?int $unitId): array
    {
        $rows = [];
        $labels = app(KodeRekeningUraian::class)->withPrefix('5.');
        $budget = DB::table('rencana_kebutuhans as r')->join('akuns as a', 'r.akun_id', '=', 'a.id')
            ->whereNull('r.deleted_at')->where('r.tahun_anggaran', (string) $year)
            ->where('a.kode_rek', 'like', '5.%')
            ->when($unit !== null, fn ($query) => $query->whereRaw('LOWER(r.nama_blud) = ?', [strtolower(trim($unit))]))
            ->where(fn ($query) => $query->whereNull('r.is_hasil_perubahan')->orWhere('r.is_hasil_perubahan', false)->orWhere('r.status_perubahan', 'verified'))
            ->selectRaw("a.kode_rek, MAX(a.uraian) AS uraian, SUM(CASE WHEN r.status_perubahan = 'verified' THEN COALESCE(r.jumlah_harga_perubahan, r.jumlah_harga) ELSE r.jumlah_harga END) AS amount")
            ->groupBy('a.kode_rek')->get();
        foreach ($budget as $item) {
            $this->addAtLevels($rows, $labels, $item->kode_rek, $item->uraian, 'budget', (string) $item->amount);
        }

        $cutoff = sprintf('%04d-%02d-01', $month === 12 ? $year + 1 : $year, $month === 12 ? 1 : $month + 1);
        $payments = app(SpdBelanjaData::class)->entries($unitId, $unit === null, $cutoff, $year);
        foreach ($payments as $payment) {
            $kind = $payment->jenis_spd === 'GU' ? 'up' : (str_starts_with((string) $payment->kode_rekening, '5.1.01') ? 'gaji' : 'barang');
            $period = (int) substr($payment->tanggal, 5, 2) === $month ? 'ini' : 'lalu';
            $this->addAtLevels($rows, $labels, (string) $payment->kode_rekening, (string) $payment->uraian,
                $kind.'_'.$period, (string) $payment->pengeluaran);
        }
        $up = ['lalu' => '0.00', 'ini' => '0.00'];
        $advances = Schema::hasTable('spd_ups')
            ? DB::table('spd_ups')->whereNull('deleted_at')->where('tahun_anggaran', (string) $year)
                ->where('tanggal_dba', '>=', $year.'-01-01')->where('tanggal_dba', '<', $cutoff)
                ->when($unit !== null, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('unit_blud_id', $unitId))
                ->select(['tanggal_dba', 'jumlah_akhir'])->get()
            : collect();
        foreach ($advances as $advance) {
            $period = (int) substr($advance->tanggal_dba, 5, 2) === $month ? 'ini' : 'lalu';
            $up[$period] = $this->add($up[$period], (string) $advance->jumlah_akhir);
        }
        $taxes = array_fill_keys(BkuBendaharaPengeluaran::JENIS_PAJAK, ['lalu' => '0.00', 'ini' => '0.00']);
        if (Schema::hasTable('bku_bendahara_pengeluarans') && Schema::hasColumn('bku_bendahara_pengeluarans', 'rincian_pajak')) {
            $taxEntries = DB::table('bku_bendahara_pengeluarans')->whereNull('parent_bku_id')
                ->where('is_saldo_awal', false)->where('tanggal', '>=', $year.'-01-01')
                ->where('tanggal', '<', $cutoff)
                ->when($unit !== null, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('unit_blud_id', $unitId))
                ->where('nominal_pajak', '>', 0)
                ->select(['tanggal', 'jenis_pajak', 'nominal_pajak', 'rincian_pajak'])->get();
            foreach ($taxEntries as $entry) {
                $period = (int) substr($entry->tanggal, 5, 2) === $month ? 'ini' : 'lalu';
                $items = $entry->rincian_pajak !== null
                    ? json_decode($entry->rincian_pajak, true) : [['jenis' => $entry->jenis_pajak, 'nominal' => $entry->nominal_pajak]];
                foreach ($items ?: [] as $item) {
                    $kind = $item['jenis'] ?? null;
                    if (isset($taxes[$kind])) {
                        $taxes[$kind][$period] = $this->add($taxes[$kind][$period], (string) ($item['nominal'] ?? 0));
                    }
                }
            }
        }
        ksort($rows, SORT_NATURAL);

        $total = $this->emptyRow('', 'JUMLAH');
        foreach ($rows as $row) {
            if (substr_count($row['code'], '.') !== 1) continue;
            foreach (array_keys($total) as $field) {
                if (in_array($field, ['code', 'label'], true)) continue;
                $total[$field] = $this->add($total[$field], $row[$field]);
            }
        }

        return ['rows' => array_values($rows), 'total' => $total, 'month' => $month, 'sp2dUp' => $up, 'taxes' => $taxes];
    }
}
