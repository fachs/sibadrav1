@extends('layouts.vertical', ['title' => 'RBA','subTitle' => 'Belanja'])
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
                                <div>
                                    <a href="{{ route('rba.pdf', ['puskesmas' => request('puskesmas')]) }}" class="btn btn-success">
                                        <i class="fas fa-file-pdf"></i> Unduh PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            @php
                                $role = strtolower(trim((string) auth()->user()->role));
                                $canDeleteRenbut = in_array($role, ['kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes'], true);
                            @endphp
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
                                            $subtotal = $itemsInKlas->flatten()->sum('jumlah_harga');
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
                                                    <td><strong>{{ number_format($items->sum('jumlah_harga'), 0, ',', '.') }}</strong></td>
                                                </tr>

                                                {{-- COLLAPSE DETAIL --}}
                                                <tr class="collapse" id="{{ $collapseId }}">
                                                    <td colspan="3" class="p-0">
                                                        <div class="p-3">
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Uraian</th>
                                                                        <th>Koefisien</th>
                                                                        <th>Satuan</th>
                                                                        <th>Harga (Rp)</th>
                                                                        <th>PPN (%)</th>
                                                                        <th>Jumlah (Rp)</th>
                                                                        <th>Keterangan</th>
                                                                        <th>Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($items as $sub)
                                                                        <tr>
                                                                            <td>{{ $sub->uraian }}</td>
                                                                            <td>{{ $sub->jumlah }}</td>
                                                                            <td>{{ $sub->satuan }}</td>
                                                                            <td>{{ number_format($sub->harga_satuan, 0, ',', '.') }}</td>
                                                                            <td>-</td>
                                                                            <td>{{ number_format($sub->jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td>{{ $sub->keterangan }}</td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                    <a href="javascript:void(0)"
                                                                                        class="btn btn-info btn-sm btn-edit"
                                                                                        data-id="{{ $sub->id }}"
                                                                                        data-uraian="{{ $sub->uraian }}"
                                                                                        data-jumlah="{{ $sub->jumlah }}"
                                                                                        data-satuan="{{ $sub->satuan }}"
                                                                                        data-harga="{{ $sub->harga_satuan }}"
                                                                                        data-keterangan="{{ $sub->keterangan }}"
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
                                                                                            action="{{ route('rencana-kebutuhan.destroy', $sub->id) }}"
                                                                                            method="POST" class="m-0"
                                                                                            data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                                                <i class="ri-delete-bin-2-line"></i>
                                                                                            </button>
                                                                                        </form>
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
                    let harga = parseFloat(document.getElementById('edit-harga').value) || 0;

                    let total = jumlah * harga;

                    document.getElementById('edit-jumlah-harga').value = formatRupiah(total);
                    document.getElementById('edit-jumlah-harga-hidden').value = total;
                }

                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('.btn-edit');
                    if (!btn || btn.classList.contains('disabled')) return;

                    let id = btn.getAttribute('data-id');

                    // FIELD YANG DIEDIT
                    document.getElementById('edit-id').value = id || '';
                    document.getElementById('edit-uraian').value = btn.getAttribute('data-uraian') || '';
                    document.getElementById('edit-jumlah').value = btn.getAttribute('data-jumlah') || '';
                    document.getElementById('edit-satuan').value = btn.getAttribute('data-satuan') || '';
                    document.getElementById('edit-harga').value = btn.getAttribute('data-harga') || '';
                    document.getElementById('edit-keterangan').value = btn.getAttribute('data-keterangan') || '';

                    // FIELD WAJIB VALIDASI (HIDDEN)
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

                    // hitung awal
                    setTimeout(() => {
                        hitungTotal();
                    }, 100);
                });

                document.getElementById('edit-jumlah').addEventListener('input', hitungTotal);
                document.getElementById('edit-harga').addEventListener('input', hitungTotal);
            </script>
        @endsection
