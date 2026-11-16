@extends('layouts.vertical', ['title' => 'BKU Bendahara Pengeluaran','subTitle' => 'Dashboard'])

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
            <form method="GET" action="{{ route('bku-bendahara-pengeluaran.index') }}" class="row g-2 align-items-end">
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
                    <a href="{{ route('bku-bendahara-pengeluaran.pdf', ['bulan' => $selectedBulan, 'unit_blud_id' => $selectedUnitBlud?->id]) }}" class="btn btn-success" target="_blank">
                        <i class="ri-file-pdf-2-line"></i> Unduh PDF
                    </a>
                    @endif
                </div>
            </form>
        </div>
            @unless($isReadOnlyViewer)
            <div>
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
            <div class="alert alert-info">Pilih puskesmas terlebih dahulu untuk melihat BKU Bendahara Pengeluaran.</div>
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
                        <th style="min-width: 75px;">No</th>
                        <th style="min-width: 100px;">Tanggal</th>
                        <th style="min-width: 120px;">No. Bukti Transaksi</th>
                        <th style="min-width: 150px;">Kode Rekening</th>
                        <th style="min-width: 250px;">Uraian</th>
                        <th class="text-end" style="min-width: 130px;">Penerimaan</th>
                        <th class="text-end" style="min-width: 130px;">Pengeluaran</th>
                        <th class="text-end" style="min-width: 130px;">Giro</th>
                        <th class="text-end" style="min-width: 130px;">Saldo</th>
                        <th style="min-width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $no = 1;
                        $childGroups = $bkuList->filter(fn ($row) => $row->type === 'pajak' || ($row->is_manual && $row->spd_ls_id))
                            ->groupBy(fn ($row) => $row->type === 'pajak' ? 'pajak-'.$row->parent_bku_id : 'ls-'.$row->spd_ls_id);
                        $manualParents = $bkuList->filter(fn ($row) => $row->is_manual)->keyBy('id');
                    @endphp
                    @forelse ($bkuList as $index => $bku)
                        @php
                            $childGroup = $bku->type === 'pajak' ? 'pajak-'.$bku->parent_bku_id : (($bku->is_manual && $bku->spd_ls_id) ? 'ls-'.$bku->spd_ls_id : null);
                            $ancestorGroup = $bku->type === 'pajak' && $manualParents->get($bku->parent_bku_id)?->spd_ls_id
                                ? 'ls-'.$manualParents->get($bku->parent_bku_id)->spd_ls_id : null;
                            $parentGroup = $bku->type === 'ls' ? 'ls-'.$bku->real_id : (($bku->is_manual && $bku->type === 'manual') ? 'pajak-'.$bku->id : null);
                            $childCount = $parentGroup ? ($childGroups->get($parentGroup)?->count() ?? 0) : 0;
                            $namaRekening = $uraianRekening->get($bku->kode_rekening);
                            $showDetailRow = ($bku->type === 'manual' && !$bku->spd_ls_id && filled($bku->uraian))
                                || ($bku->type === 'ls' && $childCount && $namaRekening && $bku->uraian !== $namaRekening);
                            $hasAccordion = $childCount || $showDetailRow;
                        @endphp
                        <tr @class(['table-light' => $bku->type === 'pajak', 'd-none' => $childGroup !== null]) @if($childGroup) data-bku-child="{{ $childGroup }}" @endif @if($ancestorGroup) data-bku-ancestor="{{ $ancestorGroup }}" @endif>
                            <td class="text-center text-nowrap">
                                @if($bku->type !== 'pajak' && !($bku->is_manual && $bku->spd_ls_id))
                                    {{ $no++ }}
                                @endif
                                @if($hasAccordion)
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1 py-0 px-1 bku-child-toggle" data-bku-toggle="{{ $parentGroup }}" aria-expanded="false" aria-label="Tampilkan rincian transaksi" title="Tampilkan rincian transaksi">
                                        <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </td>
                            <td>
                                @if($bku->type !== 'pajak' && !($bku->is_manual && $bku->spd_ls_id))
                                    {{ \Carbon\Carbon::parse($bku->tanggal)->format('d-m-Y') }}
                                @endif
                            </td>
                            <td>
                                @if($bku->type !== 'pajak' && !($bku->is_manual && $bku->spd_ls_id))
                                    {{ $bku->no_transaksi ?: '-' }}
                                @endif
                            </td>
                            <td>
                                @if($bku->type !== 'pajak' && !($bku->is_manual && $bku->spd_ls_id))
                                    {{ $bku->kode_rekening ?: '-' }}
                                @endif
                            </td>
                            <td>
                                @if($bku->type === 'pajak')
                                    <i class="ri-corner-down-right-line text-muted me-1" aria-hidden="true"></i>
                                @elseif(isset($bku->spd_ls_id) && $bku->spd_ls_id)
                                    <i class="ri-corner-down-right-line text-muted me-1"></i>
                                @endif
                                @if($bku->type === 'pajak' || ($bku->is_manual && $bku->spd_ls_id && !$namaRekening))
                                    {{ $bku->uraian }}
                                @elseif($bku->kode_rekening && $bku->kode_rekening !== '-')
                                    {{ $namaRekening ?: '-' }}
                                @elseif(!$bku->is_manual)
                                    {{ $bku->uraian }}
                                @endif
                            </td>
                            <td class="text-end">
                                {{ $bku->is_saldo_awal ? '-' : 'Rp ' . number_format($bku->penerimaan, 0, ',', '.') }}
                            </td>
                            <td class="text-end">
                                {{ $bku->is_saldo_awal ? '-' : 'Rp ' . number_format($bku->pengeluaran, 0, ',', '.') }}
                            </td>
                            <td class="text-end">Rp {{ number_format($bku->giro, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">Rp {{ number_format($bku->saldo, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if($isReadOnlyViewer)
                                    {{ $bku->is_saldo_awal ? 'Saldo Awal' : ($bku->is_manual ? '-' : ($bku->type === 'pajak' ? ucfirst($bku->pajak_entry_type).' Pajak' : strtoupper($bku->type))) }}
                                @elseif($bku->is_saldo_awal)
                                    <span class="badge bg-info">Saldo Awal</span>
                                @elseif($bku->is_manual)
                                    @php
                                        $isToday = \Carbon\Carbon::parse($bku->tanggal)->toDateString() === now('Asia/Jakarta')->toDateString();
                                        $editRequest = $changeRequests->get($bku->id.':edit');
                                        $deleteRequest = $changeRequests->get($bku->id.':hapus');
                                    @endphp
                                    @if($isToday || $editRequest?->status === 'approved')
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditBku{{ $bku->id }}" title="Edit" aria-label="Edit"><i class="ri-edit-box-line" aria-hidden="true"></i></button>
                                    @elseif($editRequest?->status === 'pending')
                                        <span class="badge bg-warning text-dark">Edit menunggu verifikasi</span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalPengajuanEdit{{ $bku->id }}" title="Ajukan Edit" aria-label="Ajukan Edit"><i class="ri-edit-box-line" aria-hidden="true"></i></button>
                                    @endif
                                    @if($isToday || $deleteRequest?->status === 'approved')
                                        <form action="{{ route('bku-bendahara-pengeluaran.destroy', $bku->id) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus" aria-label="Hapus"><i class="ri-delete-bin-2-line" aria-hidden="true"></i></button>
                                        </form>
                                    @elseif($deleteRequest?->status === 'pending')
                                        <span class="badge bg-warning text-dark">Hapus menunggu verifikasi</span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalPengajuanHapus{{ $bku->id }}" title="Ajukan Hapus" aria-label="Ajukan Hapus"><i class="ri-delete-bin-2-line" aria-hidden="true"></i></button>
                                    @endif
                                @else
                                    <span class="badge bg-secondary mb-1">{{ $bku->type === 'pajak' ? ucfirst($bku->pajak_entry_type).' Pajak' : '('.strtoupper($bku->type).')' }}</span>
                                @endif
                            </td>
                        </tr>
                        @if($showDetailRow)
                            <tr class="table-light d-none" data-bku-child="{{ $parentGroup }}" @if($childGroup) data-bku-ancestor="{{ $childGroup }}" @endif>
                                <td colspan="4"></td>
                                <td><i class="ri-corner-down-right-line text-muted me-1" aria-hidden="true"></i>{{ $bku->uraian }}</td>
                                <td class="text-end">Rp {{ number_format($bku->penerimaan, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($bku->pengeluaran, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($bku->giro, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($bku->saldo, 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        @endif

                        <!-- Modal Edit -->
                        @if(!$isReadOnlyViewer && $bku->is_manual && !$bku->is_saldo_awal)
                        @push('bku-edit-modals')
                        @php
                            $failedEdit = (string) old('edit_bku_id') === (string) $bku->id;
                            $editSubkegiatan = $failedEdit ? old('subkegiatan') : $bku->subkegiatan;
                            $editKodeRekening = $failedEdit ? old('kode_rekening') : $bku->kode_rekening;
                            $editPajaks = $failedEdit ? old('pajaks', []) : $bku->pajak_items;
                        @endphp
                        <div class="modal fade" id="modalEditBku{{ $bku->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form action="{{ route('bku-bendahara-pengeluaran.update', $bku->id) }}" method="POST" class="bku-transaction-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="edit_bku_id" value="{{ $bku->id }}">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Transaksi</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="edit-subkegiatan-{{ $bku->id }}">Pilih Sub Kegiatan</label>
                                                    <select name="subkegiatan" id="edit-subkegiatan-{{ $bku->id }}" class="form-select bku-subkegiatan" required>
                                                        <option value="">-- Pilih Sub Kegiatan --</option>
                                                        @if($editSubkegiatan && !$rbas->pluck('subkegiatan')->contains($editSubkegiatan))
                                                            <option value="{{ $editSubkegiatan }}" selected>{{ $editSubkegiatan }}</option>
                                                        @endif
                                                        @foreach($rbas->pluck('subkegiatan')->filter()->unique() as $subkegiatan)
                                                            <option value="{{ $subkegiatan }}" @selected($editSubkegiatan === $subkegiatan)>{{ $subkegiatan }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="edit-tanggal-{{ $bku->id }}">Pilih Tanggal</label>
                                                    <input type="date" name="tanggal" id="edit-tanggal-{{ $bku->id }}" class="form-control" required value="{{ $failedEdit ? old('tanggal') : $bku->tanggal }}">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="edit-kode-rekening-{{ $bku->id }}">Pilih Rekening</label>
                                                    <select name="kode_rekening" id="edit-kode-rekening-{{ $bku->id }}" class="form-select bku-rekening" required>
                                                        <option value="">-- Pilih Rekening --</option>
                                                        @if($editKodeRekening && !$rbas->contains(fn ($rba) => $rba->subkegiatan === $editSubkegiatan && $rba->akuns->kode_rek === $editKodeRekening))
                                                            <option value="{{ $editKodeRekening }}" data-subkegiatan="{{ $editSubkegiatan }}" selected>{{ $editKodeRekening }} - {{ $uraianRekening->get($editKodeRekening, 'Rekening lama') }}</option>
                                                        @endif
                                                        @foreach($rbas as $rba)
                                                            <option value="{{ $rba->akuns->kode_rek }}" data-subkegiatan="{{ $rba->subkegiatan }}" @selected($editKodeRekening === $rba->akuns->kode_rek && $editSubkegiatan === $rba->subkegiatan)>
                                                                {{ $rba->akuns->kode_rek }} - {{ $rba->akuns->uraian }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="edit-uraian-{{ $bku->id }}">Rincian</label>
                                                    <textarea name="uraian" id="edit-uraian-{{ $bku->id }}" class="form-control" rows="2" required>{{ $failedEdit ? old('uraian') : $bku->uraian }}</textarea>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="edit-no-transaksi-{{ $bku->id }}">No. Bukti Transaksi</label>
                                                    <input type="text" name="no_transaksi" id="edit-no-transaksi-{{ $bku->id }}" class="form-control" maxlength="255" value="{{ $failedEdit ? old('no_transaksi') : $bku->no_transaksi }}">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="edit-jumlah-{{ $bku->id }}">Jumlah (Pengeluaran)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rp</span>
                                                        <input type="text" name="jumlah" id="edit-jumlah-{{ $bku->id }}" class="form-control format-rupiah bku-jumlah" inputmode="numeric" value="{{ $failedEdit ? old('jumlah') : number_format($bku->pengeluaran, 0, ',', '.') }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Pajak</label>
                                                    <div class="pajak-list" data-next-index="{{ count($editPajaks) }}">
                                                        @foreach($editPajaks as $index => $pajak)
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
                                                                <div class="col-md-5"><label class="form-label">Jumlah Pajak</label><div class="input-group"><span class="input-group-text">Rp</span><input type="text" name="pajaks[{{ $index }}][nominal]" class="form-control format-rupiah pajak-nominal" inputmode="numeric" value="{{ $failedEdit ? ($pajak['nominal'] ?? '') : number_format($pajak['nominal'], 0, ',', '.') }}" required></div></div>
                                                                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 hapus-pajak">Hapus</button></div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <button type="button" class="btn btn-outline-primary mt-2 tambah-pajak">+ Tambah Pajak</button>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label" for="edit-setelah-pajak-{{ $bku->id }}">Jumlah Diterima Rekanan</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rp</span>
                                                        <input type="text" id="edit-setelah-pajak-{{ $bku->id }}" class="form-control bku-setelah-pajak" value="{{ number_format($bku->pengeluaran - $bku->total_pajak, 0, ',', '.') }}" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <div class="form-actions">
                                                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-save">Simpan Perubahan</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endpush
                        @endif

                    @empty
                        <tr>
                            <td colspan="10" class="text-center">Data BKU belum tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @stack('bku-edit-modals')
        @endif
    </div>
</div>

@unless($isReadOnlyViewer)
@foreach($bkuList as $bku)
    @if($bku->is_manual && !$bku->is_saldo_awal && \Carbon\Carbon::parse($bku->tanggal)->toDateString() !== now('Asia/Jakarta')->toDateString())
        @foreach(['edit' => 'Edit', 'hapus' => 'Hapus'] as $aksi => $label)
            @if(!in_array($changeRequests->get($bku->id.':'.$aksi)?->status, ['pending', 'approved'], true))
                @php
                    $verifikatorBku = \App\Models\BkuBendaharaPerubahanRequest::verifikatorUntukTanggal($bku->tanggal)
                        === \App\Models\BkuBendaharaPerubahanRequest::KEPALA_PUSKESMAS
                        ? 'Kepala Puskesmas' : 'Dinas Kesehatan';
                @endphp
                <div class="modal fade" id="modalPengajuan{{ $label }}{{ $bku->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('bku-bendahara-pengajuan.store', $bku->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="aksi" value="{{ $aksi }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Ajukan {{ $label }} Transaksi</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Transaksi tanggal {{ \Carbon\Carbon::parse($bku->tanggal)->format('d-m-Y') }} memerlukan persetujuan {{ $verifikatorBku }} sebelum dapat {{ strtolower($label) }}.</p>
                                    <label class="form-label" for="alasan-{{ $aksi }}-{{ $bku->id }}">Alasan perubahan</label>
                                    <textarea id="alasan-{{ $aksi }}-{{ $bku->id }}" name="alasan" class="form-control" minlength="5" maxlength="2000" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
@endforeach

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambahBku" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('bku-bendahara-pengeluaran.store') }}" method="POST" class="bku-transaction-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="tambah-subkegiatan">Pilih Sub Kegiatan</label>
                            <select name="subkegiatan" id="tambah-subkegiatan" class="form-select bku-subkegiatan" required>
                                <option value="">-- Pilih Sub Kegiatan --</option>
                                @foreach($rbas->pluck('subkegiatan')->filter()->unique() as $subkegiatan)
                                    <option value="{{ $subkegiatan }}" @selected(old('subkegiatan') === $subkegiatan)>{{ $subkegiatan }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="tambah-tanggal">Pilih Tanggal</label>
                            <input type="date" name="tanggal" id="tambah-tanggal" class="form-control" required value="{{ old('tanggal', \App\Support\TahunAnggaran::inputDate()) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="tambah-kode-rekening">Pilih Rekening</label>
                            <select name="kode_rekening" id="tambah-kode-rekening" class="form-select bku-rekening" required>
                                <option value="">-- Pilih Rekening --</option>
                                @foreach($rbas as $rba)
                                    <option value="{{ $rba->akuns->kode_rek }}" data-subkegiatan="{{ $rba->subkegiatan }}" data-pagu="{{ $paguRekening->get($rba->akuns->kode_rek, 0) }}" data-sisa-pagu="{{ $sisaPaguRekening->get($rba->akuns->kode_rek, 0) }}" @selected(old('kode_rekening') === $rba->akuns->kode_rek && old('subkegiatan') === $rba->subkegiatan)>
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
                            <label class="form-label" for="tambah-no-transaksi">No. Bukti Transaksi</label>
                            <input type="text" name="no_transaksi" id="tambah-no-transaksi" class="form-control" maxlength="255" value="{{ old('no_transaksi') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="tambah-jumlah">Jumlah (Pengeluaran)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" name="jumlah" id="tambah-jumlah" class="form-control format-rupiah bku-jumlah" inputmode="numeric" value="{{ old('jumlah') }}" required>
                            </div>
                            <div id="tambah-sisa-pagu" class="form-text" aria-live="polite">Pilih rekening untuk melihat sisa pagu.</div>
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
                            <label class="form-label" for="tambah-setelah-pajak">Jumlah Diterima Rekanan</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="tambah-setelah-pajak" class="form-control bku-setelah-pajak" value="0" readonly>
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

@endunless
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.bku-child-toggle').forEach(function(button) {
            button.addEventListener('click', function() {
                var group = button.dataset.bkuToggle;
                var expanded = button.getAttribute('aria-expanded') !== 'true';
                document.querySelectorAll('[data-bku-child]').forEach(function(row) {
                    if (row.dataset.bkuChild === group) row.classList.toggle('d-none', !expanded);
                    if (!expanded && row.dataset.bkuAncestor === group) row.classList.add('d-none');
                });
                if (!expanded) {
                    document.querySelectorAll('.bku-child-toggle').forEach(function(nestedButton) {
                        if (nestedButton.closest('[data-bku-child="' + group + '"]')) {
                            nestedButton.setAttribute('aria-expanded', 'false');
                            nestedButton.setAttribute('aria-label', 'Tampilkan rincian transaksi');
                            nestedButton.title = 'Tampilkan rincian transaksi';
                            nestedButton.querySelector('i').className = 'ri-arrow-down-s-line';
                        }
                    });
                }
                button.setAttribute('aria-expanded', String(expanded));
                button.setAttribute('aria-label', (expanded ? 'Sembunyikan' : 'Tampilkan') + ' rincian transaksi');
                button.title = (expanded ? 'Sembunyikan' : 'Tampilkan') + ' rincian transaksi';
                button.querySelector('i').className = expanded ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
            });
        });

        var jenisPajakOptions = @json(\App\Models\BkuBendaharaPengeluaran::JENIS_PAJAK);
        document.querySelectorAll('.bku-transaction-form').forEach(function(form) {
            var rekening = form.querySelector('.bku-rekening');
            var subkegiatan = form.querySelector('.bku-subkegiatan');
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
        });

        var hitungPajak = function(form) {
            if (!form) return;
            var jumlah = form.querySelector('.bku-jumlah');
            var setelahPajak = form.querySelector('.bku-setelah-pajak');
            if (jumlah && setelahPajak) {
                var bruto = Number(jumlah.value.replace(/\D/g, '')) || 0;
                var potongan = Array.from(form.querySelectorAll('.pajak-nominal'))
                    .reduce(function(total, input) { return total + (Number(input.value.replace(/\D/g, '')) || 0); }, 0);
                setelahPajak.value = new Intl.NumberFormat('id-ID').format(bruto - potongan);
            }
        };

        document.querySelectorAll('.pajak-list').forEach(function(list) {
            var tombolTambah = list.parentElement.querySelector('.tambah-pajak');
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
                    hitungPajak(list.closest('form'));
                }
            });
        });
        document.querySelectorAll('.bku-transaction-form').forEach(hitungPajak);

        var tambahRekening = document.getElementById('tambah-kode-rekening');
        var tambahJumlah = document.getElementById('tambah-jumlah');
        var tambahSisaPagu = document.getElementById('tambah-sisa-pagu');
        var periksaSisaPagu = function() {
            var pilihan = tambahRekening.selectedOptions[0];
            if (!pilihan || !pilihan.value) {
                tambahSisaPagu.textContent = 'Pilih rekening untuk melihat sisa pagu.';
                tambahJumlah.setCustomValidity('');
                return;
            }
            var pagu = Number(pilihan.dataset.pagu || 0);
            var sisa = Number(pilihan.dataset.sisaPagu || 0);
            tambahSisaPagu.textContent = 'Pagu: Rp ' + new Intl.NumberFormat('id-ID').format(pagu)
                + ' · Sisa yang dapat digunakan: Rp ' + new Intl.NumberFormat('id-ID').format(sisa);
            tambahJumlah.setCustomValidity(Number(tambahJumlah.value.replace(/\D/g, '')) > sisa
                ? 'Jumlah melebihi sisa pagu rekening (Rp ' + new Intl.NumberFormat('id-ID').format(sisa) + ').' : '');
        };
        if (tambahRekening && tambahJumlah && tambahSisaPagu) {
            tambahRekening.addEventListener('change', periksaSisaPagu);
            tambahJumlah.addEventListener('input', periksaSisaPagu);
            document.getElementById('tambah-subkegiatan').addEventListener('change', periksaSisaPagu);
            periksaSisaPagu();
        }

        @if($errors->any() && (old('jumlah') !== null || old('edit_bku_id')))
        var failedModal = document.getElementById(@json(old('edit_bku_id') ? 'modalEditBku'.old('edit_bku_id') : 'modalTambahBku'));
        if (failedModal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(failedModal).show();
        }
        @endif

        // Implementasi format-rupiah.js untuk input form
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('format-rupiah')) {
                if (typeof formatRupiahInput === 'function') {
                    formatRupiahInput(e.target);
                } else if (typeof formatRupiah === 'function') {
                    e.target.value = formatRupiah(e.target.value);
                }
            }
            if (e.target.classList.contains('bku-jumlah') || e.target.classList.contains('pajak-nominal')) {
                hitungPajak(e.target.closest('.bku-transaction-form'));
            }
        });
    });
</script>
@endsection
