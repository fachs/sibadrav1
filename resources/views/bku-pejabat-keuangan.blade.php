@extends('layouts.vertical', ['title' => 'BKU Pejabat Keuangan','subTitle' => 'Dashboard'])

@section('content')

@if(!$isDinasViewer || $selectedUnitBlud)
<div class="row">
    <div class="col-md-4 mb-1">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                    <iconify-icon icon="solar:wallet-money-broken" class="fs-32 text-success avatar-title"></iconify-icon>
                </div>
                <div>
                    <p class="text-muted mb-1">Total Penerimaan</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalPenerimaan, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-1">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                    <iconify-icon icon="solar:calculator-minimalistic-broken" class="fs-32 text-warning avatar-title"></iconify-icon>
                </div>
                <div>
                    <p class="text-muted mb-1">Total Pengeluaran</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-1">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                    <iconify-icon icon="solar:buildings-2-broken" class="fs-32 text-primary avatar-title"></iconify-icon>
                </div>
                <div>
                    <p class="text-muted mb-1">Saldo Akhir</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
            <form method="GET" action="{{ route('bku-pejabat-keuangan.index') }}" class="row g-2 align-items-end">
                @if($isDinasViewer)
                <div class="col-md-5">
                    <label class="form-label">Puskesmas</label>
                    <select name="unit_blud_id" class="form-select" required>
                        <option value="">Pilih Puskesmas</option>
                        @foreach($puskesmasList as $puskesmas)
                            <option value="{{ $puskesmas->id }}" @selected($selectedUnitBlud?->id === $puskesmas->id)>{{ $puskesmas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-4">
                    
                    <select name="bulan" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($bulanList as $bulan)
                            <option value="{{ $bulan }}" {{ $selectedBulan === $bulan ? 'selected' : '' }}>{{ $bulan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-warning">Terapkan</button>
                    @if(!$isDinasViewer || $selectedUnitBlud)
                    <a href="{{ route('bku-pejabat-keuangan.pdf', ['bulan' => $selectedBulan, 'unit_blud_id' => $selectedUnitBlud?->id]) }}" class="btn btn-success" target="_blank">
                        <i class="ri-file-pdf-2-line"></i> Unduh PDF
                    </a>
                    @endif
                </div>
            </form>
        </div>
            @unless($isReadOnlyViewer)
            <div>
                <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#modalSaldoAwal">
                    <i class="ri-wallet-3-line"></i> Set Saldo Awal
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahBku">
                    <i class="ri-add-line"></i> Tambah Transaksi
                </button>
            </div>
            @endunless
            @if(Auth::user()->hasRole('Kepala Puskesmas') && !$isDinasViewer)
            <a href="{{ route('bku-bendahara-pengajuan.index') }}" class="btn btn-outline-primary">
                Pengajuan Perubahan BKU @if($pendingApprovalCount)<span class="badge bg-danger">{{ $pendingApprovalCount }}</span>@endif
            </a>
            @endif
        </div>

        @if($isDinasViewer && !$selectedUnitBlud)
            <div class="alert alert-info">Pilih puskesmas terlebih dahulu untuk melihat BKU Pejabat Keuangan.</div>
        @endif

        

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(!$isDinasViewer || $selectedUnitBlud)
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle table-sm">
                <thead class="table-dark text-center">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="min-width: 100px;">Tanggal</th>
                        <th style="min-width: 120px;">No Transaksi</th>
                        <th style="min-width: 150px;">Kode Rekening</th>
                        <th style="min-width: 250px;">Uraian</th>
                        <th class="text-end" style="min-width: 130px;">Penerimaan</th>
                        <th class="text-end" style="min-width: 130px;">Pengeluaran</th>
                        <th class="text-end" style="min-width: 130px;">Saldo</th>
                        <th class="text-end" style="min-width: 130px;">Giro</th>
                        <th style="min-width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bkuList as $index => $bku)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($bku->tanggal)->format('d-m-Y') }}</td>
                            <td>{{ $bku->no_transaksi ?: '-' }}</td>
                            <td>{{ $bku->kode_rekening ?: '-' }}</td>
                            <td>{{ $bku->uraian }}</td>
                            <td class="text-end">
                                {{ $bku->is_saldo_awal ? '-' : 'Rp ' . number_format($bku->penerimaan, 0, ',', '.') }}
                            </td>
                            <td class="text-end">
                                {{ $bku->is_saldo_awal ? '-' : 'Rp ' . number_format($bku->pengeluaran, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-bold">Rp {{ number_format($bku->saldo, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($bku->giro, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if($bku->is_spd)
                                    <span class="badge bg-warning text-dark">SPD {{ $bku->jenis_spd }}</span>
                                @elseif($bku->is_penyetoran)
                                    <span class="badge bg-success">Setoran Penerimaan</span>
                                @elseif($bku->is_saldo_awal)
                                    <span class="badge bg-info">Saldo Awal</span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="10" class="text-center">Data BKU belum tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@unless($isReadOnlyViewer)
<!-- Modal Tambah -->
<div class="modal fade" id="modalTambahBku" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('bku-pejabat-keuangan.store') }}" method="POST" class="bku-transaction-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="pk-subkegiatan">Pilih Sub Kegiatan</label>
                            <select name="subkegiatan" id="pk-subkegiatan" class="form-select bku-subkegiatan" required>
                                <option value="">-- Pilih Sub Kegiatan --</option>
                                @foreach($rbas->pluck('subkegiatan')->filter()->unique() as $subkegiatan)
                                    <option value="{{ $subkegiatan }}" @selected(old('subkegiatan') === $subkegiatan)>{{ $subkegiatan }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="pk-tanggal">Pilih Tanggal</label>
                            <input type="date" name="tanggal" id="pk-tanggal" class="form-control" required value="{{ old('tanggal', \App\Support\TahunAnggaran::inputDate()) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="pk-kode-rekening">Pilih Rekening</label>
                            <select name="kode_rekening" id="pk-kode-rekening" class="form-select bku-rekening" required>
                                <option value="">-- Pilih Rekening --</option>
                                @foreach($rbas as $rba)
                                    <option value="{{ optional($rba->akuns)->kode_rek }}" data-subkegiatan="{{ $rba->subkegiatan }}" data-pagu="{{ $paguRekening->get(optional($rba->akuns)->kode_rek, 0) }}" data-sisa-pagu="{{ $sisaPaguRekening->get(optional($rba->akuns)->kode_rek, 0) }}" @selected(old('kode_rekening') === optional($rba->akuns)->kode_rek && old('subkegiatan') === $rba->subkegiatan)>
                                        {{ optional($rba->akuns)->kode_rek }} - {{ optional($rba->akuns)->uraian }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Rincian</label>
                            <textarea name="uraian" class="form-control" rows="2" required>{{ old('uraian') }}</textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="pk-no-transaksi">No. Bukti Transaksi</label>
                            <input type="text" name="no_transaksi" id="pk-no-transaksi" class="form-control" maxlength="255" value="{{ old('no_transaksi') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="pk-jumlah">Jumlah (Pengeluaran)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" name="jumlah" id="pk-jumlah" class="form-control format-rupiah bku-jumlah" inputmode="numeric" value="{{ old('jumlah') }}" required>
                            </div>
                            <div id="pk-sisa-pagu" class="form-text" aria-live="polite">Pilih rekening untuk melihat sisa pagu.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Pajak</label>
                            <div class="pajak-list" data-next-index="{{ count(old('pajaks', [])) }}">
                                @foreach(old('pajaks', []) as $index => $pajak)
                                    <div class="row g-2 align-items-end mb-2 pajak-row">
                                        <div class="col-md-5">
                                            <label class="form-label">Jenis Pajak</label>
                                            <select name="pajaks[{{ $index }}][jenis]" class="form-select" required>
                                                <option value="">-- Pilih Jenis Pajak --</option>
                                                @foreach(\App\Models\BkuBendaharaPengeluaran::JENIS_PAJAK as $jenis)
                                                    <option value="{{ $jenis }}" @selected(($pajak['jenis'] ?? '') === $jenis)>{{ $jenis }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Jumlah Pajak</label>
                                            <div class="input-group"><span class="input-group-text">Rp</span><input type="text" name="pajaks[{{ $index }}][nominal]" class="form-control format-rupiah pajak-nominal" inputmode="numeric" value="{{ $pajak['nominal'] ?? '' }}" required></div>
                                        </div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 hapus-pajak">Hapus</button></div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-outline-primary mt-2 tambah-pajak">+ Tambah Pajak</button>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="pk-setelah-pajak">Jumlah Diterima Rekanan</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="pk-setelah-pajak" class="form-control bku-setelah-pajak" value="0" readonly>
                            </div>
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

<!-- Modal Saldo Awal -->
<div class="modal fade" id="modalSaldoAwal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('bku-pejabat-keuangan.set-saldo-awal') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Set Saldo Awal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="{{ $bkuSaldoAwal ? $bkuSaldoAwal->tanggal : \App\Support\TahunAnggaran::inputDate() }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Saldo Awal</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="saldo_awal" class="form-control format-rupiah" required value="{{ $bkuSaldoAwal ? number_format($bkuSaldoAwal->saldo, 0, ',', '.') : 0 }}">
                        </div>
                        <small class="text-muted">Masukkan nilai nominal saldo awal Anda.</small>
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

@endunless
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var jenisPajakOptions = @json(\App\Models\BkuBendaharaPengeluaran::JENIS_PAJAK);
        document.querySelectorAll('.bku-transaction-form').forEach(function(form) {
            var rekening = form.querySelector('.bku-rekening');
            var subkegiatan = form.querySelector('.bku-subkegiatan');
            if (rekening && subkegiatan) {
                var filterRekening = function() {
                    Array.from(rekening.options).forEach(function(option) {
                        if (!option.value) return;
                        var sesuai = option.dataset.subkegiatan === subkegiatan.value;
                        option.hidden = !sesuai;
                        option.disabled = !sesuai;
                    });
                    if (rekening.selectedOptions[0]?.disabled) rekening.value = '';
                };
                subkegiatan.addEventListener('change', function() {
                    rekening.value = '';
                    filterRekening();
                });
                filterRekening();
            }

            var jumlah = form.querySelector('.bku-jumlah');
            var setelahPajak = form.querySelector('.bku-setelah-pajak');
            var hitungPajak = function() {
                if (!jumlah || !setelahPajak) return;
                var bruto = Number(jumlah.value.replace(/\D/g, '')) || 0;
                var potongan = Array.from(form.querySelectorAll('.pajak-nominal'))
                    .reduce(function(total, input) { return total + (Number(input.value.replace(/\D/g, '')) || 0); }, 0);
                setelahPajak.value = new Intl.NumberFormat('id-ID').format(bruto - potongan);
            };

            var list = form.querySelector('.pajak-list');
            var tombolTambah = form.querySelector('.tambah-pajak');
            if (list && tombolTambah) {
                tombolTambah.addEventListener('click', function() {
                    var index = Number(list.dataset.nextIndex || 0);
                    while (list.querySelector('[name="pajaks[' + index + '][nominal]"]')) index++;
                    list.dataset.nextIndex = index + 1;
                    var row = document.createElement('div');
                    row.className = 'row g-2 align-items-end mb-2 pajak-row';
                    row.innerHTML = '<div class="col-md-5"><label class="form-label">Jenis Pajak</label><select name="pajaks[' + index + '][jenis]" class="form-select" required><option value="">-- Pilih Jenis Pajak --</option></select></div>' +
                        '<div class="col-md-5"><label class="form-label">Jumlah Pajak</label><div class="input-group"><span class="input-group-text">Rp</span><input type="text" name="pajaks[' + index + '][nominal]" class="form-control format-rupiah pajak-nominal" inputmode="numeric" required></div></div>' +
                        '<div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 hapus-pajak">Hapus</button></div>';
                    var selectJenis = row.querySelector('select');
                    jenisPajakOptions.forEach(function(jenis) { selectJenis.add(new Option(jenis, jenis)); });
                    list.appendChild(row);
                    row.querySelector('.pajak-nominal').focus();
                });
                list.addEventListener('click', function(event) {
                    if (event.target.closest('.hapus-pajak')) {
                        event.target.closest('.pajak-row').remove();
                        hitungPajak();
                    }
                });
            }
            hitungPajak();

            var sisaPagu = form.querySelector('#pk-sisa-pagu');
            var periksaSisaPagu = function() {
                if (!rekening || !jumlah || !sisaPagu) return;
                var pilihan = rekening.selectedOptions[0];
                if (!pilihan || !pilihan.value) {
                    sisaPagu.textContent = 'Pilih rekening untuk melihat sisa pagu.';
                    jumlah.setCustomValidity('');
                    return;
                }
                var pagu = Number(pilihan.dataset.pagu || 0);
                var sisa = Number(pilihan.dataset.sisaPagu || 0);
                sisaPagu.textContent = 'Pagu: Rp ' + new Intl.NumberFormat('id-ID').format(pagu) +
                    ' · Sisa yang dapat digunakan: Rp ' + new Intl.NumberFormat('id-ID').format(sisa);
                jumlah.setCustomValidity(Number(jumlah.value.replace(/\D/g, '')) > sisa
                    ? 'Jumlah melebihi sisa pagu rekening (Rp ' + new Intl.NumberFormat('id-ID').format(sisa) + ').' : '');
            };
            if (rekening && jumlah && sisaPagu) {
                rekening.addEventListener('change', periksaSisaPagu);
                jumlah.addEventListener('input', periksaSisaPagu);
                periksaSisaPagu();
            }
        });

        @if($errors->any() && old('jumlah') !== null)
        var failedModal = document.getElementById('modalTambahBku');
        if (failedModal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(failedModal).show();
        }
        @endif

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('format-rupiah')) {
                if (typeof formatRupiahInput === 'function') formatRupiahInput(e.target);
                else if (typeof formatRupiah === 'function') e.target.value = formatRupiah(e.target.value);
            }
            if (e.target.classList.contains('bku-jumlah') || e.target.classList.contains('pajak-nominal')) {
                var form = e.target.closest('.bku-transaction-form');
                if (form) {
                    var jumlah = form.querySelector('.bku-jumlah');
                    var setelahPajak = form.querySelector('.bku-setelah-pajak');
                    var bruto = Number(jumlah?.value.replace(/\D/g, '')) || 0;
                    var potongan = Array.from(form.querySelectorAll('.pajak-nominal'))
                        .reduce(function(total, input) { return total + (Number(input.value.replace(/\D/g, '')) || 0); }, 0);
                    if (setelahPajak) setelahPajak.value = new Intl.NumberFormat('id-ID').format(bruto - potongan);
                }
            }
        });
    });
</script>
@endsection
