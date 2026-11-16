@extends('layouts.vertical', ['title' => 'Dashboard BLUD', 'subTitle' => 'Home', 'hidePageTitle' => true])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/dashboard-blud.css') }}">
@endsection

@section('content')
@php
    $number = fn ($value) => number_format((float) $value, fmod((float) $value, 1) == 0 ? 0 : 2, ',', '.');
    $rupiah = fn ($value) => 'Rp '.$number($value);
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $weekTotal = (string) array_reduce($weekly, fn ($sum, $day) => $sum->plus($day['pendapatan']), \Brick\Math\BigDecimal::zero());
@endphp
<section class="blud-dashboard" aria-labelledby="dashboard-heading">
    <div class="dashboard-heading">
        <div>
            <h1 id="dashboard-heading">Dashboard BLUD</h1>
            <p class="dashboard-subtitle"><i class="ri-hospital-line" aria-hidden="true"></i> {{ $unitLabel }} <span class="dashboard-separator">/</span> Ringkasan keuangan {{ $year }}</p>
        </div>
        <form method="GET" action="{{ route('index') }}" class="dashboard-filters">
            <input type="hidden" name="minggu" value="{{ $selectedWeekDate }}">
            @if($canFilter)
                <div class="dashboard-filter-field">
                <label for="dashboard-blud">Unit BLUD</label>
                <select id="dashboard-blud" name="puskesmas" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="">Semua Unit BLUD</option>
                    @foreach($puskesmasList as $unit)<option value="{{ $unit->nama }}" @selected($selectedBlud === $unit->nama)>{{ $unit->nama }}</option>@endforeach
                </select>
                </div>
            @endif
            <input type="hidden" name="tahun" value="{{ $year }}">
            <noscript><button type="submit" class="btn btn-primary btn-sm">Tampilkan</button></noscript>
        </form>
    </div>
    <div class="dashboard-summary-grid">
        @foreach(['pendapatan' => ['Realisasi Pendapatan', 'target', 'target_pendapatan', 'income-fill'], 'belanja' => ['Realisasi Belanja', 'pagu', 'pagu_belanja', 'expense-fill']] as $type => [$label, $caption, $budget, $color])
            <article class="dashboard-card dashboard-summary-card">
                <div class="dashboard-summary-top">
                    <span class="dashboard-icon {{ $type === 'pendapatan' ? 'dashboard-icon-income' : 'dashboard-icon-expense' }}" aria-hidden="true"><i class="{{ $type === 'pendapatan' ? 'ri-funds-line' : 'ri-bank-card-line' }}"></i></span>
                    <span class="dashboard-badge {{ $type === 'pendapatan' ? 'dashboard-badge-income' : 'dashboard-badge-expense' }}">{{ $totals['persentase_'.$type] === null ? 'Belum ada anggaran' : number_format($totals['persentase_'.$type], 1, ',', '.').'% '.$caption }}</span>
                </div>
                <h2 class="dashboard-metric-label">{{ $label }}</h2>
                <div class="dashboard-metric-value">{{ $rupiah($totals['realisasi_'.$type]) }}</div>
                <div class="dashboard-progress" role="progressbar" aria-label="Persentase {{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ max(0, min(100, $totals['persentase_'.$type] ?? 0)) }}">
                    <span class="{{ $color }}" style="width: {{ max(0, min(100, $totals['persentase_'.$type] ?? 0)) }}%"></span>
                </div>
                <p class="dashboard-metric-caption">
                    @if($totals['persentase_'.$type] === null)
                        {{ $type === 'pendapatan' ? 'Target' : 'Pagu' }} belum tersedia
                    @else
                        {{ number_format($totals['persentase_'.$type], 1, ',', '.') }}% dari {{ $caption }} {{ $rupiah($totals[$budget]) }}
                    @endif
                </p>
            </article>
        @endforeach
        <article class="dashboard-card dashboard-summary-card">
            <div class="dashboard-summary-top">
                <span class="dashboard-icon dashboard-icon-surplus" aria-hidden="true"><i class="ri-scales-3-line"></i></span>
                <span class="dashboard-badge {{ (float) $totals['surplus_realisasi'] < 0 ? 'dashboard-badge-danger' : 'dashboard-badge-income' }}">{{ (float) $totals['surplus_realisasi'] < 0 ? 'Defisit' : ((float) $totals['surplus_realisasi'] > 0 ? 'Surplus' : 'Seimbang') }}</span>
            </div>
            <h2 class="dashboard-metric-label">Surplus / (Defisit) Realisasi</h2>
            <div class="dashboard-metric-value {{ (float) $totals['surplus_realisasi'] < 0 ? 'text-danger' : '' }}">{{ $rupiah($totals['surplus_realisasi']) }}</div>
            <p class="dashboard-metric-caption">Anggaran {{ $rupiah($totals['surplus_anggaran']) }}</p>
        </article>
        <article class="dashboard-card dashboard-summary-card dashboard-balance-card">
            <div class="dashboard-summary-top">
                <span class="dashboard-icon" aria-hidden="true"><i class="ri-wallet-3-line"></i></span>
                <span class="dashboard-badge">Tahun {{ $year }}</span>
            </div>
            <h2 class="dashboard-metric-label">Saldo Kas</h2>
            <div class="dashboard-metric-value">{{ $rupiah($totals['saldo_kas']) }}</div>
            <p class="dashboard-metric-caption">Saldo awal tahun {{ $rupiah($totals['saldo_awal']) }}</p>
        </article>
    </div>
    <div class="dashboard-analytics-grid">
    <article class="dashboard-card dashboard-chart-card dashboard-weekly-card">
        <div class="dashboard-card-heading">
            <div>
                <h2 class="dashboard-card-title">Pendapatan Mingguan</h2>
                <p class="dashboard-chart-caption">Pendapatan harian Senin–Sabtu</p>
            </div>
            <span class="dashboard-icon dashboard-icon-income" aria-hidden="true"><i class="ri-bar-chart-grouped-line"></i></span>
        </div>
        <div class="dashboard-week-overview">
            <span>Total pendapatan minggu terpilih</span>
            <strong>{{ $rupiah($weekTotal) }}</strong>
            <p>{{ $weeklyPeriod }}</p>
        </div>
            <form method="GET" action="{{ route('index') }}" class="dashboard-filters dashboard-week-filter">
                <input type="hidden" name="tahun" value="{{ $year }}">
                @if($canFilter)<input type="hidden" name="puskesmas" value="{{ $selectedBlud }}">@endif
                <label for="dashboard-week"><i class="ri-calendar-line" aria-hidden="true"></i> Pilih minggu</label>
                <input type="date" id="dashboard-week" name="minggu" class="form-control form-control-sm" value="{{ $selectedWeekDate }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" onchange="this.form.requestSubmit()">
                <noscript><button type="submit" class="btn btn-primary btn-sm">Tampilkan</button></noscript>
            </form>
        <div class="dashboard-chart-content"><div class="dashboard-chart-body"><canvas id="dashboard-weekly-chart" role="img" aria-label="Grafik pendapatan harian Senin sampai Sabtu {{ $weeklyPeriod }} untuk {{ $unitLabel }}"></canvas></div></div>
        <table class="visually-hidden">
            <caption>Pendapatan harian {{ $weeklyPeriod }}</caption>
            <thead><tr><th>Hari dan tanggal</th><th>Pendapatan</th></tr></thead>
            <tbody>@foreach($weekly as $row)<tr><th>{{ $row['title'] }}</th><td>{{ $rupiah($row['pendapatan']) }}</td></tr>@endforeach</tbody>
        </table>
    </article>
    <article class="dashboard-card dashboard-chart-card dashboard-monthly-card">
        <div class="dashboard-card-heading">
            <div>
                <h2 class="dashboard-card-title">Realisasi per Bulan</h2>
                <p class="dashboard-chart-caption">Perbandingan pendapatan dan belanja tahun {{ $year }}</p>
            </div>
            <span class="dashboard-badge dashboard-badge-neutral"><i class="ri-calendar-2-line" aria-hidden="true"></i> Jan – Des {{ $year }}</span>
        </div>
        <div class="dashboard-chart-content">
            <div class="dashboard-legend" aria-hidden="true"><span><i></i>Pendapatan</span><span><i class="expense-dot"></i>Belanja</span></div>
            <div class="dashboard-chart-body"><canvas id="dashboard-monthly-chart" role="img" aria-label="Grafik realisasi pendapatan dan belanja Januari sampai Desember {{ $year }}"></canvas></div>
        </div>
        <table class="visually-hidden">
            <caption>Realisasi per bulan tahun {{ $year }}</caption>
            <thead><tr><th>Bulan</th><th>Pendapatan</th><th>Belanja</th></tr></thead>
            <tbody>@foreach($monthly as $index => $row)<tr><th>{{ $months[$index] }}</th><td>{{ $rupiah($row['pendapatan']) }}</td><td>{{ $rupiah($row['belanja']) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="dashboard-chart-footer">
            @foreach(['Pendapatan' => 'realisasi_pendapatan', 'Belanja' => 'realisasi_belanja', 'Surplus / (Defisit)' => 'surplus_realisasi'] as $label => $key)
                <div><span>{{ $label }}</span><strong class="{{ (float) $totals[$key] < 0 ? 'text-danger' : '' }}">{{ $rupiah($totals[$key]) }}</strong></div>
            @endforeach
        </div>
    </article>
    </div>
    <div class="dashboard-details-grid">
        <article class="dashboard-card dashboard-table-card">
            <div class="dashboard-card-heading">
                <div class="dashboard-title-with-icon"><span class="dashboard-icon dashboard-icon-income" aria-hidden="true"><i class="ri-hand-coin-line"></i></span><div><h2 class="dashboard-card-title">Pendapatan per Jenis Layanan</h2><p class="dashboard-chart-caption">Anggaran dan realisasi pendapatan</p></div></div>
                <span class="dashboard-badge dashboard-badge-neutral">{{ count($incomeRows) }} jenis</span>
            </div>
            @include('partials.dashboard-budget-table', ['rows' => $incomeRows, 'kind' => 'pendapatan', 'color' => 'income-fill'])
        </article>
        <article class="dashboard-card dashboard-table-card">
            <div class="dashboard-card-heading">
                <div class="dashboard-title-with-icon"><span class="dashboard-icon dashboard-icon-expense" aria-hidden="true"><i class="ri-file-list-3-line"></i></span><div><h2 class="dashboard-card-title">Belanja per Kelompok</h2><p class="dashboard-chart-caption">Anggaran dan realisasi belanja</p></div></div>
                <span class="dashboard-badge dashboard-badge-neutral">{{ count($expenseRows) }} kelompok</span>
            </div>
            @include('partials.dashboard-budget-table', ['rows' => $expenseRows, 'kind' => 'belanja', 'color' => 'expense-fill'])
        </article>
    </div>
</section>
<script type="application/json" id="dashboard-chart-data">@json($monthly)</script>
<script type="application/json" id="dashboard-weekly-data">@json($weekly)</script>
@endsection

@section('script')
    @vite(['resources/js/pages/dashboard-blud.js'])
@endsection
