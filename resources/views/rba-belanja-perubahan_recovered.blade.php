@extends('layouts.vertical', ['title' => 'RBA Belanja Perubahan','subTitle' => 'RBA Perubahan'])
    @section('css')
        <!-- DataTables -->

        <style>
            
            .select2-container {
                width: 100% !important;
            }

            .select2-dropdown {
                z-index: 9999 !important;
            }
        </style>
    @endsection
    @section('body')

        <body data-sidebar="dark">
            @endsection @section('content')

            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">

                        <div class="card-body">
                            @php
                                $role = strtolower(trim((string) auth()->user()->role));
                                $canDeleteRenbut = in_array($role, ['kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes'], true);
                                $hasPending = collect($groupedByKlasifikasi)->flatten()->where('status_perubahan', 'pending')->isNotEmpty();
                            @endphp
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @if(strcasecmp(Auth::user()->role, 'Superuser') === 0)
                                    <form method="GET" action="{{ route('rba.belanja') }}" class="d-flex align-items-center">
                                        <label for="puskesmasFilter" class="me-2 mb-0 fw-bold">Pilih Puskesmas:</label>
                                        <select name="puskesmas" id="puskesmasFilter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                            <option value="">-- Semua Puskesmas --</option>
                                            @foreach($puskesmasList as $p)
                                                <option value="{{ $p->nama }}" {{ request('puskesmas') == $p->nama ? 'selected' : '' }}>{{ $p->nama }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    @endif
                                </div>
                                <div class="d-flex gap-2">
                                    @if(in_array($role, ['klaster 1', 'klaster 2', 'klaster 3', 'klaster 4']))
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalPergeseranAkun">
                                            <i class="ri-exchange-box-line"></i> Pengajuan Perubahan
                                        </button>
                                    @endif
                                    @if($role === 'kepala puskesmas' && $hasPending)
                                        <form action="{{ route('rba-perubahan.belanja.verifikasi-semua') }}" method="POST" onsubmit="return confirm('Verifikasi semua perubahan yang pending?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ri-check-double-line"></i> Verifikasi Semua
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('rba.pdf', ['puskesmas' => request('puskesmas')]) }}" class="btn btn-success">
                                        <i class="fas fa-file-pdf"></i> Unduh PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Kode Rekening</th>
                                        <th>Uraian</th>
                                        <th>Jumlah Harga (Rp)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {{-- STATIC HEADERS FOR 5 AND 5.1 --}}
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="2">5 - BELANJA</td>
                                        <td>{{ number_format($total_5, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="table-light fw-bold">
                                        <td colspan="2">5.1 - BELANJA OPERASIONAL</td>
                                        <td>{{ number_format($total_5_1, 0, ',', '.') }}</td>
                                    </tr>
                                    @foreach ($klasifikasi as $key => $klas)
                                        @php
                                            $itemsInKlas = $groupedByKlasifikasi[$key];
                                            $subtotal = $itemsInKlas->flatten()->sum('proposed_jumlah_harga');
                                        @endphp

                                        @if ($itemsInKlas->isNotEmpty())
                                            {{-- BARIS HEADER KLASIFIKASI (tidak collapse) --}}
                                            <tr class="{{ $klas['color'] }} fw-bold">
                                                <td colspan="2">{{ $klas['label'] }}</td>
                                                <td>{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            </tr>

                                            @foreach ($itemsInKlas as $akun_id => $items)
                                                @php
                                                    $collapseId = 'akun_' . $akun_id;
                                                    $first = $items->first();
                                                @endphp

                                                {{-- HEADER AKUN (bisa collapse ke detail) --}}
                                                <tr class="table-secondary"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    style="cursor: pointer;">
                                                    <td class="ps-4">
                                                        <i class="fas fa-chevron-down me-2 small"></i>
                                                        <strong>{{ $first->akuns->kode_rek ?? '-' }}</strong>
                                                    </td>
                                                    <td><strong>{{ $first->akuns->uraian ?? '-' }}</strong></td>
                                                    <td><strong>{{ number_format($items->sum('proposed_jumlah_harga'), 0, ',', '.') }}</strong></td>
                                                </tr>

                                                {{-- COLLAPSE DETAIL --}}
                                                <tr class="collapse" id="{{ $collapseId }}">
                                                    <td colspan="3" class="p-0">
                                                        <div class="p-3">
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="table-light text-center">
                                                                    <tr>
                                                                        <th colspan="7" class="table-secondary">Sebelum Pergesaran (Murni)</th>
                                                                        <th colspan="7" class="table-info">Sesudah Pergesaran</th>
                                                                        <th rowspan="2" class="align-middle">Aksi</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Uraian</th>
                                                                        <th>Koef</th>
                                                                        <th>Satuan</th>
                                                                        <th>Harga</th>
                                                                        <th>PPN</th>
                                                                        <th>Jumlah</th>
                                                                        <th>Ket</th>
                                                                        
                                                                        <th>Uraian</th>
                                                                        <th>Koef</th>
                                                                        <th>Satuan</th>
                                                                        <th>Harga</th>
                                                                        <th>PPN</th>
                                                                        <th>Jumlah</th>
                                                                        <th>Ket</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($items as $sub)
                                                                        <tr>
                                                                            <!-- Data Asli -->
                                                                            <td>{{ $sub->uraian }}</td>
                                                                            <td class="text-center">{{ $sub->jumlah }}</td>
                                                                            <td class="text-center">{{ $sub->satuan }}</td>
                                                                            <td class="text-end">{{ number_format($sub->harga_satuan, 0, ',', '.') }}</td>
                                                                            <td class="text-center">-</td>
                                                                            <td class="text-end">{{ number_format($sub->jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td>{{ $sub->keterangan }}</td>

                                                                            <!-- Data Perubahan -->
                                                                            <td class="{{ $sub->uraian_perubahan !== null && $sub->uraian_perubahan != $sub->uraian ? 'text-success fw-bold' : '' }}">{{ $sub->proposed_uraian }}</td>
                                                                            <td class="text-center {{ $sub->jumlah_perubahan !== null && $sub->jumlah_perubahan != $sub->jumlah ? 'text-success fw-bold' : '' }}">{{ $sub->proposed_jumlah }}</td>
                                                                            <td class="text-center {{ $sub->satuan_perubahan !== null && $sub->satuan_perubahan != $sub->satuan ? 'text-success fw-bold' : '' }}">{{ $sub->proposed_satuan }}</td>
                                                                            <td class="text-end {{ $sub->harga_satuan_perubahan !== null && $sub->harga_satuan_perubahan != $sub->harga_satuan ? 'text-success fw-bold' : '' }}">{{ number_format($sub->proposed_harga_satuan, 0, ',', '.') }}</td>
                                                                            <td class="text-center">-</td>
                                                                            <td class="text-end {{ $sub->jumlah_harga_perubahan !== null && $sub->jumlah_harga_perubahan != $sub->jumlah_harga ? 'text-success fw-bold' : '' }}">{{ number_format($sub->proposed_jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td class="{{ $sub->keterangan_perubahan !== null && $sub->keterangan_perubahan != $sub->keterangan ? 'text-success fw-bold' : '' }}">{{ $sub->proposed_keterangan }}</td>

                                                                            <td class="text-center">
                                                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                    <a href="javascript:void(0)"
                                                                                        class="btn btn-info btn-sm btn-edit"
                                                                                        data-id="{{ $sub->id }}"
                                                                                        data-uraian="{{ $sub->proposed_uraian }}"
                                                                                        data-jumlah="{{ $sub->proposed_jumlah }}"
                                                                                        data-satuan="{{ $sub->proposed_satuan }}"
                                                                                        data-harga="{{ $sub->proposed_harga_satuan }}"
                                                                                        data-keterangan="{{ $sub->proposed_keterangan }}"
                                                                                        data-nama_blud="{{ $sub->nama_blud }}"
                                                                                        data-nama_unit="{{ $sub->nama_unit }}"
                                                                                        data-tahun_anggaran="{{ $sub->tahun_anggaran }}"
                                                                                        data-program="{{ $sub->program }}"
                                                                                        data-kegiatan="{{ $sub->kegiatan }}"
                                                                                        data-subkegiatan="{{ $sub->subkegiatan }}"
                                                                                        data-ruang="{{ $sub->ruang }}"
                                                                                        data-spesifikasi="{{ $sub->spesifikasi }}"
                                                                                        data-sumber_dana="{{ $sub->sumber_dana }}"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#modalEdit">
                                                                                        <i class="ri-edit-box-line"></i>
                                                                                    </a>
                                                                                    @if ($canDeleteRenbut)
                                                                                        <form
                                                                                            action="{{ route('rba-perubahan.belanja.zero-out', $sub->id) }}"
                                                                                            method="POST" class="m-0"
                                                                                            data-confirm-delete data-confirm-message="Yakin ingin menghapus data perubahan ini (nilai menjadi 0)?">
                                                                                            @csrf
                                                                                            @method('PATCH')
                                                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                                                <i class="ri-delete-bin-2-line"></i>
                                                                                            </button>
                                                                                        </form>
                                                                                    @endif
                                                                                    
                                                                                    @if(strtolower(trim((string) auth()->user()->role)) === 'kepala puskesmas' && $sub->status_perubahan === 'pending')
                                                                                        <form action="{{ route('rba-perubahan.belanja.verifikasi', $sub->id) }}" method="POST" class="m-0" onsubmit="return confirm('Verifikasi perubahan ini?');">
                                                                                            @csrf
                                                                                            @method('PATCH')
                                                                                            <button type="submit" class="btn btn-success btn-sm" title="Verifikasi">
                                                                                                <i class="ri-check-line"></i>
                                                                                            </button>
                                                                                        </form>
                                                                                        <form action="{{ route('rba-perubahan.belanja.tolak', $sub->id) }}" method="POST" class="m-0" onsubmit="return confirm('Tolak perubahan ini?');">
                                                                                            @csrf
                                                                                            @method('PATCH')
                                                                                            <button type="submit" class="btn btn-warning btn-sm" title="Tolak">
                                                                                                <i class="ri-close-line"></i>
                                                                                            </button>
                                                                                        </form>
                                                                                    @endif
                                                                                    
                                                                                    @if($sub->status_perubahan === 'pending')
                                                                                        <span class="badge bg-warning" title="Menunggu Verifikasi">Pending</span>
                                                                                    @endif
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            {{-- SUBTOTAL PER KLASIFIKASI --}}
                                            <tr class="table-light">
                                                <th colspan="2" class="text-end fst-italic">Subtotal {{ $klas['label'] }}</th>
                                                <th class="fst-italic">{{ number_format($subtotal, 0, ',', '.') }}</th>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-warning">
                                        <th colspan="2" class="text-end">JUMLAH</th>
                                        <th>{{ number_format($grandTotalJumlah, 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->

            <div class="modal fade" id="modalEdit" tabindex="-1">
                <div class="modal-dialog modal-md">
                    <form method="POST" id="formEdit">
                        @csrf
                        @method('PUT')

                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Rencana Kebutuhan</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                    <input type="number" name="jumlah" id="edit-jumlah" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label>Satuan</label>
                                    <input type="text" name="satuan" id="edit-satuan" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label>Harga Satuan</label>
                                    <input type="number" name="harga_satuan" id="edit-harga" class="form-control" required>
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
                                <button type="submit" class="btn btn-success">Simpan</button>
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL PERGESERAN LINTAS AKUN -->
            <div class="modal fade" id="modalPergeseranAkun" tabindex="-1" aria-labelledby="modalPergeseranAkunLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form id="formPergeseran" action="{{ route('rba-perubahan.belanja.pergeseran-lintas-akun') }}" method="POST">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-white">
                                <h5 class="modal-title" id="modalPergeseranAkunLabel">Pengajuan Perubahan (Lintas Kode Rekening)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-4">
                                <h6 class="fw-bold mb-3">1. Data Sumber (Awal)</h6>
                                <div class="mb-3">
                                    <label class="form-label">Pilih Rencana Belanja Awal</label>
                                    <select name="sumber_renbut_id" id="sumber_renbut_id" class="form-select select2" required>
                                        <option value="">-- Pilih Item --</option>
                                        @foreach($grouped->flatten() as $item)
                                            <option value="{{ $item->id }}" 
                                                    data-uraian="{{ $item->proposed_uraian }}" 
                                                    data-jumlah="{{ $item->proposed_jumlah }}" 
                                                    data-satuan="{{ $item->proposed_satuan }}" 
                                                    data-harga="{{ $item->proposed_harga_satuan }}">
                                                {{ optional($item->akuns)->kode_rek }} - {{ $item->proposed_uraian }} (Rp {{ number_format($item->proposed_jumlah_harga, 0, ',', '.') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Jumlah Tersisa</label>
                                        <input type="number" name="sumber_jumlah" id="sumber_jumlah" class="form-control" required min="0" step="any">
                                        <small class="text-muted">Isi 0 jika seluruhnya dipindahkan.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Harga Satuan</label>
                                        <input type="number" name="sumber_harga_satuan" id="sumber_harga_satuan" class="form-control" required min="0" step="any">
                                    </div>
                                </div>

                                <hr class="my-4">

                                <h6 class="fw-bold mb-3">2. Data Tujuan (Baru)</h6>
                                <div class="mb-3">
                                    <label class="form-label">Pilih Kode Rekening Tujuan</label>
                                    <select name="tujuan_akun_id" id="tujuan_akun_id" class="form-select select2" required>
                                        <option value="">-- Pilih Akun --</option>
                                        @foreach($allAkuns as $akun)
                                            <option value="{{ $akun->id }}">{{ $akun->kode_rek }} - {{ $akun->uraian }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Uraian Tujuan</label>
                                    <input type="text" name="tujuan_uraian" id="tujuan_uraian" class="form-control" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Jumlah</label>
                                        <input type="number" name="tujuan_jumlah" id="tujuan_jumlah" class="form-control" required min="0" step="any">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Satuan</label>
                                        <input type="text" name="tujuan_satuan" id="tujuan_satuan" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Harga Satuan</label>
                                        <input type="number" name="tujuan_harga_satuan" id="tujuan_harga_satuan" class="form-control" required min="0" step="any">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Total Harga Tujuan</label>
                                    <input type="text" id="tujuan_jumlah_harga_display" class="form-control" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea name="tujuan_keterangan" id="tujuan_keterangan" class="form-control"></textarea>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit" class="btn btn-warning">Ajukan Perubahan</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endsection @section('script')

            <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
            <script src="{{ URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js') }}"></script>

            <script src="{{ URL::asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>

            <script src="{{ URL::asset('assets/js/app.js') }}"></script>

            <script>
                function formatRupiah(angka) {
                    return new Intl.NumberFormat('id-ID').format(angka);
                }

                function hitungTotal() {
                    let jumlah = parseFloat(document.getElementById('edit-jumlah').value) || 0;
