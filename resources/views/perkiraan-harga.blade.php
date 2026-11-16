@extends('layouts.vertical', ['title' => 'Perkiraan Harga', 'subTitle' => 'RBA'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
@endsection

@section('content')
@php $tahunRba = \App\Support\TahunRba::selected(); @endphp
<div class="card rba-budget">
    <div class="card-header rba-heading">
        <div class="d-flex align-items-center gap-3">
            <span class="rba-heading-icon"><i class="ri-price-tag-3-line" aria-hidden="true"></i></span>
            <div>
                <h5 class="card-title mb-1" id="harga-table-title">Perkiraan Harga</h5>
                <p class="text-body-secondary small mb-0">Harga satuan dan rincian dari Rencana Kebutuhan tahun anggaran {{ $tahunRba }}.</p>
            </div>
        </div>
        <span class="rba-count">{{ number_format($perkiraanHarga->total(), 0, ',', '.') }} data harga</span>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('rba.perkiraan-harga') }}" class="row g-3 align-items-end mb-4 rba-search-form">
            @include('partials.rba-tahun-input')
            <div class="col-md-{{ $isSuperuser ? '5' : '8' }}">
                <label for="search" class="form-label">Cari uraian atau satuan</label>
                <div class="rba-search-input">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <input type="search" id="search" name="search" class="form-control" value="{{ $search }}" placeholder="Masukkan uraian atau satuan" maxlength="255">
                </div>
            </div>
            @if($isSuperuser)
                <div class="col-md-3">
                    <label for="puskesmas" class="form-label">Unit BLUD</label>
                    <select id="puskesmas" name="puskesmas" class="form-select">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($puskesmasList as $unit)
                            <option value="{{ $unit->nama }}" @selected($puskesmas === $unit->nama)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-4 d-flex flex-wrap gap-2 rba-search-actions">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
                <a href="{{ route('rba.perkiraan-harga', ['tahun' => $tahunRba]) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="harga-table-title" tabindex="0">
            <table class="table align-middle rba-table rba-table--prices">
                <caption class="visually-hidden">Perkiraan harga dari Rencana Kebutuhan tahun anggaran {{ $tahunRba }}.</caption>
                <colgroup><col class="rba-col-number"><col><col class="rba-col-amount"><col class="rba-col-unit"><col class="rba-col-notes"></colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="text-center">No.</th>
                        <th scope="col">Uraian</th>
                        <th scope="col" class="text-end">Harga Satuan (Rp)</th>
                        <th scope="col">Satuan</th>
                        <th scope="col">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($perkiraanHarga as $item)
                        <tr class="rba-item">
                            <td class="text-center text-body-secondary rba-amount">{{ $perkiraanHarga->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-body-emphasis">{{ $item->uraian }}</td>
                            <td class="text-end rba-amount fw-semibold">{{ number_format($item->harga_satuan, 2, ',', '.') }}</td>
                            <td><span class="rba-unit">{{ $item->satuan }}</span></td>
                            <td class="text-body-secondary">{{ $item->keterangan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="rba-empty"><i class="ri-search-2-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Tidak ada data perkiraan harga.</p><p class="small mb-0">Sesuaikan kata pencarian atau filter untuk melihat data lainnya.</p></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3 rba-pagination">
            <span class="text-body-secondary small">Menampilkan {{ $perkiraanHarga->firstItem() ?? 0 }}&ndash;{{ $perkiraanHarga->lastItem() ?? 0 }} dari {{ $perkiraanHarga->total() }} data</span>
            <div class="rba-pagination-links">{{ $perkiraanHarga->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</div>
@endsection
