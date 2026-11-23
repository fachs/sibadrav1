@extends('layouts.vertical', ['title' => 'RBA Pendapatan Perubahan', 'subTitle' => 'RBA Perubahan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
    @include('partials.rba-agregat-styles')
@endsection

@section('content')
@php
    $user = auth()->user();
    $isSuperuser = $user->hasRole('superuser');
    $isDinas = \App\Support\AlurPerubahanPendapatan::isDinas($user);
    $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
    $canFilter = $isSuperuser || $isDinas || $isPerencanaanOrSuperuser;
    $isAllBlud = $isAllBlud ?? ($canFilter && (!request()->has('puskesmas') || request('puskesmas') == ''));
    $canReview = $grouped->flatten()->contains(fn ($record) => \App\Support\AlurPerubahanPendapatan::canReview($user, $record));
    $grandTotalAwal = $grouped->flatten()->sum('jumlah');
    $filledUnitCount = collect($agregatPuskesmas ?? [])->filter(fn ($r) => ($r['total'] ?? 0) > 0 || ($r['count'] ?? 0) > 0)->count();
@endphp
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
<div class="card rba-budget rba-change">

    <div class="card-body">
        @include('partials.rba-tahun-filter')
        <div class="rba-toolbar">
            @if($canFilter)
                <form method="GET" action="{{ route('rba-perubahan.pendapatan') }}" class="rba-filter d-flex align-items-center gap-2">
                    @include('partials.rba-tahun-input')
                    <label for="puskesmas-filter" class="form-label mb-0 fw-semibold text-nowrap"><i class="ri-hospital-line me-1 text-primary"></i>Unit BLUD:</label>
                    <select name="puskesmas" id="puskesmas-filter" class="form-select form-select-sm" style="min-width: 240px;" onchange="this.form.submit()">
                        <option value="">Semua Unit BLUD (Rekap Agregat)</option>
                        @foreach($puskesmasList as $unit)
                            <option value="{{ $unit->nama }}" @selected(request('puskesmas') === $unit->nama)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                    @if(request()->filled('puskesmas'))
                        <a href="{{ route('rba-perubahan.pendapatan', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke tampilan agregat seluruh BLUD"><i class="ri-refresh-line me-1"></i>Semua BLUD</a>
                    @endif
                </form>
            @else
                <span class="text-body-secondary">{{ auth()->user()->upt }}</span>
            @endif
            <div class="rba-toolbar-actions">
                @if((!$isDinas || $isSuperuser) && !$isAllBlud)
                    <a href="{{ route('rba-perubahan.pendapatan.pergeseran-lintas-akun.create', ['puskesmas' => $isSuperuser ? request('puskesmas') : null, 'tahun' => request('tahun')]) }}" class="btn btn-warning"><i class="ri-exchange-box-line" aria-hidden="true"></i> Pengajuan Perubahan</a>
                @endif
                @if($canReview)
                    @foreach(['verifikasi-semua' => ['Verifikasi Usulan', 'btn-success'], 'tolak-semua' => ['Tolak Usulan', 'btn-danger']] as $action => [$label, $buttonClass])
                        <form method="POST" action="{{ route('rba-perubahan.pendapatan.'.$action) }}" onsubmit="return confirm('{{ $label }} untuk seluruh usulan pending?');">
                            @csrf @method('PATCH')
                            @include('partials.rba-tahun-input')
                            @if($canFilter && request('puskesmas'))<input type="hidden" name="puskesmas" value="{{ request('puskesmas') }}">@endif
                            <button type="submit" class="btn {{ $buttonClass }}">{{ $label }}</button>
                        </form>
                    @endforeach
                @endif
                <a href="{{ route('rba-perubahan.pendapatan.pdf', ['puskesmas' => $canFilter ? request('puskesmas') : null, 'tahun' => request('tahun')]) }}" class="btn btn-success"><i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh PDF</a>
            </div>
        </div>
        @if($isAllBlud)
            <div class="rba-kpi-grid">
                <div class="rba-kpi-card">
                    <div class="rba-kpi-top">
                        <span class="rba-kpi-code">4.1</span>
                        <i class="ri-stethoscope-line text-primary fs-18"></i>
                    </div>
                    <div class="rba-kpi-label">Jasa Layanan (Perubahan)</div>
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
                    <div class="rba-kpi-label">Total Pendapatan Perubahan</div>
                    <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</div>
                </div>
            </div>

            <div class="rba-agregat-header">
                <div>
                    <h5 class="rba-agregat-title">
                        <i class="ri-table-2 text-primary"></i>
                        <span>Matriks Agregat Pendapatan Perubahan BLUD per Kode Rekening</span>
                    </h5>
                    <p class="rba-agregat-subtitle">
                        Tahun Anggaran <strong>{{ $tahunRba }}</strong> · Klik nama Puskesmas atau tombol <strong>Rincian</strong> untuk membuka detail perbandingan perubahan.
                    </p>
                </div>
                <div class="rba-agregat-controls">
                    <div class="rba-agregat-search">
                        <i class="ri-search-line"></i>
                        <input type="search" class="form-control form-control-sm" placeholder="Cari Puskesmas / BLUD..." aria-label="Cari Puskesmas" oninput="filterAgregatRows(this, 'agregat-pendapatan-perubahan-table')">
                    </div>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 fs-12">
                        <i class="ri-hospital-line me-1"></i>{{ $filledUnitCount }} / {{ count($agregatPuskesmas ?? []) }} Unit Terisi
                    </span>
                </div>
            </div>

            <div class="table-responsive rba-table-wrap" role="region" aria-label="Tabel Agregat Pendapatan Perubahan Seluruh RBA" tabindex="0">
                <table class="table align-middle rba-table rba-table--agregat" id="agregat-pendapatan-perubahan-table">
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
                                        <a href="{{ route('rba-perubahan.pendapatan', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="agregat-unit-link" title="Lihat rincian perubahan {{ $row['nama'] }}">
                                            <span class="agregat-unit-icon"><i class="ri-hospital-line"></i></span>
                                            <span>{{ $row['nama'] }}</span>
                                        </a>
                                        @if(($row['count'] ?? 0) > 0)
                                            <span class="agregat-unit-badge">{{ $row['count'] }} rek</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end rba-amount {{ $jl > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($jl, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount {{ $hb > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($hb, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount {{ $ks > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ks, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount {{ $ap > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ap, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount {{ $ll > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($ll, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount agregat-total-col fw-bold {{ $tt > 0 ? 'text-success' : 'agregat-val-zero' }}">
                                    {{ number_format($tt, 2, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('rba-perubahan.pendapatan', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="btn btn-sm btn-soft-primary px-2 py-1" title="Lihat rincian perubahan {{ $row['nama'] }}">
                                        <i class="ri-eye-line me-1"></i>Rincian
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted p-4">Belum ada data pendapatan perubahan untuk tahun anggaran {{ $tahunRba }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <th scope="row" colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                            <td class="text-end rba-amount">{{ number_format($totalAgregat['jasa_layanan'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount">{{ number_format($totalAgregat['hibah'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount">{{ number_format($totalAgregat['hasil_kerjasama'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount">{{ number_format($totalAgregat['apbd'] ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end rba-amount">{{ number_format($totalAgregat['lain_lain'] ?? 0, 2, ',', '.') }}</td>
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
            @if($canFilter)
                <div class="rba-unit-banner">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-hospital-fill fs-18"></i>
                        <span>Menampilkan Rincian RBA Pendapatan Perubahan Unit BLUD: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong></span>
                    </div>
                    <a href="{{ route('rba-perubahan.pendapatan', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-info text-white">
                        <i class="ri-arrow-left-line me-1"></i> Kembali ke Rekap Agregat Seluruh BLUD
                    </a>
                </div>
            @endif
            <h5 class="visually-hidden" id="perubahan-table-title">RBA Pendapatan Perubahan</h5>
            <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="perubahan-table-title" tabindex="0">
                <table class="table align-middle rba-table rba-table--comparison">
                <caption class="visually-hidden">Perbandingan RBA Pendapatan sebelum dan sesudah perubahan tahun anggaran {{ $tahunRba }}.</caption>
                <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"><col><col class="rba-col-amount"><col class="rba-col-amount"><col class="rba-col-status"><col class="rba-col-actions"></colgroup>
                <thead>
                    <tr>
                        <th scope="col" rowspan="2" class="align-middle">Kode Rekening</th>
                        <th scope="colgroup" colspan="2" class="text-center">Sebelum Perubahan</th>
                        <th scope="colgroup" colspan="2" class="text-center rba-after">Sesudah Perubahan</th>
                        <th scope="col" rowspan="2" class="align-middle">Bertambah/Berkurang (Rp)</th>
                        <th scope="col" rowspan="2" class="align-middle">Status</th>
                        <th scope="col" rowspan="2" class="align-middle">Aksi</th>
                    </tr>
                    <tr><th scope="col">Uraian dan Jenis Pelayanan</th><th scope="col">Jumlah (Rp)</th><th scope="col">Uraian dan Jenis Pelayanan</th><th scope="col">Jumlah (Rp)</th></tr>
                </thead>
                <tbody>
                    @foreach($klasifikasi as $key => $klas)
                        @php
                            $items = $groupedByKlasifikasi[$key]->flatten();
                            $subtotalAwal = $items->sum('jumlah');
                            $subtotalAkhir = $items->sum(fn ($item) => $item->jumlah_perubahan ?? $item->jumlah);
                        @endphp
                        @if($items->isNotEmpty())
                            <tr class="rba-group rba-group--{{ str_replace('table-', '', $klas['color']) }}">
                                <th scope="row" colspan="2"><div class="d-flex align-items-center flex-wrap gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="rba-group-count">{{ $items->count() }} rincian</span></div></th>
                                <td class="text-end rba-amount">{{ number_format($subtotalAwal, 2, ',', '.') }}</td>
                                <td></td><td class="text-end rba-amount">{{ number_format($subtotalAkhir, 2, ',', '.') }}</td>
                                <td class="text-end rba-amount">{{ number_format($subtotalAkhir - $subtotalAwal, 2, ',', '.') }}</td><td colspan="2"></td>
                            </tr>
                            @foreach($items as $item)
                                @php
                                    $sesudah = $item->jumlah_perubahan ?? $item->jumlah;
                                    $selisih = $sesudah - $item->jumlah;
                                    $pelayananSesudah = $item->pelayananPerubahan();
                                    $canReviewItem = \App\Support\AlurPerubahanPendapatan::canReview(auth()->user(), $item);
                                @endphp
                                <tr class="rba-item">
                                    <td>
                                        <span class="rba-account-code">{{ $item->kode_rek }}</span>
                                        @if($canFilter)<div class="small text-body-secondary">{{ $item->nama_blud }}</div>@endif
                                        @if($item->pergeseran_id)
                                            <div class="small text-body-secondary mt-2">{{ $item->rekening_awal }} &rarr; {{ $item->rekening_akhir }}</div>
                                            @if($item->is_hasil_perubahan)<span class="rba-status rba-status--info">Rekening Baru</span>@endif
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $item->uraian }}</div>
                                        <details class="rba-details">
                                            <summary><i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i><i class="ri-list-check" aria-hidden="true"></i>{{ $item->jenisPelayanans->count() }} jenis pelayanan awal</summary>
                                            @include('partials.pendapatan-pelayanan-vertical', ['pelayanans' => $item->jenisPelayanans])
                                        </details>
                                    </td>
                                    <td class="text-end rba-amount">{{ number_format($item->jumlah, 2, ',', '.') }}</td>
                                    <td class="rba-after-cell">
                                        <div class="fw-semibold text-body-emphasis">{{ $item->uraian_perubahan ?? $item->uraian }}</div>
                                        @if($item->jumlah_perubahan !== null && $item->jenis_pelayanan_perubahan === null)
                                            <p class="small text-body-secondary mt-2 mb-0">Usulan sebelumnya belum memiliki rincian jenis pelayanan.</p>
                                        @else
                                            <details class="rba-details">
                                                <summary><i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i><i class="ri-list-check" aria-hidden="true"></i>{{ $pelayananSesudah->count() }} jenis pelayanan</summary>
                                                @include('partials.pendapatan-pelayanan-vertical', ['pelayanans' => $pelayananSesudah])
                                            </details>
                                        @endif
                                    </td>
                                    <td class="text-end rba-amount fw-semibold">{{ number_format($sesudah, 2, ',', '.') }}</td>
                                    <td class="text-end rba-amount {{ $selisih > 0 ? 'rba-delta-positive' : ($selisih < 0 ? 'rba-delta-negative' : '') }}">{{ number_format($selisih, 2, ',', '.') }}</td>
                                    <td>
                                        @if($item->status_perubahan === 'pending')<span class="rba-status rba-status--pending">Menunggu Verifikasi ({{ ucwords(\App\Support\AlurPerubahanPendapatan::verifikator($item)) }})</span>
                                        @elseif($item->status_perubahan === 'verified')<span class="rba-status rba-status--verified">Terverifikasi</span>
                                        @elseif($item->status_perubahan === 'rejected')<span class="rba-status rba-status--rejected">Ditolak</span>
                                        @else<span class="rba-status">Tidak Ada Perubahan</span>@endif
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1 flex-wrap">
                                            @if($canReviewItem)
                                                @foreach(['verifikasi' => ['Verifikasi', 'btn-success', 'ri-check-line'], 'tolak' => ['Tolak', 'btn-danger', 'ri-close-line']] as $action => [$label, $buttonClass, $icon])
                                                    <form method="POST" action="{{ route('rba-perubahan.pendapatan.'.$action, $item->id) }}" class="m-0" onsubmit="return confirm('{{ $label }} perubahan ini?');">
                                                        @csrf @method('PATCH')
                                                        @include('partials.rba-tahun-input')
                                                        <button type="submit" class="btn btn-sm {{ $buttonClass }}" title="{{ $label }}" aria-label="{{ $label }}"><i class="{{ $icon }}" aria-hidden="true"></i></button>
                                                    </form>
                                                @endforeach
                                            @else
                                                <span class="text-body-secondary">-</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                    @if($grouped->isEmpty())<tr><td colspan="8" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana pendapatan.</p><p class="small mb-0">Usulan perubahan akan tampil setelah rencana anggaran ditambahkan.</p></td></tr>@endif
                </tbody>
                <tfoot><tr>
                    <th scope="row" colspan="2">TOTAL RENCANA PENDAPATAN</th><td class="text-end rba-amount">{{ number_format($grandTotalAwal, 2, ',', '.') }}</td><td></td>
                    <td class="text-end rba-amount">{{ number_format($grandTotalJumlah, 2, ',', '.') }}</td>
                    <td class="text-end rba-amount">{{ number_format($grandTotalJumlah - $grandTotalAwal, 2, ',', '.') }}</td><td colspan="2"></td>
                </tr></tfoot>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection

@section('modal')
<div class="modal fade" id="modalPendapatan" tabindex="-1" aria-labelledby="modalPendapatanLabel" aria-hidden="true" data-open-on-load="{{ $errors->any() ? '1' : '0' }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content rba-budget rba-change rba-change-form">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPendapatanLabel">Usulkan Perubahan Rincian Pendapatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('partials.pendapatan-pelayanan-form', ['pendapatan' => null, 'isPerubahan' => true])
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script src="{{ asset('js/rba-pendapatan-pelayanan.js') }}"></script>
@endsection
