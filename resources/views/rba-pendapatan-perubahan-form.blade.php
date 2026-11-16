@extends('layouts.vertical', ['title' => 'Pengajuan Perubahan Pendapatan', 'subTitle' => 'RBA Perubahan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
@endsection

@section('content')
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="alert alert-info">
    Semua pengajuan diperiksa Kepala Puskesmas terlebih dahulu. Pergeseran dengan tiga bagian awal kode rekening berbeda diteruskan ke Dinas Kesehatan.
</div>
<div class="card rba-budget rba-change rba-change-form">
    <div class="card-body">
        <form id="pergeseran-pendapatan-form" method="POST" action="{{ route('rba-perubahan.pendapatan.pergeseran-lintas-akun') }}">
            @csrf
            @include('partials.rba-tahun-input')
            <input type="hidden" name="pergeseran_id" value="{{ old('pergeseran_id', $editing['pergeseran_id'] ?? '') }}">
            <div class="row g-4">
                <div class="col-xl-6"><div class="rba-form-panel">
                    <h5>Sumber Perubahan</h5>
                    <label for="sumber-pendapatan" class="form-label">Rekening Pendapatan Awal</label>
                    <select id="sumber-pendapatan" name="{{ $editing ? '' : 'sumber_pendapatan_id' }}" class="form-select mb-3" required @disabled($editing)>
                        <option value="">Pilih Rekening Sumber</option>
                        @foreach($records as $record)
                            <option value="{{ $record->id }}" @selected((string) old('sumber_pendapatan_id', $editing['sumber_id'] ?? '') === (string) $record->id)>
                                {{ $record->kode_rek }} — {{ $record->uraian }}{{ $isSuperuser ? ' ('.$record->nama_blud.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if($editing)<input type="hidden" name="sumber_pendapatan_id" value="{{ $editing['sumber_id'] }}">@endif
                    <p class="text-body-secondary">Total saat ini: <strong id="sumber-awal">-</strong></p>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">Rincian Setelah Pergeseran</h6>
                        <button type="button" id="edit-rincian-sumber" class="btn btn-outline-primary btn-sm" disabled><i class="ri-edit-box-line" aria-hidden="true"></i> Kelola Pelayanan</button>
                    </div>
                    <input type="hidden" name="sumber_rincian" id="sumber-rincian" value="{{ old('sumber_rincian', '') }}">
                    <div id="sumber-preview" class="table-responsive"></div>
                    <div class="mt-3">Total sumber setelah pergeseran: <strong id="sumber-total">-</strong></div>
                    <p class="small text-body-secondary mt-2">Isi Volume 1 dengan 0 jika seluruh pendapatan sumber dipindahkan.</p>
                </div></div>
                <div class="col-xl-6"><div class="rba-form-panel">
                    <h5>Tujuan Perubahan</h5>
                    <label for="tipe-tujuan" class="form-label">Rekening Tujuan</label>
                    <select id="tipe-tujuan" name="{{ $editing ? '' : 'tipe_tujuan' }}" class="form-select mb-3" @disabled($editing)>
                        <option value="existing" @selected(old('tipe_tujuan', 'existing') === 'existing')>Rekening yang sudah ada</option>
                        <option value="baru" @selected(!$editing && old('tipe_tujuan') === 'baru')>Rekening baru</option>
                    </select>
                    @if($editing)<input type="hidden" name="tipe_tujuan" value="existing">@endif
                    <div id="tujuan-existing-panel" class="mb-3">
                        <label for="tujuan-pendapatan" class="form-label">Pilih Rekening Tujuan</label>
                        <select id="tujuan-pendapatan" name="{{ $editing ? '' : 'tujuan_pendapatan_id' }}" class="form-select" data-selected="{{ old('tujuan_pendapatan_id', $editing['tujuan_id'] ?? '') }}" @disabled($editing)>
                            <option value="">Pilih Rekening Tujuan</option>
                        </select>
                        @if($editing)<input type="hidden" name="tujuan_pendapatan_id" value="{{ $editing['tujuan_id'] }}">@endif
                    </div>
                    <div id="tujuan-baru-panel" class="mb-3" hidden>
                        <label for="tujuan-kode-rek" class="form-label">Kode Rekening Baru</label>
                        <select id="tujuan-kode-rek" name="tujuan_kode_rek" class="form-select">
                            <option value="">Pilih Kode Rekening</option>
                            @foreach(app(\App\Support\ReferensiPendapatan::class)->all() as $account)
                                <option value="{{ $account['kode_rek'] }}" @selected(old('tujuan_kode_rek') === $account['kode_rek'])>{{ $account['kode_rek'] }} — {{ $account['uraian'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-body-secondary">Total saat ini: <strong id="tujuan-awal">-</strong></p>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">Rincian Setelah Pergeseran</h6>
                        <button type="button" id="edit-rincian-tujuan" class="btn btn-outline-primary btn-sm" disabled><i class="ri-edit-box-line" aria-hidden="true"></i> Kelola Pelayanan</button>
                    </div>
                    <input type="hidden" name="tujuan_rincian" id="tujuan-rincian" value="{{ old('tujuan_rincian', '') }}">
                    <div id="tujuan-preview" class="table-responsive"></div>
                    <div class="mt-3">Total tujuan setelah pergeseran: <strong id="tujuan-total">-</strong></div>
                </div></div>
            </div>
            <div class="rba-form-total mt-4 mb-3">
                <div>Total kedua rekening sebelum: <strong id="pergeseran-total-awal">-</strong></div>
                <div>Total kedua rekening setelah: <strong id="pergeseran-total-akhir">-</strong></div>
                <div>Selisih: <strong id="pergeseran-selisih">-</strong></div>
                <div class="mt-2" id="pergeseran-tahap">Pilih rekening sumber dan tujuan.</div>
            </div>
            <div class="form-actions">
                <a href="{{ route('rba-perubahan.pendapatan', ['puskesmas' => $isSuperuser ? request('puskesmas') : null, 'tahun' => request('tahun')]) }}" class="btn btn-cancel">Batal</a>
                <button type="submit" id="simpan-pergeseran" class="btn btn-save" disabled>Simpan Pengajuan</button>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="pergeseran-pendapatan-records">@json($editorRecords)</script>
@endsection

@section('modal')
<div class="modal fade" id="modalPendapatan" tabindex="-1" aria-labelledby="modalPendapatanLabel" aria-hidden="true" data-open-on-load="0">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content rba-budget rba-change rba-change-form">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPendapatanLabel">Rincian Pelayanan Setelah Pergeseran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">@include('partials.pendapatan-pelayanan-form', ['pendapatan' => null, 'isPerubahan' => true, 'localEditor' => true])</div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script src="{{ asset('js/rba-pendapatan-pelayanan.js') }}"></script>
    <script src="{{ asset('js/rba-pendapatan-pergeseran.js') }}"></script>
@endsection
