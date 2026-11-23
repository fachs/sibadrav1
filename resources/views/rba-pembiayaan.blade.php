@extends('layouts.vertical', ['title' => 'RBA Pembiayaan', 'subTitle' => 'RBA'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
@endsection

@section('content')
@php
    $isPerubahan = false;
    $user = auth()->user();
    $isSuperuser = $user->hasRole('Superuser');
    $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
    $isAllBlud = $isAllBlud ?? ($isPerencanaanOrSuperuser && (!request()->has('puskesmas') || request('puskesmas') == ''));
    $canReview = false;
@endphp
<div class="card rba-budget">
    
    <div class="card-body">
    @include('partials.pembiayaan-toolbar')
    @if($isAllBlud)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-3">
            <div>
                <h5 class="card-title mb-1">Rekapitulasi Agregat Pembiayaan BLUD</h5>
                <p class="text-muted small mb-0">Tahun Anggaran {{ $tahunRba }} · Data agregat per jenis kode rekening seluruh Puskesmas / Unit BLUD</p>
            </div>
            <span class="badge bg-primary-subtle text-primary px-2 py-1 fs-12">
                <i class="ri-hospital-line me-1"></i>{{ count($agregatPuskesmas ?? []) }} Unit BLUD
            </span>
        </div>
        <div class="table-responsive rba-table-wrap" role="region" aria-label="Tabel Agregat Pembiayaan Seluruh RBA" tabindex="0">
            <table class="table table-bordered table-hover align-middle rba-table">
                <thead class="table-light">
                    <tr class="text-center align-middle">
                        <th scope="col" style="width: 50px;">No</th>
                        <th scope="col" class="text-start">Nama Puskesmas / Unit BLUD</th>
                        <th scope="col" class="text-end" style="width: 22%;">6.1 - PENERIMAAN PEMBIAYAAN</th>
                        <th scope="col" class="text-end" style="width: 22%;">6.2 - PENGELUARAN PEMBIAYAAN</th>
                        <th scope="col" class="text-end" style="width: 22%;">TOTAL PEMBIAYAAN</th>
                        <th scope="col" class="text-center" style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agregatPuskesmas ?? [] as $idx => $row)
                        <tr>
                            <td class="text-center text-muted">{{ $idx + 1 }}</td>
                            <td>
                                <a href="{{ route('rba.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="fw-semibold text-primary text-decoration-none" title="Lihat rincian pembiayaan {{ $row['nama'] }}">
                                    {{ $row['nama'] }}
                                </a>
                                @if(($row['count'] ?? 0) > 0)
                                    <span class="badge bg-light text-secondary ms-1">{{ $row['count'] }} rek</span>
                                @endif
                            </td>
                            <td class="text-end rba-amount">{{ number_format($row['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount">{{ number_format($row['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount fw-bold {{ ($row['total'] ?? 0) > 0 ? 'text-success' : 'text-muted' }}">
                                {{ number_format($row['total'] ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('rba.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="btn btn-sm btn-outline-primary" title="Lihat rincian pembiayaan {{ $row['nama'] }}">
                                    <i class="ri-eye-line me-1"></i>Rincian
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">Belum ada data pembiayaan untuk tahun anggaran {{ $tahunRba }}.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <th scope="row" colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                        <td class="text-end text-primary rba-amount">{{ number_format($totalAgregat['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end text-primary rba-amount">{{ number_format($totalAgregat['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end text-success rba-amount fs-5">{{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        @if($isPerencanaanOrSuperuser)
            <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 mb-3 py-2">
                <div>
                    <i class="ri-information-line me-1"></i> Menampilkan rincian pembiayaan untuk: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong>
                </div>
                <a href="{{ route('rba.pembiayaan', ['tahun' => $tahunRba]) }}" class="btn btn-sm btn-outline-info">
                    <i class="ri-arrow-left-line me-1"></i> Kembali ke Tampilan Agregat
                </a>
            </div>
        @endif
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
                                <td><span class="rba-account-code">{{ $item->kode_rek }}</span>@if($isPerencanaanOrSuperuser)<div class="small text-body-secondary mt-2">{{ $item->nama_blud }}</div>@endif</td>
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
    @endif
</div></div>
@endsection

@section('modal')
    @include('partials.pembiayaan-modal', ['isPerubahan' => false])
@endsection
@section('script')
    <script src="{{ asset('js/rba-pembiayaan.js') }}"></script>
@endsection
