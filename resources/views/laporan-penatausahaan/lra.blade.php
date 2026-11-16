@extends('layouts.vertical', ['title' => 'LRA', 'subTitle' => 'Penatausahaan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
@endsection

@section('content')
@php
    $classificationOptions = [1 => 'Akun', 2 => 'Kelompok', 3 => 'Jenis', 4 => 'Objek', 5 => 'Rincian Objek', 6 => 'Sub Rincian Objek'];
    $pdfFilters = array_merge($selectedUnit ? ['unit_blud_id' => $selectedUnit->id] : [], $lraFilters);
@endphp
<div class="card rba-budget">
    <div class="card-body">
        <div class="mb-3">
            <h4 class="mb-1">Laporan Realisasi Anggaran (LRA)</h4>
            <div class="text-muted">{{ $unitLabel }} · Tahun Anggaran {{ $year }}</div>
        </div>
        <form method="GET" action="{{ route('lra.index') }}" class="row g-3 align-items-end mb-4" aria-label="Filter LRA">
            <div class="col-12 col-lg-6">
                <label class="form-label" for="unit-blud-filter">Unit BLUD</label>
                @if($canFilter)
                    <select class="form-select" id="unit-blud-filter" name="unit_blud_id">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($unitOptions as $unit)
                            <option value="{{ $unit->id }}" @selected($selectedUnit?->id === $unit->id)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="form-control" id="unit-blud-filter" value="{{ $unitLabel }}" readonly>
                @endif
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="lra-klasifikasi">Klasifikasi, Kodefikasi dan Nomenklatur Rekening</label>
                <select class="form-select @error('klasifikasi') is-invalid @enderror" id="lra-klasifikasi" name="klasifikasi">
                    @foreach($classificationOptions as $depth => $name)
                        <option value="{{ $depth }}" @selected($lraFilters['klasifikasi'] === $depth)>{{ $depth }}. {{ $name }}</option>
                    @endforeach
                </select>
                @error('klasifikasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="lra-tanggal-awal">Tanggal awal</label>
                <input type="date" class="form-control @error('tanggal_awal') is-invalid @enderror" id="lra-tanggal-awal"
                    name="tanggal_awal" min="{{ $year }}-01-01" max="{{ $year }}-12-31" value="{{ $lraFilters['tanggal_awal'] }}" required>
                @error('tanggal_awal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="lra-tanggal-akhir">Tanggal akhir</label>
                <input type="date" class="form-control @error('tanggal_akhir') is-invalid @enderror" id="lra-tanggal-akhir"
                    name="tanggal_akhir" min="{{ $year }}-01-01" max="{{ $year }}-12-31" value="{{ $lraFilters['tanggal_akhir'] }}" required>
                @error('tanggal_akhir')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-lg-6 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a class="btn btn-outline-secondary" href="{{ route('lra.index') }}">Reset</a>
                <a class="btn btn-success" href="{{ route('lra.download', $pdfFilters) }}">
                    <i class="ri-file-pdf-2-line me-1" aria-hidden="true"></i>Unduh LRA PDF
                </a>
            </div>
        </form>
        <div class="lra-report-heading">
            <img src="{{ asset('images/logo-pemkot.png') }}" alt="Lambang Kota Sukabumi">
            <div>PEMERINTAHAN KOTA SUKABUMI</div>
            <div>{{ strtoupper($unitLabel) }}</div>
            <div>LAPORAN REALISASI ANGGARAN PENDAPATAN DAN BELANJA DAERAH</div>
            <div>TAHUN ANGGARAN {{ $year }}</div>
            <div>{{ $periodLabel }}</div>
        </div>
        <div class="table-responsive lra-report-wrap" role="region" aria-label="Tabel Laporan Realisasi Anggaran" tabindex="0">
            @include('laporan-penatausahaan.partials.lra-table')
        </div>
    </div>
</div>
@endsection
