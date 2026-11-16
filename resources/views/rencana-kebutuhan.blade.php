@extends('layouts.vertical', ['title' => 'Rincian Rencana Kebutuhan','subTitle' => 'Rencana Kebutuhan'])

@section('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap-editable/css/bootstrap-editable.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rencana-kebutuhan.css') }}">
@endsection

@section('content')
    

    {{-- Modal: ditempatkan di luar row agar tidak mempengaruhi layout --}}
    <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header py-3 px-4">
                                <h5 class="modal-title" id="myModalLabel">Buat Rencana Kebutuhan</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form method="POST" action="{{ route('rencana-kebutuhan.store') }}" id="form-create-renbut"
                                    class="needs-validation" novalidate>
                                    @csrf

                                    <div class="row">


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nama BLUD</label>
                                                <input class="form-control" type="text" value="{{ Auth::user()->upt }}"
                                                    disabled>
                                                <input type="hidden" name="nama_blud" value="{{ Auth::user()->upt }}">
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tahun Anggaran</label>
                                                <input class="form-control" type="text" value="{{ \App\Support\TahunRba::selected() }}" disabled>
                                                <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunRba::selected() }}">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Unit</label>
                                                @php
                                                    $isLabkes = trim((string) auth()->user()->upt) === 'Labkes Kota Sukabumi';
                                                    $selectedNamaUnit = old('nama_unit');
                                                @endphp
                                                
                                                <select
                                                    class="form-control @error('nama_unit', 'createRenbut') is-invalid @enderror"
                                                    name="nama_unit" required>
                                                    <option value="">-- Pilih Unit --</option>
                                                
                                                    @if ($isLabkes)
                                                        <option value="Manajemen" {{ $selectedNamaUnit === 'Manajemen' ? 'selected' : '' }}>Manajemen</option>
                                                        <option value="Tim Kerja Mutu" {{ $selectedNamaUnit === 'Tim Kerja Mutu' ? 'selected' : '' }}>Tim Kerja Mutu</option>
                                                        <option value="Tim Program Layanan Laboratorium Klinik" {{ $selectedNamaUnit === 'Tim Program Layanan Laboratorium Klinik' ? 'selected' : '' }}>Tim Program Layanan Laboratorium Klinik</option>
                                                        <option value="Tim Program Biomolekuler" {{ $selectedNamaUnit === 'Tim Program Biomolekuler' ? 'selected' : '' }}>Tim Program Biomolekuler</option>
                                                        <option value="Tim Penguatan SDM & Kemitraan" {{ $selectedNamaUnit === 'Tim Penguatan SDM & Kemitraan' ? 'selected' : '' }}>Tim Penguatan SDM & Kemitraan</option>
                                                        <option value="Tim Surveilans Faktor Resiko Kesehatan & Kejadian Luar biasa" {{ $selectedNamaUnit === 'Tim Surveilans Faktor Resiko Kesehatan & Kejadian Luar biasa' ? 'selected' : '' }}>Tim Surveilans Faktor Resiko Kesehatan & Kejadian Luar biasa</option>
                                                        <option value="Tim Program Layanan Laboratorium Kesehatan Masyarakat" {{ $selectedNamaUnit === 'Tim Program Layanan Laboratorium Kesehatan Masyarakat' ? 'selected' : '' }}>Tim Program Layanan Laboratorium Kesehatan Masyarakat</option>
                                                    @else
                                                        <option value="Klaster 1" {{ $selectedNamaUnit === 'Klaster 1' ? 'selected' : '' }}>Klaster 1 - Manajemen</option>
                                                        <option value="Klaster 2" {{ $selectedNamaUnit === 'Klaster 2' ? 'selected' : '' }}>Klaster 2 - Ibu dan Anak</option>
                                                        <option value="Klaster 3" {{ $selectedNamaUnit === 'Klaster 3' ? 'selected' : '' }}>Klaster 3 - Usia Dewasa & Lanjut Usia</option>
                                                        <option value="Klaster 4" {{ $selectedNamaUnit === 'Klaster 4' ? 'selected' : '' }}>Klaster 4 - Penanggulangan Penyakit Menular</option>
                                                        <option value="Lintas Klaster" {{ $selectedNamaUnit === 'Lintas Klaster' ? 'selected' : '' }}>Lintas Klaster</option>
                                                    @endif
                                                </select>
                                                @error('nama_unit', 'createRenbut')
                                                    <span class="invalid-feedback d-block"
                                                        role="alert"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Program</label>
                                                <input class="form-control" type="text"
                                                    value="1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
                                                    disabled>
                                                <input type="hidden" name="program"
                                                    value="1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA">

                                                <div class="invalid-feedback">Please provide a valid program</div>
                                                @error('program')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Kegiatan</label>

                                                <input class="form-control" type="text"
                                                    value="1.02.01.2.10 - Peningkatan Pelayanan BLUD" disabled>
                                                <input type="hidden" name="kegiatan"
                                                    value="1.02.01.2.10 - Peningkatan Pelayanan BLUD">
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Sub Kegiatan</label>

                                                <input class="form-control" type="text"
                                                    value="1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD"
                                                    disabled>
                                                <input type="hidden" name="subkegiatan"
                                                    value="1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD">
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Uraian</label>
                                                <input type="text" name="uraian"
                                                    class="form-control @error('uraian', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                @error('uraian', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Spesifikasi</label>
                                                <input type="text" name="spesifikasi"
                                                    class="form-control @error('spesifikasi', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                @error('spesifikasi', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Ruang</label>
                                                <input type="text" name="ruang"
                                                    class="form-control @error('ruang', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                @error('ruang', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Satuan</label>
                                                <input type="text" name="satuan"
                                                    class="form-control @error('satuan', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                @error('satuan', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Keterangan</label>
                                                <input type="text" name="keterangan"
                                                    class="form-control @error('keterangan', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                @error('keterangan', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Jumlah</label>
                                                <input type="text" id="jumlah_view"
                                                    class="form-control @error('jumlah', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                <input type="hidden" name="jumlah" id="jumlah" value="{{ old('jumlah') }}">
                                                @error('jumlah', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Harga Satuan</label>
                                                <input type="text" id="harga_satuan_view"
                                                    class="form-control @error('harga_satuan', 'createRenbut') is-invalid @enderror"
                                                    required>
                                                <input type="hidden" name="harga_satuan" id="harga_satuan" value="{{ old('harga_satuan') }}">
                                                @error('harga_satuan', 'createRenbut')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- TOTAL --}}
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Jumlah Harga</label>
                                                <input type="text" id="jumlah_harga_view" class="form-control"
                                                    disabled>
                                                <input type="hidden" name="jumlah_harga" id="jumlah_harga">
                                            </div>
                                        </div>

                                    </div>

                                    <div class="text-end">
                                        <div class="form-actions">
                                            <a href="{{ route('rencana-kebutuhan.index') }}" class="btn btn-cancel">
                                            Batal
                                        </a>
                                            <button type="submit" class="btn btn-save">
                                            Simpan
                                        </button>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                <!-- end modal-content-->
            </div>
            <!-- end modal dialog-->
        </div>
        <!-- end modal -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card premium-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            @if(strcasecmp(Auth::user()->upt, 'Dinas Kesehatan') !== 0)
                            <button type="button" class="btn btn-primary" id="btn-new-event"
                                data-bs-toggle="modal" data-bs-target="#myModal">
                                <i class="ri-add-line" aria-hidden="true"></i> Buat Rencana Kebutuhan
                            </button>
                            @endif
                        </div>
                        <div style="width: 100%; max-width: 500px;">
                            <form action="{{ route('rencana-kebutuhan.index') }}" method="GET" class="m-0 d-flex gap-2">
                                @if(isset($puskesmasList) && count($puskesmasList) > 0)
                                    <select name="puskesmas" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">-- Semua BLUD --</option>
                                        @foreach($puskesmasList as $p)
                                            <option value="{{ $p->nama }}" {{ request('puskesmas') == $p->nama ? 'selected' : '' }}>{{ $p->nama }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Pencarian..." value="{{ request('search') }}">
                                    <button class="btn btn-primary btn-sm" type="submit">Cari</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="rba-budget renbut-budget">
                    <div class="table-responsive rba-table-wrap" role="region" aria-label="Rincian rencana kebutuhan" tabindex="0">
                        <table class="table align-middle rba-table table-renbut">
                        <caption class="visually-hidden">Rincian rencana kebutuhan tahun anggaran {{ \App\Support\TahunRba::selected() }}.</caption>
                        <colgroup>
                            <col class="rba-col-number">
                            @if($canSeeUnitBlud)<col class="renbut-col-blud">@endif
                            <col class="renbut-col-description">
                            @if($canManageDanaAkun)<col class="renbut-col-funding"><col class="renbut-col-account">@endif
                            <col class="renbut-col-unit"><col class="renbut-col-specification"><col class="renbut-col-quantity">
                            <col class="renbut-col-unit-price"><col class="renbut-col-price"><col class="rba-col-actions">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">No</th>
                                @if ($canSeeUnitBlud)
                                    <th scope="col">BLUD</th>
                                @endif
                                <th scope="col">Uraian</th>
                                @if ($canManageDanaAkun)
                                    <th scope="col" class="col-sumber-dana">Sumber Dana</th>
                                    <th scope="col" class="col-nama-barang">Nama Barang/Jasa</th>
                                @endif
                                <th scope="col">Nama Unit</th>
                                <th scope="col">Spesifikasi</th>
                                <th scope="col" class="text-end">Jumlah</th>
                                <th scope="col" class="text-end">Harga Satuan (Rp)</th>
                                <th scope="col" class="text-end">Jumlah Harga (Rp)</th>
                                <th scope="col" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($renbut as $item)
                                <tr class="rba-item">
                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        {{ $renbut->firstItem() + $loop->index }}</td>
                                    @if ($canSeeUnitBlud)
                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        {{ $item->nama_blud }}</td>
                                    @endif
                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        <span class="fw-semibold text-body-emphasis">{{ $item->uraian ?? 'N/A' }}</span></td>

                                    @if ($canManageDanaAkun)
                                        <td class="col-sumber-dana {{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                            @if ($item->trashed())
                                                {{ $item->sumber_dana ?? '-' }}
                                            @else
                                                @php $currentSumberDana = $item->sumber_dana ?? 'Pilih Sumber Dana'; @endphp
                                                <span class="text-sumber-dana text-primary fw-medium" data-id="{{ $item->id }}">
                                                    {{ $currentSumberDana }}
                                                </span>
                                                <select class="form-control select-sumber-dana d-none" data-id="{{ $item->id }}">
                                                    <option value="">Pilih Sumber Dana</option>
                                                    @foreach (['APBD', 'APBN', 'BLUD', 'HIBAH', 'LAINNYA'] as $sumberDana)
                                                        <option value="{{ $sumberDana }}" {{ $item->sumber_dana === $sumberDana ? 'selected' : '' }}>
                                                            {{ $sumberDana }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>

                                        <td class="col-nama-barang {{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                            @php
                                                $akunText = $item->akuns?->kode_rek && $item->akuns?->uraian
                                                    ? $item->akuns->kode_rek . ' - ' . $item->akuns->uraian
                                                    : null;
                                            @endphp
                                            @if ($item->trashed())
                                                {{ $akunText ?? '-' }}
                                            @else
                                                <span class="text-akun text-primary fw-medium" data-id="{{ $item->id }}" data-value="{{ $item->akun_id ?? '' }}">
                                                    {{ $akunText ?? 'Pilih Barang/Jasa' }}
                                                </span>
                                                <select class="form-control select-akun d-none" data-id="{{ $item->id }}">
                                                    @if ($item->akun_id && $akunText)
                                                        <option value="{{ $item->akun_id }}" selected>{{ $akunText }}</option>
                                                    @endif
                                                </select>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        <span class="rba-unit">{{ $item->nama_unit }}</span></td>
                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        {{ $item->spesifikasi }}</td>
                                    <td class="text-end rba-amount {{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                       {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                    <td class="text-end rba-amount {{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                       {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                    <td class="text-end rba-amount fw-semibold {{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                       {{ number_format($item->jumlah_harga, 0, ',', '.') }}</td>
                                    <td class="{{ $item->trashed() ? 'text-decoration-line-through' : '' }}">
                                        @if (!$item->trashed())
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <a href="javascript:void(0)"
                                                    class="btn btn-sm btn-warning btn-edit"
                                                    title="Edit Rencana Kebutuhan" aria-label="Edit Rencana Kebutuhan"
                                                    data-id="{{ $item->id }}"
                                                    data-uraian="{{ $item->uraian }}"
                                                    data-jumlah="{{ $item->jumlah }}"
                                                    data-satuan="{{ $item->satuan }}"
                                                    data-harga="{{ $item->harga_satuan }}"
                                                    data-keterangan="{{ $item->keterangan }}"
                                                    data-nama_blud="{{ $item->nama_blud }}"
                                                    data-nama_unit="{{ $item->nama_unit }}"
                                                    data-tahun_anggaran="{{ $item->tahun_anggaran }}"
                                                    data-program="{{ $item->program }}"
                                                    data-kegiatan="{{ $item->kegiatan }}"
                                                    data-subkegiatan="{{ $item->subkegiatan }}"
                                                    data-ruang="{{ $item->ruang }}"
                                                    data-spesifikasi="{{ $item->spesifikasi }}"
                                                    data-sumber_dana="{{ $item->sumber_dana }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEdit"
                                                >
                                                    <i class="ri-edit-box-line" aria-hidden="true"></i>
                                                </a>
                                    
                                                <form action="{{ route('rencana-kebutuhan.destroy', $item->id) }}"
                                                    method="POST"
                                                    class="m-0"
                                                    data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="btn btn-sm btn-danger" title="Hapus Rencana Kebutuhan" aria-label="Hapus Rencana Kebutuhan">
                                                        <i class="ri-delete-bin-2-line" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 8 + ($canSeeUnitBlud ? 1 : 0) + ($canManageDanaAkun ? 2 : 0) }}" class="rba-empty">
                                        <i class="ri-file-list-3-line" aria-hidden="true"></i>
                                        <p class="fw-semibold text-body-emphasis mb-1">Data tidak ditemukan</p>
                                        <p class="small mb-0">{{ request()->filled('search') || request()->filled('puskesmas') ? 'Coba ubah kata pencarian atau filter BLUD.' : 'Belum ada rencana kebutuhan pada tahun anggaran ini.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        </table>
                    </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-end">
                        {{ $renbut->links('pagination::bootstrap-5') }}
                    </div>

                </div>
            </div>
        </div>
        <!-- end col -->
    </div>
    <!-- end row -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel">
    <div class="modal-dialog modal-md">
        <form method="POST" id="formEdit">
            @csrf
            @method('PUT')

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditLabel">Edit Rencana Kebutuhan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="nama_blud" id="edit-nama_blud">
                    <input type="hidden" name="nama_unit" id="edit-nama_unit">
                    <input type="hidden" name="tahun_anggaran" id="edit-tahun_anggaran">
                    <input type="hidden" name="program" id="edit-program">
                    <input type="hidden" name="kegiatan" id="edit-kegiatan">
                    <input type="hidden" name="subkegiatan" id="edit-subkegiatan">
                    <input type="hidden" name="ruang" id="edit-ruang">
                    <input type="hidden" name="spesifikasi" id="edit-spesifikasi">
                    <input type="hidden" name="sumber_dana" id="edit-sumber_dana">

                    <div class="mb-3">
                        <label>Uraian</label>
                        <input type="text" name="uraian" id="edit-uraian" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Jumlah</label>
                        <input type="text" id="edit-jumlah-view" class="form-control" required>
                        <input type="hidden" name="jumlah" id="edit-jumlah">
                    </div>

                    <div class="mb-3">
                        <label>Satuan</label>
                        <input type="text" name="satuan" id="edit-satuan" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Harga Satuan</label>
                        <input type="text" id="edit-harga-view" class="form-control" required>
                        <input type="hidden" name="harga_satuan" id="edit-harga">
                    </div>

                    <div class="mb-3">
                        <label>Total Harga</label>
                        <input type="text" id="edit-jumlah-harga" class="form-control" readonly>
                    </div>
                    <input type="hidden" name="jumlah_harga" id="edit-jumlah-harga-hidden">

                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="edit-keterangan" class="form-control" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-save">Simpan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')


    @if ($canManageDanaAkun)
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const csrfToken = '{{ csrf_token() }}';
                let activePanel = null;
                let searchTimer = null;

                function postForm(url, data) {
                    const body = new URLSearchParams({
                        ...data,
                        _token: csrfToken
                    });

                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body
                    }).then(function(response) {
                        if (!response.ok) {
                            throw new Error('Request gagal');
                        }

                        return response.json();
                    });
                }

                function closeAkunPanel() {
                    if (activePanel) {
                        activePanel.remove();
                        activePanel = null;
                    }
                }

                function renderAkunResults(listEl, items, onChoose) {
                    listEl.innerHTML = '';

                    if (!items.length) {
                        const empty = document.createElement('div');
                        empty.className = 'akun-search-empty text-muted';
                        empty.textContent = 'Data tidak ditemukan';
                        listEl.appendChild(empty);
                        return;
                    }

                    items.forEach(function(item) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'akun-search-item';
                        button.textContent = item.text;
                        button.addEventListener('click', function() {
                            onChoose(item);
                        });
                        listEl.appendChild(button);
                    });
                }

                function loadAkunOptions(term, listEl, onChoose) {
                    listEl.innerHTML = '<div class="akun-search-empty text-muted">Memuat...</div>';

                    fetch('{{ url('/api/akun') }}?q=' + encodeURIComponent(term || ''), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error('Gagal memuat akun');
                            }

                            return response.json();
                        })
                        .then(function(data) {
                            renderAkunResults(listEl, data.results || data || [], onChoose);
                        })
                        .catch(function(error) {
                            console.error(error);
                            listEl.innerHTML = '<div class="akun-search-empty text-danger">Gagal memuat data</div>';
                        });
                }

                function openAkunPanel(textEl) {
                    closeAkunPanel();

                    const id = textEl.dataset.id;
                    const cell = textEl.closest('td');
                    const panel = document.createElement('div');
                    const input = document.createElement('input');
                    const list = document.createElement('div');

                    cell.style.position = 'relative';
                    panel.className = 'akun-search-panel';
                    input.type = 'search';
                    input.className = 'form-control';
                    input.placeholder = 'Cari kode rekening atau uraian...';
                    list.className = 'akun-search-list';

                    panel.appendChild(input);
                    panel.appendChild(list);
                    cell.appendChild(panel);
                    activePanel = panel;

                    function chooseAkun(item) {
                        textEl.textContent = 'Loading...';
                        postForm('/rencana-kebutuhan/update-akun', {
                            id: id,
                            akun_id: item.id
                        })
                            .then(function(response) {
                                if (response.success) {
                                    textEl.textContent = item.text;
                                    textEl.dataset.value = item.id;
                                    closeAkunPanel();
                                    return;
                                }

                                alert(response.message || 'Gagal update akun');
                                textEl.textContent = 'Pilih Barang/Jasa';
                            })
                            .catch(function(error) {
                                console.error(error);
                                alert('Gagal update akun');
                            });
                    }

                    input.addEventListener('input', function() {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(function() {
                            loadAkunOptions(input.value, list, chooseAkun);
                        }, 250);
                    });

                    loadAkunOptions('', list, chooseAkun);
                    input.focus();
                }

                document.addEventListener('click', function(e) {
                    const sumberDanaText = e.target.closest('.text-sumber-dana');
                    if (sumberDanaText) {
                        const id = sumberDanaText.dataset.id;
                        const selectEl = document.querySelector(`.select-sumber-dana[data-id="${id}"]`);

                        if (selectEl) {
                            sumberDanaText.classList.add('d-none');
                            selectEl.classList.remove('d-none');
                            selectEl.focus();
                        }

                        return;
                    }

                    const akunText = e.target.closest('.text-akun');
                    if (akunText) {
                        openAkunPanel(akunText);
                        return;
                    }

                    if (!e.target.closest('.akun-search-panel')) {
                        closeAkunPanel();
                    }
                });

                document.addEventListener('change', function(e) {
                    const selectEl = e.target.closest('.select-sumber-dana');
                    if (!selectEl) return;

                    const id = selectEl.dataset.id;
                    const textEl = document.querySelector(`.text-sumber-dana[data-id="${id}"]`);
                    const value = selectEl.value;

                    if (!textEl) return;

                    textEl.textContent = 'Loading...';
                    postForm('/rencana-kebutuhan/update-sumber-dana', {
                        id: id,
                        sumber_dana: value
                    })
                        .then(function(response) {
                            if (response.success) {
                                textEl.textContent = value || 'Pilih Sumber Dana';
                                return;
                            }

                            alert(response.message || 'Terjadi error');
                        })
                        .catch(function(error) {
                            console.error(error);
                            alert('Terjadi error');
                        })
                        .finally(function() {
                            selectEl.classList.add('d-none');
                            textEl.classList.remove('d-none');
                        });
                });
            });
        </script>
    @endif
    
    <script>
        function parseRibuan(text) {
            if(!text) return 0;
            return parseFloat(text.toString().replace(/\./g, '').replace(',', '.')) || 0;
        }

        function formatAndSet(el, hiddenId) {
            let val = el.value.replace(/[^,\d]/g, '');
            let parsed = parseRibuan(val);
            document.getElementById(hiddenId).value = parsed;
            
            let parts = val.split(',');
            if(parts[0]) {
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
            el.value = parts.join(',');
        }

        function formatNumberDisplay(num) {
            if(!num) return '';
            return parseFloat(num).toLocaleString('id-ID');
        }

        // =========================
        // FORM TAMBAH (myModal)
        // =========================
        function hitungTotalTambah() {
            const jumlahHidden = document.getElementById('jumlah');
            const hargaHidden = document.getElementById('harga_satuan');
            const totalHiddenEl = document.getElementById('jumlah_harga');
            const totalViewEl = document.getElementById('jumlah_harga_view');

            if (!jumlahHidden || !hargaHidden || !totalHiddenEl || !totalViewEl) return;

            const jumlah = parseFloat(jumlahHidden.value) || 0;
            const harga = parseFloat(hargaHidden.value) || 0;
            const total = jumlah * harga;

            totalHiddenEl.value = total;
            totalViewEl.value = total.toLocaleString('id-ID');
        }

        function initTotalTambah() {
            const jumlahView = document.getElementById('jumlah_view');
            const hargaView = document.getElementById('harga_satuan_view');
            const jumlahHidden = document.getElementById('jumlah');
            const hargaHidden = document.getElementById('harga_satuan');

            if (jumlahView) {
                if(jumlahHidden && jumlahHidden.value) jumlahView.value = formatNumberDisplay(jumlahHidden.value);
                jumlahView.addEventListener('input', function() {
                    formatAndSet(this, 'jumlah');
                    hitungTotalTambah();
                });
            }
            if (hargaView) {
                if(hargaHidden && hargaHidden.value) hargaView.value = formatNumberDisplay(hargaHidden.value);
                hargaView.addEventListener('input', function() {
                    formatAndSet(this, 'harga_satuan');
                    hitungTotalTambah();
                });
            }

            document.addEventListener('shown.bs.modal', function (event) {
                if (event.target.id === 'myModal') {
                    hitungTotalTambah();
                }
            });

            hitungTotalTambah();
        }

        // =========================
        // FORM EDIT (modalEdit)
        // =========================
        function hitungTotalEdit() {
            const jumlahHidden = document.getElementById('edit-jumlah');
            const hargaHidden = document.getElementById('edit-harga');
            const totalViewEl = document.getElementById('edit-jumlah-harga');
            const totalHiddenEl = document.getElementById('edit-jumlah-harga-hidden');

            if (!jumlahHidden || !hargaHidden || !totalViewEl || !totalHiddenEl) return;

            const jumlah = parseFloat(jumlahHidden.value) || 0;
            const harga = parseFloat(hargaHidden.value) || 0;
            const total = jumlah * harga;

            totalViewEl.value = total.toLocaleString('id-ID');
            totalHiddenEl.value = total;
        }

        function initTotalEdit() {
            const jumlahView = document.getElementById('edit-jumlah-view');
            const hargaView = document.getElementById('edit-harga-view');

            if (jumlahView) {
                jumlahView.addEventListener('input', function() {
                    formatAndSet(this, 'edit-jumlah');
                    hitungTotalEdit();
                });
            }
            if (hargaView) {
                hargaView.addEventListener('input', function() {
                    formatAndSet(this, 'edit-harga');
                    hitungTotalEdit();
                });
            }

            document.addEventListener('shown.bs.modal', function (event) {
                if (event.target.id === 'modalEdit') {
                    hitungTotalEdit();
                }
            });

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-edit');
                if (!btn || btn.classList.contains('disabled')) return;
        
                const id = btn.getAttribute('data-id');
        
                document.getElementById('edit-id').value = id || '';
                document.getElementById('edit-uraian').value = btn.getAttribute('data-uraian') || '';
                
                const jumlahVal = btn.getAttribute('data-jumlah') || '';
                document.getElementById('edit-jumlah').value = jumlahVal;
                if(document.getElementById('edit-jumlah-view')) document.getElementById('edit-jumlah-view').value = formatNumberDisplay(jumlahVal);
                
                document.getElementById('edit-satuan').value = btn.getAttribute('data-satuan') || '';
                
                const hargaVal = btn.getAttribute('data-harga') || '';
                document.getElementById('edit-harga').value = hargaVal;
                if(document.getElementById('edit-harga-view')) document.getElementById('edit-harga-view').value = formatNumberDisplay(hargaVal);
                
                document.getElementById('edit-keterangan').value = btn.getAttribute('data-keterangan') || '';
        
                document.getElementById('edit-nama_blud').value = btn.getAttribute('data-nama_blud') || '';
                document.getElementById('edit-nama_unit').value = btn.getAttribute('data-nama_unit') || '';
                document.getElementById('edit-tahun_anggaran').value = btn.getAttribute('data-tahun_anggaran') || '';
                document.getElementById('edit-program').value = btn.getAttribute('data-program') || '';
                document.getElementById('edit-kegiatan').value = btn.getAttribute('data-kegiatan') || '';
                document.getElementById('edit-subkegiatan').value = btn.getAttribute('data-subkegiatan') || '';
                document.getElementById('edit-ruang').value = btn.getAttribute('data-ruang') || '';
                document.getElementById('edit-spesifikasi').value = btn.getAttribute('data-spesifikasi') || '';
                document.getElementById('edit-sumber_dana').value = btn.getAttribute('data-sumber_dana') || '';
        
                document.getElementById('formEdit').action = `/rencana-kebutuhan/${id}`;
                hitungTotalEdit();
            });

            hitungTotalEdit();
        }

        function initTotalRenbut() {
            initTotalTambah();
            initTotalEdit();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTotalRenbut);
        } else {
            initTotalRenbut();
        }
    </script>

    @if ($errors->createRenbut->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('myModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    @endif
@endsection
