@extends('layouts.vertical', ['title' => 'RBA Pembiayaan', 'subTitle' => 'RBA'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
@endsection

@section('content')
@php
    $isPerubahan = false;
    $isSuperuser = auth()->user()->hasRole('Superuser');
    $canReview = false;
@endphp
<div class="card rba-budget">
    
    <div class="card-body">
    @include('partials.pembiayaan-toolbar')
    <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="pembiayaan-table-title" tabindex="0">
        <table class="table align-middle rba-table">
            <caption class="visually-hidden">Rencana pembiayaan tahun anggaran {{ $tahunRba }}, dikelompokkan berdasarkan klasifikasi rekening.</caption>
            <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"><col class="rba-col-actions"></colgroup>
            <thead><tr><th scope="col">Kode Rekening</th><th scope="col">Uraian</th><th scope="col" class="text-end">Jumlah (Rp)</th><th scope="col" class="text-center">Aksi</th></tr></thead>
            <tbody>
                @foreach($klasifikasi as $key => $klas)
                    @php $items = $groupedByKlasifikasi[$key]->flatten(); @endphp
                    @if($items->isNotEmpty())
                        <tr class="rba-group {{ $key === 'pengeluaran_pembiayaan' ? 'rba-group--info' : '' }}"><th scope="row" colspan="2"><div class="d-flex align-items-center flex-wrap gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="rba-group-count">{{ $items->count() }} rincian</span></div></th><td class="text-end rba-amount">{{ number_format($items->sum('jumlah'), 2, ',', '.') }}</td><td></td></tr>
                        @foreach($items as $item)
                            <tr class="rba-item">
                                <td><span class="rba-account-code">{{ $item->kode_rek }}</span>@if($isSuperuser)<div class="small text-body-secondary mt-2">{{ $item->nama_blud }}</div>@endif</td>
                                <td><div class="fw-semibold text-body-emphasis">{{ $item->uraian }}</div>@include('partials.pembiayaan-rincian', ['perubahan' => false, 'styledRincian' => true])</td>
                                <td class="text-end rba-amount fw-semibold">{{ number_format($item->jumlah, 2, ',', '.') }}</td>
                                <td><div class="d-flex align-items-center justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-warning" data-pembiayaan-edit data-record="{{ json_encode($item->rincian()) }}" data-id="{{ $item->id }}" data-action="{{ route('rba.pembiayaan.update', ['id' => $item->id] + $pembiayaanFilters) }}" data-bs-toggle="modal" data-bs-target="#modalPembiayaan" title="Edit pembiayaan" aria-label="Edit pembiayaan"><i class="ri-edit-box-line" aria-hidden="true"></i></button>
                                    <form method="POST" action="{{ route('rba.pembiayaan.destroy', ['id' => $item->id] + $pembiayaanFilters) }}" class="m-0" data-confirm-delete data-confirm-message="Hapus rencana pembiayaan ini?">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-danger" title="Hapus pembiayaan" aria-label="Hapus pembiayaan"><i class="ri-delete-bin-2-line" aria-hidden="true"></i></button></form>
                                </div></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                @if($grouped->isEmpty())<tr><td colspan="4" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana pembiayaan.</p><p class="small mb-0">Rincian pembiayaan akan tampil di sini setelah ditambahkan.</p></td></tr>@endif
            </tbody>
            <tfoot><tr><th scope="row" colspan="2">TOTAL RENCANA PEMBIAYAAN</th><td class="text-end rba-amount">{{ number_format($grandTotalJumlah, 2, ',', '.') }}</td><td></td></tr></tfoot>
        </table>
    </div>
</div></div>
@endsection

@section('modal')
    @include('partials.pembiayaan-modal', ['isPerubahan' => false])
@endsection
@section('script')
    <script src="{{ asset('js/rba-pembiayaan.js') }}"></script>
@endsection
