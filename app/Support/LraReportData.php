<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LraReportData
{
    private const FIELDS = ['budget', 'actual', 'previous'];

    public function build(int $year, ?string $unit, ?int $unitId, ?string $startDate = null, ?string $endDate = null, int $classification = 6): array
    {
        $startDate ??= $year.'-01-01';
        $endDate ??= $year.'-12-31';
        $layout = json_decode(file_get_contents(resource_path('data/lra-format.json')), true, 512, JSON_THROW_ON_ERROR);
        $labels = collect($layout)->where('code', '!=', '')->pluck('label', 'code')->all();
        $labels += ['6' => 'PEMBIAYAAN DAERAH', '6.1' => 'PENERIMAAN PEMBIAYAAN', '6.2' => 'PENGELUARAN PEMBIAYAAN'];
        $amounts = [];
        $this->incomeBudget($year, $unit, $amounts, $labels);
        $this->expenseBudget($year, $unit, $amounts, $labels);
        $this->financingBudget($year, $unit, $amounts, $labels);
        $plannedCodes = array_fill_keys(array_keys($amounts), true);
        foreach ([$year => 'actual', $year - 1 => 'previous'] as $period => $field) {
            $periodStart = $period === $year ? $startDate : CarbonImmutable::parse($startDate)->subYear()->toDateString();
            $periodEnd = $period === $year ? $endDate : CarbonImmutable::parse($endDate)->subYear()->toDateString();
            $this->incomeActual($period, $unit, $unitId, $field, $periodStart, $periodEnd, $plannedCodes, $amounts);
            $this->expenseActual($period, $unit, $unitId, $field, $periodStart, $periodEnd, $plannedCodes, $amounts);
        }
        $knownLabels = app(KodeRekeningUraian::class)->withPrefix('')
            + collect(app(ReferensiPendapatan::class)->all())->pluck('uraian', 'kode_rek')->all();
        $activeCodes = $plannedCodes;
        foreach (array_keys($plannedCodes) as $code) {
            $parts = explode('.', $code);
            while (count($parts) > 1) {
                array_pop($parts);
                $parent = implode('.', $parts);
                $activeCodes[$parent] = true;
                $labels[$parent] ??= $knownLabels[$parent] ?? $labels[$code] ?? 'Rekening '.$parent;
            }
        }
        $codes = array_keys($activeCodes);
        usort($codes, 'strnatcmp');
        $rows = [];
        $hasIncome = isset($activeCodes['4']);
        $hasExpense = isset($activeCodes['5']);
        $hasFinancing = isset($activeCodes['6']);
        $operationTotalAdded = false;
        $financingReceiptsAdded = false;
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code === '5') {
                if ($hasIncome) $rows = array_merge($rows, $this->incomeTotals($amounts));
            }
            if ($code === '5.2' && isset($activeCodes['5.1']) && ! $operationTotalAdded) {
                $rows[] = $this->summary('JUMLAH BELANJA OPERASI', $this->totalForPrefix($amounts, '5.1'));
                $operationTotalAdded = true;
            }
            if ($code === '6') {
                if ($hasIncome && ! $hasExpense) $rows = array_merge($rows, $this->incomeTotals($amounts));
                if ($hasExpense) {
                    if (isset($activeCodes['5.1']) && ! $operationTotalAdded) {
                        $rows[] = $this->summary('JUMLAH BELANJA OPERASI', $this->totalForPrefix($amounts, '5.1'));
                        $operationTotalAdded = true;
                    }
                    if (isset($activeCodes['5.2'])) $rows[] = $this->summary('JUMLAH BELANJA MODAL', $this->totalForPrefix($amounts, '5.2'));
                    $rows[] = $this->summary('JUMLAH BELANJA', $this->totalForPrefix($amounts, '5'));
                }
                if ($hasIncome || $hasExpense) $rows[] = $this->summary('SURPLUS/DEFISIT', $this->subtract($this->totalForPrefix($amounts, '4'), $this->totalForPrefix($amounts, '5')));
            }
            if ($code === '6.2' && isset($activeCodes['6.1']) && ! $financingReceiptsAdded) {
                $rows[] = $this->summary('JUMLAH PENERIMAAN PEMBIAYAAN', $this->totalForPrefix($amounts, '6.1'));
                $financingReceiptsAdded = true;
            }
            $rows[] = $this->row($code, $labels[$code] ?? $knownLabels[$code] ?? 'Rekening '.$code, $this->totalForPrefix($amounts, $code),
                in_array($code, ['4', '5', '6', '4.1', '5.1', '5.2', '6.1', '6.2'], true) ? 'section' : 'detail');
        }
        if ($hasIncome && ! $hasExpense && ! $hasFinancing) {
            $rows = array_merge($rows, $this->incomeTotals($amounts));
        }
        if ($hasExpense && ! $hasFinancing) {
            if (isset($activeCodes['5.1']) && ! $operationTotalAdded) $rows[] = $this->summary('JUMLAH BELANJA OPERASI', $this->totalForPrefix($amounts, '5.1'));
            if (isset($activeCodes['5.2'])) $rows[] = $this->summary('JUMLAH BELANJA MODAL', $this->totalForPrefix($amounts, '5.2'));
            $rows[] = $this->summary('JUMLAH BELANJA', $this->totalForPrefix($amounts, '5'));
        }
        if (($hasIncome || $hasExpense) && ! $hasFinancing) {
            $rows[] = $this->summary('SURPLUS/DEFISIT', $this->subtract($this->totalForPrefix($amounts, '4'), $this->totalForPrefix($amounts, '5')));
        }
        if ($hasFinancing) {
            if (isset($activeCodes['6.1']) && ! $financingReceiptsAdded) $rows[] = $this->summary('JUMLAH PENERIMAAN PEMBIAYAAN', $this->totalForPrefix($amounts, '6.1'));
            if (isset($activeCodes['6.2'])) $rows[] = $this->summary('JUMLAH PENGELUARAN PEMBIAYAAN', $this->totalForPrefix($amounts, '6.2'));
            $rows[] = $this->summary('PEMBIAYAAN NETO', $this->subtract($this->totalForPrefix($amounts, '6.1'), $this->totalForPrefix($amounts, '6.2')));
        }
        if ($activeCodes) {
            $surplus = $this->subtract($this->totalForPrefix($amounts, '4'), $this->totalForPrefix($amounts, '5'));
            $financing = $this->subtract($this->totalForPrefix($amounts, '6.1'), $this->totalForPrefix($amounts, '6.2'));
            $rows[] = $this->summary('SISA LEBIH PEMBIAYAAN ANGGARAN', $this->sum($surplus, $financing));
        }

        return array_values(array_filter($rows, fn ($row) => $row['code'] === ''
            || count(explode('.', $row['code'])) <= $classification));
    }

    private function incomeBudget(int $year, ?string $unit, array &$amounts, array &$labels): void
    {
        $plans = DB::table('rencana_pendapatans')->where('tahun_anggaran', $year)
            ->when($unit !== null, fn ($query) => $query->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim($unit))]))
            ->where(fn ($query) => $query->whereNull('is_hasil_perubahan')->orWhere('is_hasil_perubahan', false)->orWhere('status_perubahan', 'verified'))
            ->get(['kode_rek', 'uraian', 'jumlah', 'jumlah_perubahan', 'status_perubahan']);
        foreach ($plans as $plan) {
            $code = trim((string) $plan->kode_rek);
            $value = $plan->status_perubahan === 'verified' ? ($plan->jumlah_perubahan ?? $plan->jumlah) : $plan->jumlah;
            if (! str_starts_with($code, '4.')) {
                continue;
            }
            $this->add($amounts, $code, 'budget', $value);
            $labels[$code] = $plan->uraian;
        }
    }

    private function expenseBudget(int $year, ?string $unit, array &$amounts, array &$labels): void
    {
        $plans = DB::table('rencana_kebutuhans as r')->join('akuns as a', 'r.akun_id', '=', 'a.id')
            ->whereNull('r.deleted_at')->where('r.tahun_anggaran', (string) $year)->where('a.kode_rek', 'like', '5.%')
            ->when($unit !== null, fn ($query) => $query->whereRaw('LOWER(r.nama_blud) = ?', [strtolower(trim($unit))]))
            ->where(fn ($query) => $query->whereNull('r.is_hasil_perubahan')->orWhere('r.is_hasil_perubahan', false)->orWhere('r.status_perubahan', 'verified'))
            ->get(['a.kode_rek', 'a.uraian', 'r.jumlah_harga', 'r.jumlah_harga_perubahan', 'r.status_perubahan']);
        foreach ($plans as $plan) {
            $value = $plan->status_perubahan === 'verified' ? ($plan->jumlah_harga_perubahan ?? $plan->jumlah_harga) : $plan->jumlah_harga;
            $this->add($amounts, $plan->kode_rek, 'budget', $value);
            $labels[$plan->kode_rek] = $plan->uraian;
        }
    }

    private function financingBudget(int $year, ?string $unit, array &$amounts, array &$labels): void
    {
        if (! Schema::hasTable('rencana_pembiayaans')) return;

        $plans = DB::table('rencana_pembiayaans')->where('tahun_anggaran', $year)
            ->when($unit !== null, fn ($query) => $query->whereRaw('LOWER(nama_blud) = ?', [strtolower(trim($unit))]))
            ->get(['kode_rek', 'kode_rek_perubahan', 'uraian', 'uraian_perubahan', 'jumlah', 'jumlah_perubahan', 'status_perubahan']);
        foreach ($plans as $plan) {
            $verified = $plan->status_perubahan === 'verified';
            $code = trim((string) ($verified ? ($plan->kode_rek_perubahan ?: $plan->kode_rek) : $plan->kode_rek));
            if (! str_starts_with($code, '6.1.') && ! str_starts_with($code, '6.2.')) continue;
            $value = $verified ? ($plan->jumlah_perubahan ?? $plan->jumlah) : $plan->jumlah;
            $this->add($amounts, $code, 'budget', $value);
            $labels[$code] = $verified ? ($plan->uraian_perubahan ?: $plan->uraian) : $plan->uraian;
        }
    }

    private function incomeActual(int $year, ?string $unit, ?int $unitId, string $field, string $startDate, string $endDate, array $plannedCodes, array &$amounts): void
    {
        $code = "COALESCE(NULLIF(TRIM(p.kode_rekening), ''), a.kode_rek, '')";
        $receipts = DB::table('transaksi_penerimaans as t')->join('penerimaans as p', 't.penerimaan_id', '=', 'p.id')
            ->leftJoin('akuns as a', 'p.akun_id', '=', 'a.id')->whereNull('p.deleted_at')
            ->where('p.tahun_anggaran', (string) $year)->whereBetween('t.tanggal', [$startDate, $endDate])
            ->when($unit !== null, fn ($query) => $unitId === null ? $query->whereRaw('1 = 0') : $query->where('p.unit_blud_id', $unitId))
            ->selectRaw($code.' AS code, SUM(t.total_biaya) AS amount')
            ->groupBy('p.kode_rekening', 'a.kode_rek')->get();
        foreach ($receipts as $receipt) {
            $accountCode = trim((string) $receipt->code);
            if (! isset($plannedCodes[$accountCode])) continue;
            $this->add($amounts, $accountCode, $field, $receipt->amount);
        }
    }

    private function expenseActual(int $year, ?string $unit, ?int $unitId, string $field, string $startDate, string $endDate, array $plannedCodes, array &$amounts): void
    {
        foreach (app(SpdBelanjaData::class)->entries($unitId, $unit === null, year: $year) as $payment) {
            if ($payment->tanggal < $startDate || $payment->tanggal > $endDate) continue;
            $code = trim((string) $payment->kode_rekening);
            if (! isset($plannedCodes[$code])) continue;
            $this->add($amounts, $code, $field, $payment->pengeluaran);
        }
    }

    private function incomeTotals(array $amounts): array
    {
        $rows = [];
        if (collect(array_keys($amounts))->contains(fn ($code) => str_starts_with($code, '4.1.'))) {
            $rows[] = $this->summary('JUMLAH PENDAPATAN ASLI DAERAH', $this->totalForPrefix($amounts, '4.1'));
        }
        $rows[] = $this->summary('JUMLAH PENDAPATAN', $this->totalForPrefix($amounts, '4'));
        return $rows;
    }

    private function row(string $code, string $label, array $amounts, string $type): array
    {
        $percentage = BigDecimal::of($amounts['budget'])->isZero() ? null : (float) (string)
            BigDecimal::of($amounts['actual'])->multipliedBy(100)->dividedBy($amounts['budget'], 2, RoundingMode::HalfUp);
        return ['code' => $code, 'label' => $label, 'type' => $type, 'budget' => $amounts['budget'],
            'actual' => $amounts['actual'], 'previous' => $amounts['previous'], 'percentage' => $percentage];
    }

    private function summary(string $label, array $amounts): array
    {
        return $this->row('', $label, $amounts, 'total');
    }

    private function totalForPrefix(array $amounts, string $prefix, ?array $extra = null): array
    {
        $total = $extra ?? $this->zero();
        foreach ($amounts as $code => $row) {
            if ($code !== $prefix && ! str_starts_with($code, $prefix.'.')) continue;
            foreach (self::FIELDS as $field) $total[$field] = $this->plus($total[$field], $row[$field]);
        }
        return $total;
    }

    private function add(array &$amounts, string $code, string $field, mixed $value): void
    {
        $amounts[$code] ??= $this->zero();
        $amounts[$code][$field] = $this->plus($amounts[$code][$field], $value);
    }

    private function zero(): array
    {
        return ['budget' => '0.00', 'actual' => '0.00', 'previous' => '0.00'];
    }

    private function plus(mixed $a, mixed $b): string
    {
        return (string) BigDecimal::of((string) $a)->plus((string) $b)->toScale(2, RoundingMode::HalfUp);
    }

    private function subtract(array $a, array $b): array
    {
        foreach (self::FIELDS as $field) $a[$field] = (string) BigDecimal::of($a[$field])->minus($b[$field])->toScale(2);
        return $a;
    }

    private function sum(array $a, array $b): array
    {
        foreach (self::FIELDS as $field) $a[$field] = $this->plus($a[$field], $b[$field]);
        return $a;
    }
}
