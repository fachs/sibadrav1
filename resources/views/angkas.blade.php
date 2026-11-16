@extends('layouts.vertical', ['title' => 'Anggaran Kas ','subTitle' => 'Dashboard'])

@section('body')
    <body data-sidebar="dark">
@endsection

@section('content')

<div class="row">
    <div class="col-md-4 mb-1">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                    <iconify-icon icon="solar:buildings-2-broken" class="fs-32 text-primary avatar-title"></iconify-icon>
                </div>
                <div>
                    <p class="text-muted mb-1">Akumulasi Alokasi Anggaran</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalAlokasiAnggaran, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-1">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-light bg-opacity-50 rounded flex-shrink-0">
                    <iconify-icon icon="solar:wallet-money-broken" class="fs-32 text-success avatar-title"></iconify-icon>
                </div>
                <div>
                    <p class="text-muted mb-1">Akumulasi Anggaran Kas</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($totalAngkas, 0, ',', '.') }}</h4>
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
                    <p class="text-muted mb-1">Akumulasi Selisih</p>
                    <h4 class="text-dark fw-bold mb-0">Rp {{ number_format($sisaAnggaran, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Matriks Angkas</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahJenisBelanja">
                <i class="fas fa-plus"></i> Tambah Jenis Belanja
            </button>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="angkas-alert" class="alert d-none"></div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="min-width: 220px;">Jenis Belanja</th>
                        <th class="text-end" style="min-width: 150px;">Alokasi Anggaran</th>
                        <th class="text-end" style="min-width: 140px;">Total RAK</th>
                        <th class="text-end" style="min-width: 140px;">Selisih</th>
                        @foreach ($bulanList as $bulan)
                            <th class="text-end" style="min-width: 120px;">{{ $bulan }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jenisBelanjaList as $jenisBelanja)
                        @php
                            $rowTotal = array_sum($angkasMatrix[$jenisBelanja] ?? []);
                            $alokasiAnggaran = $alokasiMatrix[$jenisBelanja] ?? 0;
                            $tw1 = array_sum(array_intersect_key($angkasMatrix[$jenisBelanja] ?? [], array_flip(['Januari', 'Februari', 'Maret'])));
                            $tw2 = array_sum(array_intersect_key($angkasMatrix[$jenisBelanja] ?? [], array_flip(['April', 'Mei', 'Juni'])));
                            $tw3 = array_sum(array_intersect_key($angkasMatrix[$jenisBelanja] ?? [], array_flip(['Juli', 'Agustus', 'September'])));
                            $tw4 = array_sum(array_intersect_key($angkasMatrix[$jenisBelanja] ?? [], array_flip(['Oktober', 'November', 'Desember'])));
                        @endphp
                        <tr class="row-rak" style="cursor: pointer;"
                            data-jenis="{{ $jenisBelanja }}"
                            data-alokasi="{{ $alokasiAnggaran }}"
                            data-jan="{{ $angkasMatrix[$jenisBelanja]['Januari'] ?? 0 }}"
                            data-feb="{{ $angkasMatrix[$jenisBelanja]['Februari'] ?? 0 }}"
                            data-mar="{{ $angkasMatrix[$jenisBelanja]['Maret'] ?? 0 }}"
                            data-apr="{{ $angkasMatrix[$jenisBelanja]['April'] ?? 0 }}"
                            data-mei="{{ $angkasMatrix[$jenisBelanja]['Mei'] ?? 0 }}"
                            data-jun="{{ $angkasMatrix[$jenisBelanja]['Juni'] ?? 0 }}"
                            data-jul="{{ $angkasMatrix[$jenisBelanja]['Juli'] ?? 0 }}"
                            data-agu="{{ $angkasMatrix[$jenisBelanja]['Agustus'] ?? 0 }}"
                            data-sep="{{ $angkasMatrix[$jenisBelanja]['September'] ?? 0 }}"
                            data-okt="{{ $angkasMatrix[$jenisBelanja]['Oktober'] ?? 0 }}"
                            data-nov="{{ $angkasMatrix[$jenisBelanja]['November'] ?? 0 }}"
                            data-des="{{ $angkasMatrix[$jenisBelanja]['Desember'] ?? 0 }}">
                            <td class="fw-semibold">{{ $jenisBelanja }}</td>
                            <td class="text-end fw-semibold">
                                Rp {{ number_format($alokasiAnggaran, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold row-total">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold {{ $alokasiAnggaran - $rowTotal < 0 ? 'text-danger' : '' }}">
                                Rp {{ number_format($alokasiAnggaran - $rowTotal, 0, ',', '.') }}
                            </td>
                            @foreach ($bulanList as $bulan)
                                <td class="text-end">Rp {{ number_format($angkasMatrix[$jenisBelanja][$bulan] ?? 0, 0, ',', '.') }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($bulanList) + 4 }}" class="text-center">
                                Data jenis belanja belum tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('modal')
<div class="modal fade" id="modalTambahJenisBelanja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('angkas.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jenis Belanja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Jenis Belanja</label>
                        <select name="jenis_belanja" class="form-select" required>
                            <option value="">Pilih Jenis Belanja</option>
                            <option value="5.1.01 - Belanja Pegawai BLUD" {{ old('jenis_belanja') === '5.1.01 - Belanja Pegawai BLUD' ? 'selected' : '' }}>5.1.01 - Belanja Pegawai BLUD</option>
                            <option value="5.1.02 - Belanja Barang dan Jasa BLUD" {{ old('jenis_belanja') === '5.1.02 - Belanja Barang dan Jasa BLUD' ? 'selected' : '' }}>5.1.02 - Belanja Barang dan Jasa BLUD</option>
                            <option value="5.2.02 - Belanja Modal Peralatan dan Mesin BLUD" {{ old('jenis_belanja') === '5.2.02 - Belanja Modal Peralatan dan Mesin BLUD' ? 'selected' : '' }}>5.2.02 - Belanja Modal Peralatan dan Mesin BLUD</option>
                            <option value="5.2.03 - Belanja Modal Gedung dan Bangunan BLUD" {{ old('jenis_belanja') === '5.2.03 - Belanja Modal Gedung dan Bangunan BLUD' ? 'selected' : '' }}>5.2.03 - Belanja Modal Gedung dan Bangunan BLUD</option>
                            <option value="5.2.04 - Belanja Modal Jalan, Jaringan dan Irigasi BLUD" {{ old('jenis_belanja') === '5.2.04 - Belanja Modal Jalan, Jaringan dan Irigasi BLUD' ? 'selected' : '' }}>5.2.04 - Belanja Modal Jalan, Jaringan dan Irigasi BLUD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alokasi Anggaran</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="alokasi_anggaran" class="form-control" placeholder="0" value="{{ old('alokasi_anggaran') ? number_format(old('alokasi_anggaran'), 0, ',', '.') : 0 }}" oninput="formatRupiahInput(this)">
                        </div>
                        <small class="text-muted">Masukkan nilai total anggaran untuk jenis belanja ini.</small>
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

<style>
    .modal-rak-title {
        color: #111827;
        font-weight: 600;
        font-size: 1.25rem;
    }
    .alert-locked {
        background-color: #fff1f2;
        border-color: #ffe4e6;
        color: #e11d48;
        border-radius: 8px;
    }
    .text-dark-blue {
        color: #0a194f !important;
    }
    .card-tw {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: none;
    }
    .input-rak-group .input-group-text {
        background-color: #ffffff;
        border-right: none;
        color: #9ca3af;
    }
    .input-rak-group .form-control {
        border-left: none;
        padding-left: 0;
    }
    .input-rak-group .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }
    .input-rak-group:focus-within {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        border-radius: 0.375rem;
    }
    .input-rak-group:focus-within .input-group-text, 
    .input-rak-group:focus-within .form-control {
        border-color: #86b7fe;
    }
</style>

<!-- Modal Detail RAK -->
<div class="modal fade" id="modalDetailRak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form id="formDetailRak">
                @csrf
                <input type="hidden" name="jenis_belanja" id="rakJenisBelanja">
                <input type="hidden" name="alokasi_anggaran" id="inputRakAlokasi">
                
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                    <h5 class="modal-title modal-rak-title">Detail Rencana Anggaran Kas (RAK)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body bg-white px-4 px-md-4 pt-4 pb-4">
                    
                    <div id="rakAlert" class="alert d-none mb-4"></div>

                    <!-- Judul Belanja -->
                    <div class="d-flex align-items-center mb-3 ms-2">
                        <i class="ri-file-list-3-line text-primary fs-3 me-3"></i>
                        <h5 class="mb-0 text-dark" style="font-weight: 500; font-size: 1rem;" id="rakJudulBelanja"></h5>
                    </div>

                    <!-- Top Stats Grid -->
                    <div class="row mb-5 g-4">
                        <div class="col-6">
                            <div class="ms-2">
                                <div class="text-muted mb-1" style="font-size: 0.9rem;"><i class="ri-instance-line me-2"></i>Total Nilai Anggaran</div>
                                <h5 class="mb-0 fw-bold text-dark-blue text-break" id="lblTotalAlokasi">Rp0,00</h5>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted mb-1" style="font-size: 0.9rem;"><i class="ri-bank-card-line me-2"></i>Total RAK</div>
                            <h5 class="mb-0 fw-bold text-dark-blue text-break" id="lblTotalRak">Rp0,00</h5>
                        </div>
                        <div class="col-6">
                            <div class="ms-2">
                                <div class="text-muted mb-1" style="font-size: 0.9rem;"><i class="ri-increase-decrease-line me-2"></i>Total Selisih</div>
                                <h5 class="mb-0 fw-bold text-dark-blue text-break" id="lblTotalSelisih">Rp0,00</h5>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted mb-1" style="font-size: 0.9rem;"><i class="ri-stack-line me-2"></i>Total Realisasi</div>
                            <h5 class="mb-0 fw-bold text-dark-blue text-break">Rp0,00</h5>
                        </div>
                    </div>

                    <div class="row g-2">
                        <!-- TW 1 -->
                        <div class="col-md-6">
                            <div class="card card-tw h-100">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-3" style="font-weight: 500;">Triwulan I</h6>
                                    
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">RAK</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;" id="lblTw1Rak">Rp0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">Realisasi</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">Rp0,00</span>
                                    </div>
                                    
                                    <hr style="border-color: #f3f4f6; opacity: 1;" class="mb-3">
                                    
                                    @foreach(['Januari', 'Februari', 'Maret'] as $bln)
                                    <div class="mb-2">
                                        <label class="form-label text-muted mb-1" style="font-weight: 500; font-size: 0.9rem;">{{ $bln }}</label>
                                        <div class="input-group input-rak-group">
                                            <span class="input-group-text"><i class="ri-cash-line"></i></span>
                                            <input type="text" class="form-control input-rak" name="bulan[{{ $bln }}]" id="rak_{{ $bln }}" data-tw="1" value="0">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <!-- TW 2 -->
                        <div class="col-md-6">
                            <div class="card card-tw h-100">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-3" style="font-weight: 500;">Triwulan II</h6>
                                    
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">RAK</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;" id="lblTw2Rak">Rp0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">Realisasi</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">Rp0,00</span>
                                    </div>
                                    
                                    <hr style="border-color: #f3f4f6; opacity: 1;" class="mb-3">
                                    
                                    @foreach(['April', 'Mei', 'Juni'] as $bln)
                                    <div class="mb-2">
                                        <label class="form-label text-muted mb-1" style="font-weight: 500; font-size: 0.9rem;">{{ $bln }}</label>
                                        <div class="input-group input-rak-group">
                                            <span class="input-group-text"><i class="ri-cash-line"></i></span>
                                            <input type="text" class="form-control input-rak" name="bulan[{{ $bln }}]" id="rak_{{ $bln }}" data-tw="2" value="0">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <!-- TW 3 -->
                        <div class="col-md-6">
                            <div class="card card-tw h-100">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-3" style="font-weight: 500;">Triwulan III</h6>
                                    
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">RAK</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;" id="lblTw3Rak">Rp0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">Realisasi</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">Rp0,00</span>
                                    </div>
                                    
                                    <hr style="border-color: #f3f4f6; opacity: 1;" class="mb-3">
                                    
                                    @foreach(['Juli', 'Agustus', 'September'] as $bln)
                                    <div class="mb-2">
                                        <label class="form-label text-muted mb-1" style="font-weight: 500; font-size: 0.9rem;">{{ $bln }}</label>
                                        <div class="input-group input-rak-group">
                                            <span class="input-group-text"><i class="ri-cash-line"></i></span>
                                            <input type="text" class="form-control input-rak" name="bulan[{{ $bln }}]" id="rak_{{ $bln }}" data-tw="3" value="0">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <!-- TW 4 -->
                        <div class="col-md-6">
                            <div class="card card-tw h-100">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-3" style="font-weight: 500;">Triwulan IV</h6>
                                    
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">RAK</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;" id="lblTw4Rak">Rp0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">Realisasi</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem;">Rp0,00</span>
                                    </div>
                                    
                                    <hr style="border-color: #f3f4f6; opacity: 1;" class="mb-3">
                                    
                                    @foreach(['Oktober', 'November', 'Desember'] as $bln)
                                    <div class="mb-2">
                                        <label class="form-label text-muted mb-1" style="font-weight: 500; font-size: 0.9rem;">{{ $bln }}</label>
                                        <div class="input-group input-rak-group">
                                            <span class="input-group-text"><i class="ri-cash-line"></i></span>
                                            <input type="text" class="form-control input-rak" name="bulan[{{ $bln }}]" id="rak_{{ $bln }}" data-tw="4" value="0">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 px-md-5 pb-4 pt-0 d-flex gap-2">
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-save" id="btnSimpanRak">Simpan RAK</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('script')
    <script src="{{ URL::asset('assets/js/app.js') }}"></script>
    <script>


        function formatRupiahModal(value) {
            const numberValue = Number(value) || 0;
            return 'Rp' + numberValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }





        document.addEventListener('DOMContentLoaded', function() {
            let currentAlokasi = 0;

            // Buka modal saat row diklik
            document.addEventListener('click', function(e) {
                let btn = e.target.closest('.row-rak');
                if (btn) {
                    const jenis = btn.getAttribute('data-jenis');
                    currentAlokasi = Number(btn.getAttribute('data-alokasi')) || 0;

                    document.getElementById('rakJenisBelanja').value = jenis;
                    document.getElementById('rakJudulBelanja').textContent = jenis;

                    const alokasiInput = document.getElementById('inputRakAlokasi');
                    alokasiInput.value = currentAlokasi; // set hidden form value
                    document.getElementById('lblTotalAlokasi').textContent = formatRupiahModal(currentAlokasi);

                    const months = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'];
                    const blnNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    
                    months.forEach(function(m, idx) {
                        let val = Number(btn.getAttribute('data-' + m)) || 0;
                        let input = document.getElementById('rak_' + blnNames[idx]);
                        if (input) input.value = val === 0 ? '0' : val.toLocaleString('id-ID');
                    });

                    calculateRak();

                    const alertEl = document.getElementById('rakAlert');
                    alertEl.classList.add('d-none');
                    alertEl.classList.remove('alert-danger', 'alert-success');
                    
                    const btnSimpan = document.getElementById('btnSimpanRak');
                    btnSimpan.disabled = false;
                    btnSimpan.textContent = 'Simpan RAK';

                    const modal = new bootstrap.Modal(document.getElementById('modalDetailRak'));
                    modal.show();
                }
            });

            // Kalkulasi otomatis (alokasi_anggaran is now readonly)
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('input-rak')) {
                    formatRupiahInput(e.target);
                    calculateRak();
                }
            });

            function calculateRak() {
                let total = 0;
                let twTotals = { 1: 0, 2: 0, 3: 0, 4: 0 };

                document.querySelectorAll('.input-rak').forEach(function(input) {
                    let val = parseRupiah(input.value);
                    let tw = input.getAttribute('data-tw');
                    if (twTotals[tw] !== undefined) {
                        twTotals[tw] += val;
                    }
                    total += val;
                });

                document.getElementById('lblTw1Rak').textContent = formatRupiahModal(twTotals[1]);
                document.getElementById('lblTw2Rak').textContent = formatRupiahModal(twTotals[2]);
                document.getElementById('lblTw3Rak').textContent = formatRupiahModal(twTotals[3]);
                document.getElementById('lblTw4Rak').textContent = formatRupiahModal(twTotals[4]);

                document.getElementById('lblTotalRak').textContent = formatRupiahModal(total);
                const sisa = currentAlokasi - total;
                
                const lblSisa = document.getElementById('lblTotalSelisih');
                lblSisa.textContent = formatRupiahModal(sisa);

                const btnSimpan = document.getElementById('btnSimpanRak');

                if (currentAlokasi > 0 && total > currentAlokasi) {
                    lblSisa.classList.remove('text-dark-blue');
                    lblSisa.classList.add('text-danger');
                    btnSimpan.disabled = true;

                    const alertEl = document.getElementById('rakAlert');
                    alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>RAK melebihi Alokasi Anggaran!';
                    alertEl.className = 'alert alert-danger mb-4';
                } else {
                    lblSisa.classList.remove('text-danger');
                    lblSisa.classList.add('text-dark-blue');
                    btnSimpan.disabled = false;

                    const alertEl = document.getElementById('rakAlert');
                    alertEl.className = 'alert d-none mb-4';
                }
            }

            // Submit form modal
            const formRak = document.getElementById('formDetailRak');
            if (formRak) {
                formRak.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('btnSimpanRak');
                    btn.disabled = true;
                    btn.textContent = 'Menyimpan...';

                    const formData = new FormData(this);
                    const formattedData = new URLSearchParams();
                    
                    for (let [name, value] of formData.entries()) {
                        if (name.startsWith('bulan') || name === 'alokasi_anggaran') {
                            formattedData.append(name, parseRupiah(value));
                        } else {
                            formattedData.append(name, value);
                        }
                    }

                    fetch('{{ route('angkas.update-modal') }}', {
                        method: 'POST',
                        body: formattedData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => {
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            return response.json().then(err => { throw err; });
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.textContent = 'Simpan RAK';
                        let msg = 'Gagal menyimpan.';
                        if (err && err.errors) {
                            msg = Object.values(err.errors).flat().join(' ');
                        }
                        const alertEl = document.getElementById('rakAlert');
                        alertEl.classList.remove('d-none', 'alert-success');
                        alertEl.classList.add('alert-danger');
                        alertEl.textContent = msg;
                    });
                });
            }
        });
    </script>


    @if ($errors->any())
        <script>
            const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahJenisBelanja'));
            modalTambah.show();
        </script>
    @endif
@endsection
