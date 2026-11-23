@extends('layouts.vertical', ['title' => 'RBA Pembiayaan Perubahan', 'subTitle' => 'RBA Perubahan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
@endsection

@section('content')
@php
    $isPerubahan = true;
    $user = auth()->user();
    $isSuperuser = $user->hasRole('superuser');
    $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
    $isAllBlud = $isAllBlud ?? ($isPerencanaanOrSuperuser && (!request()->has('puskesmas') || request('puskesmas') == ''));
    $canReview = $user->hasAnyRole(['superuser', 'perencanaan', 'dinas', 'dinas kesehatan']) || $user->isDinasKesehatan();
    $grandTotalAwal = $grouped->flatten()->sum('jumlah');
@endphp
<div class="card rba-budget rba-change"><div class="card-body">
    @include('partials.pembiayaan-toolbar')
    @if($isAllBlud)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-3">
            <div>
                <h5 class="card-title mb-1">Rekapitulasi Agregat Pembiayaan Perubahan BLUD</h5>
                <p class="text-muted small mb-0">Tahun Anggaran {{ $tahunRba }} · Data agregat per jenis kode rekening seluruh Puskesmas / Unit BLUD</p>
            </div>
            <span class="badge bg-primary-subtle text-primary px-2 py-1 fs-12">
                <i class="ri-hospital-line me-1"></i>{{ count($agregatPuskesmas ?? []) }} Unit BLUD
            </span>
        </div>
        <div class="table-responsive rba-table-wrap" role="region" aria-label="Tabel Agregat Pembiayaan Perubahan Seluruh RBA" tabindex="0">
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
                                <a href="{{ route('rba-perubahan.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="fw-semibold text-primary text-decoration-none" title="Lihat rincian perubahan pembiayaan {{ $row['nama'] }}">
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
                                <a href="{{ route('rba-perubahan.pembiayaan', ['puskesmas' => $row['nama'], 'tahun' => $tahunRba]) }}" class="btn btn-sm btn-outline-primary" title="Lihat rincian perubahan pembiayaan {{ $row['nama'] }}">
                                    <i class="ri-eye-line me-1"></i>Rincian
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">Belum ada data pembiayaan perubahan untuk tahun anggaran {{ $tahunRba }}.</td>
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
                    <i class="ri-information-line me-1"></i> Menampilkan rincian pembiayaan perubahan untuk: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong>
                </div>
                <a href="{{ route('rba-perubahan.pembiayaan', ['tahun' => $tahunRba]) }}" class="btn btn-sm btn-outline-info">
                    <i class="ri-arrow-left-line me-1"></i> Kembali ke Tampilan Agregat
                </a>
            </div>
        @endif
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
                                <td><span class="rba-account-code">{{ $item->kode_rek }}</span>@if($isPerencanaanOrSuperuser)<div class="small text-body-secondary">{{ $item->nama_blud }}</div>@endif</td>
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
    @endif
</div></div>
@endsection

@section('modal')
    @include('partials.pembiayaan-modal', ['isPerubahan' => true])
@endsection
@section('script')
    <script src="{{ asset('js/rba-pembiayaan.js') }}"></script>
@endsection
