<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .align-middle { vertical-align: middle; }
        .no-border { border: none !important; }
        .no-border-right { border-right: none !important; }
        .no-border-left { border-left: none !important; }
        .no-border-top { border-top: none !important; }
        .no-border-bottom { border-bottom: none !important; }
    </style>
</head>
<body>

@php
    if (!function_exists('formatLebihKurang')) {
        function formatLebihKurang($angka) {
            if ($angka < 0) {
                return '<span style="color: red;">(' . number_format(abs($angka), 2, ',', '.') . ')</span>';
            }
            return number_format($angka, 2, ',', '.');
        }
    }
@endphp

<table style="width: 100%; border: 1px solid black; margin-bottom: -1px;">
    <tr>
        <td style="width: 15%; text-align: center; border-right: none;">
            @php
                $logoPath = public_path('images/logo-pemkot.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width: 80px;" alt="Logo">
            @endif
        </td>
        <td style="width: 70%; text-align: center; border-right: none; border-left: none;">
            <div style="font-size: 14px; font-weight: bold;">PEMERINTAH KOTA SUKABUMI</div>
            <div style="font-size: 14px; font-weight: bold;">DINAS KESEHATAN</div>
            <div style="font-size: 16px; font-weight: bold;">BLUD UPTD {{ strtoupper($uptName) }}</div>
            <div style="font-size: 12px;">{{ $penandatangan->alamat ?? '<<Alamat Puskesmas>>' }}</div>
        </td>
        <td style="width: 15%; border-left: none;"></td>
    </tr>
    <tr>
        <td colspan="3" class="text-center" style="border-top: 1px solid black;">
            <div style="font-size: 14px;">RINCIAN PERUBAHAN BISNIS DAN ANGGARAN</div>
            <div style="font-size: 14px;">ANGGARAN PEMBIAYAAN</div>
            <div style="font-size: 14px;">TAHUN ANGGARAN {{ $tahunRba }}</div>
        </td>
    </tr>
</table>

@php
    $grandTotalAwal = $grouped->flatten()->sum('jumlah');
    $grandTotalAkhir = $grouped->flatten()->sum(fn ($item) => $item->jumlah_perubahan ?? $item->jumlah);
@endphp

<table style="width: 100%; font-size: 12px;">
    <thead>
        <tr>
            <th colspan="4" class="text-center align-middle" style="width: 15%;">Kode</th>
            <th class="text-center align-middle" style="width: 40%;">Uraian</th>
            <th class="text-center align-middle" style="width: 15%;">Sebelum<br>Perubahan</th>
            <th class="text-center align-middle" style="width: 15%;">Setelah<br>Perubahan</th>
            <th class="text-center align-middle" style="width: 15%;">Tambah/<br>Kurang</th>
        </tr>
        <tr>
            <th colspan="4" class="text-center">1</th>
            <th class="text-center">2</th>
            <th class="text-center">3</th>
            <th class="text-center">4</th>
            <th class="text-center">5</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center font-bold" style="width: 3.75%;">6</td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="font-bold">PEMBIAYAAN BLUD</td>
            <td class="text-right font-bold">{{ number_format($grandTotalAwal, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($grandTotalAkhir, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{!! formatLebihKurang($grandTotalAkhir - $grandTotalAwal) !!}</td>
        </tr>
        @foreach ($klasifikasi as $key => $klas)
            @php
                $itemsInKlas = $groupedByKlasifikasi[$key] ?? collect();
                if ($itemsInKlas->isEmpty()) continue;
                
                $subtotalAwal = $itemsInKlas->flatten()->sum('jumlah');
                $subtotalAkhir = $itemsInKlas->flatten()->sum(function($item) {
                    return $item->jumlah_perubahan ?? $item->jumlah;
                });


                $prefixParts = explode('.', $klas['prefix']);
                $p1 = $prefixParts[0] ?? '';
                $p2 = $prefixParts[1] ?? '';
                
                // Trim suffix (like label '6.1 - PENERIMAAN PEMBIAYAAN' -> 'PENERIMAAN PEMBIAYAAN')
                $label = trim(preg_replace('/^[0-9\.]+ - /', '', $klas['label']));
            @endphp
            
            @if ($key != 'lainnya')
            <tr>
                <td class="text-center font-bold" style="width: 3.75%;">{{ $p1 }}</td>
                <td class="text-center font-bold" style="width: 3.75%;">{{ $p2 }}</td>
                <td class="text-center font-bold" style="width: 3.75%;"></td>
                <td class="text-center font-bold" style="width: 3.75%;"></td>
                <td class="font-bold">{{ $label }}</td>
                <td class="text-right font-bold">{{ number_format($subtotalAwal, 2, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($subtotalAkhir, 2, ',', '.') }}</td>
                <td class="text-right font-bold">{!! formatLebihKurang($subtotalAkhir - $subtotalAwal) !!}</td>
            </tr>
            @else
            <tr>
                <td class="text-center font-bold" style="width: 3.75%;">6</td>
                <td class="text-center font-bold" style="width: 3.75%;">3</td>
                <td class="text-center font-bold" style="width: 3.75%;"></td>
                <td class="text-center font-bold" style="width: 3.75%;"></td>
                <td class="font-bold">{{ $label }}</td>
                <td class="text-right font-bold">{{ number_format($subtotalAwal, 2, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($subtotalAkhir, 2, ',', '.') }}</td>
                <td class="text-right font-bold">{!! formatLebihKurang($subtotalAkhir - $subtotalAwal) !!}</td>
            </tr>
            @endif

            @foreach ($itemsInKlas->flatten() as $sub)
                @php
                    $kodes = explode('.', $sub->kode_rek_awal ?? $sub->kode_rek);
                    $k1 = $kodes[0] ?? '';
                    $k2 = $kodes[1] ?? '';
                    $k3 = $kodes[2] ?? '';
                    $k4 = implode('.', array_slice($kodes, 3));

                    $jumlahAwal = $sub->jumlah;
                    $jumlahAkhir = $sub->jumlah_perubahan ?? $sub->jumlah;
                    $selisih = $jumlahAkhir - $jumlahAwal;
                @endphp
                <tr>
                    <td class="text-center">{{ $k1 }}</td>
                    <td class="text-center">{{ $k2 }}</td>
                    <td class="text-center">{{ $k3 }}</td>
                    <td class="text-center">{{ $k4 }}</td>
                    <td>{{ $sub->uraian_perubahan ?? $sub->uraian }}</td>
                    <td class="text-right">{{ number_format($jumlahAwal, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($jumlahAkhir, 2, ',', '.') }}</td>
                    <td class="text-right">{!! formatLebihKurang($selisih) !!}</td>
                </tr>
            @endforeach
        @endforeach

        <tr>
            <td colspan="4" class="text-center font-bold"></td>
            <td class="font-bold text-left">JUMLAH</td>
            <td class="text-right font-bold">{{ number_format($grandTotalAwal, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($grandTotalAkhir, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{!! formatLebihKurang($grandTotalAkhir - $grandTotalAwal) !!}</td>
        </tr>

    </tbody>
</table>

<table style="width: 100%; border: none; margin-top: 20px;">
    <tr>
        <td style="width: 60%; border: none;"></td>
        <td style="width: 40%; border: none; text-align: center;">
            <div style="margin-bottom: 70px;">
                Sukabumi, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                Pemimpin BLUD<br>
                UPTD {{ ucwords(strtolower($uptName)) }}
            </div>
            <div>
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
