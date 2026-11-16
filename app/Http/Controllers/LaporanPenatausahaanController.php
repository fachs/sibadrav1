<?php

namespace App\Http\Controllers;

use App\Models\UnitBlud;
use App\Support\DashboardBludData;
use App\Support\LraReportData;
use App\Support\LpjReportData;
use App\Support\TahunAnggaran;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanPenatausahaanController extends Controller
{
    public function lra(Request $request, DashboardBludData $dashboard)
    {
        return $this->show($request, $dashboard, 'lra');
    }

    public function downloadLra(
        Request $request,
        LraReportData $report,
    )
    {
        $canFilter = $request->user()->isDinasKesehatan() || $request->user()->hasRole('Superuser');
        $filters = $request->validate(['unit_blud_id' => ['nullable', 'integer', Rule::exists('unit_bluds', 'id')]]);
        $unit = $canFilter && isset($filters['unit_blud_id'])
            ? UnitBlud::findOrFail($filters['unit_blud_id'])
            : UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $request->user()->upt))])->first();
        $label = $canFilter && ! isset($filters['unit_blud_id']) ? 'Semua Unit BLUD' : ($unit?->nama ?? trim((string) $request->user()->upt));
        $year = TahunAnggaran::selected($request);
        $lraFilters = $this->lraFilters($request, $year);
        $rows = $report->build($year, $label === 'Semua Unit BLUD' ? null : $label, $unit?->id,
            $lraFilters['tanggal_awal'], $lraFilters['tanggal_akhir'], $lraFilters['klasifikasi']);
        $pdf = Pdf::loadView('pdf.lra', [
            'reportRows' => $rows, 'unitLabel' => $label, 'year' => $year, 'selectedUnit' => $unit,
            'periodLabel' => $this->periodLabel($lraFilters),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('lra-'.\Illuminate\Support\Str::slug($label).'-'.$year.'.pdf');
    }

    public function lpj(Request $request, DashboardBludData $dashboard)
    {
        return $this->show($request, $dashboard, 'lpj');
    }

    public function downloadLpj(Request $request, LpjReportData $report)
    {
        $canFilter = $request->user()->isDinasKesehatan() || $request->user()->hasRole('Superuser');
        $filters = $request->validate([
            'unit_blud_id' => ['nullable', 'integer', Rule::exists('unit_bluds', 'id')],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $unit = $canFilter && isset($filters['unit_blud_id'])
            ? UnitBlud::findOrFail($filters['unit_blud_id'])
            : ($canFilter ? null : UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $request->user()->upt))])->first());
        $label = $canFilter && ! $unit ? 'Semua Unit BLUD' : ($unit?->nama ?? trim((string) $request->user()->upt));
        $year = TahunAnggaran::selected($request);
        $month = (int) ($filters['bulan'] ?? (now('Asia/Jakarta')->year === $year ? now('Asia/Jakarta')->month : 12));
        $lpj = $report->build($year, $month, $label === 'Semua Unit BLUD' ? null : $label, $unit?->id);

        return Pdf::loadView('pdf.lpj', [
            'lpj' => $lpj, 'year' => $year, 'unitLabel' => $label, 'selectedUnit' => $unit,
        ])->setPaper('a3', 'landscape')->download('lpj-'.\Illuminate\Support\Str::slug($label).'-'.$year.'-'.sprintf('%02d', $month).'.pdf');
    }

    private function show(Request $request, DashboardBludData $dashboard, string $kind)
    {
        $canFilter = $request->user()->isDinasKesehatan() || $request->user()->hasRole('Superuser');
        $filters = $request->validate([
            'unit_blud_id' => ['nullable', 'integer', Rule::exists('unit_bluds', 'id')],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $selectedUnit = $canFilter
            ? (isset($filters['unit_blud_id']) ? UnitBlud::findOrFail($filters['unit_blud_id']) : null)
            : UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower(trim((string) $request->user()->upt))])->first();
        $year = TahunAnggaran::selected($request);
        $scope = $canFilter && !$selectedUnit ? null : ($selectedUnit?->nama ?? trim((string) $request->user()->upt));
        $data = $dashboard->build($year, $scope, $selectedUnit?->id);
        if ($kind === 'lra') {
            $lraFilters = $this->lraFilters($request, $year);
            $data['reportRows'] = app(LraReportData::class)->build($year, $scope, $selectedUnit?->id,
                $lraFilters['tanggal_awal'], $lraFilters['tanggal_akhir'], $lraFilters['klasifikasi']);
            $data['lraFilters'] = $lraFilters;
            $data['periodLabel'] = $this->periodLabel($lraFilters);
        } elseif ($kind === 'lpj') {
            $month = (int) ($filters['bulan'] ?? (now('Asia/Jakarta')->year === $year ? now('Asia/Jakarta')->month : 12));
            $data['lpj'] = app(LpjReportData::class)->build($year, $month, $scope, $selectedUnit?->id);
        }

        return view('laporan-penatausahaan.'.$kind, $data + [
            'year' => $year,
            'unitLabel' => $scope ?? 'Semua Unit BLUD',
            'selectedUnit' => $selectedUnit,
            'canFilter' => $canFilter,
            'unitOptions' => $canFilter ? UnitBlud::orderBy('nama')->get() : collect(),
        ]);
    }

    private function lraFilters(Request $request, int $year): array
    {
        $validated = $request->validate([
            'klasifikasi' => ['nullable', 'integer', 'between:1,6'],
            'tanggal_awal' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$year.'-01-01', 'before_or_equal:'.$year.'-12-31'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$year.'-01-01', 'before_or_equal:'.$year.'-12-31'],
        ]);
        $start = $validated['tanggal_awal'] ?? $year.'-01-01';
        $end = $validated['tanggal_akhir'] ?? $year.'-12-31';
        if ($start > $end) {
            throw ValidationException::withMessages(['tanggal_akhir' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }

        return ['klasifikasi' => (int) ($validated['klasifikasi'] ?? 6), 'tanggal_awal' => $start, 'tanggal_akhir' => $end];
    }

    private function periodLabel(array $filters): string
    {
        $start = CarbonImmutable::parse($filters['tanggal_awal'], 'Asia/Jakarta')->locale('id')->translatedFormat('d F Y');
        $end = CarbonImmutable::parse($filters['tanggal_akhir'], 'Asia/Jakarta')->locale('id')->translatedFormat('d F Y');

        return $start.' Sampai '.$end;
    }
}
