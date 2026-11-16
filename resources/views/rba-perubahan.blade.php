    @extends('layouts.master')
    @section('title')
        RBA Perubahan
    @endsection
    @section('css')

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
            <div class="page-title-box">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="page-title">RBA Perubahan</h6>
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item">
                                <a href="#">SIBADRA</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">RBA Perubahan</li>
                        </ol>
                    </div>


                </div>
            </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">

                        <div class="card-body">
                            <div class="text-end">

                                {{-- <a href="{{ route('rba-perubahan.pdf') }}" class="btn btn-success">
                                    <i class="fas fa-file-pdf"></i> Unduh PDF
                                </a> --}}
                            </div>
                        </div>
                        <div class="card-body">
                            @php
                                $canDeleteRenbut = auth()->user()->hasAnyRole(['kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
                                $grandTotalJumlah = $grouped->flatten()->sum('jumlah_harga');

                                // Klasifikasi kode rekening
                                $klasifikasi = [
                                    'belanja_pegawai'           => ['prefix' => '5.1.01', 'label' => '5.1.01 - Belanja Pegawai BLUD',                     'color' => 'table-primary'],
                                    'belanja_barang_jasa'       => ['prefix' => '5.1.02', 'label' => '5.1.02 - Belanja Barang dan Jasa BLUD',             'color' => 'table-info'],
                                    'belanja_modal_peralatan'   => ['prefix' => '5.2.02', 'label' => '5.2.02 - Belanja Modal Peralatan dan Mesin BLUD',   'color' => 'table-warning'],
                                    'belanja_modal_gedung'      => ['prefix' => '5.2.03', 'label' => '5.2.03 - Belanja Modal Gedung dan Bangunan BLUD',   'color' => 'table-success'],
                                    'belanja_modal_jalan'       => ['prefix' => '5.2.04', 'label' => '5.2.04 - Belanja Modal Jalan, Jaringan dan Irigasi BLUD', 'color' => 'table-secondary'],
                                ];

                                // Kelompokkan $grouped berdasarkan klasifikasi
                                $groupedByKlasifikasi = [];
                                foreach ($klasifikasi as $key => $klas) {
                                    $groupedByKlasifikasi[$key] = $grouped->filter(function ($items) use ($klas) {
                                        $kodeRek = optional($items->first()->akuns)->kode_rek ?? '';
                                        return str_starts_with($kodeRek, $klas['prefix']);
                                    });
                                }
                                
                                $total_5_2 = $grouped->filter(function ($items) {
                                    $kodeRek = optional($items->first()->akuns)->kode_rek ?? '';
                                    return str_starts_with($kodeRek, '5.2');
                                })->flatten()->sum('jumlah_harga');
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
                                    @php $printed_5_2 = false; @endphp
                                    @foreach ($klasifikasi as $key => $klas)
                                        @if (str_starts_with($klas['prefix'], '5.2') && !$printed_5_2 && $total_5_2 > 0)
                                            <tr class="table-light fw-bold">
                                                <td colspan="2">5.2 - BELANJA MODAL</td>
                                                <td>{{ number_format($total_5_2, 0, ',', '.') }}</td>
                                            </tr>
                                            @php $printed_5_2 = true; @endphp
                                        @endif
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
                                                    $collapseId = 'akun_perubahan_' . $akun_id;
                                                    $first = $items->first();
                                                @endphp

                                                {{-- HEADER AKUN (bisa collapse ke detail) --}}
                                                <tr class="{{ $klas['color'] }}"
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
                                                                                        class="btn btn-warning btn-sm btn-edit"
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
                                        <th colspan="2" class="text-end">Total Jumlah Anggaran</th>
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
                                    <input type="text" name="uraian" id="edit-uraian" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label>Jumlah</label>
                                    <input type="number" name="jumlah" id="edit-jumlah" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label>Satuan</label>
                                    <input type="text" name="satuan" id="edit-satuan" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label>Harga Satuan</label>
                                    <input type="number" name="harga_satuan" id="edit-harga" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label>Total Harga</label>
                                    <input type="text" id="edit-jumlah-harga" class="form-control" readonly>
                                </div>
                                <input type="hidden" name="jumlah_harga" id="edit-jumlah-harga-hidden">
                                <div class="mb-3">
                                    <label>Spesifikasi</label>
                                    <textarea name="keterangan" id="edit-keterangan" class="form-control"></textarea>
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
            @endsection @section('scripts')

            <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
            <script src="{{ URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js') }}"></script>

            <script src="{{ URL::asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>

            <script src="{{ URL::asset('assets/js/app.js') }}"></script>

            <script>


                function hitungTotal() {
                    let jumlah = parseFloat(document.getElementById('edit-jumlah').value) || 0;
                    let harga = parseFloat(document.getElementById('edit-harga').value) || 0;

                    let total = jumlah * harga;

                    document.getElementById('edit-jumlah-harga').value = formatRupiah(total);
                    document.getElementById('edit-jumlah-harga-hidden').value = total;
                }

                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.addEventListener('click', function() {

                        let id = this.dataset.id;

                        // FIELD YANG DIEDIT
                        document.getElementById('edit-id').value = id;
                        document.getElementById('edit-uraian').value = this.dataset.uraian;
                        document.getElementById('edit-jumlah').value = this.dataset.jumlah;
                        document.getElementById('edit-satuan').value = this.dataset.satuan;
                        document.getElementById('edit-harga').value = this.dataset.harga;
                        document.getElementById('edit-keterangan').value = this.dataset.keterangan;

                        // FIELD WAJIB VALIDASI (HIDDEN)
                        document.getElementById('edit-nama_blud').value = this.dataset.nama_blud;
                        document.getElementById('edit-nama_unit').value = this.dataset.nama_unit;
                        document.getElementById('edit-tahun_anggaran').value = this.dataset.tahun_anggaran;
                        document.getElementById('edit-program').value = this.dataset.program;
                        document.getElementById('edit-kegiatan').value = this.dataset.kegiatan;
                        document.getElementById('edit-subkegiatan').value = this.dataset.subkegiatan;
                        document.getElementById('edit-ruang').value = this.dataset.ruang;
                        document.getElementById('edit-spesifikasi').value = this.dataset.spesifikasi;
                        document.getElementById('edit-sumber_dana').value = this.dataset.sumber_dana;

                        document.getElementById('formEdit').action = `/rencana-kebutuhan/${id}`;

                        // hitung awal
                        setTimeout(() => {
                            hitungTotal();
                        }, 100);
                    });
                });

                document.getElementById('edit-jumlah').addEventListener('input', hitungTotal);
                document.getElementById('edit-harga').addEventListener('input', hitungTotal);
            </script>
        @endsection
