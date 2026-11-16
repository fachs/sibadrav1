@extends('layouts.vertical', ['title' => 'Pengajuan Perubahan Belanja', 'subTitle' => 'RBA Perubahan'])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rba-perubahan.css') }}">
@endsection

@section('content')
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="alert alert-info">Semua pengajuan diperiksa Kepala Puskesmas terlebih dahulu. Pilih rekening sumber dan tujuan, lalu lengkapi rincian setelah pergeseran.</div>
<div class="card rba-budget rba-change rba-change-form rba-belanja-shift">
    <div class="card-body">
        <form id="formPergeseran" action="{{ route('rba-perubahan.belanja.pergeseran-lintas-akun') }}" method="POST" data-has-old-input="{{ session()->hasOldInput() ? '1' : '0' }}">
            @csrf
            @include('partials.rba-tahun-input')
            <div class="row g-4">
                <div class="col-xl-6">
                    <div class="rba-form-panel">
                        <h5>Sumber Perubahan</h5>
                        <label for="sumber_renbut_id" class="form-label">Rekening Belanja Awal</label>
                        <select name="sumber_renbut_id" id="sumber_renbut_id" class="form-select mb-3" required>
                            <option value="">Pilih Rekening Sumber</option>
                            @foreach($grouped->flatten() as $item)
                                <option value="{{ $item->id }}" @selected((string) old('sumber_renbut_id') === (string) $item->id)
                                    data-uraian="{{ $item->proposed_uraian }}" data-jumlah="{{ $item->proposed_jumlah }}"
                                    data-satuan="{{ $item->proposed_satuan }}" data-harga="{{ $item->proposed_harga_satuan }}" data-total="{{ $item->proposed_jumlah_harga }}"
                                    data-pagu-rekening="{{ $paguBkuPerRincian->get($item->id)['pagu'] ?? 0 }}"
                                    data-realisasi-bku="{{ $paguBkuPerRincian->get($item->id)['realisasi'] ?? 0 }}"
                                    data-sisa-pagu="{{ $paguBkuPerRincian->get($item->id)['sisa'] ?? 0 }}">
                                    {{ optional($item->akuns)->kode_rek }} &mdash; {{ $item->proposed_uraian }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-body-secondary">Total saat ini: <strong id="sumber-awal">-</strong></p>
                        <p class="text-body-secondary">Pagu rekening: <strong id="sumber-pagu-rekening">-</strong> · Realisasi BKU: <strong id="sumber-realisasi-bku">-</strong> · Sisa yang dapat digeser: <strong id="sumber-sisa-pagu">-</strong></p>
                        <details id="preview-sumber" class="rba-details d-none mb-3">
                            <summary><i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i>Rincian saat ini</summary>
                            <dl class="row mb-0 mt-2">
                                <dt class="col-5">Uraian</dt><dd class="col-7" id="prev-sumber-uraian">-</dd>
                                <dt class="col-5">Volume / Satuan</dt><dd class="col-7"><span id="prev-sumber-jumlah">-</span> <span id="prev-sumber-satuan"></span></dd>
                                <dt class="col-5">Harga satuan</dt><dd class="col-7" id="prev-sumber-harga">-</dd>
                            </dl>
                        </details>
                        <h6 class="mb-3">Rincian Setelah Pergeseran</h6>
                        <div class="mb-3">
                            <label for="sumber_uraian" class="form-label">Uraian Sumber</label>
                            <input type="text" name="sumber_uraian" id="sumber_uraian" class="form-control" value="{{ old('sumber_uraian') }}" required placeholder="Masukkan uraian belanja">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="sumber_jumlah" class="form-label">Volume Tersisa</label>
                                <input type="number" name="sumber_jumlah" id="sumber_jumlah" class="form-control" value="{{ old('sumber_jumlah') }}" required min="0" step="any">
                            </div>
                            <div class="col-md-4">
                                <label for="sumber_satuan" class="form-label">Satuan</label>
                                <input type="text" name="sumber_satuan" id="sumber_satuan" class="form-control" value="{{ old('sumber_satuan') }}" required placeholder="Unit, bulan, ...">
                            </div>
                            <div class="col-md-4">
                                <label for="sumber_harga_satuan" class="form-label">Harga Satuan (Rp)</label>
                                <input type="number" name="sumber_harga_satuan" id="sumber_harga_satuan" class="form-control" value="{{ old('sumber_harga_satuan') }}" required min="0" step="any">
                            </div>
                        </div>
                        <div class="mt-3">Total sumber setelah pergeseran: <strong id="sumber_jumlah_harga_display">-</strong></div>
                        <p class="small text-body-secondary mt-2 mb-0">Isi volume dengan 0 jika seluruh anggaran sumber dipindahkan.</p>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="rba-form-panel">
                        <h5>Tujuan Perubahan</h5>
                        <label for="tipe-tujuan" class="form-label">Rekening Tujuan</label>
                        <select id="tipe-tujuan" name="tipe_tujuan" class="form-select mb-3">
                            <option value="existing" @selected(old('tipe_tujuan', 'existing') === 'existing')>Rekening yang sudah ada</option>
                            <option value="baru" @selected(old('tipe_tujuan') === 'baru')>Objek belanja baru</option>
                        </select>
                        <div id="form-tujuan-existing" @class(['d-none' => old('tipe_tujuan') === 'baru'])>
                            <input type="hidden" name="tujuan_renbut_id" id="tujuan_renbut_id" value="{{ old('tujuan_renbut_id') }}">
                            <div class="mb-3">
                                <label for="tujuan_akun_id" class="form-label">Pilih Rekening Tujuan</label>
                                <select name="tujuan_akun_id" id="tujuan_akun_id" class="form-select" required>
                                    <option value="">Pilih Rekening Tujuan</option>
                                    @foreach($grouped->flatten() as $item)
                                        <option value="{{ optional($item->akuns)->id }}" @selected((string) old('tujuan_renbut_id') === (string) $item->id)
                                            data-renbut-id="{{ $item->id }}" data-uraian="{{ $item->proposed_uraian }}"
                                            data-jumlah="{{ $item->proposed_jumlah }}" data-satuan="{{ $item->proposed_satuan }}"
                                            data-harga="{{ $item->proposed_harga_satuan }}" data-total="{{ $item->proposed_jumlah_harga }}">
                                            {{ optional($item->akuns)->kode_rek }} &mdash; {{ $item->proposed_uraian }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-body-secondary">Total saat ini: <strong id="tujuan-awal">-</strong></p>
                            <details id="preview-existing" class="rba-details d-none mb-3">
                                <summary><i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i>Rincian saat ini</summary>
                                <dl class="row mb-0 mt-2">
                                    <dt class="col-5">Uraian</dt><dd class="col-7" id="prev-uraian">-</dd>
                                    <dt class="col-5">Volume / Satuan</dt><dd class="col-7"><span id="prev-jumlah">-</span> <span id="prev-satuan"></span></dd>
                                    <dt class="col-5">Harga satuan</dt><dd class="col-7" id="prev-harga">-</dd>
                                </dl>
                            </details>
                            <h6 class="mb-3">Rincian Setelah Pergeseran</h6>
                            <div class="mb-3">
                                <label for="tujuan_uraian" class="form-label">Uraian Tujuan</label>
                                <input type="text" name="tujuan_uraian" id="tujuan_uraian" class="form-control" value="{{ old('tujuan_uraian') }}" required placeholder="Masukkan uraian belanja">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="tujuan_jumlah" class="form-label">Volume</label>
                                    <input type="number" name="tujuan_jumlah" id="tujuan_jumlah" class="form-control" value="{{ old('tujuan_jumlah') }}" required min="0" step="any">
                                </div>
                                <div class="col-md-4">
                                    <label for="tujuan_satuan" class="form-label">Satuan</label>
                                    <input type="text" name="tujuan_satuan" id="tujuan_satuan" class="form-control" value="{{ old('tujuan_satuan') }}" required placeholder="Unit, bulan, ...">
                                </div>
                                <div class="col-md-4">
                                    <label for="tujuan_harga_satuan" class="form-label">Harga Satuan (Rp)</label>
                                    <input type="number" name="tujuan_harga_satuan" id="tujuan_harga_satuan" class="form-control" value="{{ old('tujuan_harga_satuan') }}" required min="0" step="any">
                                </div>
                            </div>
                            <div class="mt-3">Total tujuan setelah pergeseran: <strong id="tujuan_jumlah_harga_display">-</strong></div>
                            <div class="mt-3">
                                <label for="tujuan_keterangan" class="form-label">Keterangan</label>
                                <textarea name="tujuan_keterangan" id="tujuan_keterangan" class="form-control" rows="2" placeholder="Tambahkan keterangan jika perlu">{{ old('tujuan_keterangan') }}</textarea>
                            </div>
                        </div>
                        <div id="form-tujuan-baru" @class(['d-none' => old('tipe_tujuan') !== 'baru'])>
                            <div class="mb-3">
                                <label for="tujuan_akun_id_baru" class="form-label">Kode Rekening Induk</label>
                                <select name="tujuan_akun_id_baru" id="tujuan_akun_id_baru" class="form-select">
                                    <option value="">Pilih Kode Rekening Induk</option>
                                    @foreach($allAkuns as $akun)<option value="{{ $akun->id }}" @selected((string) old('tujuan_akun_id_baru') === (string) $akun->id)>{{ $akun->kode_rek }} &mdash; {{ $akun->uraian }}</option>@endforeach
                                </select>
                            </div>
                            <p class="text-body-secondary">Total saat ini: <strong>Rp 0,00</strong></p>
                            <h6 class="mb-3">Rincian Setelah Pergeseran</h6>
                            <div class="mb-3">
                                <label for="tujuan_uraian_baru" class="form-label">Uraian Objek Baru</label>
                                <input type="text" name="tujuan_uraian_baru" id="tujuan_uraian_baru" class="form-control" value="{{ old('tujuan_uraian_baru') }}" placeholder="Masukkan uraian belanja baru">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="tujuan_jumlah_baru" class="form-label">Volume</label>
                                    <input type="number" name="tujuan_jumlah_baru" id="tujuan_jumlah_baru" class="form-control" value="{{ old('tujuan_jumlah_baru') }}" min="0" step="any">
                                </div>
                                <div class="col-md-4">
                                    <label for="tujuan_satuan_baru" class="form-label">Satuan</label>
                                    <input type="text" name="tujuan_satuan_baru" id="tujuan_satuan_baru" class="form-control" value="{{ old('tujuan_satuan_baru') }}" placeholder="Unit, bulan, ...">
                                </div>
                                <div class="col-md-4">
                                    <label for="tujuan_harga_satuan_baru" class="form-label">Harga Satuan (Rp)</label>
                                    <input type="number" name="tujuan_harga_satuan_baru" id="tujuan_harga_satuan_baru" class="form-control" value="{{ old('tujuan_harga_satuan_baru') }}" min="0" step="any">
                                </div>
                            </div>
                            <div class="mt-3">Total tujuan setelah pergeseran: <strong id="tujuan_jumlah_harga_display_baru">-</strong></div>
                            <div class="mt-3">
                                <label for="tujuan_keterangan_baru" class="form-label">Keterangan</label>
                                <textarea name="tujuan_keterangan_baru" id="tujuan_keterangan_baru" class="form-control" rows="2" placeholder="Tambahkan keterangan jika perlu">{{ old('tujuan_keterangan_baru') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rba-form-total mt-4 mb-3" aria-live="polite">
                <div>Total kedua rekening sebelum: <strong id="pergeseran-total-awal">-</strong></div>
                <div>Total kedua rekening setelah: <strong id="pergeseran-total-akhir">-</strong></div>
                <div>Selisih: <strong id="pergeseran-selisih">-</strong></div>
                <div class="mt-2" id="pergeseran-tahap">Pilih rekening sumber dan tujuan.</div>
            </div>
            <div class="form-actions">
                <a href="{{ route('rba-perubahan.belanja', ['tahun' => \App\Support\TahunRba::selected(), 'puskesmas' => request('puskesmas')]) }}" class="btn btn-cancel">Batal</a>
                <button type="submit" id="simpan-pergeseran" class="btn btn-save" disabled>Simpan Pengajuan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/rba-belanja-pergeseran.js') }}"></script>
@endsection
