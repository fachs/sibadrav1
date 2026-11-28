@extends('layouts.vertical', ['title' => 'Detail Transaksi', 'subTitle' => 'Penerimaan'])

@section('content')
@php
    $isPerTrxTbp = $penerimaan->isTbpPerTransaksi();
    $allTransaksis = $penerimaan->transaksis;
    $filledTbpCount = $allTransaksis->filter(fn ($t) => trim((string) $t->nomor_tbp) !== '')->count();
    $headerTbp = $penerimaan->nomor_tbp ?: $penerimaan->nomor_terima;
@endphp

<div class="row">
    <!-- Info Penerimaan -->
    <div class="col-xl-12 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Informasi Penerimaan</h5>
                    <a href="{{ route('penerimaan.index', ['periode' => $penerimaan->periode]) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Mekanisme Nomor TBP</span>
                        @if($isPerTrxTbp)
                            <span class="fw-bold fs-6 text-primary">1 TBP per Transaksi</span>
                            <span class="badge bg-info-subtle text-info ms-1">{{ $filledTbpCount }} / {{ $allTransaksis->count() }} Terisi</span>
                        @else
                            <span class="fw-bold fs-5 text-dark">{{ $headerTbp ?: '-' }}</span>
                            <span class="badge bg-success-subtle text-success ms-1">1 TBP Tunai</span>
                        @endif
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Tanggal Terima</span>
                        <span class="fw-bold fs-5 text-dark">{{ \Carbon\Carbon::parse($penerimaan->tanggal_terima)->locale('id')->translatedFormat('d F Y') }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Periode</span>
                        <span class="fw-bold fs-5 text-dark">{{ $penerimaan->periode }} {{ $penerimaan->tahun_anggaran }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Kode Rekening</span>
                        <span class="fw-bold fs-5 text-dark">{{ $penerimaan->kode_rekening }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Cara Pembayaran</span>
                        <span class="fw-bold fs-5 text-dark">{{ $penerimaan->cara_pembayaran }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="text-muted d-block small">Total Biaya (Rekap)</span>
                        <span class="fw-bold fs-5 text-success">Rp {{ number_format($totalBiaya, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
                <div>
                    <h4 class="card-title mb-0">Daftar Transaksi</h4>
                    @if($isPerTrxTbp)
                        <small class="text-muted">Cara pembayaran <strong>{{ $penerimaan->cara_pembayaran }}</strong> menggunakan mekanisme <strong>1 Nomor TBP untuk 1 Transaksi</strong>.</small>
                    @else
                        <small class="text-muted">Seluruh transaksi tunai di bawah ini tergabung dalam Nomor TBP: <strong>{{ $headerTbp ?: '-' }}</strong>.</small>
                    @endif
                </div>
                @if(!$isDinkes)
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahManual">
                        <i class="fas fa-plus align-middle"></i> Tambah Manual
                    </button>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                        <i class="fas fa-file-excel align-middle"></i> Upload Laporan
                    </button>
                </div>
                @endif
            </div>

            <div class="card-body p-0">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0" id="datatable-transaksi">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Nomor TBP</th>
                                <th>Nomor Transaksi</th>
                                <th>Nama Penjamin</th>
                                <th>Total</th>
                                <th>Metode Pembayaran</th>
                                @if(!$isDinkes)
                                <th>Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transaksis as $index => $trx)
                            <tr>
                                <td>{{ $transaksis->firstItem() + $index }}</td>
                                <td>{{ \Carbon\Carbon::parse($trx->tanggal)->format('d/m/Y') }}</td>
                                <td>
                                    @if($isPerTrxTbp)
                                        @if(!empty($trx->nomor_tbp))
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <span class="fw-semibold text-primary">{{ $trx->nomor_tbp }}</span>
                                                @if(!$isDinkes)
                                                    <button type="button"
                                                        class="btn btn-sm btn-soft-secondary py-0 px-1 btn-isi-tbp"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalEditTbpTransaksi"
                                                        data-action="{{ route('transaksi-penerimaan.update-nomor-tbp', $trx->id) }}"
                                                        data-nomor-transaksi="{{ $trx->nomor_transaksi }}"
                                                        data-nomor-tbp="{{ $trx->nomor_tbp }}"
                                                        title="Ubah Nomor TBP">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            @if(!$isDinkes)
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-warning py-0 px-2 btn-isi-tbp"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEditTbpTransaksi"
                                                    data-action="{{ route('transaksi-penerimaan.update-nomor-tbp', $trx->id) }}"
                                                    data-nomor-transaksi="{{ $trx->nomor_transaksi }}"
                                                    data-nomor-tbp="">
                                                    <i class="ri-edit-box-line me-1"></i>Isi TBP
                                                </button>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning">Belum Diisi</span>
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-dark fw-medium">{{ $headerTbp ?: '-' }}</span>
                                    @endif
                                </td>
                                <td>{{ $trx->nomor_transaksi ?: '-' }}</td>
                                <td>{{ $trx->nama_penjamin ?: '-' }}</td>
                                <td>Rp {{ number_format($trx->total_biaya, 0, ',', '.') }}</td>
                                <td>
                                    <span class="text-dark fw-medium">{{ $trx->metode_pembayaran }}</span>
                                </td>
                                @if(!$isDinkes)
                                <td>
                                    <div class="d-flex gap-2">
                                        <form action="{{ route('transaksi-penerimaan.destroy', $trx->id) }}" method="POST" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus Transaksi">
                                                <i class="ri-delete-bin-2-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isDinkes ? 7 : 8 }}" class="text-center py-4 text-muted">Belum ada data transaksi</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 d-flex justify-content-end px-3">
                    {{ $transaksis->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Manual -->
<div class="modal fade" id="modalTambahManual" tabindex="-1" aria-labelledby="modalTambahManualLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('penerimaan.transaksi.manual', $penerimaan->uuid) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTambahManualLabel">Tambah Transaksi Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Transaksi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" value="{{ old('tanggal', $penerimaan->tanggal_terima) }}" required>
                        </div>
                        @if($isPerTrxTbp)
                        <div class="col-md-6">
                            <label class="form-label">Nomor TBP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_tbp" maxlength="100" value="{{ old('nomor_tbp') }}" placeholder="Masukkan Nomor TBP untuk transaksi ini" required>
                        </div>
                        @else
                        <div class="col-md-6">
                            <label class="form-label">Nomor TBP (Tunai Kolektif)</label>
                            <input type="text" class="form-control bg-light" value="{{ $headerTbp }}" readonly>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label">Nomor Transaksi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nomor_transaksi" value="{{ old('nomor_transaksi') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Penjamin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_penjamin" value="{{ old('nama_penjamin') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control format-rupiah trx-biaya" name="total_biaya" value="{{ old('total_biaya') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="metode_pembayaran" required>
                                <option value="Cash" @selected(old('metode_pembayaran') === 'Cash')>Cash</option>
                                <option value="Transfer" @selected(old('metode_pembayaran', $isPerTrxTbp ? 'Transfer' : '') === 'Transfer')>Transfer</option>
                                <option value="BPJS" @selected(old('metode_pembayaran') === 'BPJS')>BPJS</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-save">Simpan Transaksi</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@if($isPerTrxTbp && !$isDinkes)
<!-- Modal Isi / Edit Nomor TBP per Transaksi -->
<div class="modal fade" id="modalEditTbpTransaksi" tabindex="-1" aria-labelledby="modalEditTbpTransaksiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formEditTbpTransaksi" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditTbpTransaksiLabel">Isi / Ubah Nomor TBP Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Nomor Transaksi</label>
                        <div class="fw-semibold text-dark" id="edit-tbp-nomor-trx">-</div>
                    </div>
                    <div class="mb-0">
                        <label for="edit-tbp-input" class="form-label fw-semibold">Nomor TBP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nomor_tbp" id="edit-tbp-input" maxlength="100" placeholder="Masukkan Nomor TBP (1 TBP untuk 1 transaksi)" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Simpan Nomor TBP</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Modal Import Excel -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('penerimaan.transaksi.import', $penerimaan->uuid) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalImportExcelLabel">Upload Laporan Transaksi Kasir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Pilih File Excel / CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="file_excel" accept=".xls,.xlsx,.csv" required>
                    </div>
                    @if($isPerTrxTbp)
                        <div class="small text-muted">
                            Setelah data diimport, Anda dapat mengisi Nomor TBP pada masing-masing baris transaksi (atau menyertakan kolom <code>nomor_tbp</code> di file Excel).
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.jQuery && $.fn.DataTable && document.querySelectorAll('#datatable-transaksi tbody tr td').length > 1) {
            $('#datatable-transaksi').DataTable({
                "paging": false,
                "info": false,
                "searching": false,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
                }
            });
        }

        document.querySelectorAll('.btn-isi-tbp').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('formEditTbpTransaksi');
                var trxLabel = document.getElementById('edit-tbp-nomor-trx');
                var tbpInput = document.getElementById('edit-tbp-input');
                if (form) form.setAttribute('action', btn.getAttribute('data-action') || '');
                if (trxLabel) trxLabel.textContent = btn.getAttribute('data-nomor-transaksi') || '-';
                if (tbpInput) {
                    tbpInput.value = btn.getAttribute('data-nomor-tbp') || '';
                    setTimeout(function () { tbpInput.focus(); }, 250);
                }
            });
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                this.querySelectorAll('.trx-biaya').forEach(input => {
                    input.value = input.value.replace(/\./g, '');
                });
            });
        });

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

    if (typeof window.formatRupiah !== 'function') {
        window.formatRupiah = function(angka, prefix) {
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
        }
    }
</script>
@endsection
