@extends('layouts.vertical', ['title' => 'LPJ', 'subTitle' => 'Penatausahaan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/lpj-report.css') }}">
@endsection

@section('content')
@php
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $downloadFilters = array_merge($selectedUnit ? ['unit_blud_id' => $selectedUnit->id] : [], ['bulan' => $lpj['month']]);
@endphp
<div class="card rba-budget lpj-page">
    <div class="card-body">
        <div class="mb-3">
            <h4 class="mb-1">Laporan Pertanggungjawaban SKPD (SPJ Fungsional)</h4>
            <div class="text-muted">{{ $unitLabel }} · Tahun Anggaran {{ $year }}</div>
        </div>
        <form method="GET" action="{{ route('lpj.index') }}" class="row g-3 align-items-end mb-4 lpj-controls" aria-label="Filter LPJ">
            <div class="col-12 col-lg-5">
                <label class="form-label" for="lpj-unit">Unit BLUD</label>
                @if($canFilter)
                    <select class="form-select" id="lpj-unit" name="unit_blud_id">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($unitOptions as $unit)
                            <option value="{{ $unit->id }}" @selected($selectedUnit?->id === $unit->id)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="form-control" id="lpj-unit" value="{{ $unitLabel }}" readonly>
                @endif
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="lpj-month">Bulan</label>
                <select class="form-select @error('bulan') is-invalid @enderror" id="lpj-month" name="bulan">
                    @foreach($months as $number => $name)
                        <option value="{{ $number }}" @selected($lpj['month'] === $number)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('bulan')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a class="btn btn-outline-secondary" href="{{ route('lpj.index') }}">Reset</a>
                <a class="btn btn-success" href="{{ route('lpj.download', $downloadFilters) }}">
                    <i class="ri-file-pdf-2-line me-1" aria-hidden="true"></i>Unduh LPJ PDF
                </a>
            </div>
        </form>
        <div class="lpj-document">
            @include('laporan-penatausahaan.partials.lpj-heading')
            <div class="table-responsive lpj-report-wrap" role="region" aria-label="Tabel SPJ Fungsional" tabindex="0">
                @include('laporan-penatausahaan.partials.lpj-table')
            </div>
        </div>
    </div>
</div>
@endsection
