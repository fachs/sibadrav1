@extends('layouts.vertical', ['title' => 'Rekening BLUD', 'subTitle' => 'Pengaturan'])

@section('css')
<style>
    .rek-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .rek-kpi-card {
        padding: 16px 18px;
        border-radius: 12px;
        border: 1px solid var(--bs-border-color, #e2e8f0);
        background: var(--bs-body-bg, #ffffff);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 8px;
    }
    .rek-kpi-card--total {
        background: var(--bs-success-bg-subtle, #ecfdf5);
        border-color: var(--bs-success-border-subtle, #a7f3d0);
    }
    .rek-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .rek-kpi-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        font-size: 1.15rem;
        background: var(--bs-primary-bg-subtle, #eef2ff);
        color: var(--bs-primary-text-emphasis, #3730a3);
    }
    .rek-kpi-card--total .rek-kpi-icon {
        background: var(--bs-success, #198754);
        color: #ffffff;
    }
    .rek-kpi-label {
        font-size: .78rem;
        font-weight: 600;
        color: var(--bs-secondary-color, #64748b);
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .rek-kpi-card--total .rek-kpi-label {
        color: var(--bs-success-text-emphasis, #065f46);
    }
    .rek-kpi-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--bs-emphasis-color, #0f172a);
        font-variant-numeric: tabular-nums;
        margin: 0;
    }
    .rek-kpi-card--total .rek-kpi-value {
        color: var(--bs-success-text-emphasis, #065f46);
        font-size: 1.2rem;
    }
    .rek-kpi-meta {
        font-size: .76rem;
        color: var(--bs-secondary-color, #64748b);
    }
    .rek-number-chip {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-family: var(--bs-font-monospace, monospace);
        font-size: .875rem;
        font-weight: 600;
        background: var(--bs-tertiary-bg, #f8fafc);
        border: 1px solid var(--bs-border-color, #cbd5e1);
        color: var(--bs-emphasis-color, #1e293b);
        letter-spacing: .03em;
    }
    .rek-table th {
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        background: var(--bs-tertiary-bg, #f8fafc);
    }
    .rek-amount {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .rek-mutasi-pill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: .73rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
</style>
@endsection

@section('content')
@php
    $iconByJenis = [
        'Rek. Bendahara Penerimaan' => ['icon' => 'ri-arrow-down-circle-line', 'badge' => 'bg-info-subtle text-info'],
        'Rek. Bendahara Pengeluaran' => ['icon' => 'ri-arrow-up-circle-line', 'badge' => 'bg-warning-subtle text-warning'],
        'Rekening BLUD' => ['icon' => 'ri-bank-line', 'badge' => 'bg-primary-subtle text-primary'],
    ];
    $filledCount = $slots->filter(fn ($s) => $s['record'] !== null)->count();
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-checkbox-circle-line me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1"><i class="ri-error-warning-line me-1"></i> Gagal menyimpan data rekening:</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(!$selectedUnit)
    <div class="alert alert-warning" role="alert">
        <i class="ri-alert-line me-1"></i> Unit BLUD akun Anda (<strong>{{ auth()->user()->upt ?: '-' }}</strong>) belum terdaftar pada menu <strong>Data UPTD</strong>. Hubungi administrator untuk melengkapi data UPTD terlebih dahulu.
    </div>
@else
    <div class="rek-kpi-grid">
        @foreach($slots as $slot)
            @php
                $rec = $slot['record'];
                $meta = $iconByJenis[$slot['jenis']] ?? ['icon' => 'ri-bank-card-line', 'badge' => 'bg-secondary-subtle text-secondary'];
            @endphp
            <div class="rek-kpi-card">
                <div class="rek-kpi-top">
                    <span class="rek-kpi-icon"><i class="{{ $meta['icon'] }}"></i></span>
                    @if($rec)
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Belum Diisi</span>
                    @endif
                </div>
                <div>
                    <div class="rek-kpi-label">{{ $slot['jenis'] }}</div>
                    <h4 class="rek-kpi-value mt-1 {{ $slot['saldo_akhir'] < 0 ? 'text-danger' : '' }}">
                        Rp {{ number_format($slot['saldo_akhir'], 2, ',', '.') }}
                    </h4>
                </div>
                <div class="rek-kpi-meta">
                    @if($rec)
                        <strong>{{ $rec->nama_bank }}</strong> · {{ $rec->nomor_rekening }}
                    @else
                        Belum ada data rekening terdaftar
                    @endif
                </div>
            </div>
        @endforeach

        <div class="rek-kpi-card rek-kpi-card--total">
            <div class="rek-kpi-top">
                <span class="rek-kpi-icon"><i class="ri-wallet-3-line"></i></span>
                <span class="badge bg-success text-white">{{ $filledCount }} / 3 Rekening</span>
            </div>
            <div>
                <div class="rek-kpi-label">Total Saldo Rekening BLUD</div>
                <h4 class="rek-kpi-value mt-1">Rp {{ number_format($totalSaldo, 2, ',', '.') }}</h4>
            </div>
            <div class="rek-kpi-meta">
                Unit BLUD: <strong>{{ $selectedUnit->nama }}</strong>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div>
                <h5 class="card-title mb-1">Daftar Rekening BLUD — {{ $selectedUnit->nama }}</h5>
                <p class="text-muted small mb-0">
                    Memuat 3 jenis rekening utama BLUD (Bendahara Penerimaan, Bendahara Pengeluaran, dan Rekening BLUD).
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($isGlobalViewer)
                    <form method="GET" action="{{ route('rekening-blud.index') }}" class="d-flex align-items-center gap-2">
                        <label for="puskesmas-select" class="form-label mb-0 fw-semibold small text-nowrap">Pilih BLUD:</label>
                        <select name="puskesmas" id="puskesmas-select" class="form-select form-select-sm" style="min-width: 220px;" onchange="this.form.submit()">
                            @foreach($puskesmasList as $unit)
                                <option value="{{ $unit->nama }}" @selected($selectedUnit && $selectedUnit->id === $unit->id)>{{ $unit->nama }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if($canManage)
                    @if($availableJenisForCreate->isNotEmpty())
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahRekening" data-default-jenis="{{ $availableJenisForCreate->first() }}">
                            <i class="ri-add-line me-1"></i> Tambah Rekening
                        </button>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled title="Ketiga jenis rekening BLUD sudah terdaftar.">
                            <i class="ri-check-double-line me-1"></i> 3 Rekening Lengkap
                        </button>
                    @endif
                @endif
            </div>
        </div>

        <div class="card-body">
            @if(!$canManage)
                <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="ri-information-line fs-16"></i>
                    <span>Penambahan dan pengubahan data rekening BLUD hanya dapat dilakukan oleh pengguna dengan role <strong>Kepala Puskesmas</strong> atau <strong>Pejabat Keuangan</strong>.</span>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 rek-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 52px;">No</th>
                            <th style="width: 28%;">Jenis Rekening</th>
                            <th style="width: 20%;">Nama Bank</th>
                            <th style="width: 22%;">Nomor Rekening</th>
                            <th class="text-end" style="width: 18%;">Saldo (Rp)</th>
                            @if($canManage)
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $idx => $slot)
                            @php
                                $rec = $slot['record'];
                                $meta = $iconByJenis[$slot['jenis']] ?? ['icon' => 'ri-bank-card-line', 'badge' => 'bg-secondary-subtle text-secondary'];
                            @endphp
                            <tr>
                                <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $meta['badge'] }} p-2"><i class="{{ $meta['icon'] }} fs-14"></i></span>
                                        <div>
                                            <div class="fw-semibold text-body-emphasis">{{ $slot['jenis'] }}</div>
                                            @if($rec && $rec->updater)
                                                <div class="text-muted" style="font-size: .72rem;">Diperbarui oleh {{ $rec->updater->name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @if($rec)
                                    <td class="fw-medium">{{ $rec->nama_bank }}</td>
                                    <td><span class="rek-number-chip">{{ $rec->nomor_rekening }}</span></td>
                                    <td class="text-end fw-bold {{ $slot['saldo_akhir'] < 0 ? 'text-danger' : 'text-success' }} rek-amount">
                                        {{ number_format($slot['saldo_akhir'], 2, ',', '.') }}
                                    </td>
                                @else
                                    <td colspan="3" class="text-muted fst-italic">Belum ada data rekening yang ditambahkan untuk jenis ini.</td>
                                @endif

                                @if($canManage)
                                    <td class="text-center">
                                        @if($rec)
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button"
                                                    class="btn btn-sm btn-warning btn-edit-rekening"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEditRekening"
                                                    data-action="{{ route('rekening-blud.update', $rec->id) }}"
                                                    data-jenis="{{ $rec->jenis }}"
                                                    data-bank="{{ $rec->nama_bank }}"
                                                    data-nomor="{{ $rec->nomor_rekening }}"
                                                    data-saldo="Rp {{ number_format($slot['saldo_akhir'], 2, ',', '.') }}"
                                                    title="Edit Informasi Bank / Nomor Rekening">
                                                    <i class="ri-edit-box-line"></i>
                                                </button>
                                                <form method="POST" action="{{ route('rekening-blud.destroy', $rec->id) }}" class="m-0" onsubmit="return confirm('Hapus data {{ $rec->jenis }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus Rekening">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-prefill-jenis"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalTambahRekening"
                                                data-jenis="{{ $slot['jenis'] }}">
                                                <i class="ri-add-line me-1"></i>Tambah
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">TOTAL SALDO REKENING BLUD</td>
                            <td class="text-end {{ $totalSaldo < 0 ? 'text-danger' : 'text-success' }} rek-amount fs-6">{{ number_format($totalSaldo, 2, ',', '.') }}</td>
                            @if($canManage)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection

@if($canManage && $selectedUnit)
@section('modal')
    {{-- Modal Tambah Rekening --}}
    <div class="modal fade" id="modalTambahRekening" tabindex="-1" aria-labelledby="modalTambahRekeningLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('rekening-blud.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahRekeningLabel">Tambah Rekening BLUD</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create-jenis" class="form-label fw-semibold">Jenis Rekening <span class="text-danger">*</span></label>
                            <select name="jenis" id="create-jenis" class="form-select" required>
                                @foreach($jenisList as $j)
                                    @php $alreadyExists = !$availableJenisForCreate->contains($j); @endphp
                                    <option value="{{ $j }}" @disabled($alreadyExists) @selected(old('jenis') === $j)>
                                        {{ $j }}{{ $alreadyExists ? ' (Sudah Terdaftar)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create-nama-bank" class="form-label fw-semibold">Nama Bank <span class="text-danger">*</span></label>
                            <input type="text" name="nama_bank" id="create-nama-bank" class="form-control" placeholder="Contoh: Bank BJB" value="{{ old('nama_bank') }}" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="create-nomor-rekening" class="form-label fw-semibold">Nomor Rekening <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_rekening" id="create-nomor-rekening" class="form-control" placeholder="Masukkan nomor rekening" value="{{ old('nomor_rekening') }}" required maxlength="100">
                        </div>
                        <div class="mb-0">
                            <label for="create-saldo-awal" class="form-label fw-semibold">
                                Saldo Awal (Rp)
                                <span class="badge bg-light text-secondary border fw-normal ms-1">Opsional</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="saldo_awal" id="create-saldo-awal" class="form-control" step="0.01" min="0" placeholder="Kosongkan jika Rp 0" value="{{ old('saldo_awal') }}">
                            </div>
                            <div class="form-text text-muted small mt-1">
                                Saldo awal bersifat opsional dan hanya diisi saat penambahan rekening. Selanjutnya saldo otomatis bertambah/berkurang dari transaksi penerimaan dan pengeluaran.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Simpan Rekening</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit Rekening (Tanpa Input Saldo) --}}
    <div class="modal fade" id="modalEditRekening" tabindex="-1" aria-labelledby="modalEditRekeningLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="formEditRekening" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditRekeningLabel">Edit Informasi Rekening BLUD</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit-jenis" class="form-label fw-semibold">Jenis Rekening <span class="text-danger">*</span></label>
                            <select name="jenis" id="edit-jenis" class="form-select" required>
                                @foreach($jenisList as $j)
                                    <option value="{{ $j }}">{{ $j }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-nama-bank" class="form-label fw-semibold">Nama Bank <span class="text-danger">*</span></label>
                            <input type="text" name="nama_bank" id="edit-nama-bank" class="form-control" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="edit-nomor-rekening" class="form-label fw-semibold">Nomor Rekening <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_rekening" id="edit-nomor-rekening" class="form-control" required maxlength="100">
                        </div>

                        <div class="p-3 rounded-3 bg-light border">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Saldo Rekening:</span>
                                <strong class="text-success" id="edit-info-saldo">Rp 0,00</strong>
                            </div>
                            <div class="text-muted" style="font-size: .75rem;">
                                <i class="ri-lock-line me-1"></i>Saldo tidak dapat diubah secara manual melalui fitur ini karena otomatis bertambah dan berkurang dari transaksi penerimaan dan pengeluaran.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var createJenisSelect = document.getElementById('create-jenis');
        document.querySelectorAll('.btn-prefill-jenis').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var jenis = btn.getAttribute('data-jenis');
                if (createJenisSelect && jenis) {
                    createJenisSelect.value = jenis;
                }
            });
        });

        var editForm = document.getElementById('formEditRekening');
        var editJenis = document.getElementById('edit-jenis');
        var editBank = document.getElementById('edit-nama-bank');
        var editNomor = document.getElementById('edit-nomor-rekening');
        var infoSaldo = document.getElementById('edit-info-saldo');

        document.querySelectorAll('.btn-edit-rekening').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (editForm) editForm.setAttribute('action', btn.getAttribute('data-action') || '');
                if (editJenis) editJenis.value = btn.getAttribute('data-jenis') || '';
                if (editBank) editBank.value = btn.getAttribute('data-bank') || '';
                if (editNomor) editNomor.value = btn.getAttribute('data-nomor') || '';
                if (infoSaldo) infoSaldo.textContent = btn.getAttribute('data-saldo') || 'Rp 0,00';
            });
        });
    });
</script>
@endsection
@endif
