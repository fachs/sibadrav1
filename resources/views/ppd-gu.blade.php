@extends('layouts.vertical', ['title' => 'PPD-GU','subTitle' => 'GU'])

@section('body')
    <body data-sidebar="dark">
@endsection

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="card-body pb-0">
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td style="width: 150px;"><strong>Unit BLUD</strong></td>
                                <td style="width: 10px;">:</td>
                                <td>{{ Auth::user()->upt }}</td>
                            </tr>
                            <tr>
                                <td><strong>Bendahara</strong></td>
                                <td>:</td>
                                <td>{{ Auth::user()->nip }} - {{ Auth::user()->name }}</td>
                            </tr>

                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"></h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPpdGu">
                            <i class="fas fa-plus"></i> Buat PPD-GU
                        </button>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>No SK</th>
                                    <th>Tanggal SK</th>
                                    <th>No Rek</th>
                                    <th>Unit BLUD</th>
                                    <th>Jumlah</th>
                                    <th style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ppdGus as $item)
                                    <tr>

                                        <td>{{ $item->nomor }}</td>
                                        <td>{{ $item->no_sk }}</td>
                                        <td>{{ $item->tanggal_sk }}</td>
                                        <td>{{ $item->no_rek }}</td>
                                        <td>{{ optional($item->unitBlud)->nama ?: '-' }}</td>
                                        <td>Rp {{ number_format($item->jumlah) }}</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('ppd-gu.unduh-surat', $item->id) }}" class="btn btn-sm btn-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditPpdGu{{ $item->id }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="{{ route('ppd-gu.destroy', $item->id) }}" method="POST" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="modalEditPpdGu{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('ppd-gu.update', $item->id) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit PPD-GU</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Nomor</label>
                                                            <input type="text" name="nomor" class="form-control" value="{{ $item->nomor }}" maxlength="100" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Nomor SK</label>
                                                            <input type="text" name="no_sk" class="form-control" value="{{ $item->no_sk }}" maxlength="100" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Tanggal SK</label>
                                                            <input type="date" name="tanggal_sk" class="form-control" value="{{ $item->tanggal_sk }}" required>
                                                        </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Jumlah</label>
                                                            <input type="number" step="0.01" min="0" name="jumlah" class="form-control" value="{{ $item->jumlah }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">No Rek</label>
                                                            <input type="text" name="no_rek" class="form-control" value="{{ $item->no_rek }}" maxlength="100" required>
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
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">Data PPD-GU belum tersedia.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTambahPpdGu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                @if ($errors->any())
                    <div class="alert alert-danger m-3 mb-0">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('ppd-gu.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah PPD-GU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nomor</label>
                            <input type="text" name="nomor" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor SK</label>
                            <input type="text" name="no_sk" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal SK</label>
                            <input type="date" name="tanggal_sk" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah</label>
                            <input type="number" step="0.01" min="0" name="jumlah" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No Rek</label>
                            <input type="text" name="no_rek" class="form-control" maxlength="100" required>
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
@endsection

@section('scripts')
    <script src="{{ URL::asset('assets/js/app.js') }}"></script>
    @if ($errors->any())
        <script>
            const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahPpdGu'));
            modalTambah.show();
        </script>
    @endif
@endsection