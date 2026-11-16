@extends('layouts.vertical', ['title' => 'Pencairan GU','subTitle' => 'GU'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <table class="table table-borderless table-sm mb-4">
                        <tr>
                            <td style="width: 150px;"><strong>Unit BLUD</strong></td>
                            <td style="width: 10px;">:</td>
                            <td>{{ Auth::user()->upt }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ implode(', ', Auth::user()->roleNames()) }}</strong></td>
                            <td>:</td>
                            <td>{{ Auth::user()->nip }} - {{ Auth::user()->name }}</td>
                        </tr>
                    </table>

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

                    {{-- Tab Navigation --}}
                    <ul class="nav nav-tabs" id="tabPencairanGu" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ request('tab', 'ppd') === 'ppd' ? 'active' : '' }}" id="tab-ppd" data-bs-toggle="tab" data-bs-target="#pane-ppd" type="button" role="tab">
                                <i class="ri-file-text-line me-1"></i> PPD-GU
                                <span class="badge bg-primary ms-1">{{ $ppdGus->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ request('tab') === 'opd' ? 'active' : '' }}" id="tab-opd" data-bs-toggle="tab" data-bs-target="#pane-opd" type="button" role="tab">
                                <i class="ri-file-check-line me-1"></i> OPD-GU
                                <span class="badge bg-success ms-1">{{ $opdGus->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ request('tab') === 'spd' ? 'active' : '' }}" id="tab-spd" data-bs-toggle="tab" data-bs-target="#pane-spd" type="button" role="tab">
                                <i class="ri-file-download-line me-1"></i> SPD-GU
                                <span class="badge bg-warning text-dark ms-1">{{ $spdGus->count() }}</span>
                            </button>
                        </li>
                    </ul>

                    {{-- Tab Content --}}
                    <div class="tab-content border border-top-0 rounded-bottom p-3" id="tabPencairanGuContent">

                        {{-- ==================== TAB PPD-GU ==================== --}}
                        <div class="tab-pane fade {{ request('tab', 'ppd') === 'ppd' ? 'show active' : '' }}" id="pane-ppd" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">Permintaan Pencairan Dana GU</h6>
                                @if(Auth::user()->hasRole('Bendahara Pengeluaran'))
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPpdGu">
                                    <i class="ri-add-line"></i> Buat PPD-GU
                                </button>
                                @endif
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Nomor Surat</th>
                                            <th>No SK</th>
                                            <th>Tanggal SK</th>
                                            <th>Bendahara Pengeluaran</th>
                                            <th>No Rek</th>
                                            <th>Unit BLUD</th>
                                            <th>Jumlah</th>
                                            <th style="width: 120px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($ppdGus as $item)
                                            <tr>
                                                <td>{{ $item->nomor }}</td>
                                                <td>{{ $item->no_sk }}</td>
                                                <td>{{ $item->tanggal_sk }}</td>
                                                <td>{{ $item->nama_bendahara }}</td>
                                                <td>{{ $item->no_rek }}</td>
                                                <td>{{ optional($item->unitBlud)->nama ?: '-' }}</td>
                                                <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-nowrap">
                                                        <a href="{{ route('ppd-gu.unduh-surat', $item->id) }}" class="btn btn-sm btn-success" title="Unduh">
                                                            <i class="ri-file-download-line"></i>
                                                        </a>
                                                        @if(Auth::user()->hasRole('Pejabat Keuangan') && !$item->opdGu)
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalProsesOpd{{ $item->id }}" title="Proses ke OPD">
                                                            <i class="ri-arrow-right-circle-line"></i> OPD
                                                        </button>
                                                        @endif
                                                        @if(Auth::user()->hasRole('Bendahara Pengeluaran') && !$item->opdGu)
                                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditPpdGu{{ $item->id }}" title="Edit">
                                                            <i class="ri-edit-box-line"></i>
                                                        </button>
                                                        <form action="{{ route('ppd-gu.destroy', $item->id) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-message="Yakin ingin menghapus?">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="ri-delete-bin-2-line"></i></button>
                                                        </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="text-center">Data PPD-GU belum tersedia.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ==================== TAB OPD-GU ==================== --}}
                        <div class="tab-pane fade {{ request('tab') === 'opd' ? 'show active' : '' }}" id="pane-opd" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">Otorisasi Pencairan Dana GU</h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Nomor</th>
                                            <th>Unit BLUD</th>
                                            <th>Nomor PPD-GU</th>
                                            <th>Jumlah</th>
                                            <th>Kepada</th>
                                            <th>Potongan</th>
                                            <th>Jumlah Akhir</th>
                                            <th style="width: 120px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($opdGus as $item)
                                            <tr>
                                                <td>{{ $item->nomor }}</td>
                                                <td>{{ optional($item->unitBlud)->nama ?: '-' }}</td>
                                                <td>{{ optional($item->ppdGu)->nomor ?: '-' }}</td>
                                                <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                                <td>{{ $item->kepada }}</td>
                                                <td>Rp {{ number_format($item->potongan, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($item->jumlah_akhir, 0, ',', '.') }}</td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-nowrap">
                                                        @if(Auth::user()->hasRole('Kepala Puskesmas') && !$item->spdGu)
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalProsesSpd{{ $item->id }}" title="Proses ke SPD">
                                                            <i class="ri-arrow-right-circle-line"></i> SPD
                                                        </button>
                                                        @endif
                                                        <a href="{{ route('opd-gu.unduh-surat', $item->id) }}" class="btn btn-sm btn-success" title="Unduh">
                                                            <i class="ri-file-download-line"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9" class="text-center">Data OPD-GU belum tersedia.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ==================== TAB SPD-GU ==================== --}}
                        <div class="tab-pane fade {{ request('tab') === 'spd' ? 'show active' : '' }}" id="pane-spd" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">Surat Pencairan Dana GU</h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Nomor SPD-GU</th>
                                            <th>Nomor DBA</th>
                                            <th>OPD-GU</th>
                                            <th>Kepada</th>
                                            <th>Jumlah</th>
                                            <th>Potongan</th>
                                            <th>Jumlah Akhir</th>
                                            <th style="width: 120px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($spdGus as $item)
                                            <tr>
                                                <td>{{ $item->nomor }}</td>
                                                <td>{{ $item->nomor_dba }}</td>
                                                <td>{{ optional($item->opdGu)->nomor ?: '-' }}</td>
                                                <td>{{ $item->kepada }}</td>
                                                <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($item->potongan, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($item->jumlah_akhir, 0, ',', '.') }}</td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-nowrap">
                                                        <a href="{{ route('spd-gu.unduh-surat', $item->id) }}" class="btn btn-sm btn-success" title="Unduh">
                                                            <i class="ri-file-download-line"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="text-center">Data SPD-GU belum tersedia.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>{{-- end tab-content --}}
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL PPD-GU ==================== --}}

    {{-- Modal Tambah PPD-GU --}}
    <div class="modal fade" id="modalTambahPpdGu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('ppd-gu.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah PPD-GU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4"><label class="form-label">Nomor Surat PPD-GU</label><input type="text" name="nomor" class="form-control" maxlength="100" required></div>
                            <div class="col-md-4"><label class="form-label">Nomor SK</label><input type="text" name="no_sk" class="form-control" maxlength="100" required></div>
                            <div class="col-md-4"><label class="form-label">Tanggal SK</label><input type="date" name="tanggal_sk" class="form-control" required></div>
                        </div>
                        <hr class="text-muted">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" required></div>
                            <div class="col-md-6"><label class="form-label">No Rek Bendahara</label><input type="text" name="no_rek" class="form-control" maxlength="100" required></div>
                            
                            <div class="col-md-6"><label class="form-label">Kepada (Penerima)</label><input type="text" class="form-control" name="kepada" required></div>
                            <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp"></div>
                            
                            <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" required></div>
                            <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" required></div>
                        </div>
                        <hr class="text-muted">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Jumlah</label>
                                <input type="text" name="jumlah" class="form-control calc-jumlah" data-max="{{ $sisaUp ?? 0 }}" value="0" required>
                                <small class="text-muted d-block mt-1">Sisa UP: Rp {{ number_format($sisaUp ?? 0, 0, ',', '.') }}</small>
                                <small class="text-danger error-limit fw-bold mt-1" style="display:none; font-size: 0.85em;">Jumlah melebihi sisa UP!</small>
                            </div>
                            <div class="col-md-4"><label class="form-label">Potongan</label><input type="text" name="potongan" class="form-control calc-potongan" value="0" required></div>
                            <div class="col-md-4"><label class="form-label">Jumlah Akhir</label><input type="text" class="form-control bg-light calc-akhir" name="jumlah_akhir" value="0" readonly required></div>
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

    {{-- Modal Edit PPD-GU --}}
    @foreach ($ppdGus as $item)
        <div class="modal fade" id="modalEditPpdGu{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('ppd-gu.update', $item->id) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit PPD-GU</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><label class="form-label">Nomor Surat PPD-GU</label><input type="text" name="nomor" class="form-control" value="{{ $item->nomor }}" maxlength="100" required></div>
                                <div class="col-md-4"><label class="form-label">Nomor SK</label><input type="text" name="no_sk" class="form-control" value="{{ $item->no_sk }}" maxlength="100" required></div>
                                <div class="col-md-4"><label class="form-label">Tanggal SK</label><input type="date" name="tanggal_sk" class="form-control" value="{{ $item->tanggal_sk }}" required></div>
                            </div>
                            <hr class="text-muted">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" value="{{ $item->bank_asal }}" required></div>
                                <div class="col-md-6"><label class="form-label">No Rek Bendahara</label><input type="text" name="no_rek" class="form-control" value="{{ $item->no_rek }}" maxlength="100" required></div>
                                
                                <div class="col-md-6"><label class="form-label">Kepada (Penerima)</label><input type="text" class="form-control" name="kepada" value="{{ $item->kepada }}" required></div>
                                <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp" value="{{ $item->npwp }}"></div>
                                
                                <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" value="{{ $item->bank_tujuan }}" required></div>
                                <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" value="{{ $item->no_rek_tujuan }}" required></div>
                            </div>
                            <hr class="text-muted">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Jumlah</label>
                                    @php $editMax = ($sisaUp ?? 0) + $item->jumlah; @endphp
                                    <input type="text" name="jumlah" class="form-control calc-jumlah" data-max="{{ $editMax }}" value="{{ $item->jumlah }}" required>
                                    <small class="text-muted d-block mt-1">Sisa UP: Rp {{ number_format($editMax, 0, ',', '.') }}</small>
                                    <small class="text-danger error-limit fw-bold mt-1" style="display:none; font-size: 0.85em;">Jumlah melebihi sisa UP!</small>
                                </div>
                                <div class="col-md-4"><label class="form-label">Potongan</label><input type="text" name="potongan" class="form-control calc-potongan" value="{{ $item->potongan }}" required></div>
                                <div class="col-md-4"><label class="form-label">Jumlah Akhir</label><input type="text" class="form-control bg-light calc-akhir" name="jumlah_akhir" value="{{ $item->jumlah_akhir }}" readonly required></div>
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
    @endforeach

    {{-- ==================== MODAL OPD-GU ==================== --}}

    {{-- Modal Proses OPD-GU --}}
    @foreach ($ppdGus as $item)
        @if(!$item->opdGu)
            <div class="modal fade" id="modalProsesOpd{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('opd-gu.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="ppd_gu_id" value="{{ $item->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Proses ke OPD-GU</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nomor OPD-GU</label>
                                    <input type="text" class="form-control" name="nomor" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nomor DBA</label>
                                    <input type="text" class="form-control" name="nomor_dba" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tanggal DBA</label>
                                    <input type="date" class="form-control" name="tanggal_dba" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Proses</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach





    {{-- ==================== MODAL SPD-GU ==================== --}}

    {{-- Modal Proses SPD-GU --}}
    @foreach ($opdGus as $item)
        @if(!$item->spdGu)
            <div class="modal fade" id="modalProsesSpd{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('spd-gu.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="opd_gu_id" value="{{ $item->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Proses ke SPD-GU</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nomor SPD-GU</label>
                                    <input type="text" class="form-control" name="nomor" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Proses</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach




@endsection

@section('script')
    <script>


        // Kalkulasi dinamis: Jumlah Akhir = Jumlah - Potongan
        function hitungAkhir(form) {
            var jumlahInput = form.querySelector('.calc-jumlah');
            if (!jumlahInput) return;
            var jumlahVal = jumlahInput.value.replace(/\./g, '');
            var potonganInput = form.querySelector('.calc-potongan') ? form.querySelector('.calc-potongan').value.replace(/\./g, '') : '0';
            var jumlah = parseFloat(jumlahVal) || 0;
            var potongan = parseFloat(potonganInput) || 0;
            var akhir = Math.max(0, jumlah - potongan);
            
            var calcAkhir = form.querySelector('.calc-akhir');
            if (calcAkhir) {
                calcAkhir.value = formatRupiah(akhir);
            }

            // Validasi Sisa UP (Hanya untuk PPD-GU yang memiliki data-max)
            if (jumlahInput.hasAttribute('data-max')) {
                var maxLimit = parseFloat(jumlahInput.dataset.max) || 0;
                var btnSubmit = form.querySelector('button[type="submit"]');
                var errorLimit = form.querySelector('.error-limit');
                
                if (jumlah > maxLimit) {
                    if (errorLimit) errorLimit.style.display = 'block';
                    if (btnSubmit) btnSubmit.disabled = true;
                    jumlahInput.classList.add('is-invalid');
                } else {
                    if (errorLimit) errorLimit.style.display = 'none';
                    if (btnSubmit) btnSubmit.disabled = false;
                    jumlahInput.classList.remove('is-invalid');
                }
            }
        }

        // Pasang event listener ke semua input calc
        document.querySelectorAll('.calc-jumlah, .calc-potongan').forEach(function(input) {
            // Format saat dimuat
            input.value = formatRupiah(input.value);

            input.addEventListener('input', function() {
                this.value = formatRupiah(this.value);
                hitungAkhir(this.closest('form'));
            });
        });
        
        // Inisialisasi akhir saat load form edit
        document.querySelectorAll('form').forEach(function(form) {
            if(form.querySelector('.calc-jumlah')){
                hitungAkhir(form);
            }
        });
    </script>
@endsection
