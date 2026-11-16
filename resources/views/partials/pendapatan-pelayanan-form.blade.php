@php
    $isPerubahan = $isPerubahan ?? false;
    $editingId = (int) old('pendapatan_id', 0);
    $referensiRekening = app(\App\Support\ReferensiPendapatan::class)->all();
    $kodeTerpilih = old('kode_rek', '');
    $rekeningTerpilih = collect($referensiRekening)->firstWhere('kode_rek', $kodeTerpilih);
@endphp
@if($errors->any())
    <div class="alert alert-danger pendapatan-errors" role="alert"><ul class="mb-0">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul></div>
@endif
@if($pendapatan && $pendapatan->jenisPelayanans->contains('is_data_lama', true))
    <div class="alert alert-info">Nilai sebelumnya dipertahankan sebagai rincian awal. Sesuaikan unit pelayanan, volume, satuan, dan harga dengan rincian pelayanan sebenarnya.</div>
@endif
<form id="pendapatan-pelayanan-form" method="POST" action="{{ $isPerubahan ? route('rba-perubahan.pendapatan.update', $editingId) : ($editingId ? route('rba.pendapatan.update', $editingId) : route('rba.pendapatan.store')) }}" data-change-mode="{{ $isPerubahan ? '1' : '0' }}" data-local-editor="{{ ($localEditor ?? false) ? '1' : '0' }}" data-store-url="{{ route('rba.pendapatan.store') }}" data-update-url="{{ route($isPerubahan ? 'rba-perubahan.pendapatan.update' : 'rba.pendapatan.update', 0) }}">
    @csrf
    @include('partials.rba-tahun-input')
    <input type="hidden" name="_method" id="pendapatan-method" value="{{ $isPerubahan || $editingId ? 'PUT' : 'POST' }}">
    <input type="hidden" name="pendapatan_id" id="pendapatan-id" value="{{ $editingId ?: '' }}">
    @if($isPerubahan)
        <input type="hidden" name="kode_rek" id="kode-rek-perubahan" value="{{ $kodeTerpilih }}">
    @endif
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Rekening Rencana Pendapatan</h5></div>
        <div class="card-body row g-3">
            @if($isSuperuser)
                <div class="col-md-4">
                    <label for="nama-blud" class="form-label">Unit BLUD</label>
                    <select name="{{ $isPerubahan ? '' : 'nama_blud' }}" id="nama-blud" class="form-select" required @disabled($isPerubahan)>
                        <option value="">Pilih Unit BLUD</option>
                        @foreach($puskesmasList as $unit)
                            <option value="{{ $unit->nama }}" @selected(old('nama_blud', $pendapatan?->nama_blud ?? request('puskesmas')) === $unit->nama)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-4">
                <label for="kode-rek" class="form-label">Kode Rekening</label>
                <select id="kode-rek" name="{{ $isPerubahan ? '' : 'kode_rek' }}" class="form-select" required @disabled($isPerubahan)>
                    <option value="">Pilih Kode Rekening</option>
                    @foreach(\App\Support\ReferensiPendapatan::KLASIFIKASI as $prefix => $label)
                        @php $rekeningKelompok = array_filter($referensiRekening, fn ($rekening) => str_starts_with($rekening['kode_rek'], $prefix.'.')); @endphp
                        @if($rekeningKelompok)
                            <optgroup label="{{ $label }}">
                                @foreach($rekeningKelompok as $rekening)
                                    <option value="{{ $rekening['kode_rek'] }}" @selected($kodeTerpilih === $rekening['kode_rek'])>{{ $rekening['kode_rek'] }} — {{ $rekening['uraian'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-{{ $isSuperuser ? '4' : '8' }}">
                <label for="uraian-rekening" class="form-label">Uraian Rekening</label>
                <select id="uraian-rekening" name="uraian" class="form-select" required aria-label="Uraian rekening otomatis sesuai kode rekening">
                    <option value="{{ $rekeningTerpilih['uraian'] ?? '' }}">{{ $rekeningTerpilih['uraian'] ?? 'Otomatis sesuai kode rekening' }}</option>
                </select>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">Rincian Jenis Pelayanan</h5>
            
        </div>
        <button id="add-pelayanan" type="button" class="btn btn-outline-primary"><i class="ri-add-line" aria-hidden="true"></i> Tambah Jenis Pelayanan</button>
    </div>
    <div id="pelayanan-list">
        @foreach(old('jenis_pelayanan', $pendapatan?->jenisPelayanans->toArray() ?: [[]]) as $index => $row)
            @include('partials.pendapatan-pelayanan-row', ['index' => $index, 'row' => $row])
        @endforeach
    </div>
    <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-muted">{{ $isPerubahan ? 'Total Usulan Perubahan' : 'Total Rencana Pendapatan' }} (seluruh pelayanan)</div>
                <output id="pendapatan-total" class="fs-4 fw-bold" aria-live="polite">Rp 0</output>
                <div id="pendapatan-status" class="text-danger small" role="status"></div>
            </div>
            <div class="d-flex gap-2">
                <div class="form-actions">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-save">{{ ($localEditor ?? false) ? 'Gunakan Rincian' : ($isPerubahan ? 'Simpan Usulan' : 'Simpan Rincian') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
<template id="pelayanan-template">
    @include('partials.pendapatan-pelayanan-row', ['index' => '__INDEX__', 'row' => []])
</template>
<script type="application/json" id="referensi-pendapatan">@json($referensiRekening)</script>
