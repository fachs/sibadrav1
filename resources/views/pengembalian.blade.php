@extends('layouts.vertical', ['title' => 'Pengembalian','subTitle' => 'Pendapatan'])


@php \Carbon\Carbon::setLocale('id'); @endphp
@section('css')
    
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
        $bulanList = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    @endphp


    <div class="row">
        <div class="col-12">
            <div class="card">

                <div class="card-body pb-0">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td style="width: 150px;"><strong>Unit BLUD</strong></td>
                            <td style="width: 10px;">:</td>
                            <td>{{ Auth::user()->upt }}</td>
                        </tr>
                        <tr>
                            <td><strong>Bendahara</strong></td>
                            <td>:</td>
                            <td>{{ Auth::user()->nip }} - {{ Auth::user()->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Periode</strong></td>
                            <td>:</td>
                            <td>{{ $selectedPeriode ?: '-' }}</td>
                        </tr>
                    </table>
                </div>

                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPengembalian">
                                <i class="fas fa-plus"></i> Tambah Pengembalian
                            </button>
                        </div>

                        <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end">
                            <div class="col-md-7 col-lg-6">
                                <label for="periode" class="form-label mb-1"><strong>Pilih Periode</strong></label>
                                <select name="periode" id="periode" class="form-select">
                                    <option value="">-- Pilih Bulan --</option>
                                    @foreach ($bulanList as $bulan)
                                        <option value="{{ $bulan }}" {{ $selectedPeriode === $bulan ? 'selected' : '' }}>
                                            {{ $bulan }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-primary">Cari</button>
                                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal fade" id="modalTambahPengembalian" tabindex="-1" aria-labelledby="modalTambahPengembalianLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form action="{{ route('pengembalian.store') }}" method="POST">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalTambahPengembalianLabel">Tambah Pengembalian</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Tanggal</label>
                                            <input type="date" class="form-control" name="tanggal" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Jumlah</label>
                                            <input type="number" class="form-control" name="jumlah" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Penyetoran</label>
                                            <select name="penerimaan_id" class="form-select" required>
                                                <option value="">-- Pilih Penyetoran --</option>
                                                @foreach (($penerimaanOptions ?? []) as $penerimaan)
                                                    <option value="{{ $penerimaan->id }}">
                                                        TBP: {{ $penerimaan->nomor_tbp }} - Jumlah : Rp {{ number_format($penerimaan->jumlah, 0, ',', '.') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Bukti Pengembalian</label>
                                            <input type="text" class="form-control" name="jenis_bukti" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Untuk</label>
                                        </div>
  
                                        <div class="col-md-6">
                                            <label class="form-label">Pihak Ketiga</label>
                                            <input type="text" class="form-control" name="pihak_ketiga" required>
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
                                <th>Tanggal</th>
                                <th>Bukti</th>
                                 @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                    <th>BLUD</th>
                                @endif
                                <th>Pihak Ketiga</th>
                                <th>Jumlah</th>
                                <th>Untuk</th>
                                <th style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pengembalians as $item)
                                <tr>
                                    <td>{{ optional($item->penerimaan)->nomor_tbp ?: '-' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($item->tanggal)->locale('id')->translatedFormat('d F Y') }}</td>
                                    <td>{{ $item->jenis_bukti }}</td>
                                    @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                        <td>{{ optional($item->unitBlud)->nama ?? '-' }}</td>
                                    @endif

                                     <td>{{ $item->pihak_ketiga }}</td>
                                    <td>Rp {{ number_format($item->jumlah) }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditPengembalian{{ $item->id }}">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form action="{{ route('pengembalian.destroy', $item->id) }}" method="POST" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="modalEditPengembalian{{ $item->id }}" tabindex="-1" aria-labelledby="modalEditPengembalianLabel{{ $item->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form action="{{ route('pengembalian.update', $item->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="modalEditPengembalianLabel{{ $item->id }}">Edit Pengembalian</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Tanggal</label>
                                                            <input type="date" class="form-control" name="tanggal" value="{{ \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d') }}" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label">Nomor</label>
                                                            <input type="text" class="form-control" name="nomor" maxlength="100" value="{{ $item->nomor }}" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label">Penyetoran</label>
                                                            <select name="penerimaan_id" class="form-select" required>
                                                                <option value="">-- Pilih Penyetoran --</option>
                                                                @foreach (($penerimaanOptions ?? []) as $penerimaan)
                                                                    <option value="{{ $penerimaan->id }}" {{ (int) $item->penerimaan_id === (int) $penerimaan->id ? 'selected' : '' }}>
                                                                        {{ $penerimaan->nomor_tbp }} - Rp {{ number_format($penerimaan->jumlah, 0, ',', '.') }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div class="col-md-3">
                                                            <label class="form-label">Bukti Pengembalian</label>
                                                            <input type="text" class="form-control" name="jenis_bukti" value="{{ $item->jenis_bukti }}" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label">Untuk</label>
                                                        </div>
  
                                                        <div class="col-md-6">
                                                            <label class="form-label">Pihak Ketiga</label>
                                                            <input type="text" class="form-control" name="pihak_ketiga" value="{{ $item->pihak_ketiga }}" required>
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
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Data pengembalian pada periode ini tidak ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-3 d-flex justify-content-center">
                        {{ $pengembalians->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
    <script src="{{ URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ URL::asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>
    <script src="{{ URL::asset('assets/js/pages/form-advanced.init.js') }}"></script>
    <script src="{{ URL::asset('assets/js/app.js') }}"></script>
    @if ($errors->any())
    <script>
        const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahPengembalian'));
        modalTambah.show();
    </script>
    @endif
@endsection
