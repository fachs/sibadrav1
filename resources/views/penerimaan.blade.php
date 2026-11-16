@extends('layouts.vertical', ['title' => 'Penerimaan','subTitle' => 'Pendapatan'])


@php \Carbon\Carbon::setLocale('id'); @endphp
@section('css')
    <!-- DataTables -->
    <link href="{{ asset('assets/libs/datatables/dataTables.min.css') }}" rel="stylesheet" type="text/css">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            .card,
            .card * {
                visibility: visible;
            }

            .card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            button {
                display: none;
            }

            table {
                font-size: 12px;
            }
        }
    </style>
@endsection

@section('body')

    <body data-sidebar="dark">
    @endsection

    @section('content')
        @php
            $bulanList = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];
        @endphp

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                            <iconify-icon icon="solar:wallet-money-broken" class="fs-32 text-success avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <p class="text-muted mb-1">Diterima</p>
                            <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalDiterima, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                            <iconify-icon icon="solar:banknote-2-broken" class="fs-32 text-primary avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <p class="text-muted mb-1">Disetorkan</p>
                            <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalDisetorkan, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                            <iconify-icon icon="solar:calculator-minimalistic-broken" class="fs-32 text-warning avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <p class="text-muted mb-1">Selisih</p>
                            <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalSelisih, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    

                    <div class="card-body pb-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td style="width: 150px;"><strong>Unit BLUD</strong></td>
                                    <td style="width: 10px;">:</td>
                                    <td>
                                        @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                            {{ $selectedUnitBludId ? optional($unitBluds->firstWhere('id', (int) $selectedUnitBludId))->nama : 'Semua Unit BLUD' }}
                                        @else
                                            {{ Auth::user()->upt }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Periode</strong></td>
                                    <td>:</td>
                                    <td>{{ $selectedPeriode ?: 'Semua Periode' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Bukti Dukung</strong></td>
                                    <td>:</td>
                                    <td>
                                        @if (empty($selectedPeriode))
                                            -
                                        @elseif (!empty($buktiDukung?->link))
                                            <a href="{{ $buktiDukung->link }}" target="_blank">{{ $buktiDukung->link }}</a>
                                        @else
                                            <span class="text-muted">Belum ada bukti dukung untuk periode
                                                {{ $selectedPeriode }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <a href="{{ route('buku-penerimaan-penyetoran.pdf', ['periode' => $selectedPeriode, 'unit_blud_id' => $selectedUnitBludId]) }}" class="btn btn-success btn-sm" {{ empty($selectedPeriode) ? 'disabled' : '' }}>
                                <i class="ri-printer-line"></i> Cetak BKU
                            </a>
                        </div>
                    </div>

                    <div class="card-body pt-0">
                        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary btn-md" data-bs-toggle="modal"
                                    data-bs-target="#modalTambahPenerimaan"
                                    {{ empty($canTambahPenerimaan) ? 'disabled' : '' }} {{ empty($selectedPeriode) ? 'disabled' : '' }}>
                                    <i class="fas fa-plus"></i> Tambah Penerimaan
                                </button>
                                

                                {{-- @if (empty($canTambahPenerimaan) && !empty($alasanBlokir))
                                    <small class="text-danger d-block mt-1">{{ $alasanBlokir }}</small>
                                @endif --}}

                                <button type="button" class="btn btn-outline-primary btn-md" data-bs-toggle="modal"
                                    data-bs-target="#modalUploadBuktiDukung">
                                    <i class="fas fa-upload"></i> Update Bukti Dukung
                                </button>
                            </div>


                    <div class="modal fade" id="modalUploadBuktiDukung" tabindex="-1"
                        aria-labelledby="modalUploadBuktiDukungLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="{{ route('penerimaan.upload-bukti-dukung') }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalUploadBuktiDukungLabel">Upload Bukti Dukung</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body">
                                        @php
                                            $bulanList = [
                                                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                                                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                                            ];
                                        @endphp
                                        <div class="mb-3">
                                            <label class="form-label">Periode</label>
                                            <select name="periode" class="form-select" required>
                                                <option value="">-- Pilih Bulan --</option>
                                                @foreach ($bulanList as $bulan)
                                                    <option value="{{ $bulan }}" {{ $selectedPeriode === $bulan ? 'selected' : '' }}>
                                                        {{ $bulan }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}">

                                        @php
                                            $isDinkes = strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan';
                                        @endphp

                                        <input type="hidden" name="unit_blud_id" value="{{ optional($penerimaans->first())->unit_blud_id }}">

                                        <div class="mb-1">
                                            <label class="form-label">Link Google Drive</label>
                                            <input type="url" class="form-control" name="link"
                                                placeholder="https://drive.google.com/..." required>
                                            <small class="text-muted">Masukkan link folder/file Google Drive bukti dukung.</small>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-save">
                                            Simpan
                                        </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                            <form method="GET" action="{{ url()->current() }}"
                                class="row g-2 align-items-end justify-content-end">
                                <div class="col-md-7 col-lg-6">
                                    <label for="periode" class="form-label mb-1"><strong>Pilih Periode</strong></label>
                                    <select name="periode" id="periode" class="form-select">
                                        <option value="">-- Pilih Bulan --</option>
                                        @foreach ($bulanList as $bulan)
                                            <option value="{{ $bulan }}"
                                                {{ $selectedPeriode === $bulan ? 'selected' : '' }}>
                                                {{ $bulan }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                    <div class="col-md-7 col-lg-6">
                                        <label for="unit_blud_id" class="form-label mb-1"><strong>Pilih Unit BLUD</strong></label>
                                        <select name="unit_blud_id" id="unit_blud_id" class="form-select">
                                            <option value="">Semua Unit BLUD</option>
                                            @foreach ($unitBluds as $unit)
                                                <option value="{{ $unit->id }}" @selected((string) $selectedUnitBludId === (string) $unit->id)>
                                                    {{ $unit->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary">Cari</button>
                                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                        @if (empty($canTambahPenerimaan) && !empty($alasanBlokir))
                            <div class="mt-2 text-danger">
                                <small>{{ $alasanBlokir }}</small>
                            </div>
                        @endif
                    </div>

                    <div class="modal fade" id="modalTambahPenerimaan" tabindex="-1"
                        aria-labelledby="modalTambahPenerimaanLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form action="{{ route('penerimaan.store') }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalTambahPenerimaanLabel">Tambah Penerimaan</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    @php
                                        $bulanList = [
                                            'Januari',
                                            'Februari',
                                            'Maret',
                                            'April',
                                            'Mei',
                                            'Juni',
                                            'Juli',
                                            'Agustus',
                                            'September',
                                            'Oktober',
                                            'November',
                                            'Desember',
                                        ];
                                    @endphp

                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Tahun Anggaran</label>
                                                <input type="text" class="form-control" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}" readonly>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Periode</label>
                                                <select name="periode" id="periode_tambah" class="form-select" required>
                                                    <option value="">-- Pilih Bulan --</option>
                                                    @foreach ($bulanList as $bulan)
                                                        <option value="{{ $bulan }}">{{ $bulan }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" class="form-control" name="tanggal_terima" onchange="updatePeriodeFromDate(this, 'periode_tambah')" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Nomor TBP</label>
                                                <input type="text" class="form-control" name="nomor_tbp" maxlength="100" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Cara Pembayaran</label>
                                                <select name="cara_pembayaran" class="form-select" required>
                                                    <option value="">-- Pilih Cara Pembayaran --</option>
                                                    <option value="Kas Penerimaan BLUD">Kas Penerimaan BLUD</option>
                                                    <option value="Kas BLUD">Kas BLUD</option>
                                                    <option value="Tunai">Tunai</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Kode Rekening</label>
                                                <select class="form-select" name="kode_rekening" id="kode_rekening" onchange="updateUraianFromKodeRekening(this, 'uraian')" required>
                                                    <option value="">-- Pilih Kode Rekening --</option>
                                                    <option value="4.1.04.16.02.0001">4.1.04.16.02.0001 - Pendapatan BLUD dari Jasa Layanan</option>
                                                    <option value="4.3.03.02.01.0001">4.3.03.02.01.0001 - Pendapatan Dana Kapitasi pada FKTP</option>
                                                    <option value="4.1.02.01.01.0001">4.1.02.01.01.0001 - Retribusi Pelayanan Kesehatan di Puskesmas</option>
                                                    <option value="4.1.02.01.01.0006">4.1.02.01.01.0006 - Retribusi Pelayanan Kesehatan di Puskesmas</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Uraian</label>
                                                <input type="text" class="form-control" name="uraian" id="uraian">
                                            </div>



                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-save">Simpan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>


                    <div class="card-body">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nomor TBP</th>
                                    <th>Tanggal Terima</th>
                                    <th>Tanggal Setor</th>
                                    <th>Cara Pembayaran</th>
                                    <th>Kode Rekening</th>
                                    @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                        <th>BLUD</th>
                                    @endif
                                    <th style="width: 20%">Uraian</th>
                                    <th>Jumlah</th>
                                    {{-- <th>Status</th> --}}
                                    <th style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @if (empty($selectedPeriode))
                                    <tr>
                                        <td colspan="7" class="text-center">Silakan pilih periode terlebih dahulu.</td>
                                    </tr>
                                @else --}}
                                @forelse ($penerimaans as $item)
                                    <tr>
                                        <td><a href="{{ route('penerimaan.transaksi', $item->uuid) }}" class="fw-bold text-primary">{{ $item->nomor_terima }}</a></td>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal_terima)->locale('id')->translatedFormat('d F Y') }}</td>
                                        <td>
                                            @if ($item->tanggal_setor)
                                                {{ \Carbon\Carbon::parse($item->tanggal_setor)->locale('id')->translatedFormat('d F Y') }}
                                            @else
                                                <span class="badge bg-danger">Belum Setor</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->cara_pembayaran }}</td>
                                        <td>{{ $item->kode_rekening }}</td>
                                        @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                            <td>{{ optional($item->unitBlud)->nama ?? '-' }}</td>
                                        @endif
                                        <td>{{ $item->uraian }}</td>
                                        <td>Rp {{ number_format($item->transaksis_sum_total_biaya ?? 0, 0, ',', '.') }}</td>

                                        <td>
                                            <div class="d-flex gap-1">

                                                @if (strtolower(trim((string) auth()->user()->upt)) != 'dinas kesehatan')
                                                        <button type="button"
                                                            class="btn btn-sm btn-info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalSetor{{ $item->id }}"
                                                            title="Setorkan"
                                                            {{ $item->tanggal_setor ? 'disabled' : '' }}>
                                                            <i class="ri-check-double-line"></i>
                                                        </button>

                                                        <button type="button"
                                                            class="btn btn-sm btn-warning"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEditPenerimaan{{ $item->id }}"
                                                            {{ $item->tanggal_setor ? 'disabled' : '' }}>
                                                            <i class="ri-edit-box-line"></i>
                                                        </button>

                                                        <form action="{{ route('penerimaan.destroy', $item->id) }}"
                                                            method="POST"
                                                            data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger" {{ $item->tanggal_setor ? 'disabled' : '' }}>
                                                                <i class="ri-delete-bin-2-line"></i>
                                                            </button>
                                                        </form>

                                                    {{-- Modal Setor --}}
                                                    <div class="modal fade" id="modalSetor{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form action="{{ route('penerimaan.update-tanggal-setor', $item->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Tanggal Penyetoran</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <label class="form-label">Tanggal Setor</label>
                                                                        <input type="date" class="form-control" name="tanggal_setor" value="{{ $item->tanggal_setor ? \Carbon\Carbon::parse($item->tanggal_setor)->format('Y-m-d') : date('Y-m-d') }}" required>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <div class="form-actions">
                                                                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                                                            <button type="submit" class="btn btn-save">Simpan</button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Modal Edit Penerimaan --}}
                                                    <div class="modal fade" id="modalEditPenerimaan{{ $item->id }}"
                                                        tabindex="-1"
                                                        aria-labelledby="modalEditPenerimaanLabel{{ $item->id }}"
                                                        aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <form action="{{ route('penerimaan.update', $item->id) }}" method="POST">
                                                                    @csrf
                                                                    @method('PUT')

                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="modalEditPenerimaanLabel{{ $item->id }}">
                                                                            Edit Penerimaan
                                                                        </h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>

                                                                    @php
                                                                        $bulanList = [
                                                                            'Januari',
                                                                            'Februari',
                                                                            'Maret',
                                                                            'April',
                                                                            'Mei',
                                                                            'Juni',
                                                                            'Juli',
                                                                            'Agustus',
                                                                            'September',
                                                                            'Oktober',
                                                                            'November',
                                                                            'Desember',
                                                                        ];
                                                                    @endphp

                                                                    <div class="modal-body">
                                                                        <div class="row g-3">
                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Tahun Anggaran</label>
                                                                                <input type="text" class="form-control" name="tahun_anggaran"
                                                                                    value="{{ $item->tahun_anggaran ?? date('Y') }}" readonly>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Periode</label>
                                                                                <select name="periode" id="periode_edit_{{ $item->id }}" class="form-select" required>
                                                                                    <option value="">-- Pilih Bulan --</option>
                                                                                    @foreach ($bulanList as $bulan)
                                                                                        <option value="{{ $bulan }}" {{ $item->periode === $bulan ? 'selected' : '' }}>
                                                                                            {{ $bulan }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Tanggal</label>
                                                                                <input type="date" class="form-control" name="tanggal_terima"
                                                                                    value="{{ $item->tanggal_terima ? \Carbon\Carbon::parse($item->tanggal_terima)->format('Y-m-d') : '' }}" required
                                                                                    onchange="updatePeriodeFromDate(this, 'periode_edit_{{ $item->id }}')">
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Nomor TBP</label>
                                                                                <input type="text" class="form-control" name="nomor_tbp" maxlength="100"
                                                                                    value="{{ $item->nomor_terima }}" required>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Cara Pembayaran</label>
                                                                                <select name="cara_pembayaran" class="form-select" required>
                                                                                    <option value="">-- Pilih Cara Pembayaran --</option>
                                                                                    <option value="Kas Penerimaan BLUD" {{ $item->cara_pembayaran === 'Kas Penerimaan BLUD' ? 'selected' : '' }}>
                                                                                        Kas Penerimaan BLUD
                                                                                    </option>
                                                                                    <option value="Kas BLUD" {{ $item->cara_pembayaran === 'Kas BLUD' ? 'selected' : '' }}>
                                                                                        Kas BLUD
                                                                                    </option>
                                                                                    <option value="Tunai" {{ $item->cara_pembayaran === 'Tunai' ? 'selected' : '' }}>
                                                                                        Tunai
                                                                                    </option>
                                                                                </select>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Kode Rekening</label>
                                                                                <select class="form-select"
                                                                                    name="kode_rekening"
                                                                                    id="kode_rekening_edit_{{ $item->id }}"
                                                                                    data-edit-kode-rekening
                                                                                    data-item-id="{{ $item->id }}"
                                                                                    onchange="updateUraianFromKodeRekening(this, 'uraian_edit_{{ $item->id }}')"
                                                                                    required>
                                                                                    <option value="">-- Pilih Kode Rekening --</option>
                                                                                    <option value="4.1.04.16.02.0001" {{ $item->kode_rekening === '4.1.04.16.02.0001' ? 'selected' : '' }}>
                                                                                        4.1.04.16.02.0001 - Pendapatan BLUD dari Jasa Layanan
                                                                                    </option>
                                                                                    <option value="4.3.03.02.01.0001" {{ $item->kode_rekening === '4.3.03.02.01.0001' ? 'selected' : '' }}>
                                                                                        4.3.03.02.01.0001 - Pendapatan Dana Kapitasi pada FKTP
                                                                                    </option>
                                                                                    <option value="4.1.02.01.01.0001" {{ $item->kode_rekening === '4.1.02.01.01.0001' ? 'selected' : '' }}>
                                                                                        4.1.02.01.01.0001 - Retribusi Pelayanan Kesehatan di Puskesmas
                                                                                    </option>
                                                                                    <option value="4.1.02.01.01.0006" {{ $item->kode_rekening === '4.1.02.01.01.0006' ? 'selected' : '' }}>
                                                                                        4.1.02.01.01.0006 - Retribusi Pelayanan Kesehatan di Puskesmas
                                                                                    </option>
                                                                                </select>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label class="form-label">Uraian</label>
                                                                                <input type="text" class="form-control" name="uraian" id="uraian_edit_{{ $item->id }}" value="{{ $item->uraian }}">
                                                                            </div>



                                                                        </div>
                                                                    </div>

                                                                    <div class="modal-footer">
                                                                        <div class="form-actions">
                                                                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                                                            <button type="submit" class="btn btn-save">Update</button>
                                                                        </div>
                                                                    </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    
                                                @endif

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">Data penerimaan pada periode ini tidak
                                            ditemukan.</td>
                                    </tr>
                                @endforelse
                                {{-- @endif --}}
                            </tbody>
                        </table>
                        <div class="mt-4  mb-4 d-flex justify-content-center">
                            {{ $penerimaans->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('script')
        <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>
        <script src="{{ URL::asset('assets/js/pages/form-advanced.init.js') }}"></script>
        <script src="{{ URL::asset('assets/js/app.js') }}"></script>

        <script>
            function printPage() {
                window.print();
            }

            function updatePeriodeFromDate(dateInput, periodeSelectId) {
                if (!dateInput.value) return;
                const date = new Date(dateInput.value);
                const month = date.getMonth(); // 0-11
                const bulanList = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                ];
                const periodeSelect = document.getElementById(periodeSelectId);
                if (periodeSelect) {
                    periodeSelect.value = bulanList[month];
                }
            }

            function updateUraianFromKodeRekening(kodeSelect, uraianInputId) {
                const mapUraian = {
                    '4.1.04.16.02.0001': 'Pendapatan BLUD dari Jasa Layanan',
                    '4.3.03.02.01.0001': 'Pendapatan Dana Kapitasi pada FKTP',
                    '4.1.02.01.01.0001': 'Retribusi Pelayanan Kesehatan di Puskesmas',
                    '4.1.02.01.01.0006': 'Retribusi Pelayanan Kesehatan di Puskesmas'
                };
                
                const uraianInput = document.getElementById(uraianInputId);
                if (uraianInput) {
                    if (mapUraian[kodeSelect.value]) {
                        uraianInput.value = mapUraian[kodeSelect.value];
                    } else if (!kodeSelect.value) {
                        uraianInput.value = '';
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Implementasi format-rupiah.js untuk input form
                document.querySelectorAll('.format-rupiah').forEach(function(input) {
                    input.addEventListener('input', function(e) {
                        if (typeof formatRupiahInput === 'function') {
                            formatRupiahInput(e.target);
                        } else if (typeof formatRupiah === 'function') {
                            e.target.value = formatRupiah(e.target.value);
                        }
                    });
                });
            });
        </script>

    @endsection
