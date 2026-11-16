@extends('layouts.vertical', ['title' => 'Buku Penerimaan & Penyetoran','subTitle' => 'Rencana Kebutuhan'])


@section('content')
@php
    \Carbon\Carbon::setLocale('id');
    $periodeText = $selectedPeriode ?: 'Semua Periode';
@endphp

<div class="page-title-box">
    <h4 class="mb-1"></h4>
    <p class="text-muted mb-0">Periode: {{ $periodeText }}</p>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('buku-penerimaan-penyetoran.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Pilih Periode</label>
                <select name="periode" class="form-select">
                    <option value="">Semua Periode</option>
                    @foreach ($bulanList as $bulan)
                        <option value="{{ $bulan }}" {{ $selectedPeriode === $bulan ? 'selected' : '' }}>{{ $bulan }}</option>
                    @endforeach
                </select>
            </div>

            @if (strtolower(trim((string) auth()->user()->upt)) === 'dinas kesehatan')
                <div class="col-md-4">
                    <label class="form-label">Pilih Puskesmas / Unit BLUD</label>
                    <select name="unit_blud_id" class="form-select">
                        <option value="">Semua Unit BLUD</option>
                        @foreach ($unitBluds as $unit)
                            <option value="{{ $unit->id }}" {{ (string) $selectedUnitBludId === (string) $unit->id ? 'selected' : '' }}>
                                {{ $unit->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="{{ route('buku-penerimaan-penyetoran.pdf', ['periode' => $selectedPeriode, 'unit_blud_id' => $selectedUnitBludId]) }}" class="btn btn-success">
                    <i class="fas fa-file-pdf"></i> Unduh PDF
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-body">
            <h6>Total Penerimaan</h6>
            <h4 class="mb-0">Rp {{ number_format($totalPenerimaan, 0, ',', '.') }}</h4>
        </div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-body">
            <h6>Total Penyetoran</h6>
            <h4 class="mb-0">Rp {{ number_format($totalPenyetoran, 0, ',', '.') }}</h4>
        </div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-body">
            <h6>Selisih</h6>
            <h4 class="mb-0">Rp {{ number_format($selisih, 0, ',', '.') }}</h4>
        </div></div>
    </div>
</div>

{{-- <div class="card mb-3">
    <div class="card-header"><strong>Daftar Penerimaan</strong></div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-sm mb-0">
            <thead>
                <tr>
                    <th>No</th><th>Tanggal</th><th>Nomor</th><th>Kode Rekening</th><th>Uraian</th><th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($penerimaans as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->tanggal)->locale('id')->translatedFormat('d F Y') }}</td>
                        <td>{{ $item->nomor }}</td>
                        <td>{{ $item->kode_rekening }}</td>
                        <td>{{ $item->uraian }}</td>
                        <td class="text-end">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">Tidak ada data penerimaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Daftar Penyetoran</strong></div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-sm mb-0">
            <thead>
                <tr>
                    <th>No</th><th>Tanggal</th><th>No Bukti</th><th>No Penerimaan</th><th>Keterangan</th><th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($penyetorans as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->tanggal)->locale('id')->translatedFormat('d F Y') }}</td>
                        <td>{{ $item->nomor_bukti }}</td>
                        <td>{{ optional($item->penerimaan)->nomor ?: '-' }}</td>
                        <td>{{ $item->keterangan }}</td>
                        <td class="text-end">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">Tidak ada data penyetoran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div> --}}
@endsection

@section('scripts')
        <script src="{{ URL::asset('assets/libs/moment/moment.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/jquery-ui-dist/jquery-ui.min.js') }}"></script>
        <script src="{{ URL::asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>
        <script src="{{ URL::asset('assets/js/pages/form-advanced.init.js') }}"></script>
        <script src="{{ URL::asset('assets/js/app.js') }}"></script>

        <script>
            function printPage() {
                window.print();
            }
        </script>
    @endsection