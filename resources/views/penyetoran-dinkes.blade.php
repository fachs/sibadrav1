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
@section('content')


<div class="card mb-3">
                    <div class="card-body pb-0">
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td style="width: 150px;"><strong>Unit</strong></td>
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

                             <form method="GET" action="{{ route('penyetoran.dinkes.index') }}" class="row g-2 align-items-end justify-content-end">
            <div class="col-md-7 col-lg-6">
                <label class="form-label">Periode</label>
                <select name="periode" class="form-select">
                    <option value="">Semua Periode</option>
                    @foreach($bulanList as $bulan)
                        <option value="{{ $bulan }}" {{ $selectedPeriode === $bulan ? 'selected' : '' }}>{{ $bulan }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary">Terapkan</button>
            </div>
        </form>
                        </div>
                    </div>

</div>

<div class="accordion" id="accPeriode">
    @forelse($grouped as $periode => $units)
        @php $periodeId = \Illuminate\Support\Str::slug($periode); @endphp
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-{{ $periodeId }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $periodeId }}">
                    {{ $periode }} 
                </button>
            </h2>

            <div id="collapse-{{ $periodeId }}" class="accordion-collapse collapse" data-bs-parent="#accPeriode">
                <div class="accordion-body">
                    <div class="accordion" id="accUnit-{{ $periodeId }}">
                        @foreach($units as $unitNama => $rows)
                            @php $unitId = \Illuminate\Support\Str::slug($periode.'-'.$unitNama); @endphp
                            <div class="accordion-item mb-2">
                                <h2 class="accordion-header" id="heading-{{ $unitId }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $unitId }}">
                                        {{ $unitNama }} 
                                    </button>
                                </h2>
                                <div id="collapse-{{ $unitId }}" class="accordion-collapse collapse" data-bs-parent="#accUnit-{{ $periodeId }}">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            @php
                                                    $perPage = 5; // jumlah baris per halaman per grup
                                                    $groupPageKey = 'page_' . \Illuminate\Support\Str::slug($periode . '_' . $unitNama, '_');
                                                    $currentPage = (int) request()->get($groupPageKey, 1);
                                                    $totalRows = $rows->count();

                                                    $pagedRows = $rows->forPage($currentPage, $perPage)->values();

                                                    $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                                                        $pagedRows,
                                                        $totalRows,
                                                        $perPage,
                                                        $currentPage,
                                                        [
                                                            'path' => request()->url(),
                                                            'query' => request()->query(),
                                                            'pageName' => $groupPageKey,
                                                        ]
                                                    );
                                                @endphp
                                                <table class="table table-bordered table-striped mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th colspan="4">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span>Bukti Dukung : </span>

                                                                <div class="d-flex gap-2">
                                                                    @php $buktiKey = $periode . '|' . ($rows->first()->unit_blud_id ?? 0); @endphp
                                                                    @if(!empty($buktiLinks[$buktiKey]))
                                                                        <a href="{{ $buktiLinks[$buktiKey] }}" target="_blank" class="btn btn-sm btn-info">Lihat Bukti Dukung</a>
                                                                    @else
                                                                        <span style="color: #fd5b5b;">Belum ada bukti dukung</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <th colspan="2">
                                                        </th>
                                                    </tr>
                                                    <tr>
                                                        <th>Nomor TBP</th>
                                                        <th>Tanggal Setor</th>
                                                        <th>Tanggal Terima</th>
                                                        <th>Kode Rekening</th>
                                                        <th>Uraian</th>
                                                        <th>Jumlah</th>

                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($pagedRows as $item)
                                                        <tr class="">
                                                            <td>{{ $item->nomor_tbp }}</td>
                                                            <td>{{ $item->tanggal_setor ? \Carbon\Carbon::parse($item->tanggal_setor)->translatedFormat('d F Y') : '-' }}</td>
                                                            <td>{{ $item->tanggal_terima ? \Carbon\Carbon::parse($item->tanggal_terima)->translatedFormat('d F Y') : '-' }}</td>
                                                            <td>{{ $item->kode_rekening }}</td>
                                                            <td>{{ $item->uraian }}</td>
                                                            <td>Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>

                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="mt-3 d-flex justify-content-center">
                                                {{ $paginator->onEachSide(1)->links() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="alert alert-info">Tidak ada data penyetoran.</div>
    @endforelse
</div>
@endsection
@section('scripts')
 {{-- <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/bootstrap-editable/bootstrap-editable.min.js') }}"></script>
        {{-- Nonaktifkan dulu ini jika bentrok --}}
        <script src="{{ URL::asset('assets/js/pages/form-xeditable.init.js') }}"></script> --}}
        <script src="{{ URL::asset('assets/js/app.js') }}"></script>

@endsection