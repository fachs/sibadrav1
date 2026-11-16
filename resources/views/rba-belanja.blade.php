@extends('layouts.vertical', ['title' => 'RBA Belanja','subTitle' => 'RBA'])
    @section('css')
        <link rel="stylesheet" href="{{ asset('css/rba-tables.css') }}">

        <style>
            
            .select2-container {
                width: 100% !important;
            }

            .select2-dropdown {
                z-index: 9999 !important;
            }
        </style>
    @endsection
    @section('content')
            <div class="row">
                <div class="col-12">
                    <div class="card rba-budget">
                        
                        <div class="card-body">
                            @include('partials.rba-tahun-filter')
                            <div class="rba-toolbar">
                                <div class="rba-toolbar-main">
                                    @if(Auth::user()->hasRole('Superuser'))
                                    <form method="GET" action="{{ route('rba.belanja') }}" class="rba-filter">
                                        @include('partials.rba-tahun-input')
                                        <label for="puskesmasFilter" class="form-label mb-0">Unit BLUD</label>
                                        <select name="puskesmas" id="puskesmasFilter" class="form-select" onchange="this.form.submit()">
                                            <option value="">-- Semua Puskesmas --</option>
                                            @foreach($puskesmasList as $p)
                                                <option value="{{ $p->nama }}" {{ request('puskesmas') == $p->nama ? 'selected' : '' }}>{{ $p->nama }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-outline-primary">Tampilkan</button></noscript>
                                    </form>
                                    @endif
                                </div>
                                <div class="rba-toolbar-actions">
                                    @if(!Auth::user()->hasRole('Superuser'))
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalKinerjaKegiatan">
                                        <i class="ri-list-check-3" aria-hidden="true"></i> Kinerja Kegiatan
                                    </button>
                                    @endif
                                    <a href="{{ route('rba.pdf', ['puskesmas' => request('puskesmas'), 'tahun' => request('tahun')]) }}" class="btn btn-success">
                                        <i class="ri-file-pdf-2-line" aria-hidden="true"></i> Unduh PDF
                                    </a>
                                </div>
                            </div>
                            @php
                                $canDeleteRenbut = auth()->user()->hasAnyRole(['kepala puskesmas', 'klaster 1', 'kasubbag tu', 'kepala labkes']);
                            @endphp
                            <div class="table-responsive rba-table-wrap" role="region" aria-labelledby="belanja-table-title" tabindex="0">
                            <table class="table align-middle rba-table rba-table--belanja">
                                <caption class="visually-hidden">Rencana belanja tahun anggaran {{ $tahunRba }}, dikelompokkan berdasarkan klasifikasi dan rekening.</caption>
                                <colgroup><col class="rba-col-code"><col><col class="rba-col-amount"></colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">Kode Rekening</th>
                                        <th scope="col">Uraian</th>
                                        <th scope="col" class="text-end">Jumlah Harga (Rp)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {{-- STATIC HEADERS FOR 5 AND 5.1 --}}
                                    <tr class="rba-section rba-section-root">
                                        <th scope="row" colspan="2">5 - BELANJA</th>
                                        <td class="text-end rba-amount">{{ number_format($total_5, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="rba-section">
                                        <th scope="row" colspan="2">5.1 - BELANJA OPERASIONAL</th>
                                        <td class="text-end rba-amount">{{ number_format($total_5_1, 0, ',', '.') }}</td>
                                    </tr>
                                    @php $printed_5_2 = false; @endphp
                                    @foreach ($klasifikasi as $key => $klas)
                                        @if (str_starts_with($klas['prefix'], '5.2') && !$printed_5_2 && $total_5_2 > 0)
                                            <tr class="rba-section">
                                                <th scope="row" colspan="2">5.2 - BELANJA MODAL</th>
                                                <td class="text-end rba-amount">{{ number_format($total_5_2, 0, ',', '.') }}</td>
                                            </tr>
                                            @php $printed_5_2 = true; @endphp
                                        @endif
                                        @php
                                            $itemsInKlas = $groupedByKlasifikasi[$key];
                                            $subtotal = $itemsInKlas->flatten()->sum('jumlah_harga');
                                        @endphp

                                        @if ($itemsInKlas->isNotEmpty())
                                            {{-- BARIS HEADER KLASIFIKASI (tidak collapse) --}}
                                            <tr class="rba-group rba-group--{{ str_replace('table-', '', $klas['color']) }}">
                                                <th scope="row" colspan="2"><div class="d-flex align-items-center flex-wrap gap-2"><i class="ri-folder-2-line" aria-hidden="true"></i><span>{{ $klas['label'] }}</span><span class="rba-group-count">{{ $itemsInKlas->flatten()->count() }} rincian</span></div></th>
                                                <td class="text-end rba-amount">{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            </tr>

                                            @foreach ($itemsInKlas as $akun_id => $items)
                                                @php
                                                    $collapseId = 'akun_' . $akun_id;
                                                    $first = $items->first();
                                                @endphp

                                                {{-- HEADER AKUN (bisa collapse ke detail) --}}
                                                <tr class="rba-item"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                    <td>
                                                        <button type="button" class="rba-account-toggle" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}" aria-label="Lihat rincian akun {{ $first->akuns->kode_rek ?? '-' }}">
                                                            <i class="ri-arrow-right-s-line rba-disclosure-icon" aria-hidden="true"></i>
                                                            <span class="rba-account-code">{{ $first->akuns->kode_rek ?? '-' }}</span>
                                                        </button>
                                                    </td>
                                                    <td><div class="fw-semibold text-body-emphasis">{{ $first->akuns->uraian ?? '-' }}</div><div class="small text-body-secondary mt-1">{{ $items->count() }} rincian kebutuhan</div></td>
                                                    <td class="text-end rba-amount fw-semibold">{{ number_format($items->sum('jumlah_harga'), 0, ',', '.') }}</td>
                                                </tr>

                                                {{-- COLLAPSE DETAIL --}}
                                                <tr class="collapse" id="{{ $collapseId }}">
                                                    <td colspan="3" class="p-0">
                                                        <div class="rba-detail-panel">
                                                            <div class="table-responsive rba-table-wrap" role="region" aria-label="Rincian kebutuhan akun {{ $first->akuns->kode_rek ?? '-' }}" tabindex="0">
                                                            <table class="table table-sm align-middle mb-0 rba-detail-table">
                                                                <caption class="visually-hidden">Rincian kebutuhan akun {{ $first->akuns->kode_rek ?? '-' }}</caption>
                                                                <thead>
                                                                    <tr>
                                                                        <th scope="col">Uraian</th>
                                                                        <th scope="col" class="text-end">Volume</th>
                                                                        <th scope="col">Satuan</th>
                                                                        <th scope="col" class="text-end">Harga (Rp)</th>
                                                                        <th scope="col" class="text-center">PPN (%)</th>
                                                                        <th scope="col" class="text-end">Jumlah (Rp)</th>
                                                                        <th scope="col">Keterangan</th>
                                                                        <th scope="col" class="text-center">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($items as $sub)
                                                                        <tr>
                                                                            <td>{{ $sub->uraian }}</td>
                                                                            <td class="text-end rba-amount">{{ $sub->jumlah }}</td>
                                                                            <td>{{ $sub->satuan }}</td>
                                                                            <td class="text-end rba-amount">{{ number_format($sub->harga_satuan, 0, ',', '.') }}</td>
                                                                            <td class="text-center">-</td>
                                                                            <td class="text-end rba-amount fw-semibold">{{ number_format($sub->jumlah_harga, 0, ',', '.') }}</td>
                                                                            <td>{{ $sub->keterangan }}</td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center justify-content-center gap-1">

                                                                                    @if ($canDeleteRenbut)
                                                                                        <form
                                                                                            action="{{ route('rencana-kebutuhan.destroy', $sub->id) }}"
                                                                                            method="POST" class="m-0"
                                                                                            data-confirm-delete data-confirm-message="Yakin ingin menghapus data ini?">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus Rencana Kebutuhan" aria-label="Hapus Rencana Kebutuhan">
                                                                                                <i class="ri-delete-bin-2-line" aria-hidden="true"></i>
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
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            {{-- SUBTOTAL PER KLASIFIKASI --}}
                                            <tr class="rba-subtotal">
                                                <th scope="row" colspan="2" class="text-end">Subtotal {{ $klas['label'] }}</th>
                                                <td class="text-end rba-amount">{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    @if($grouped->isEmpty())
                                        <tr><td colspan="3" class="rba-empty"><i class="ri-file-list-3-line" aria-hidden="true"></i><p class="fw-semibold text-body-emphasis mb-1">Belum ada rencana belanja.</p><p class="small mb-0">Rincian belanja akan tampil setelah rencana kebutuhan ditambahkan.</p></td></tr>
                                    @endif
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th scope="row" colspan="2">TOTAL RENCANA BELANJA</th>
                                        <td class="text-end rba-amount">{{ number_format($grandTotalJumlah, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
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
                                <div class="form-actions">
                                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-save">Simpan</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal fade" id="modalKinerjaKegiatan" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <form action="{{ route('rba.kinerja-kegiatan.store') }}" method="POST">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Kinerja Kegiatan (Tahun {{ $tahun }})</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="tahun" value="{{ $tahun }}">
                                
                                <h6 class="fw-bold">Capaian Program</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label>Tolak Ukur Kinerja</label>
                                        <textarea name="capaian_program_tolak_ukur" class="form-control" rows="2">{{ old('capaian_program_tolak_ukur', $kinerjaKegiatan->capaian_program_tolak_ukur ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Target Kinerja</label>
                                        <input type="text" name="capaian_program_target" class="form-control" value="{{ old('capaian_program_target', $kinerjaKegiatan->capaian_program_target ?? '') }}">
                                    </div>
                                </div>

                                <h6 class="fw-bold mt-3">Masukan</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label>Tolak Ukur Kinerja</label>
                                        <textarea name="masukan_tolak_ukur" class="form-control" rows="2">{{ old('masukan_tolak_ukur', $kinerjaKegiatan->masukan_tolak_ukur ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Target Kinerja</label>
                                        <input type="text" name="masukan_target" class="form-control" value="{{ old('masukan_target', $kinerjaKegiatan->masukan_target ?? '') }}">
                                    </div>
                                </div>

                                <h6 class="fw-bold mt-3">Keluaran</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label>Tolak Ukur Kinerja</label>
                                        <textarea name="keluaran_tolak_ukur" class="form-control" rows="2">{{ old('keluaran_tolak_ukur', $kinerjaKegiatan->keluaran_tolak_ukur ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Target Kinerja</label>
                                        <input type="text" name="keluaran_target" class="form-control" value="{{ old('keluaran_target', $kinerjaKegiatan->keluaran_target ?? '') }}">
                                    </div>
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

            @endsection @section('script')

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
