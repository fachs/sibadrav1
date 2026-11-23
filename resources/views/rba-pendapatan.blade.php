@extends('layouts.vertical', ['title' => 'RBA Pendapatan', 'subTitle' => 'RBA'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-pendapatan.css') }}">
@endsection

@section('content')
@php
    $user = auth()->user();
    $isSuperuser = $user->hasRole('Superuser');
    $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
    $isAllBlud = $isAllBlud ?? ($isPerencanaanOrSuperuser && (!request()->has('puskesmas') || request('puskesmas') == ''));
    $filledUnitCount = collect($agregatPuskesmas ?? [])->filter(fn ($r) => ($r['total'] ?? 0) > 0 || ($r['count'] ?? 0) > 0)->count();
@endphp
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
<div class="card rba-pendapatan rba-budget">
    
    <div class="card-body">
        @include('partials.rba-tahun-filter')
        <div class="pendapatan-toolbar mb-3">
            @if(!$isAllBlud)
                <button type="button" id="tambah-pendapatan" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPendapatan"><i class="ri-add-line" aria-hidden="true"></i> Tambah Rincian Pendapatan</button>
            @endif
            @if($isPerencanaanOrSuperuser)
                <form method="GET" action="{{ route('rba.pendapatan') }}" class="pendapatan-filter d-flex align-items-center gap-2">
                    @include('partials.rba-tahun-input')
                    <label for="puskesmas-filter" class="form-label mb-0 fw-semibold text-nowrap"><i class="ri-hospital-line me-1 text-primary"></i>Unit BLUD:</label>
                    <select name="puskesmas" id="puskesmas-filter" class="form-select form-select-sm" style="min-width: 240px;" onchange="this.form.submit()">
                        <option value="">Semua Unit BLUD (Rekap Agregat)</option>
                        @foreach($puskesmasList as $unit)
                            <option value="{{ $unit->nama }}" @selected(request('puskesmas') === $unit->nama)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                    @if(request()->filled('puskesmas'))
                        <a href="{{ route('rba.pendapatan', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke tampilan agregat seluruh BLUD"><i class="ri-refresh-line me-1"></i>Semua BLUD</a>
                    @endif
                </form>
            @endif
            <a href="{{ route('rba.pendapatan.pdf', ['puskesmas' => request('puskesmas'), 'tahun' => request('tahun')]) }}" class="btn btn-success"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh PDF</a>
        </div>

        @if($isAllBlud)
            <div class="rba-kpi-grid">
                <div class="rba-kpi-card">
                    <div class="rba-kpi-top">
                        <span class="rba-kpi-code">4.1</span>
                        <i class="ri-stethoscope-line text-primary fs-18"></i>
                    </div>
                    <div class="rba-kpi-label">Jasa Layanan</div>
                    <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['jasa_layanan'] ?? 0, 2, ',', '.') }}</div>
                </div>
                <div class="rba-kpi-card">
                    <div class="rba-kpi-top">
                        <span class="rba-kpi-code">4.2 &amp; 4.3</span>
                        <i class="ri-hand-heart-line text-info fs-18"></i>
                    </div>
                    <div class="rba-kpi-label">Hibah &amp; Hasil Kerjasama</div>
                    <div class="rba-kpi-value">Rp {{ number_format(($totalAgregat['hibah'] ?? 0) + ($totalAgregat['hasil_kerjasama'] ?? 0), 2, ',', '.') }}</div>
                </div>
                <div class="rba-kpi-card">
                    <div class="rba-kpi-top">
                        <span class="rba-kpi-code">4.4 &amp; 4.5</span>
                        <i class="ri-government-line text-warning fs-18"></i>
                    </div>
                    <div class="rba-kpi-label">APBD &amp; Lain-Lain Sah</div>
                    <div class="rba-kpi-value">Rp {{ number_format(($totalAgregat['apbd'] ?? 0) + ($totalAgregat['lain_lain'] ?? 0), 2, ',', '.') }}</div>
                </div>
                <div class="rba-kpi-card rba-kpi-card--highlight">
                    <div class="rba-kpi-top">
                        <span class="rba-kpi-code">TOTAL 4</span>
                        <i class="ri-money-dollar-circle-line text-success fs-18"></i>
                    </div>
                    <div class="rba-kpi-label">Total Pendapatan Seluruh BLUD</div>
                    <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</div>
                </div>
            </div>

            <div class="rba-agregat-header">
                <div>
                    <h5 class="rba-agregat-title">
                        <i class="ri-table-2 text-primary"></i>
                        <span>Matriks Agregat Pendapatan BLUD per Kode Rekening</span>
                    </h5>
                    <p class="rba-agregat-subtitle">
                        Tahun Anggaran <strong>{{ $tahunRba }}</strong> · Klik nama Puskesmas atau tombol <strong>Rincian</strong> untuk membuka detail akun &amp; pelayanan.
                    </p>
                </div>
                <div class="rba-agregat-controls">
                    <div class="rba-agregat-search">
                        <i class="ri-search-line"></i>
                        <input type="search" class="form-control form-control-sm" placeholder="Cari Puskesmas / BLUD..." aria-label="Cari Puskesmas" oninput="filterAgregatRows(this, 'agregat-pendapatan-table')">
                    </div>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 fs-12">
                        <i class="ri-hospital-line me-1"></i>{{ $filledUnitCount }} / {{ count($agregatPuskesmas ?? []) }} Unit Terisi
                    </span>
                </div>
            </div>

            <div class="table-responsive pendapatan-table-wrap" role="region" aria-label="Tabel Agregat Pendapatan Seluruh RBA" tabindex="0">
                <table class="table align-middle pendapatan-table pendapatan-table--agregat rba-table rba-table--agregat" id="agregat-pendapatan-table">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 48px;">No</th>
                            <th scope="col">
                                <div class="agregat-col-header agregat-col-header--start">
                                    <span class="agregat-col-code">UNIT BLUD</span>
                                    <span class="agregat-col-label">Nama Puskesmas / Unit BLUD</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code">4.1</span>
                                    <span class="agregat-col-label">Jasa Layanan (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code">4.2</span>
                                    <span class="agregat-col-label">Hibah (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code">4.3</span>
                                    <span class="agregat-col-label">Hasil Kerjasama (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code">4.4</span>
                                    <span class="agregat-col-label">APBD (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code">4.5</span>
                                    <span class="agregat-col-label">Lain-Lain Pendapatan Sah (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-end agregat-total-col">
                                <div class="agregat-col-header">
                                    <span class="agregat-col-code agregat-col-code--total">TOTAL 4</span>
                                    <span class="agregat-col-label text-success">Total Pendapatan (Rp)</span>
                                </div>
                            </th>
                            <th scope="col" class="text-center" style="width: 95px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agregatPuskesmas ?? [] as $idx => $row)
                            @php
                                $jl = (float) ($row['jasa_layanan'] ?? 0);
                                $hb = (float) ($row['hibah'] ?? 0);
                                $ks = (float) ($row['hasil_kerjasama'] ?? 0);
                                $ap = (float) ($row['apbd'] ?? 0);
                                $ll = (float) ($row['lain_lain'] ?? 0);
                                $tt = (float) ($row['total'] ?? 0);
                            @endphp
                            <tr data-unit-name="{{ strtolower($row['nama']) }}">
                                <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="agregat-unit-cell">
                                        <a href="{{ route('rba.pendapatan', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="agregat-unit-link" title="Lihat rincian akun {{ $row['nama'] }}">
                                            <span class="agregat-unit-icon"><i class="ri-hospital-line"></i></span>
                                            <span>{{ $row['nama'] }}</span>
                                        </a>
                                        @if(($row['count'] ?? 0) > 0)
                                            <span class="agregat-unit-badge">{{ $row['count'] }} rek</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end pendapatan-amount {{ $jl > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($jl, 2, ',', '.') }}</td>
                                <td class="text-end pendapatan-amount {{ $hb > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($hb, 2, ',', '.') }}</td>
                                <td class="text-end pendapatan-amount {{ $ks > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ks, 2, ',', '.') }}</td>
                                <td class="text-end pendapatan-amount {{ $ap > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ap, 2, ',', '.') }}</td>
                                <td class="text-end pendapatan-amount {{ $ll > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ll, 2, ',', '.') }}</td>
                                <td class="text-end pendapatan-amount agregat-total-col fw-bold {{ $tt > 0 ? 'text-success' : 'agregat-val-zero' }}">
                                    {{ number_format($tt, 2, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('rba.pendapatan', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="btn btn-sm btn-soft-primary px-2 py-1" title="Lihat rincian akun {{ $row['nama'] }}">
                                        <i class="ri-eye-line me-1"></i>Rincian
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted p-4">Belum ada data pendapatan untuk tahun anggaran {{ $tahunRba }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <th scope="row" colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                            <td class="text-end pendapatan-amount">{{ number_format($totalAgregat['jasa_layanan'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end pendapatan-amount">{{ number_format($totalAgregat['hibah'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end pendapatan-amount">{{ number_format($totalAgregat['hasil_kerjasama'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end pendapatan-amount">{{ number_format($totalAgregat['apbd'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end pendapatan-amount">{{ number_format($totalAgregat['lain_lain'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end text-success pendapatan-amount fs-6">{{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</td>
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
                        <span>Menampilkan Rincian RBA Pendapatan Unit BLUD: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong></span>
                    </div>
                    <a href="{{ route('rba.pendapatan', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-info text-white">
                        <i class="ri-arrow-left-line me-1"></i> Kembali ke Rekap Agregat Seluruh BLUD
                    </a>
                </div>
            @endif
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
                                        <td><span class="pendapatan-account-code">{{ $item->kode_rek }}</span>@if($isPerencanaanOrSuperuser)<div class="small text-body-secondary mt-2">{{ $item->nama_blud }}</div>@endif</td>
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
        @endif
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
