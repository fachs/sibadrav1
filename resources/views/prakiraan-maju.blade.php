@extends('layouts.vertical', ['title' => 'Prakiraan Maju', 'subTitle' => 'Perencanaan'])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1"><h4 class="fs-18 fw-semibold m-0">Prakiraan Maju</h4><p class="text-muted mb-0 mt-1">Proyeksi belanja berdasarkan RBA Belanja.</p></div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">Periksa kembali nilai prakiraan yang diinput.</div> @endif

    <div class="card">
        <div class="card-body">
            @if($puskesmasList->isNotEmpty())
                <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                    <form method="GET" action="{{ route('prakiraan-maju.index') }}" class="d-flex gap-2">
                        <select name="puskesmas" class="form-select" onchange="this.form.submit()">
                            @foreach($puskesmasList as $unit)<option value="{{ $unit->nama }}" @selected($namaBlud === $unit->nama)>{{ $unit->nama }}</option>@endforeach
                        </select>
                    </form>
                </div>
            @endif
            <div class="alert alert-info py-2"><i class="ri-information-line me-1"></i> Nilai baseline {{ $tahunBaseline }} diambil otomatis dari total RBA Belanja. Isi nilai untuk {{ $tahunBaseline + 1 }} dan {{ $tahunBaseline + 2 }}.</div>
            <form method="POST" action="{{ route('prakiraan-maju.store') }}">
                @csrf
                <input type="hidden" name="_tahun_aktif" value="{{ $tahunBaseline }}">
                @if($puskesmasList->isNotEmpty()) <input type="hidden" name="puskesmas" value="{{ $namaBlud }}"> @endif
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light"><tr><th style="width:9%">Kode</th><th>Jenis Belanja</th><th class="text-end">{{ $tahunBaseline }} (RBA)</th><th style="width:22%">{{ $tahunBaseline + 1 }}</th><th style="width:22%">{{ $tahunBaseline + 2 }}</th></tr></thead>
                        <tbody>
                        @php
                            $formatNilai = static function ($value) {
                                if (! preg_match('/^(\d+)(?:\.(\d+))?$/', (string) $value, $parts)) return $value;
                                $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[1]);
                                $decimal = rtrim($parts[2] ?? '', '0');
                                return $whole.($decimal !== '' ? ','.$decimal : '');
                            };
                        @endphp
                        @foreach($rows as $row)
                            <tr><td><span class="badge bg-light text-dark">{{ $row['kode_rek'] }}</span></td><td>{{ $row['uraian'] }}</td><td class="text-end fw-semibold">{{ number_format($row['nilai_baseline'], 0, ',', '.') }}</td>
                                <td><input class="form-control text-end format-rupiah" type="text" inputmode="decimal" autocomplete="off" name="nilai_tahun_1[{{ $row['kode_rek'] }}]" value="{{ $formatNilai(old('nilai_tahun_1', [])[$row['kode_rek']] ?? $row['nilai_tahun_1']) }}" required></td>
                                <td><input class="form-control text-end format-rupiah" type="text" inputmode="decimal" autocomplete="off" name="nilai_tahun_2[{{ $row['kode_rek'] }}]" value="{{ $formatNilai(old('nilai_tahun_2', [])[$row['kode_rek']] ?? $row['nilai_tahun_2']) }}" required></td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3"><button class="btn btn-primary"><i class="ri-save-line me-1"></i>Simpan Prakiraan</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    document.querySelectorAll('.format-rupiah').forEach(function (input) {
        input.addEventListener('input', function () {
            formatRupiahInput(input);
        });
    });
</script>
@endsection
