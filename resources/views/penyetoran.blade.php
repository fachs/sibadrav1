@extends('layouts.vertical', ['title' => 'Penyetoran','subTitle' => 'Pendapatan'])


@php \Carbon\Carbon::setLocale('id'); @endphp
@section('css')
    <!-- DataTables -->
    <link href="{{URL::asset('assets/libs/bootstrap-editable/bootstrap-editable.min.css')}}" rel="stylesheet">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            .card,
            .card * {
                visibility: visible;
            }

            .card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            button {
                display: none;
            }

            table {
                font-size: 12px;
            }
        }
    </style>
@endsection

@section('body')

    <body data-sidebar="dark">
    @endsection

    @section('content')
        @php
            $bulanList = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];
        @endphp


        <div class="row">
            <div class="col-12">
                <div class="card">

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
                            <tr>
                                <td><strong>Periode</strong></td>
                                <td>:</td>
                                <td>{{ $selectedPeriode ?: 'Semua Periode' }}</td>
                            </tr>

                        </table>
                    </div>

                    <div class="card-body pt-0">
                        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                            <div class="d-flex gap-2">
                               
                            </div>

                            <div>

                            </div>

                            <form method="GET" action="{{ url()->current() }}"
                                class="row g-2 align-items-end justify-content-end">
                                <div class="col-md-7 col-lg-6">
                                    <label for="periode" class="form-label mb-1"><strong>Pilih Periode</strong></label>
                                    <select name="periode" id="periode" class="form-select">
                                        <option value="">-- Pilih Bulan --</option>
                                        @foreach ($bulanList as $bulan)
                                            <option value="{{ $bulan }}"
                                                {{ $selectedPeriode === $bulan ? 'selected' : '' }}>
                                                {{ $bulan }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary">Cari</button>
                                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nomor TBP</th>
                                    <th style="width: 160px;">Tanggal Setor</th>
                                    <th>Cara Pembayaran</th>
                                    <th>Kode Rekening</th>
                                    @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                        <th>BLUD</th>
                                    @endif
                                    <th style="width: 120px;">Uraian</th>
                                    <th>Jumlah</th>

                                    
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @if (empty($selectedPeriode))
                                    <tr>
                                        <td colspan="7" class="text-center">Silakan pilih periode terlebih dahulu.</td>
                                    </tr>
                                @else --}}
                                @forelse ($penerimaans as $item)
                                    <tr>
                                        <td>
                                            <a href="#"
                                            class="editable-nomor-tbp"
                                            data-type="text"
                                            data-pk="{{ $item->id }}"
                                            data-url="{{ route('penerimaan.update-nomor-tbp', $item->id) }}"
                                            data-title="Edit Nomor TBP">
                                                {{ $item->nomor_tbp ?? 'Isi nomor TBP' }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span id="tanggal-setor-text-{{ $item->id }}">
                                                    {{ $item->tanggal_setor ? \Carbon\Carbon::parse($item->tanggal_setor)->translatedFormat('d F Y') : '-' }}
                                                </span>


                                                    <button type="button"
                                                        class="btn"
                                                        onclick="toggleEditTanggalSetor({{ $item->id }})"
                                                        title="Edit Tanggal Setor">
                                                        <i class="typcn typcn-edit"></i>
                                                    </button>

                                            </div>

                                            <form id="form-tanggal-setor-{{ $item->id }}"
                                                action="{{ route('penerimaan.update-tanggal-setor', $item->id) }}"
                                                method="POST"
                                                class="mt-2 d-none">
                                                @csrf
                                                @method('PATCH')
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="date"
                                                        name="tanggal_setor"
                                                        class="form-control form-control-sm"
                                                        value="{{ $item->tanggal_setor ? \Carbon\Carbon::parse($item->tanggal_setor)->format('Y-m-d') : now()->format('Y-m-d') }}"
                                                        min="2026-01-01"
                                                        max="2026-12-31"
                                                        required>
                                                    <div class="form-actions">
                                                        <button type="button"
                                                            class="btn btn-cancel"
                                                            onclick="toggleEditTanggalSetor({{ $item->id }})">
                                                            Batal
                                                        </button>
                                                        <button type="submit" class="btn btn-save">Simpan</button>
                                                    </div>
                                                    <input type="hidden" name="periode" value="{{ $selectedPeriode }}">
                                                </div>
                                            </form>
                                        </td>
                                        <td>{{ $item->cara_pembayaran }}</td>
                                        <td>{{ $item->kode_rekening }}</td>
                                        @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                                            <td>{{ optional($item->unitBlud)->nama ?? '-' }}</td>
                                        @endif
                                        <td>{{ $item->uraian }}</td>
                                        <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>

                                        
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">Data penyetoran pada periode ini tidak
                                            ditemukan.</td>
                                    </tr>
                                @endforelse
                                {{-- @endif --}}
                            </tbody>
                        </table>
                        <div class="mt-4  mb-4 d-flex justify-content-center">
                            {{ $penerimaans->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('scripts')
        <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/bootstrap-editable/bootstrap-editable.min.js') }}"></script>
        {{-- Nonaktifkan dulu ini jika bentrok --}}
        <script src="{{ URL::asset('assets/js/pages/form-xeditable.init.js') }}"></script>
        <script src="{{ URL::asset('assets/js/app.js') }}"></script>

        <script>
        function toggleEditTanggalSetor(id) {
            const form = document.getElementById('form-tanggal-setor-' + id);
            form.classList.toggle('d-none');
        }
        </script>

        <script>
            $(function () {
                $.fn.editable.defaults.mode = 'inline';

                $('.editable-nomor-tbp').editable({
                    type: 'text',
                    name: 'nomor_tbp',
                    inputclass: 'form-control-sm',
                    ajaxOptions: { type: 'PATCH', dataType: 'json' },
                    params: function (params) {
                        return { _token: '{{ csrf_token() }}', nomor_tbp: params.value };
                    }
                });

                // $('.editable-tanggal-setor').editable({
                //     type: 'date',
                //     name: 'tanggal_setor',
                //     inputclass: 'form-control-sm',
                //     format: 'yyyy-mm-dd',
                //     viewformat: 'yyyy-mm-dd',
                //     datepicker: {
                //         autoclose: true,
                //         todayHighlight: true,
                //         startDate: '2026-01-01',
                //         endDate: '2026-12-31'
                //     },
                //     ajaxOptions: { type: 'PATCH', dataType: 'json' },
                //     params: function (params) {
                //         return { _token: '{{ csrf_token() }}', tanggal_setor: params.value };
                //     }
                // });
            });
            </script>

        <script>
            function printPage() {
                window.print();
            }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const kodeRekeningEl = document.getElementById('kode_rekening');
                const uraianEl = document.getElementById('uraian');

                const uraianMap = {
                    '4.1.04.16.02.0001': ['Non Kapitasi', 'Prolanis', 'Jasa Giro', 'PKS', 'Lain-lain'],
                    '4.3.03.02.01.0001': ['Kapitasi'],
                    '4.1.02.01.01.0001': ['Retribusi'],
                    '4.1.02.01.01.0006': ['Retribusi'],
                };

                function setUraianOptions(selectedKode) {
                    const options = uraianMap[selectedKode] || [];
                    uraianEl.innerHTML = '';

                    if (!options.length) {
                        uraianEl.innerHTML = '<option value="">-- Pilih Uraian --</option>';
                        return;
                    }

                    if (options.length > 1) {
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = '-- Pilih Uraian --';
                        placeholder.selected = true;
                        placeholder.disabled = true;
                        uraianEl.appendChild(placeholder);
                    }

                    options.forEach((item) => {
                        const opt = document.createElement('option');
                        opt.value = item;
                        opt.textContent = item;
                        uraianEl.appendChild(opt);
                    });

                    if (options.length === 1) {
                        uraianEl.value = options[0];
                    }
                }

                kodeRekeningEl.addEventListener('change', function() {
                    setUraianOptions(this.value);
                });

                setUraianOptions(kodeRekeningEl.value);
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const uraianMap = {
                    '4.1.04.16.02.0001': ['Non Kapitasi', 'Prolanis', 'Jasa Giro', 'PKS', 'Lain-lain'],
                    '4.3.03.02.01.0001': ['Kapitasi'],
                    '4.1.02.01.01.0001': ['Retribusi'],
                    '4.1.02.01.01.0006': ['Retribusi'],
                };

                function setUraianOptions(uraianEl, selectedKode, selectedUraian = '') {
                    const options = uraianMap[selectedKode] || [];
                    uraianEl.innerHTML = '';

                    if (!options.length) {
                        uraianEl.innerHTML = '<option value="">-- Pilih Uraian --</option>';
                        return;
                    }

                    if (options.length > 1) {
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = '-- Pilih Uraian --';
                        placeholder.disabled = true;
                        uraianEl.appendChild(placeholder);
                    }

                    options.forEach((item) => {
                        const opt = document.createElement('option');
                        opt.value = item;
                        opt.textContent = item;
                        if (selectedUraian && selectedUraian === item) opt.selected = true;
                        uraianEl.appendChild(opt);
                    });

                    if (options.length === 1) {
                        uraianEl.value = options[0];
                    } else if (!selectedUraian) {
                        uraianEl.selectedIndex = 0;
                    }
                }

                document.querySelectorAll('[data-edit-kode-rekening]').forEach((kodeEl) => {
                    const itemId = kodeEl.dataset.itemId;
                    const uraianEl = document.getElementById('uraian_edit_' + itemId);
                    if (!uraianEl) return;

                    const currentUraian = uraianEl.dataset.currentUraian || '';
                    setUraianOptions(uraianEl, kodeEl.value, currentUraian);

                    kodeEl.addEventListener('change', function() {
                        setUraianOptions(uraianEl, this.value, '');
                    });
                });
            });
        </script>
    @endsection
