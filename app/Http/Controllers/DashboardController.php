<?php

namespace App\Http\Controllers;

use App\Models\UnitBlud;
use App\Support\AlurPerubahanPendapatan;
use App\Support\DashboardBludData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardBludData $dashboard)
    {
        $canFilter = AlurPerubahanPendapatan::isSuperuser($request->user()) || AlurPerubahanPendapatan::isDinas($request->user());
        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'puskesmas' => $canFilter ? ['nullable', 'string', Rule::exists('unit_bluds', 'nama')] : ['nullable', 'string', 'max:255'],
        ]);
        $activeYear = (int) config('rba.tahun_anggaran');
        $currentYear = now('Asia/Jakarta')->year;
        $year = \App\Support\TahunAnggaran::selected($request, $currentYear);
        $weekFilter = $request->validate([
            'minggu' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$year.'-01-01', 'before_or_equal:'.$year.'-12-31'],
        ]);
        $selectedBlud = $canFilter ? ($filters['puskesmas'] ?? '') : trim((string) $request->user()->upt);
        $unit = $selectedBlud !== '' ? UnitBlud::whereRaw('LOWER(nama) = ?', [strtolower($selectedBlud)])->first() : null;
        $scope = $canFilter && $selectedBlud === '' ? null : ($unit?->nama ?? $selectedBlud);
        $unitLabel = $scope === null ? 'Semua Unit BLUD' : ($scope ?: 'Unit BLUD belum ditentukan');
        $years = range(max(2000, min($currentYear, $activeYear) - 3), min(2100, max($currentYear, $activeYear) + 1));
        $years[] = $year;
        $years = array_unique($years);
        rsort($years);
        $puskesmasList = $canFilter ? UnitBlud::orderBy('nama')->get() : collect();

        return view('index', $dashboard->build($year, $scope, $unit?->id, $weekFilter['minggu'] ?? null) + compact(
            'year', 'activeYear', 'unitLabel', 'selectedBlud', 'canFilter', 'puskesmasList', 'years'
        ));
    }
}
