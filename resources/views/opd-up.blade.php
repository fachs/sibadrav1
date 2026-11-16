@extends('layouts.vertical', ['title' => 'OPD-UP','subTitle' => 'UP'])

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
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahOpdUp">
                            <i class="fas fa-plus"></i> Buat OPD-UP
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

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Unit BLUD</th>
                                    <th>Nomor PPD-UP</th>
                                    <th>Jumlah</th>
                                    <th>Kepada</th>
                                    <th>Potongan</th>
                                    <th>Jumlah Akhir</th>
                                    <th style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($opdUps as $item)
                                    <tr>
                                        <td>{{ $item->nomor }}</td>
                                        <td>{{ optional($item->unitBlud)->nama ?: '-' }}</td>
                                        <td>{{ optional($item->ppdUp)->nomor ?: '-' }}</td>
                                        <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                        <td>{{ $item->kepada }}</td>
                                        <td>Rp {{ number_format($item->potongan, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($item->jumlah_akhir, 0, ',', '.') }}</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('opd-up.unduh-surat', $item->id) }}" class="btn btn-sm btn-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditOpdUp{{ $item->id }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="{{ route('opd-up.destroy', $item->id) }}" method="POST" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">Data OPD-UP belum tersedia.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Edit --}}
    @foreach ($opdUps as $item)
        <div class="modal fade" id="modalEditOpdUp{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('opd-up.update', $item->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit OPD-UP</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}">
                                <div class="col-md-6"><label class="form-label">Nomor</label><input type="text" class="form-control" name="nomor" value="{{ $item->nomor }}" required></div>
                                <div class="col-md-6"><label class="form-label">Nomor DBA</label><input type="text" class="form-control" name="nomor_dba" value="{{ $item->nomor_dba }}" required></div>
                                <div class="col-md-6"><label class="form-label">Tanggal DBA</label><input type="date" class="form-control" name="tanggal_dba" value="{{ $item->tanggal_dba }}" required></div>
                                <div class="col-md-6"><label class="form-label">PPD-UP</label><select class="form-select" name="ppd_up_id" required>@foreach($ppdUps as $ppd)<option value="{{ $ppd->id }}" {{ (int) $item->ppd_up_id === (int) $ppd->id ? 'selected' : '' }}>{{ $ppd->nomor }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" value="{{ $item->bank_asal }}" required></div>
                                <div class="col-md-6"><label class="form-label">Jumlah</label><input type="number" step="1" class="form-control opd-jumlah" name="jumlah" value="{{ $item->jumlah }}" required></div>
                                <div class="col-md-6"><label class="form-label">Kepada</label><input type="text" class="form-control" name="kepada" value="{{ $item->kepada }}" required></div>
                                <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp" value="{{ $item->npwp }}"></div>
                                <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" value="{{ $item->no_rek_tujuan }}" required></div>
                                <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" value="{{ $item->bank_tujuan }}" required></div>
                                <div class="col-md-6"><label class="form-label">Potongan</label><input type="number" step="1" class="form-control opd-potongan" name="potongan" value="{{ $item->potongan }}" required></div>
                                <div class="col-md-6"><label class="form-label">Jumlah Akhir</label><input type="number" step="1" class="form-control bg-light opd-jumlah-akhir" name="jumlah_akhir" value="{{ $item->jumlah_akhir }}" readonly required></div>
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

    {{-- Modal Tambah --}}
    <div class="modal fade" id="modalTambahOpdUp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('opd-up.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah OPD-UP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}">
                            <div class="col-md-6"><label class="form-label">Nomor</label><input type="text" class="form-control" name="nomor" required></div>
                            <div class="col-md-6"><label class="form-label">Nomor DBA</label><input type="text" class="form-control" name="nomor_dba" required></div>
                            <div class="col-md-6"><label class="form-label">Tanggal DBA</label><input type="date" class="form-control" name="tanggal_dba" required></div>
                            <div class="col-md-6"><label class="form-label">PPD-UP</label><select class="form-select" name="ppd_up_id" required><option value="">-- Pilih PPD-UP --</option>@foreach($ppdUps as $ppd)<option value="{{ $ppd->id }}">{{ $ppd->nomor }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" required></div>
                            <div class="col-md-6"><label class="form-label">Jumlah</label><input type="number" step="1" class="form-control opd-jumlah" name="jumlah" value="0" required></div>
                            <div class="col-md-6"><label class="form-label">Kepada</label><input type="text" class="form-control" name="kepada" required></div>
                            <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp"></div>
                            <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" required></div>
                            <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" required></div>
                            <div class="col-md-6"><label class="form-label">Potongan</label><input type="number" step="1" class="form-control opd-potongan" name="potongan" value="0" required></div>
                            <div class="col-md-6"><label class="form-label">Jumlah Akhir</label><input type="number" step="1" class="form-control bg-light opd-jumlah-akhir" name="jumlah_akhir" value="0" readonly required></div>
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

@section('script')
    <script>
        // Kalkulasi: Jumlah Akhir = Jumlah - Potongan
        function hitungJumlahAkhir(form) {
            var jumlah = parseFloat(form.querySelector('.opd-jumlah').value) || 0;
            var potongan = parseFloat(form.querySelector('.opd-potongan').value) || 0;
            form.querySelector('.opd-jumlah-akhir').value = Math.max(0, jumlah - potongan);
        }

        // Pasang event listener ke semua input jumlah dan potongan
        document.querySelectorAll('.opd-jumlah, .opd-potongan').forEach(function(input) {
            input.addEventListener('input', function() {
                hitungJumlahAkhir(this.closest('form'));
            });
        });
    </script>

    @if ($errors->any())
        <script>
            var modalTambah = new bootstrap.Modal(document.getElementById('modalTambahOpdUp'));
            modalTambah.show();
        </script>
    @endif
@endsection
