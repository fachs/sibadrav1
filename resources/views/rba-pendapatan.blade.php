@extends('layouts.vertical', ['title' => 'RBA Pendapatan', 'subTitle' => 'RBA'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-pendapatan.css') }}">
@endsection

@section('content')
@php
    $isSuperuser = auth()->user()->hasRole('Superuser');
@endphp
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
<div class="card rba-pendapatan">
    
    <div class="card-body">
        @include('partials.rba-tahun-filter')
        <div class="pendapatan-toolbar">
            <button type="button" id="tambah-pendapatan" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPendapatan"><i class="ri-add-line" aria-hidden="true"></i> Tambah Rincian Pendapatan</button>
            @if($isSuperuser)
                <form method="GET" action="{{ route('rba.pendapatan') }}" class="pendapatan-filter">
                    @include('partials.rba-tahun-input')
                    <label for="puskesmas-filter" class="form-label mb-0">BLUD</label>
                    <select name="puskesmas" id="puskesmas-filter" class="form-select">
                        <option value="">Semua Unit BLUD</option>
                        @foreach($puskesmasList as $unit)
                            <option value="{{ $unit->nama }}" @selected(request('puskesmas') === $unit->nama)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Tampilkan</button>
                </form>
            @endif
            <a href="{{ route('rba.pendapatan.pdf', ['puskesmas' => request('puskesmas'), 'tahun' => request('tahun')]) }}" class="btn btn-success"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh PDF</a>
        </div>
        <div class="table-responsive pendapatan-table-wrap" role="region" aria-labelledby="pendapatan-table-title" tabindex="0">
            <table class="table align-middle pendapatan-table">
                <caption class="visually-hidden">Rencana pendapatan tahun anggaran {{ $tahunRba }}, dikelompokkan berdasarkan klasifikasi rekening.</caption>
                <colgroup><col class="pendapatan-col-code"><col><col class="pendapatan-col-amount"><col class="pendapatan-col-actions"></colgroup>
                <thead><tr><th scope="col">Kode Rekening</th><th scope="col">Uraian dan Jenis Pelayanan</th><th scope="col" class="text-end">Jumlah (Rp)</th><th scope="col" class="text-center">Aksi</th></tr></thead>
                <tbody>
                    @foreach($klasifikasi as $key => $klas)
                        @php $items = $groupedByKlasifikasi[$key]->flatten(); @endphp
                        @if($items->isNotEmpty())
                            <tr class="pendapatan-group pendapatan-group--{{ $key }}"><th scope="row" colspan="2"><div class="d-flex align-items-center gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="pendapatan-group-count">{{ $items->count() }} rincian</span></div></th><td class="text-end pendapatan-amount">{{ number_format($items->sum('jumlah'), 2, ',', '.') }}</td><td></td></tr>
                            @foreach($items as $item)
                                <tr class="pendapatan-item">
                                    <td><span class="pendapatan-account-code">{{ $item->kode_rek }}</span>@if($isSuperuser)<div class="small text-body-secondary mt-2">{{ $item->nama_blud }}</div>@endif</td>
                                    <td>
                                        <div class="fw-semibold text-body-emphasis">{{ $item->uraian }}</div>
                                        <details class="pendapatan-service-details">
                                            <summary><i class="ri-arrow-right-s-line pendapatan-disclosure-icon" aria-hidden="true"></i><i class="ri-list-check" aria-hidden="true"></i>{{ $item->jenisPelayanans->count() }} jenis pelayanan</summary>
                                            @if($item->jenisPelayanans->contains('is_data_lama', true))<p class="text-muted small mt-2 mb-0">Rincian awal dari data sebelumnya; lengkapi melalui Kelola Pelayanan.</p>@endif
                                            @include('partials.pendapatan-pelayanan-table', ['pelayanans' => $item->jenisPelayanans])
                                        </details>
                                    </td>
                                    <td class="text-end pendapatan-amount fw-semibold">{{ number_format($item->jumlah, 2, ',', '.') }}</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" class="btn btn-sm btn-warning edit-pendapatan" data-pendapatan-id="{{ $item->id }}" data-record="{{ $item->toJson() }}" data-bs-toggle="modal" data-bs-target="#modalPendapatan" title="Kelola Pelayanan" aria-label="Kelola Pelayanan"><i class="ri-edit-box-line" aria-hidden="true"></i></button>
                                            <form method="POST" action="{{ route('rba.pendapatan.destroy', $item->id) }}" class="m-0" data-confirm-delete data-confirm-title="Hapus Rencana Pendapatan?" data-confirm-message="Hapus rencana pendapatan beserta seluruh rincian pelayanannya?">
                                                @csrf @method('DELETE')
                                                @include('partials.rba-tahun-input')
                                                <button class="btn btn-sm btn-danger" type="submit" title="Hapus Rencana Pendapatan" aria-label="Hapus Rencana Pendapatan"><i class="ri-delete-bin-2-line" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                    @if($grouped->isEmpty())
                        <tr><td colspan="4" class="pendapatan-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana pendapatan.</p><p class="small mb-0">Gunakan tombol Tambah Rincian Pendapatan untuk mulai mengisi.</p></td></tr>
                    @endif
                </tbody>
                <tfoot><tr><th scope="row" colspan="2">TOTAL RENCANA PENDAPATAN</th><td class="text-end pendapatan-amount">{{ number_format($grandTotalJumlah, 2, ',', '.') }}</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@section('modal')
<div class="modal fade" id="modalPendapatan" tabindex="-1" aria-labelledby="modalPendapatanLabel" aria-hidden="true" data-open-on-load="{{ $errors->any() || request()->boolean('tambah') ? '1' : '0' }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPendapatanLabel">{{ old('pendapatan_id') ? 'Kelola Pelayanan Pendapatan' : 'Tambah Rincian Pendapatan' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('partials.pendapatan-pelayanan-form', ['pendapatan' => null])
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script src="{{ asset('js/rba-pendapatan-pelayanan.js') }}"></script>
@endsection
