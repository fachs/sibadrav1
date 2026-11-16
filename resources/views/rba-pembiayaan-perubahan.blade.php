@extends('layouts.vertical', ['title' => 'RBA Pembiayaan Perubahan', 'subTitle' => 'RBA Perubahan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
@endsection

@section('content')
@php
    $isPerubahan = true;
    $isSuperuser = auth()->user()->hasRole('superuser');
    $canReview = auth()->user()->hasAnyRole(['superuser', 'dinas', 'dinas kesehatan']);
    $grandTotalAwal = $grouped->flatten()->sum('jumlah');
@endphp
<div class="card rba-budget rba-change"><div class="card-body">
    @include('partials.pembiayaan-toolbar')
    <h5 class="visually-hidden" id="perubahan-table-title">RBA Pembiayaan Perubahan</h5>
        <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="perubahan-table-title" tabindex="0">
        <table class="table align-middle rba-table rba-table--comparison">
                <caption class="visually-hidden">Perbandingan RBA Pembiayaan sebelum dan sesudah perubahan tahun anggaran {{ $tahunRba }}.</caption>
                <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"><col><col class="rba-col-amount"><col class="rba-col-amount"><col class="rba-col-status"><col class="rba-col-actions"></colgroup>
            <thead>
                <tr><th scope="col" rowspan="2" class="align-middle">Kode Rekening</th><th scope="colgroup" colspan="2" class="text-center">Sebelum Perubahan</th><th scope="colgroup" colspan="2" class="text-center rba-after">Sesudah Perubahan</th><th scope="col" rowspan="2" class="align-middle">Bertambah/Berkurang (Rp)</th><th scope="col" rowspan="2" class="align-middle">Status</th><th scope="col" rowspan="2" class="align-middle">Aksi</th></tr>
                <tr><th scope="col">Uraian</th><th scope="col" class="text-end">Jumlah (Rp)</th><th scope="col">Uraian</th><th scope="col" class="text-end">Jumlah (Rp)</th></tr>
            </thead>
            <tbody>
                @foreach($klasifikasi as $key => $klas)
                    @php
                        $items = $groupedByKlasifikasi[$key]->flatten();
                        $subtotalAwal = $items->sum('jumlah');
                        $subtotalAkhir = $items->sum(fn ($item) => $item->jumlah_perubahan ?? $item->jumlah);
                    @endphp
                    @if($items->isNotEmpty())
                        <tr class="rba-group rba-group--{{ str_replace('table-', '', $klas['color']) }}"><th scope="row" colspan="2"><div class="d-flex align-items-center flex-wrap gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="rba-group-count">{{ $items->count() }} rincian</span></div></th><td class="text-end rba-amount">{{ number_format($subtotalAwal, 2, ',', '.') }}</td><td></td><td class="text-end rba-amount">{{ number_format($subtotalAkhir, 2, ',', '.') }}</td><td class="text-end rba-amount">{{ number_format($subtotalAkhir - $subtotalAwal, 2, ',', '.') }}</td><td colspan="2"></td></tr>
                        @foreach($items as $item)
                            @php
                                $rincianAkhir = $item->rincian(true);
                                $owned = $isSuperuser || $item->nama_blud === auth()->user()->upt;
                            @endphp
                            <tr class="rba-item">
                                <td><span class="rba-account-code">{{ $item->kode_rek }}</span>@if($isSuperuser)<div class="small text-body-secondary">{{ $item->nama_blud }}</div>@endif</td>
                                <td><div class="fw-semibold text-body-emphasis">{{ $item->uraian }}</div>@include('partials.pembiayaan-rincian', ['perubahan' => false, 'styledRincian' => true])</td>
                                <td class="text-end rba-amount">{{ number_format($item->jumlah, 2, ',', '.') }}</td>
                                <td class="rba-after-cell">@if($rincianAkhir['kode_rek'] !== $item->kode_rek)<div class="rba-account-code mb-2">{{ $rincianAkhir['kode_rek'] }}</div>@endif<div class="fw-semibold text-body-emphasis">{{ $rincianAkhir['uraian'] }}</div>@include('partials.pembiayaan-rincian', ['perubahan' => true, 'styledRincian' => true])</td>
                                <td class="text-end rba-amount">{{ number_format($rincianAkhir['jumlah'], 2, ',', '.') }}</td>
                                <td class="text-end rba-amount">{{ number_format($rincianAkhir['jumlah'] - $item->jumlah, 2, ',', '.') }}</td>
                                <td><span class="rba-status {{ in_array($item->status_perubahan, ['pending', 'verified', 'rejected'], true) ? 'rba-status--'.$item->status_perubahan : '' }}">{{ ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'][$item->status_perubahan] ?? 'Belum ada usulan' }}</span></td>
                                <td><div class="d-flex flex-wrap justify-content-center gap-1">
                                    @if($owned && $item->status_perubahan !== 'verified')
                                        <button type="button" class="btn btn-sm btn-warning" data-pembiayaan-edit data-record="{{ json_encode($rincianAkhir) }}" data-id="{{ $item->id }}" data-action="{{ route('rba-perubahan.pembiayaan.update', ['id' => $item->id] + $pembiayaanFilters) }}" data-bs-toggle="modal" data-bs-target="#modalPembiayaan" title="Usulkan perubahan" aria-label="Usulkan perubahan"><i class="ri-edit-box-line"></i></button>
                                        <form method="POST" action="{{ route('rba-perubahan.pembiayaan.zero-out', ['id' => $item->id] + $pembiayaanFilters) }}" data-confirm-delete data-confirm-message="Nolkan usulan pembiayaan ini?" data-confirm-button="Ya, Nolkan">@csrf @method('PATCH')<button type="submit" class="btn btn-sm btn-warning" title="Nolkan perubahan" aria-label="Nolkan perubahan"><i class="ri-delete-back-2-line"></i></button></form>
                                    @endif
                                    @if($canReview && $item->status_perubahan === 'pending')
                                        @foreach(['verifikasi' => ['Verifikasi', 'btn-success', 'ri-check-line'], 'tolak' => ['Tolak', 'btn-danger', 'ri-close-line']] as $action => [$label, $buttonClass, $icon])
                                            <form method="POST" action="{{ route('rba-perubahan.pembiayaan.'.$action, ['id' => $item->id] + $pembiayaanFilters) }}" onsubmit="return confirm('{{ $label }} usulan ini?');">@csrf @method('PATCH')<button type="submit" class="btn btn-sm {{ $buttonClass }}" title="{{ $label }}" aria-label="{{ $label }}"><i class="{{ $icon }}"></i></button></form>
                                        @endforeach
                                    @endif
                                </div></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                @if($grouped->isEmpty())<tr><td colspan="8" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana pembiayaan.</p><p class="small mb-0">Usulan perubahan akan tampil setelah rencana anggaran ditambahkan.</p></td></tr>@endif
            </tbody>
            <tfoot><tr><th scope="row" colspan="2">TOTAL RENCANA PEMBIAYAAN</th><td class="text-end rba-amount">{{ number_format($grandTotalAwal, 2, ',', '.') }}</td><td></td><td class="text-end rba-amount">{{ number_format($grandTotalJumlah, 2, ',', '.') }}</td><td class="text-end rba-amount">{{ number_format($grandTotalJumlah - $grandTotalAwal, 2, ',', '.') }}</td><td colspan="2"></td></tr></tfoot>
        </table>
    </div>
</div></div>
@endsection

@section('modal')
    @include('partials.pembiayaan-modal', ['isPerubahan' => true])
@endsection
@section('script')
    <script src="{{ asset('js/rba-pembiayaan.js') }}"></script>
@endsection
