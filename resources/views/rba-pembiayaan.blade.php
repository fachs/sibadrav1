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
    $filledUnitCount = collect($agregatPuskesmas ?? [])->filter(fn ($r) => ($r['total'] ?? 0) > 0 || ($r['count'] ?? 0) > 0)->count();
@endphp
<div class="card rba-budget">
    
    <div class="card-body">
    @include('partials.pembiayaan-toolbar')
    @if($isAllBlud)
        <div class="rba-kpi-grid">
            <div class="rba-kpi-card">
                <div class="rba-kpi-top">
                    <span class="rba-kpi-code">6.1</span>
                    <i class="ri-arrow-down-circle-line text-primary fs-18"></i>
                </div>
                <div class="rba-kpi-label">Penerimaan Pembiayaan</div>
                <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</div>
            </div>
            <div class="rba-kpi-card">
                <div class="rba-kpi-top">
                    <span class="rba-kpi-code">6.2</span>
                    <i class="ri-arrow-up-circle-line text-info fs-18"></i>
                </div>
                <div class="rba-kpi-label">Pengeluaran Pembiayaan</div>
                <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</div>
            </div>
            <div class="rba-kpi-card rba-kpi-card--highlight">
                <div class="rba-kpi-top">
                    <span class="rba-kpi-code">TOTAL 6</span>
                    <i class="ri-exchange-funds-line text-success fs-18"></i>
                </div>
                <div class="rba-kpi-label">Total Pembiayaan Seluruh BLUD</div>
                <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</div>
            </div>
        </div>

        <div class="rba-agregat-header">
            <div>
                <h5 class="rba-agregat-title">
                    <i class="ri-table-2 text-primary"></i>
                    <span>Matriks Agregat Pembiayaan BLUD per Kode Rekening</span>
                </h5>
                <p class="rba-agregat-subtitle">
                    Tahun Anggaran <strong>{{ $tahunRba }}</strong> · Klik nama Puskesmas atau tombol <strong>Rincian</strong> untuk membuka detail rekening pembiayaan.
                </p>
            </div>
            <div class="rba-agregat-controls">
                <div class="rba-agregat-search">
                    <i class="ri-search-line"></i>
                    <input type="search" class="form-control form-control-sm" placeholder="Cari Puskesmas / BLUD..." aria-label="Cari Puskesmas" oninput="filterAgregatRows(this, 'agregat-pembiayaan-table')">
                </div>
                <span class="badge bg-primary-subtle text-primary px-3 py-2 fs-12">
                    <i class="ri-hospital-line me-1"></i>{{ $filledUnitCount }} / {{ count($agregatPuskesmas ?? []) }} Unit Terisi
                </span>
            </div>
        </div>

        <div class="table-responsive rba-table-wrap" role="region" aria-label="Tabel Agregat Pembiayaan Seluruh RBA" tabindex="0">
            <table class="table align-middle rba-table rba-table--agregat rba-table--agregat-compact" id="agregat-pembiayaan-table">
                <thead>
                    <tr>
                        <th scope="col" class="text-center" style="width: 48px;">No</th>
                        <th scope="col">
                            <div class="agregat-col-header agregat-col-header--start">
                                <span class="agregat-col-code">UNIT BLUD</span>
                                <span class="agregat-col-label">Nama Puskesmas / Unit BLUD</span>
                            </div>
                        </th>
                        <th scope="col" class="text-end" style="width: 22%;">
                            <div class="agregat-col-header">
                                <span class="agregat-col-code">6.1</span>
                                <span class="agregat-col-label">Penerimaan Pembiayaan (Rp)</span>
                            </div>
                        </th>
                        <th scope="col" class="text-end" style="width: 22%;">
                            <div class="agregat-col-header">
                                <span class="agregat-col-code">6.2</span>
                                <span class="agregat-col-label">Pengeluaran Pembiayaan (Rp)</span>
                            </div>
                        </th>
                        <th scope="col" class="text-end agregat-total-col" style="width: 22%;">
                            <div class="agregat-col-header">
                                <span class="agregat-col-code agregat-col-code--total">TOTAL 6</span>
                                <span class="agregat-col-label text-success">Total Pembiayaan (Rp)</span>
                            </div>
                        </th>
                        <th scope="col" class="text-center" style="width: 95px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agregatPuskesmas ?? [] as $idx => $row)
                        @php
                            $pn = (float) ($row['penerimaan_pembiayaan'] ?? 0);
                            $pg = (float) ($row['pengeluaran_pembiayaan'] ?? 0);
                            $tt = (float) ($row['total'] ?? 0);
                        @endphp
                        <tr data-unit-name="{{ strtolower($row['nama']) }}">
                            <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                            <td>
                                <div class="agregat-unit-cell">
                                    <a href="{{ route('rba.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="agregat-unit-link" title="Lihat rincian pembiayaan {{ $row['nama'] }}">
                                        <span class="agregat-unit-icon"><i class="ri-hospital-line"></i></span>
                                        <span>{{ $row['nama'] }}</span>
                                    </a>
                                    @if(($row['count'] ?? 0) > 0)
                                        <span class="agregat-unit-badge">{{ $row['count'] }} rek</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end rba-amount {{ $pn > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($pn, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount {{ $pg > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($pg, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount agregat-total-col fw-bold {{ $tt > 0 ? 'text-success' : 'agregat-val-zero' }}">
                                {{ number_format($tt, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('rba.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="btn btn-sm btn-soft-primary px-2 py-1" title="Lihat rincian pembiayaan {{ $row['nama'] }}">
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
                <tfoot>
                    <tr class="fw-bold">
                        <th scope="row" colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                        <td class="text-end rba-amount">{{ number_format($totalAgregat['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end rba-amount">{{ number_format($totalAgregat['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end text-success rba-amount fs-6">{{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <script>
            function filterAgregatRows(input, tableId) {
                var q = (input.value || '').toLowerCase().trim();
                var rows = document.querySelectorAll('#' + tableId + ' tbody tr[data-unit-name]');
                rows.forEach(function (tr) {
                    var name = tr.getAttribute('data-unit-name') || '';
                    tr.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
                });
            }
        </script>
    @else
        @if($isPerencanaanOrSuperuser)
            <div class="rba-unit-banner">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-hospital-fill fs-18"></i>
                    <span>Menampilkan Rincian RBA Pembiayaan Unit BLUD: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong></span>
                </div>
                <a href="{{ route('rba.pembiayaan', ['tahun' => $tahunRba]) }}" class="btn btn-sm btn-info text-white">
                    <i class="ri-arrow-left-line me-1"></i> Kembali ke Rekap Agregat Seluruh BLUD
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
