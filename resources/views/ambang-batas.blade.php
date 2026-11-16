@extends('layouts.vertical', ['title' => 'Ambang Batas', 'subTitle' => 'Perencanaan'])

@section('css')
<style>
    .ambang-table th { color: var(--bs-heading-color); font-weight: 600; }
    .ambang-table td { color: var(--bs-secondary-color); }
    .ambang-table th, .ambang-table td { padding: 1.5rem 1rem; vertical-align: middle; }
    .ambang-table tbody tr:last-child td { border-bottom: 0; }
</style>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">

        @if($isSuperuser)
            <form method="GET" action="{{ route('rba.ambang-batas') }}" class="d-flex align-items-center gap-2">
                <label for="filter-blud" class="form-label mb-0 text-nowrap">Unit BLUD</label>
                <select id="filter-blud" name="puskesmas" class="form-select">
                    <option value="">Semua Unit BLUD</option>
                    @foreach($puskesmasList as $unit)
                        <option value="{{ $unit->nama }}" @selected($puskesmas === $unit->nama)>{{ $unit->nama }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">Tampilkan</button>
            </form>
        @else
            <span class="text-muted">{{ auth()->user()->upt }}</span>
        @endif
            @if($editing)
                <a class="btn btn-primary" href="{{ route('rba.ambang-batas', ['puskesmas' => $puskesmas, 'tambah' => 1]) }}#form-ambang-batas">
                    <i class="ri-add-line" aria-hidden="true"></i> Tambah Data
                </a>
            @else
                <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#form-ambang-batas" aria-controls="form-ambang-batas" aria-expanded="{{ $errors->any() || request()->boolean('tambah') ? 'true' : 'false' }}">
                    <i class="ri-add-line" aria-hidden="true"></i> Tambah Data
                </button>
            @endif
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table ambang-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Tahun</th>
                        @if($isSuperuser && $puskesmas === '')
                            <th scope="col">Unit BLUD</th>
                        @endif
                        <th scope="col">Anggaran</th>
                        <th scope="col">Realisasi</th>
                        <th scope="col">Selisih</th>
                        <th scope="col">Persentase</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ambangBatas as $item)
                        <tr>
                            <td>{{ $ambangBatas->firstItem() + $loop->index }}</td>
                            <td>{{ $item->tahun }}</td>
                            @if($isSuperuser && $puskesmas === '')
                                <td>{{ $item->nama_blud }}</td>
                            @endif
                            <td class="text-nowrap">Rp {{ number_format($item->anggaran, fmod((float) $item->anggaran, 1) == 0 ? 0 : 2, ',', '.') }}</td>
                            <td class="text-nowrap">Rp {{ number_format($item->realisasi, fmod((float) $item->realisasi, 1) == 0 ? 0 : 2, ',', '.') }}</td>
                            <td class="text-nowrap">Rp {{ number_format($item->selisih, fmod($item->selisih, 1) == 0 ? 0 : 2, ',', '.') }}</td>
                            <td>{{ $item->persentase === null ? '-' : number_format($item->persentase, 2, ',', '.').'%' }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-info btn-sm" href="{{ route('rba.ambang-batas', ['edit' => $item->id, 'puskesmas' => $puskesmas]) }}#form-ambang-batas" title="Edit Ambang Batas" aria-label="Edit Ambang Batas">
                                        <i class="ri-edit-box-line" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('rba.ambang-batas.destroy', $item->id) }}" data-confirm-delete data-confirm-title="Hapus Ambang Batas?" data-confirm-message="Hapus data ambang batas tahun {{ $item->tahun }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus Ambang Batas" aria-label="Hapus Ambang Batas">
                                            <i class="ri-delete-bin-2-line" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 7 + ($isSuperuser && $puskesmas === '' ? 1 : 0) }}" class="text-center py-5">Belum ada data ambang batas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $ambangBatas->links('pagination::bootstrap-5') }}</div>
        <p class="text-muted small mt-3 mb-0">Selisih = Realisasi − Anggaran. Persentase = (Selisih ÷ Anggaran) × 100%.</p>
    </div>
</div>

    <div class="collapse {{ $editing || $errors->any() || request()->boolean('tambah') ? 'show' : '' }}" id="form-ambang-batas">
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">{{ $editing ? 'Ubah' : 'Tambah' }} Data Ambang Batas</h5></div>
        <div class="card-body">
            <form id="ambang-batas-form" method="POST" action="{{ $editing ? route('rba.ambang-batas.update', $editing->id) : route('rba.ambang-batas.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="row g-3">
                    @if($isSuperuser)
                        <div class="col-md-4">
                            <label for="nama-blud" class="form-label">Unit BLUD</label>
                            <select id="nama-blud" name="nama_blud" class="form-select" required>
                                <option value="">Pilih Unit BLUD</option>
                                @foreach($puskesmasList as $unit)
                                    <option value="{{ $unit->nama }}" @selected(old('nama_blud', $editing?->nama_blud ?? $puskesmas) === $unit->nama)>{{ $unit->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-{{ $isSuperuser ? '2' : '4' }}">
                        <label for="tahun" class="form-label">Tahun</label>
                        <input id="tahun" name="tahun" type="number" min="1900" max="9999" step="1" class="form-control" required value="{{ old('tahun', $editing?->tahun ?? now()->year - 1) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="anggaran-view" class="form-label">Anggaran (Rp)</label>
                        <input id="anggaran-view" type="text" inputmode="decimal" class="form-control" required autocomplete="off" placeholder="650.000.000">
                        <input id="anggaran" name="anggaran" type="hidden" value="{{ old('anggaran', $editing?->anggaran) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="realisasi-view" class="form-label">Realisasi (Rp)</label>
                        <input id="realisasi-view" type="text" inputmode="decimal" class="form-control" required autocomplete="off" placeholder="700.000.000">
                        <input id="realisasi" name="realisasi" type="hidden" value="{{ old('realisasi', $editing?->realisasi) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="selisih-preview" class="form-label">Selisih (otomatis)</label>
                        <input id="selisih-preview" class="form-control bg-light" value="-" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="persentase-preview" class="form-label">Persentase (otomatis)</label>
                        <input id="persentase-preview" class="form-control bg-light" value="-" readonly>
                    </div>
                </div>
                <p class="text-muted small mt-3">Isi anggaran dan realisasi untuk menghitung selisih dan persentase. Anggaran harus lebih besar dari nol.</p>
                <div class="form-actions">
                    @if($editing)
                        <a class="btn btn-cancel" href="{{ route('rba.ambang-batas', ['puskesmas' => $puskesmas]) }}">Batal</a>
                    @else
                        <button type="button" class="btn btn-cancel" data-bs-toggle="collapse" data-bs-target="#form-ambang-batas" aria-controls="form-ambang-batas">Batal</button>
                    @endif
                    <button type="submit" class="btn btn-save">{{ $editing ? 'Simpan Perubahan' : 'Simpan Data' }}</button>
                </div>
            </form>
        </div>
    </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('js/ambang-batas.js') }}"></script>
@endsection
