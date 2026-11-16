@extends('layouts.vertical', ['title' => 'Rekap per Unit', 'subTitle' => 'Rencana Kebutuhan'])

@section('css')
<style>
    .renbut-recap .unit-toggle[aria-expanded="true"] .unit-chevron { transform: rotate(180deg); }
    .renbut-recap .unit-chevron { display: inline-block; }
    .renbut-recap .unit-details-table { min-width: 780px; }
    @media print {
        .main-nav, .app-topbar, footer, .renbut-recap .recap-actions, .renbut-recap .pdf-action { display: none !important; }
        .renbut-recap .collapse { display: block !important; }
        .renbut-recap .table-responsive { overflow: visible; }
        .renbut-recap table { font-size: 12px; }
    }
</style>
@endsection

@section('content')
@php
    $pdfFilters = array_merge(['tahun' => $tahunRba], $canFilter && $selectedBlud !== '' ? ['puskesmas' => $selectedBlud] : []);
    $grandTotal = $grouped->sum(fn ($items) => $items->sum('jumlah_harga'));
@endphp
<div class="row renbut-recap">
    <div class="col-12">
        <div class="card">
            {{-- <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="card-title">Rekap Rencana Kebutuhan</h2>
                    <p class="text-muted mb-0 mt-1">{{ $bludLabel }} · Tahun Anggaran {{ $tahunRba }}</p>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ $grouped->count() }} unit</span>
            </div> --}}
            {{-- <div class="card-body pb-0">
                <div class="table-responsive">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr><th style="width: 150px;">Program</th><td>:</td><td>1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA</td></tr>
                            <tr><th>Kegiatan</th><td>:</td><td>1.02.01.2.10 - Peningkatan Pelayanan BLUD</td></tr>
                            <tr><th>Sub Kegiatan</th><td>:</td><td>1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD</td></tr>
                        </tbody>
                    </table>
                </div>
            </div> --}}
            <div class="card-body">
                <div class="recap-actions d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                    <form method="GET" action="{{ route('renbut-per-unit') }}" class="d-flex align-items-end flex-wrap gap-3">
                        <input type="hidden" name="tahun" value="{{ $tahunRba }}">
                        @if($canFilter)
                        <div>
                            <label for="puskesmasFilter" class="form-label">Pilih BLUD</label>
                            <div class="d-flex align-items-center gap-2">
                                <select name="puskesmas" id="puskesmasFilter" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                                    <option value="">Semua BLUD</option>
                                    @foreach($puskesmasList as $unit)
                                        <option value="{{ $unit->nama }}" @selected($selectedBlud === $unit->nama)>{{ $unit->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endif
                        <noscript><button class="btn btn-sm btn-primary" type="submit">Tampilkan</button></noscript>
                    </form>
                    @if($grouped->isNotEmpty())
                        <a href="{{ route('rincian.pdf', $pdfFilters) }}" class="btn btn-success btn-sm"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh Semua (PDF)</a>
                    @endif
                </div>
                <p class="text-muted small">Klik nama unit untuk membuka atau menutup rincian kebutuhan.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light"><tr><th>Nama Unit</th><th class="text-end">Total Anggaran (Rp)</th><th class="text-center pdf-action">Unduh per Unit</th></tr></thead>
                        <tbody>
                            @forelse($grouped as $unit => $items)
                                @php $collapseId = 'rekap-unit-'.$loop->index; @endphp
                                <tr class="table-light">
                                    <td>
                                        <button type="button" class="unit-toggle btn btn-link p-0 text-decoration-none fw-semibold text-start" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                                            <i class="ri-arrow-down-s-line unit-chevron" aria-hidden="true"></i> {{ $unit ?: 'Unit belum ditentukan' }}
                                        </button>
                                        <span class="text-muted small ms-2">{{ $items->count() }} item</span>
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap">{{ number_format($items->sum('jumlah_harga'), 0, ',', '.') }}</td>
                                    <td class="text-center pdf-action">
                                        @if(trim((string) $unit) !== '')
                                            <a href="{{ route('rincian.pdf', array_merge($pdfFilters, ['nama_unit' => $unit])) }}" class="btn btn-sm btn-outline-success"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> PDF {{ $unit }}</a>
                                        @else
                                            <span class="text-muted small">Lengkapi nama unit</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="p-0">
                                        <div id="{{ $collapseId }}" class="collapse {{ $loop->first ? 'show' : '' }}">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped mb-0 unit-details-table">
                                                    <thead><tr><th>Uraian</th><th class="text-end">Koefisien</th><th class="text-end">Harga Satuan (Rp)</th><th class="text-end">Jumlah Harga (Rp)</th><th>Satuan</th><th>Ruang</th><th>Keterangan</th></tr></thead>
                                                    <tbody>
                                                        @foreach($items as $item)
                                                            <tr>
                                                                <td>
                                                                    <div>{{ $item->uraian }}</div>
                                                                    <div class="text-muted small">Spesifikasi: {{ $item->spesifikasi ?: '-' }}</div>
                                                                    @if($canFilter && $selectedBlud === '')<div class="text-muted small">{{ $item->nama_blud }}</div>@endif
                                                                </td>
                                                                <td class="text-end">{{ $item->jumlah }}</td>
                                                                <td class="text-end text-nowrap">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                                                <td class="text-end text-nowrap">{{ number_format($item->jumlah_harga, 0, ',', '.') }}</td>
                                                                <td>{{ $item->satuan ?: '-' }}</td><td>{{ $item->ruang ?: '-' }}</td><td>{{ $item->keterangan ?: '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-5 text-muted">Belum ada rencana kebutuhan untuk {{ $bludLabel }} pada tahun anggaran {{ $tahunRba }}. Tambahkan data melalui halaman Rincian Rencana Kebutuhan atau pilih tahun anggaran lain.</td></tr>
                            @endforelse
                        </tbody>
                        @if($grouped->isNotEmpty())
                            <tfoot class="table-light"><tr><th>Total Seluruh Unit</th><th class="text-end">{{ number_format($grandTotal, 0, ',', '.') }}</th><td class="pdf-action"></td></tr></tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
