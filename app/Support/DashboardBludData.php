<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardBludData
{
    private const BELANJA = [
        '5.1.01' => 'Belanja Pegawai', '5.1.02' => 'Belanja Barang dan Jasa',
        '5.1.03' => 'Belanja Bunga', '5.1.04' => 'Belanja Subsidi',
        '5.1.05' => 'Belanja Hibah', '5.1.06' => 'Belanja Bantuan Sosial',
        '5.2.01' => 'Belanja Modal Tanah', '5.2.02' => 'Belanja Modal Peralatan dan Mesin',
        '5.2.03' => 'Belanja Modal Gedung dan Bangunan',
        '5.2.04' => 'Belanja Modal Jalan, Jaringan, dan Irigasi',
        '5.2.05' => 'Belanja Modal Aset Tetap Lainnya',
    ];

    private function scoped($query, ?string $blud, string $column)
    {
        return $query->when($blud !== null,
            fn ($query) => $query->whereRaw('LOWER('.$column.') = ?', [strtolower(trim($blud))]));
    }

    private function add(array &$rows, string $code, string $label, string $field, $amount): void
    {
        $rows[$code] ??= ['kode_rek' => $code, 'uraian' => $label, 'anggaran' => '0.00', 'realisasi' => '0.00'];
        $rows[$code][$field] = (string) BigDecimal::of($rows[$code][$field])->plus((string) $amount)->toScale(2, RoundingMode::HalfUp);
    }

    private function expenseCode(?string $code): string
    {
        $code = trim((string) $code);
        return str_starts_with($code, '5.') ? implode('.', array_slice(explode('.', $code), 0, 3)) : '';
    }

    public static function percentage($amount, $budget): ?float
    {
        if (BigDecimal::of((string) $budget)->isLessThanOrEqualTo(0)) {
            return null;
        }

        return (float) (string) BigDecimal::of((string) $amount)->multipliedBy(100)
            ->dividedBy((string) $budget, 1, RoundingMode::HalfUp);
    }

    private function sum(array $rows, string $field): string
    {
        $sum = BigDecimal::of(0);
        foreach ($rows as $row) {
            $sum = $sum->plus($row[$field]);
        }
        return (string) $sum->toScale(2, RoundingMode::HalfUp);
    }

    private function cash(int $year, ?string $blud, ?int $unitId): array
    {
        $start = $year.'-01-01';
        $rows = app(BkuPejabatKeuanganData::class)->entries($unitId, $blud === null, ($year + 1).'-01-01');
        $balances = [];
        $openings = [];
        foreach ($rows as $row) {
            $key = $row->unit_blud_id ?? 'unassigned';
            $balances[$key] ??= BigDecimal::of(0);
            if ($row->tanggal >= $start) {
                $openings[$key] ??= $balances[$key];
            }
            if ($row->is_saldo_awal) {
                $balances[$key] = BigDecimal::of((string) $row->saldo);
                if ($row->tanggal >= $start) {
                    $openings[$key] = $balances[$key];
                }
            } else {
                $balances[$key] = $balances[$key]->plus((string) $row->penerimaan)->minus((string) $row->pengeluaran);
            }
        }
        $balance = BigDecimal::of(0);
        $opening = BigDecimal::of(0);
        foreach ($balances as $key => $amount) {
            $balance = $balance->plus($amount);
            $opening = $opening->plus($openings[$key] ?? $amount);
        }

        return [(string) $opening->toScale(2), (string) $balance->toScale(2)];
    }

    public function build(int $year, ?string $blud, ?int $unitId, ?string $weekDate = null): array
    {
        $income = [];
        $expense = [];
        $monthly = array_fill(1, 12, ['pendapatan' => '0.00', 'belanja' => '0.00']);
        $weekly = [];
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = $yearStart->endOfYear();
        $selectedWeekDate = ($weekDate ? CarbonImmutable::parse($weekDate, 'Asia/Jakarta') : CarbonImmutable::now('Asia/Jakarta'))
            ->max($yearStart)->min($yearEnd)->toDateString();
        $weekStart = CarbonImmutable::parse($selectedWeekDate)->startOfWeek(CarbonImmutable::MONDAY);
        for ($offset = 0; $offset < 6; $offset++) {
            $day = $weekStart->addDays($offset)->locale('id');
            $weekly[$day->toDateString()] = [
                'date' => $day->toDateString(), 'label' => $day->translatedFormat('l'),
                'title' => $day->translatedFormat('l, d M Y'),
                'pendapatan' => '0.00',
            ];
        }
        $weeklyPeriod = $weekStart->locale('id')->translatedFormat('d M Y').' – '.$weekStart->addDays(5)->locale('id')->translatedFormat('d M Y');
        $references = collect(app(ReferensiPendapatan::class)->all())->pluck('uraian', 'kode_rek');
        $plans = $this->scoped(DB::table('rencana_pendapatans'), $blud, 'nama_blud')
            ->where('tahun_anggaran', $year)
            ->where(fn ($query) => $query->whereNull('is_hasil_perubahan')->orWhere('is_hasil_perubahan', false)->orWhere('status_perubahan', 'verified'))
            ->select(['kode_rek', 'uraian', 'jumlah', 'jumlah_perubahan', 'status_perubahan'])->get();
        foreach ($plans as $plan) {
            $amount = $plan->status_perubahan === 'verified' ? ($plan->jumlah_perubahan ?? $plan->jumlah) : $plan->jumlah;
            $this->add($income, $plan->kode_rek, $plan->uraian, 'anggaran', $amount);
        }
        $plans = $this->scoped(DB::table('rencana_kebutuhans as r')->join('akuns as a', 'r.akun_id', '=', 'a.id'), $blud, 'r.nama_blud')
            ->whereNull('r.deleted_at')->where('r.tahun_anggaran', (string) $year)->where('a.kode_rek', 'like', '5.%')
            ->where(fn ($query) => $query->whereNull('r.is_hasil_perubahan')->orWhere('r.is_hasil_perubahan', false)->orWhere('r.status_perubahan', 'verified'))
            ->selectRaw("a.kode_rek, SUM(CASE WHEN r.status_perubahan = 'verified' THEN COALESCE(r.jumlah_harga_perubahan, r.jumlah_harga) ELSE r.jumlah_harga END) AS amount")
            ->groupBy('a.kode_rek')->get();
        foreach ($plans as $plan) {
            $code = $this->expenseCode($plan->kode_rek);
            $this->add($expense, $code, self::BELANJA[$code] ?? 'Belanja Lainnya', 'anggaran', $plan->amount);
        }
        $start = $year.'-01-01';
        $end = ($year + 1).'-01-01';
        $codeExpression = "COALESCE(NULLIF(TRIM(p.kode_rekening), ''), a.kode_rek, '')";
        $receipts = DB::table('transaksi_penerimaans as t')->join('penerimaans as p', 't.penerimaan_id', '=', 'p.id')
            ->leftJoin('akuns as a', 'p.akun_id', '=', 'a.id')
            ->whereNull('p.deleted_at')->where('p.tahun_anggaran', (string) $year)
            ->where('t.tanggal', '>=', $start)->where('t.tanggal', '<', $end)
            ->when($blud !== null, fn ($query) => $unitId ? $query->where('p.unit_blud_id', $unitId) : $query->whereRaw('1 = 0'))
            ->selectRaw('t.tanggal, '.$codeExpression.' AS code, MAX(a.uraian) AS label, SUM(t.total_biaya) AS amount')
            ->groupBy('t.tanggal', 'p.kode_rekening', 'a.kode_rek')->get();
        foreach ($receipts as $receipt) {
            $code = $receipt->code;
            $label = $references->get($code) ?? $receipt->label ?? 'Pendapatan Belum Diklasifikasikan';
            $this->add($income, $code, $label, 'realisasi', $receipt->amount);
            $month = (int) substr($receipt->tanggal, 5, 2);
            $monthly[$month]['pendapatan'] = (string) BigDecimal::of($monthly[$month]['pendapatan'])->plus((string) $receipt->amount)->toScale(2);
            $date = substr($receipt->tanggal, 0, 10);
            if (isset($weekly[$date])) {
                $weekly[$date]['pendapatan'] = (string) BigDecimal::of($weekly[$date]['pendapatan'])->plus((string) $receipt->amount)->toScale(2);
            }
        }
        $payments = app(SpdBelanjaData::class)->entries($unitId, $blud === null, year: $year);
        foreach ($payments as $payment) {
            $code = $this->expenseCode($payment->kode_rekening);
            $this->add($expense, $code, self::BELANJA[$code] ?? 'Belanja Belum Diklasifikasikan', 'realisasi', $payment->pengeluaran);
            $month = (int) substr($payment->tanggal, 5, 2);
            $monthly[$month]['belanja'] = (string) BigDecimal::of($monthly[$month]['belanja'])->plus((string) $payment->pengeluaran)->toScale(2);
        }
        $totals = [
            'target_pendapatan' => $this->sum($income, 'anggaran'), 'realisasi_pendapatan' => $this->sum($income, 'realisasi'),
            'pagu_belanja' => $this->sum($expense, 'anggaran'), 'realisasi_belanja' => $this->sum($expense, 'realisasi'),
        ];
        $totals['surplus_anggaran'] = (string) BigDecimal::of($totals['target_pendapatan'])->minus($totals['pagu_belanja']);
        $totals['surplus_realisasi'] = (string) BigDecimal::of($totals['realisasi_pendapatan'])->minus($totals['realisasi_belanja']);
        [$totals['saldo_awal'], $totals['saldo_kas']] = $this->cash($year, $blud, $unitId);
        foreach (['pendapatan', 'belanja'] as $type) {
            $totals['persentase_'.$type] = self::percentage($totals['realisasi_'.$type], $totals[$type === 'pendapatan' ? 'target_pendapatan' : 'pagu_belanja']);
        }
        $finish = function (array $rows) {
            ksort($rows, SORT_NATURAL);
            return array_values(array_map(fn ($row) => $row + ['persentase' => self::percentage($row['realisasi'], $row['anggaran'])], $rows));
        };

        return ['totals' => $totals, 'incomeRows' => $finish($income), 'expenseRows' => $finish($expense),
            'monthly' => array_values($monthly), 'weekly' => array_values($weekly),
            'selectedWeekDate' => $selectedWeekDate, 'weeklyPeriod' => $weeklyPeriod];
    }
}
