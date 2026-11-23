@extends('layouts.vertical', ['title' => 'RBA Belanja Perubahan', 'subTitle' => 'RBA Perubahan'])
@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
    @include('partials.rba-agregat-styles')
@endsection
@section('content')
            <div class="row">
                <div class="col-12">
                    <div class="card rba-budget rba-change">

                        <div class="card-body">
                            @if($errors->any())
                                <div class="alert alert-danger" role="alert">
                                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                                </div>
                            @endif
                            @include('partials.rba-tahun-filter')
                            @php
                                $user = auth()->user();
                                $isDinas = \App\Support\AlurPerubahanPendapatan::isDinas($user);
                                $isPerencanaanOrSuperuser = $isPerencanaanOrSuperuser ?? ($user->hasAnyRole(['Superuser', 'Perencanaan', 'Dinas', 'Dinas Kesehatan']) || $user->isDinasKesehatan());
                                $isAllBlud = $isAllBlud ?? ($isPerencanaanOrSuperuser && (!request()->has('puskesmas') || request('puskesmas') == ''));
                                $canDeleteRenbut = $user->hasAnyRole(['kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
                                $allItems = collect($groupedByKlasifikasi)->flatten();
                                $hasPending = $allItems->contains(fn ($item) => $item->status_perubahan === 'pending' &&
                                    ($user->hasRole('Superuser') || $user->hasRole($item->verifikator_perubahan ?? 'kepala puskesmas') ||
                                    ($isDinas && $item->verifikator_perubahan === 'dinas kesehatan')));
                                $filledUnitCount = collect($agregatPuskesmas ?? [])->filter(fn ($r) => ($r['total'] ?? 0) > 0 || ($r['count'] ?? 0) > 0)->count();
                                $totalModal = ($totalAgregat['belanja_modal_peralatan'] ?? 0) + ($totalAgregat['belanja_modal_gedung'] ?? 0) + ($totalAgregat['belanja_modal_jalan'] ?? 0);
                            @endphp
                            <div class="rba-toolbar">
                                <div class="rba-toolbar-main">
                                    @if($isPerencanaanOrSuperuser)
                                    <form method="GET" action="{{ route('rba-perubahan.belanja') }}" class="rba-filter d-flex align-items-center gap-2">
                                        @include('partials.rba-tahun-input')
                                        <label for="puskesmasFilter" class="form-label mb-0 fw-semibold text-nowrap"><i class="ri-hospital-line me-1 text-primary"></i>Unit BLUD:</label>
                                        <select name="puskesmas" id="puskesmasFilter" class="form-select form-select-sm" style="min-width: 240px;" onchange="this.form.submit()">
                                            <option value="">Semua Unit BLUD (Rekap Agregat)</option>
                                            @foreach($puskesmasList as $p)
                                                <option value="{{ $p->nama }}" {{ request('puskesmas') == $p->nama ? 'selected' : '' }}>{{ $p->nama }}</option>
                                            @endforeach
                                        </select>
                                        @if(request()->filled('puskesmas'))
                                            <a href="{{ route('rba-perubahan.belanja', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke tampilan agregat seluruh BLUD"><i class="ri-refresh-line me-1"></i>Semua BLUD</a>
                                        @endif
                                    </form>
                                    @endif
                                </div>
                                <div class="rba-toolbar-actions">
                                    @if(!$isPerencanaanOrSuperuser)
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalKinerjaKegiatan">
                                            <i class="ri-list-check-3"></i> Kinerja Kegiatan
                                        </button>
                                    @endif
                                    @if(!$isAllBlud)
                                        <a href="{{ route('rba-perubahan.belanja.pergeseran-lintas-akun.create', ['puskesmas' => request('puskesmas'), 'tahun' => request('tahun')]) }}" class="btn btn-warning">
                                            <i class="ri-exchange-box-line"></i> Pengajuan Perubahan
                                        </a>
                                    @endif
                                    @if((auth()->user()->hasRole('kepala puskesmas') || $isDinas || auth()->user()->hasRole('Superuser')) && $hasPending)
                                        <div class="d-flex flex-wrap gap-2 rba-review-actions">
                                            <form action="{{ route('rba-perubahan.belanja.verifikasi-semua') }}" method="POST" onsubmit="return confirm('Verifikasi semua perubahan yang pending?');">
                                                @csrf
                                                @method('PATCH')
                                                @if(request('puskesmas'))<input type="hidden" name="puskesmas" value="{{ request('puskesmas') }}">@endif
                                                <button type="submit" class="btn btn-success">
                                                    <i class="ri-check-double-line"></i> Verifikasi Usulan
                                                </button>
                                            </form>
                                            <form action="{{ route('rba-perubahan.belanja.tolak-semua') }}" method="POST" onsubmit="return confirm('Tolak semua perubahan yang pending?');">
                                                @csrf
                                                @method('PATCH')
                                                @if(request('puskesmas'))<input type="hidden" name="puskesmas" value="{{ request('puskesmas') }}">@endif
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="ri-close-circle-line"></i> Tolak Usulan
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                    <a href="{{ route('rba-perubahan.belanja.pdf', ['puskesmas' => request('puskesmas'), 'tahun' => request('tahun')]) }}" class="btn btn-success">
                                        <i class="ri-file-pdf-2-line"></i> Unduh PDF
                                    </a>
                                </div>
                            </div>

                            @if($isAllBlud)
                                <div class="rba-kpi-grid">
                                    <div class="rba-kpi-card">
                                        <div class="rba-kpi-top">
                                            <span class="rba-kpi-code">5.1.01</span>
                                            <i class="ri-user-star-line text-primary fs-18"></i>
                                        </div>
                                        <div class="rba-kpi-label">Belanja Pegawai (Perubahan)</div>
                                        <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['belanja_pegawai'] ?? 0, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rba-kpi-card">
                                        <div class="rba-kpi-top">
                                            <span class="rba-kpi-code">5.1.02</span>
                                            <i class="ri-shopping-bag-3-line text-info fs-18"></i>
                                        </div>
                                        <div class="rba-kpi-label">Barang &amp; Jasa (Perubahan)</div>
                                        <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['belanja_barang_jasa'] ?? 0, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rba-kpi-card">
                                        <div class="rba-kpi-top">
                                            <span class="rba-kpi-code">5.2.02 – 5.2.04</span>
                                            <i class="ri-building-2-line text-warning fs-18"></i>
                                        </div>
                                        <div class="rba-kpi-label">Total Belanja Modal (Perubahan)</div>
                                        <div class="rba-kpi-value">Rp {{ number_format($totalModal, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rba-kpi-card rba-kpi-card--highlight">
                                        <div class="rba-kpi-top">
                                            <span class="rba-kpi-code">TOTAL 5</span>
                                            <i class="ri-wallet-3-line text-success fs-18"></i>
                                        </div>
                                        <div class="rba-kpi-label">Total Belanja Perubahan</div>
                                        <div class="rba-kpi-value">Rp {{ number_format($totalAgregat['total'] ?? 0, 0, ',', '.') }}</div>
                                    </div>
                                </div>

                                <div class="rba-agregat-header">
                                    <div>
                                        <h5 class="rba-agregat-title">
                                            <i class="ri-table-2 text-primary"></i>
                                            <span>Matriks Agregat Belanja Perubahan BLUD per Kode Rekening</span>
                                        </h5>
                                        <p class="rba-agregat-subtitle">
                                            Tahun Anggaran <strong>{{ $tahunRba }}</strong> · Klik nama Puskesmas atau tombol <strong>Rincian</strong> untuk membuka detail perbandingan belanja perubahan.
                                        </p>
                                    </div>
                                    <div class="rba-agregat-controls">
                                        <div class="rba-agregat-search">
                                            <i class="ri-search-line"></i>
                                            <input type="search" class="form-control form-control-sm" placeholder="Cari Puskesmas / BLUD..." aria-label="Cari Puskesmas" oninput="filterAgregatRows(this, 'agregat-belanja-perubahan-table')">
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary px-3 py-2 fs-12">
                                            <i class="ri-hospital-line me-1"></i>{{ $filledUnitCount }} / {{ count($agregatPuskesmas ?? []) }} Unit Terisi
                                        </span>
                                    </div>
                                </div>

                                <div class="table-responsive rba-table-wrap" role="region" aria-label="Tabel Agregat Belanja Perubahan Seluruh RBA" tabindex="0">
                                    <table class="table align-middle rba-table rba-table--agregat" id="agregat-belanja-perubahan-table">
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
                                                        <span class="agregat-col-code">5.1.01</span>
                                                        <span class="agregat-col-label">Belanja Pegawai (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-end">
                                                    <div class="agregat-col-header">
                                                        <span class="agregat-col-code">5.1.02</span>
                                                        <span class="agregat-col-label">Barang &amp; Jasa (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-end">
                                                    <div class="agregat-col-header">
                                                        <span class="agregat-col-code">5.2.02</span>
                                                        <span class="agregat-col-label">Modal Peralatan &amp; Mesin (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-end">
                                                    <div class="agregat-col-header">
                                                        <span class="agregat-col-code">5.2.03</span>
                                                        <span class="agregat-col-label">Modal Gedung &amp; Bangunan (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-end">
                                                    <div class="agregat-col-header">
                                                        <span class="agregat-col-code">5.2.04</span>
                                                        <span class="agregat-col-label">Modal Jalan, Jaringan &amp; Irigasi (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-end agregat-total-col">
                                                    <div class="agregat-col-header">
                                                        <span class="agregat-col-code agregat-col-code--total">TOTAL 5</span>
                                                        <span class="agregat-col-label text-success">Total Belanja (Rp)</span>
                                                    </div>
                                                </th>
                                                <th scope="col" class="text-center" style="width: 95px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($agregatPuskesmas ?? [] as $idx => $row)
                                                @php
                                                    $bp = (float) ($row['belanja_pegawai'] ?? 0);
                                                    $bj = (float) ($row['belanja_barang_jasa'] ?? 0);
                                                    $mp = (float) ($row['belanja_modal_peralatan'] ?? 0);
                                                    $mg = (float) ($row['belanja_modal_gedung'] ?? 0);
                                                    $mj = (float) ($row['belanja_modal_jalan'] ?? 0);
                                                    $tt = (float) ($row['total'] ?? 0);
                                                @endphp
                                                <tr data-unit-name="{{ strtolower($row['nama']) }}">
                                                    <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                                                    <td>
                                                        <div class="agregat-unit-cell">
                                                            <a href="{{ route('rba-perubahan.belanja', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="agregat-unit-link" title="Lihat rincian perubahan belanja {{ $row['nama'] }}">
                                                                <span class="agregat-unit-icon"><i class="ri-hospital-line"></i></span>
                                                                <span>{{ $row['nama'] }}</span>
                                                            </a>
                                                            @if(($row['count'] ?? 0) > 0)
                                                                <span class="agregat-unit-badge">{{ $row['count'] }} item</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-end rba-amount {{ $bp > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($bp, 0, ',', '.') }}</td>
                                                    <td class="text-end rba-amount {{ $bj > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($bj, 0, ',', '.') }}</td>
                                                    <td class="text-end rba-amount {{ $mp > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($mp, 0, ',', '.') }}</td>
                                                    <td class="text-end rba-amount {{ $mg > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($mg, 0, ',', '.') }}</td>
                                                    <td class="text-end rba-amount {{ $mj > 0 ? 'agregat-val-positive' : 'agregat-val-zero' }}">{{ number_format($mj, 0, ',', '.') }}</td>
                                                    <td class="text-end rba-amount agregat-total-col fw-bold {{ $tt > 0 ? 'text-success' : 'agregat-val-zero' }}">
                                                        {{ number_format($tt, 0, ',', '.') }}
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="{{ route('rba-perubahan.belanja', ['puskesmas' => $row['nama'], 'tahun' => request('tahun')]) }}" class="btn btn-sm btn-soft-primary px-2 py-1" title="Lihat rincian perubahan belanja {{ $row['nama'] }}">
                                                            <i class="ri-eye-line me-1"></i>Rincian
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted p-4">Belum ada data belanja perubahan untuk tahun anggaran {{ $tahunRba }}.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <th scope="row" colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                                                <td class="text-end rba-amount">{{ number_format($totalAgregat['belanja_pegawai'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end rba-amount">{{ number_format($totalAgregat['belanja_barang_jasa'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end rba-amount">{{ number_format($totalAgregat['belanja_modal_peralatan'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end rba-amount">{{ number_format($totalAgregat['belanja_modal_gedung'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end rba-amount">{{ number_format($totalAgregat['belanja_modal_jalan'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end text-success rba-amount fs-6">{{ number_format($totalAgregat['total'] ?? 0, 0, ',', '.') }}</td>
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
                                            <span>Menampilkan Rincian RBA Belanja Perubahan Unit BLUD: <strong>{{ request('puskesmas') ?: auth()->user()->upt }}</strong></span>
                                        </div>
                                        <a href="{{ route('rba-perubahan.belanja', ['tahun' => request('tahun')]) }}" class="btn btn-sm btn-info text-white">
                                            <i class="ri-arrow-left-line me-1"></i> Kembali ke Rekap Agregat Seluruh BLUD
                                        </a>
                                    </div>
                                @endif
                            <h5 class="visually-hidden" id="belanja-perubahan-table-title">RBA Belanja Perubahan</h5>
                            <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="belanja-perubahan-table-title" tabindex="0">
                            <table class="table align-middle rba-table rba-table--belanja">
                                <caption class="visually-hidden">Rencana belanja setelah perubahan tahun anggaran {{ $tahunRba }}.</caption>
                                <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"></colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">Kode Rekening</th>
                                        <th scope="col">Uraian</th>
                                        <th scope="col" class="text-end rba-amount">Jumlah Harga (Rp)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {{-- STATIC HEADERS FOR 5 AND 5.1 --}}
                                    <tr class="rba-section rba-section-root">
                                        <th scope="row" colspan="2">5 - BELANJA</th>
                                        <td class="text-end rba-amount">{{ number_format($total_5, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="rba-section">
                                        <th scope="row" colspan="2">5.1 - BELANJA OPERASIONAL</th>
                                        <td class="text-end rba-amount">{{ number_format($total_5_1, 0, ',', '.') }}</td>
                                    </tr>
                                    @php $printed_5_2 = false; @endphp
                                    @foreach ($klasifikasi as $key => $klas)
                                        @if (str_starts_with($klas['prefix'], '5.2') && !$printed_5_2 && $total_5_2 > 0)
                                            <tr class="rba-section">
                                                <th scope="row" colspan="2">5.2 - BELANJA MODAL</th>
                                                <td class="text-end rba-amount">{{ number_format($total_5_2, 0, ',', '.') }}</td>
                                            </tr>
                                            @php $printed_5_2 = true; @endphp
                                        @endif
                                        @php
                                            $itemsInKlas = $groupedByKlasifikasi[$key];
                                            $subtotal = $itemsInKlas->flatten()->sum('proposed_jumlah_harga');
                                        @endphp

                                        @if ($itemsInKlas->isNotEmpty())
                                            {{-- BARIS HEADER KLASIFIKASI (tidak collapse) --}}
                                            <tr class="rba-group rba-group--{{ str_replace('table-', '', $klas['color']) }}">
                                                <th scope="row" colspan="2"><div class="d-flex align-items-center flex-wrap gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="rba-group-count">{{ $itemsInKlas->flatten()->count() }} rincian</span></div></th>
                                                <td class="text-end rba-amount">{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            </tr>

                                            @foreach ($itemsInKlas as $akun_id => $items)
                                                @php
                                                    $collapseId = 'akun_' . $akun_id;
                                                    $first = $items->first();
                                                @endphp

                                                {{-- HEADER AKUN (bisa collapse ke detail) --}}
                                                <tr class="rba-item"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                    <td><button type="button" class="rba-account-toggle" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}" aria-label="Lihat rincian akun {{ $first->akuns->kode_rek ?? '-' }}"><i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i><span class="rba-account-code">{{ $first->akuns->kode_rek ?? '-' }}</span></button></td>
                                                    <td><div class="fw-semibold text-body-emphasis">{{ $first->akuns->uraian ?? '-' }}</div><div class="small text-body-secondary mt-1">{{ $items->count() }} rincian kebutuhan</div></td>
                                                    <td class="text-end rba-amount fw-semibold">{{ number_format($items->sum('proposed_jumlah_harga'), 0, ',', '.') }}</td>
                                                </tr>

                                                {{-- COLLAPSE DETAIL --}}
                                                <tr class="collapse" id="{{ $collapseId }}">
                                                    <td colspan="3" class="p-0">
                                                        <div class="rba-detail-panel">
                                                            <div class="table-responsive rba-table-wrap" role="region" aria-label="Perbandingan rincian akun {{ $first->akuns->kode_rek ?? '-' }}" tabindex="0">
                                                            <table class="table table-sm align-middle mb-0 rba-detail-table rba-detail-table--comparison">
                                                                <caption class="visually-hidden">Rincian belanja sebelum dan sesudah perubahan.</caption>
                                                                <thead class="text-center">
                                                                    <tr>
                                                                        <th scope="col" rowspan="2" class="align-middle">Uraian</th>
                                                                        <th scope="colgroup" colspan="6" >Sebelum Perubahan (Murni)</th>
                                                                        <th scope="colgroup" colspan="6" class="rba-after">Sesudah Perubahan</th>
                                                                        <th scope="col" rowspan="2" class="align-middle text-end">Selisih (Rp)</th>
                                                                        <th scope="col" rowspan="2" class="align-middle">Status</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th scope="col">Vol</th>
                                                                        <th scope="col">Satuan</th>
                                                                        <th scope="col">Harga</th>
                                                                        <th scope="col">PPN</th>
                                                                        <th scope="col">Jumlah</th>
                                                                        <th scope="col">Keterangan</th>
                                                                        
                                                                        <th scope="col">Vol</th>
                                                                        <th scope="col">Satuan</th>
                                                                        <th scope="col">Harga</th>
                                                                        <th scope="col">PPN</th>
                                                                        <th scope="col">Jumlah</th>
                                                                        <th scope="col">Keterangan</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($items as $sub)
                                                                        @php
                                                                            $selisih = (float) $sub->proposed_jumlah_harga - (float) $sub->jumlah_harga;
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="{{ $sub->uraian_perubahan !== null && $sub->uraian_perubahan != $sub->uraian ? 'rba-delta-positive fw-semibold' : '' }}">{{ $sub->proposed_uraian }}</td>
                                                                            <!-- Data Asli -->
                                                                            <td class="text-center">{{ $sub->jumlah }}</td>
                                                                            <td class="text-center">{{ $sub->satuan }}</td>
                                                                            <td class="text-end rba-amount">{{ number_format($sub->harga_satuan, 0, ',', '.') }}</td>
                                                                            <td class="text-center">-</td>
                                                                            <td class="text-end rba-amount">{{ number_format($sub->jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td>{{ $sub->keterangan }}</td>

                                                                            <!-- Data Perubahan -->
                                                                            <td class="text-center {{ $sub->jumlah_perubahan !== null && $sub->jumlah_perubahan != $sub->jumlah ? 'rba-delta-positive fw-semibold' : '' }}">{{ $sub->proposed_jumlah }}</td>
                                                                            <td class="text-center {{ $sub->satuan_perubahan !== null && $sub->satuan_perubahan != $sub->satuan ? 'rba-delta-positive fw-semibold' : '' }}">{{ $sub->proposed_satuan }}</td>
                                                                            <td class="text-end rba-amount {{ $sub->harga_satuan_perubahan !== null && $sub->harga_satuan_perubahan != $sub->harga_satuan ? 'rba-delta-positive fw-semibold' : '' }}">{{ number_format($sub->proposed_harga_satuan, 0, ',', '.') }}</td>
                                                                            <td class="text-center">-</td>
                                                                            <td class="text-end rba-amount {{ $sub->jumlah_harga_perubahan !== null && $sub->jumlah_harga_perubahan != $sub->jumlah_harga ? 'rba-delta-positive fw-semibold' : '' }}">{{ number_format($sub->proposed_jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td class="{{ $sub->keterangan_perubahan !== null && $sub->keterangan_perubahan != $sub->keterangan ? 'rba-delta-positive fw-semibold' : '' }}">{{ $sub->proposed_keterangan }}</td>

                                                                            <td class="text-end rba-amount fw-semibold {{ $selisih > 0 ? 'rba-delta-positive' : ($selisih < 0 ? 'rba-delta-negative' : '') }}">{{ $selisih > 0 ? '+' : '' }}{{ number_format($selisih, 0, ',', '.') }}</td>
                                                                            <td class="text-center">
                                                                                @if($sub->status_perubahan === 'pending')
                                                                                    @php
                                                                                        $verifikator = strtolower(trim((string) ($sub->verifikator_perubahan ?? 'kepala puskesmas')));
                                                                                    @endphp
                                                                                    <span class="rba-status rba-status--pending"><i class="ri-time-line me-1"></i>Menunggu Verifikasi ({{ ucwords($verifikator) }})</span>
                                                                                    @if(auth()->user()->hasRole('superuser') || auth()->user()->hasRole($verifikator) || ($isDinas && $verifikator === 'dinas kesehatan'))
                                                                                        <div class="d-flex justify-content-center gap-1 mt-1">
                                                                                            <form action="{{ route('rba-perubahan.belanja.verifikasi', $sub->id) }}" method="POST" class="m-0" onsubmit="return confirm('Verifikasi perubahan ini?');">
                                                                                                @csrf
                                                                                                @method('PATCH')
                                                                                                <button type="submit" class="btn btn-sm btn-success" title="Verifikasi" aria-label="Verifikasi"><i class="ri-check-line"></i></button>
                                                                                            </form>
                                                                                            <form action="{{ route('rba-perubahan.belanja.tolak', $sub->id) }}" method="POST" class="m-0" onsubmit="return confirm('Tolak perubahan ini?');">
                                                                                                @csrf
                                                                                                @method('PATCH')
                                                                                                <button type="submit" class="btn btn-sm btn-danger" title="Tolak" aria-label="Tolak"><i class="ri-close-line"></i></button>
                                                                                            </form>
                                                                                        </div>
                                                                                    @endif
                                                                                @elseif($sub->status_perubahan === 'verified')
                                                                                    <span class="rba-status rba-status--verified"><i class="ri-checkbox-circle-line me-1"></i>Terverifikasi</span>
                                                                                @elseif($sub->status_perubahan === 'rejected')
                                                                                    <span class="rba-status rba-status--rejected"><i class="ri-close-circle-line me-1"></i>Ditolak</span>
                                                                                @else
                                                                                    <span class="rba-status">Tidak Ada Perubahan</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            {{-- SUBTOTAL PER KLASIFIKASI --}}
                                            <tr class="rba-subtotal">
                                                <th colspan="2" scope="row" class="text-end">Subtotal {{ $klas['label'] }}</th>
                                                <td class="text-end rba-amount">{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    @if($grouped->isEmpty())<tr><td colspan="3" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana belanja.</p><p class="small mb-0">Usulan perubahan akan tampil setelah rencana kebutuhan ditambahkan.</p></td></tr>@endif
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th scope="row" colspan="2">TOTAL RENCANA BELANJA</th>
                                        <td class="text-end rba-amount">{{ number_format($grandTotalJumlah, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->

            <div class="modal fade" id="modalKinerjaKegiatan" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
                    <form action="{{ route('rba-perubahan.kinerja-kegiatan.store') }}" method="POST">
                        @csrf
                        <div class="modal-content rba-budget rba-change">
                            <div class="modal-header">
                                <h5 class="modal-title">Perubahan Kinerja Kegiatan (Tahun {{ $tahun }})</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <input type="hidden" name="tahun" value="{{ $tahun }}">
                                
                                @if(isset($kinerjaKegiatan) && $kinerjaKegiatan->status_perubahan)
                                    <div class="alert alert-info m-3 mb-0">
                                        Status Perubahan: <strong>{{ ucfirst($kinerjaKegiatan->status_perubahan) }}</strong>
                                        @if($kinerjaKegiatan->status_perubahan === 'pending')
                                            (Menunggu Verifikasi: {{ ucwords($kinerjaKegiatan->verifikator_perubahan ?? 'Kepala Puskesmas') }})
                                        @endif
                                    </div>
                                @endif

                                <div class="p-3"><div class="table-responsive rba-table-wrap" role="region" aria-label="Perubahan Kinerja Kegiatan" tabindex="0"><table class="table align-middle mb-0 rba-detail-table rba-kinerja-table">
                                    <thead class="text-center">
                                        <tr>
                                            <th scope="col" rowspan="2" class="align-middle" style="width: 20%;">Indikator</th>
                                            <th scope="col" colspan="2">Sebelum Perubahan</th>
                                            <th scope="col" colspan="2" class="rba-after">Setelah Perubahan</th>
                                        </tr>
                                        <tr>
                                            <th scope="col">Tolak Ukur Kinerja</th>
                                            <th scope="col">Target Kinerja</th>
                                            <th scope="col">Tolak Ukur Kinerja</th>
                                            <th scope="col">Target Kinerja</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="font-bold">Capaian Program</td>
                                            <td><div style="white-space: pre-wrap;">{{ $kinerjaKegiatan->capaian_program_tolak_ukur ?? '' }}</div></td>
                                            <td class="text-center">{{ $kinerjaKegiatan->capaian_program_target ?? '' }}</td>
                                            <td>
                                                <textarea name="capaian_program_tolak_ukur_perubahan" class="form-control" rows="2">{{ old('capaian_program_tolak_ukur_perubahan', $kinerjaKegiatan->capaian_program_tolak_ukur_perubahan ?? $kinerjaKegiatan->capaian_program_tolak_ukur ?? '') }}</textarea>
                                            </td>
                                            <td>
                                                <input type="text" name="capaian_program_target_perubahan" class="form-control" value="{{ old('capaian_program_target_perubahan', $kinerjaKegiatan->capaian_program_target_perubahan ?? $kinerjaKegiatan->capaian_program_target ?? '') }}">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-bold">Masukan</td>
                                            <td><div style="white-space: pre-wrap;">{{ $kinerjaKegiatan->masukan_tolak_ukur ?? '' }}</div></td>
                                            <td class="text-center">{{ $kinerjaKegiatan->masukan_target ?? '' }}</td>
                                            <td>
                                                <textarea name="masukan_tolak_ukur_perubahan" class="form-control" rows="2">{{ old('masukan_tolak_ukur_perubahan', $kinerjaKegiatan->masukan_tolak_ukur_perubahan ?? $kinerjaKegiatan->masukan_tolak_ukur ?? '') }}</textarea>
                                            </td>
                                            <td>
                                                <input type="text" name="masukan_target_perubahan" class="form-control" value="{{ old('masukan_target_perubahan', $kinerjaKegiatan->masukan_target_perubahan ?? $kinerjaKegiatan->masukan_target ?? '') }}">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-bold">Keluaran</td>
                                            <td><div style="white-space: pre-wrap;">{{ $kinerjaKegiatan->keluaran_tolak_ukur ?? '' }}</div></td>
                                            <td class="text-center">{{ $kinerjaKegiatan->keluaran_target ?? '' }}</td>
                                            <td>
                                                <textarea name="keluaran_tolak_ukur_perubahan" class="form-control" rows="2">{{ old('keluaran_tolak_ukur_perubahan', $kinerjaKegiatan->keluaran_tolak_ukur_perubahan ?? $kinerjaKegiatan->keluaran_tolak_ukur ?? '') }}</textarea>
                                            </td>
                                            <td>
                                                <input type="text" name="keluaran_target_perubahan" class="form-control" value="{{ old('keluaran_target_perubahan', $kinerjaKegiatan->keluaran_target_perubahan ?? $kinerjaKegiatan->keluaran_target ?? '') }}">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table></div></div>
                            </div>
                            <div class="modal-footer d-flex justify-content-between">
                                <div>
                                    @if(isset($kinerjaKegiatan) && $kinerjaKegiatan->status_perubahan === 'pending' && 
                                        (auth()->user()->hasRole($kinerjaKegiatan->verifikator_perubahan ?? 'kepala puskesmas') ||
                                        (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan' && strtolower(trim((string)($kinerjaKegiatan->verifikator_perubahan ?? 'kepala puskesmas'))) === 'dinas kesehatan')
                                        ))
                                        <button type="submit" form="formVerifikasiKinerja" class="btn btn-success"><i class="ri-check-line"></i> Verifikasi</button>
                                        <button type="submit" form="formTolakKinerja" class="btn btn-danger"><i class="ri-close-line"></i> Tolak</button>
                                    @endif
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-save">Simpan Perubahan</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if(isset($kinerjaKegiatan) && $kinerjaKegiatan->status_perubahan === 'pending')
                <form id="formVerifikasiKinerja" action="{{ route('rba-perubahan.kinerja-kegiatan.verifikasi', $kinerjaKegiatan->id) }}" method="POST" style="display: none;" onsubmit="return confirm('Verifikasi perubahan ini?');">
                    @csrf
                    @method('PATCH')
                </form>
                <form id="formTolakKinerja" action="{{ route('rba-perubahan.kinerja-kegiatan.tolak', $kinerjaKegiatan->id) }}" method="POST" style="display: none;" onsubmit="return confirm('Tolak perubahan ini?');">
                    @csrf
                    @method('PATCH')
                </form>
            @endif

            <div class="modal fade" id="modalEdit" tabindex="-1">
                <div class="modal-dialog modal-md">
                    <form method="POST" id="formEdit">
                        @csrf
                        @method('PUT')

                        <div class="modal-content rba-budget rba-change">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Usulan Perubahan Rencana Belanja</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                                <input type="hidden" name="id" id="edit-id">

                                <div class="mb-3">
                                    <label>Uraian</label>
                                    <input type="text" name="uraian" id="edit-uraian" class="form-control" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Vol</label>
                                        <input type="number" name="jumlah" id="edit-jumlah" class="form-control" step="any" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Satuan</label>
                                        <input type="text" name="satuan" id="edit-satuan" class="form-control" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Harga Satuan</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" name="harga_satuan" id="edit-harga-satuan-display" class="form-control" oninput="formatEditHargaSatuan(this)">
                                        <input type="hidden" name="harga_satuan" id="edit-harga-satuan-value">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Keterangan</label>
                                    <input type="text" name="keterangan" id="edit-keterangan" class="form-control">
                                </div>

                            </div>
                            <div class="modal-footer">
                                <div class="form-actions">
                                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-save">Simpan Perubahan</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @endsection @section('script')

            <script>


                function formatEditHargaSatuan(input) {
                    let value = input.value.replace(/\D/g, '');
                    if(value !== '') {
                        document.getElementById('edit-harga-satuan-value').value = value;
                        input.value = new Intl.NumberFormat('id-ID').format(value);
                    } else {
                        document.getElementById('edit-harga-satuan-value').value = '';
                        input.value = '';
                    }
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const btnEdits = document.querySelectorAll('.btn-edit');
                    btnEdits.forEach(btn => {
                        btn.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            const uraian = this.getAttribute('data-uraian');
                            const jumlah = this.getAttribute('data-jumlah');
                            const satuan = this.getAttribute('data-satuan');
                            const harga_satuan = this.getAttribute('data-harga_satuan');
                            const keterangan = this.getAttribute('data-keterangan');

                            document.getElementById('edit-id').value = id;
                            document.getElementById('edit-uraian').value = uraian;
                            document.getElementById('edit-jumlah').value = jumlah;
                            document.getElementById('edit-satuan').value = satuan;
                            
                            document.getElementById('edit-harga-satuan-value').value = harga_satuan;
                            document.getElementById('edit-harga-satuan-display').value = new Intl.NumberFormat('id-ID').format(harga_satuan);
                            
                            document.getElementById('edit-keterangan').value = keterangan;

                            const form = document.getElementById('formEdit');
                            form.action = `/rba-perubahan/belanja/${id}`;
                        });
                    });
                });
            </script>
        @endsection
