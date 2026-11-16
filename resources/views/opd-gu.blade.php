@extends('layouts.vertical', ['title' => 'OPD-GU','subTitle' => 'GU'])

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
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahOpdGu">
                            <i class="fas fa-plus"></i> Buat OPD-GU
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
                                    <th>Nomor PPD-GU</th>
                                    <th>Jumlah</th>
                                    <th>Kepada</th>
                                    <th>Potongan</th>
                                    <th>Jumlah Akhir</th>
                                    <th style="width: 140px;">Aksi</th>
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
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('opd-gu.unduh-surat', $item->id) }}" class="btn btn-sm btn-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditOpdGu{{ $item->id }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="{{ route('opd-gu.destroy', $item->id) }}" method="POST" data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
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
                                        <td colspan="16" class="text-center">Data OPD-GU belum tersedia.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($opdGus as $item)
        <div class="modal fade" id="modalEditOpdGu{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('opd-gu.update', $item->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit OPD-GU</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}">
                                <div class="col-md-6"><label class="form-label">Nomor</label><input type="text" class="form-control" name="nomor" value="{{ $item->nomor }}" required></div>
                                <div class="col-md-6"><label class="form-label">Nomor DBA</label><input type="text" class="form-control" name="nomor_dba" value="{{ $item->nomor_dba }}" required></div>
                                <div class="col-md-6"><label class="form-label">Tanggal DBA</label><input type="date" class="form-control" name="tanggal_dba" value="{{ $item->tanggal_dba }}" required></div>
                                <div class="col-md-6"><label class="form-label">PPD-GU</label><select class="form-select" name="ppd_gu_id" required>@foreach($ppdGus as $ppd)<option value="{{ $ppd->id }}" {{ (int) $item->ppd_gu_id === (int) $ppd->id ? 'selected' : '' }}>{{ $ppd->nomor }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" value="{{ $item->bank_asal }}" required></div>
                                <div class="col-md-6"><label class="form-label">Jumlah</label><input type="number" step="1" class="form-control js-jumlah" name="jumlah" value="{{ $item->jumlah }}" oninput="calculateJumlahAkhir(this.form)" onchange="calculateJumlahAkhir(this.form)" required></div>
                                <div class="col-md-6"><label class="form-label">Kepada</label><input type="text" class="form-control" name="kepada" value="{{ $item->kepada }}" required></div>
                                <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp" value="{{ $item->npwp }}"></div>
                                <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" value="{{ $item->no_rek_tujuan }}" required></div>
                                <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" value="{{ $item->bank_tujuan }}" required></div>
                                <div class="col-md-6"><label class="form-label">Potongan</label><input type="number" step="1" class="form-control js-potongan" name="potongan" value="{{ $item->potongan }}" oninput="calculateJumlahAkhir(this.form)" onchange="calculateJumlahAkhir(this.form)" required></div>
                                <div class="col-md-6"><label class="form-label">Jumlah Akhir</label><input type="number" step="1" class="form-control js-jumlah-akhir" name="jumlah_akhir" value="{{ $item->jumlah_akhir }}" readonly required></div>
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

    <div class="modal fade" id="modalTambahOpdGu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('opd-gu.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah OPD-GU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="tahun_anggaran" value="{{ \App\Support\TahunAnggaran::selected() }}">
                            <div class="col-md-6"><label class="form-label">Nomor</label><input type="text" class="form-control" name="nomor" required></div>
                            <div class="col-md-6"><label class="form-label">Nomor DBA</label><input type="text" class="form-control" name="nomor_dba" required></div>
                            <div class="col-md-6"><label class="form-label">Tanggal DBA</label><input type="date" class="form-control" name="tanggal_dba" required></div>
                            <div class="col-md-6"><label class="form-label">PPD-GU</label><select class="form-select" name="ppd_gu_id" required><option value="">-- Pilih PPD-GU --</option>@foreach($ppdGus as $ppd)<option value="{{ $ppd->id }}">{{ $ppd->nomor }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Bank Asal</label><input type="text" class="form-control" name="bank_asal" required></div>
                            <div class="col-md-6"><label class="form-label">Jumlah</label><input type="number" step="1" class="form-control js-jumlah" name="jumlah" oninput="calculateJumlahAkhir(this.form)" onchange="calculateJumlahAkhir(this.form)" required></div>
                            <div class="col-md-6"><label class="form-label">Kepada</label><input type="text" class="form-control" name="kepada" required></div>
                            <div class="col-md-6"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp"></div>
                            <div class="col-md-6"><label class="form-label">No Rek Tujuan</label><input type="text" class="form-control" name="no_rek_tujuan" required></div>
                            <div class="col-md-6"><label class="form-label">Bank Tujuan</label><input type="text" class="form-control" name="bank_tujuan" required></div>
                            <div class="col-md-6"><label class="form-label">Potongan</label><input type="number" step="1" class="form-control js-potongan" name="potongan" value="0" oninput="calculateJumlahAkhir(this.form)" onchange="calculateJumlahAkhir(this.form)" required></div>
                            <div class="col-md-6"><label class="form-label">Jumlah Akhir</label><input type="number" step="1" class="form-control js-jumlah-akhir" name="jumlah_akhir" readonly required></div>
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
    <script>
        function calculateJumlahAkhir(form) {
            if (!form) return;

            const jumlahInput = form.querySelector('.js-jumlah');
            const potonganInput = form.querySelector('.js-potongan');
            const jumlahAkhirInput = form.querySelector('.js-jumlah-akhir');

            if (!jumlahInput || !potonganInput || !jumlahAkhirInput) return;

            const jumlah = Number(jumlahInput.value) || 0;
            const potongan = Number(potonganInput.value) || 0;
            const hasil = Math.max(0, jumlah - potongan);

            jumlahAkhirInput.value = hasil;
        }

        function calculateTambah() {
            const formTambah = document.querySelector('#modalTambahOpdGu form');
            calculateJumlahAkhir(formTambah);
        }

        function calculateEdit(modal) {
            if (!modal || !modal.id || !modal.id.startsWith('modalEditOpdGu')) return;
            calculateJumlahAkhir(modal.querySelector('form'));
        }

        function initJumlahAkhirTambah() {
            const formTambah = document.querySelector('#modalTambahOpdGu form');
            calculateJumlahAkhir(formTambah);

            document.addEventListener('input', function(event) {
                if (!event.target.closest('#modalTambahOpdGu')) return;
                if (event.target.classList.contains('js-jumlah') || event.target.classList.contains('js-potongan')) {
                    calculateTambah();
                }
            });

            document.addEventListener('change', function(event) {
                if (!event.target.closest('#modalTambahOpdGu')) return;
                if (event.target.classList.contains('js-jumlah') || event.target.classList.contains('js-potongan')) {
                    calculateTambah();
                }
            });

            document.addEventListener('shown.bs.modal', function(event) {
                if (event.target.id === 'modalTambahOpdGu') {
                    calculateTambah();
                }
            });
        }

        function initJumlahAkhirEdit() {
            document.querySelectorAll('[id^="modalEditOpdGu"] form').forEach((form) => calculateJumlahAkhir(form));

            document.addEventListener('input', function(event) {
                const modal = event.target.closest('[id^="modalEditOpdGu"]');
                if (!modal) return;
                if (event.target.classList.contains('js-jumlah') || event.target.classList.contains('js-potongan')) {
                    calculateEdit(modal);
                }
            });

            document.addEventListener('change', function(event) {
                const modal = event.target.closest('[id^="modalEditOpdGu"]');
                if (!modal) return;
                if (event.target.classList.contains('js-jumlah') || event.target.classList.contains('js-potongan')) {
                    calculateEdit(modal);
                }
            });

            document.addEventListener('shown.bs.modal', function(event) {
                calculateEdit(event.target);
            });
        }

        function initJumlahAkhir() {
            initJumlahAkhirTambah();
            initJumlahAkhirEdit();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initJumlahAkhir);
        } else {
            initJumlahAkhir();
        }
    </script>
    @if ($errors->any())
        <script>
            const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahOpdUp'));
            modalTambah.show();
        </script>
    @endif
@endsection
