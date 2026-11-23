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
        .no-border { border: none !important; }
        .no-border-right { border-right: none !important; }
        .no-border-left { border-left: none !important; }
        .no-border-top { border-top: none !important; }
        .no-border-bottom { border-bottom: none !important; }
    </style>
</head>
<body>
@if($isAllBlud ?? false)
<table style="width: 100%; border: 1px solid black; margin-bottom: -1px;">
    <tr>
        <td style="width: 15%; text-align: center; border-right: none;">
            @php
                $logoPath = public_path('images/logo-pemkot.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width: 70px;" alt="Logo">
            @endif
        </td>
        <td style="width: 70%; text-align: center; border-right: none; border-left: none;">
            <div style="font-size: 14px; font-weight: bold;">PEMERINTAH KOTA SUKABUMI</div>
            <div style="font-size: 14px; font-weight: bold;">DINAS KESEHATAN</div>
            <div style="font-size: 16px; font-weight: bold;">REKAPITULASI RBA PEMBIAYAAN SELURUH BLUD</div>
            <div style="font-size: 11px;">Kota Sukabumi</div>
        </td>
        <td style="width: 15%; border-left: none;"></td>
    </tr>
    <tr>
        <td colspan="3" class="text-center" style="border-top: 1px solid black;">
            <div style="font-size: 13px; font-weight: bold;">RINCIAN BISNIS DAN ANGGARAN (RBA)</div>
            <div style="font-size: 12px;">DATA AGREGAT PEMBIAYAAN PER JENIS KODE REKENING</div>
            <div style="font-size: 12px;">TAHUN ANGGARAN {{ $tahunRba }}</div>
        </td>
    </tr>
</table>

<table style="width: 100%; font-size: 11px;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th style="width: 5%; text-align: center;">No</th>
            <th style="text-align: left; width: 35%;">Nama Puskesmas / Unit BLUD</th>
            <th style="width: 20%; text-align: right;">6.1 - PENERIMAAN PEMBIAYAAN (Rp)</th>
            <th style="width: 20%; text-align: right;">6.2 - PENGELUARAN PEMBIAYAAN (Rp)</th>
            <th style="width: 20%; text-align: right;">TOTAL PEMBIAYAAN (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($agregatPuskesmas ?? [] as $idx => $row)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $row['nama'] }}</td>
                <td class="text-right">{{ number_format($row['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($row['total'] ?? 0, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <td colspan="2" class="text-center font-bold">TOTAL KESELURUHAN</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['penerimaan_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['pengeluaran_pembiayaan'] ?? 0, 2, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($totalAgregat['total'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<div style="page-break-inside: avoid; margin-top: 30px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td style="width: 40%; text-align: left; border: none; padding-left: 50px;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br>
                Kepala Dinas Kesehatan Kota Sukabumi<br>
                
                <br><br><br><br>
                
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>
@else
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
            <div style="font-size: 14px;">RINCIAN BISNIS DAN ANGGARAN</div>
            <div style="font-size: 14px;">ANGGARAN PEMBIAYAAN</div>
            <div style="font-size: 14px;">TAHUN ANGGARAN {{ $tahunRba }}</div>
        </td>
    </tr>
</table>

<table style="width: 100%; font-size: 12px;">
    <thead>
        <tr>
            <th colspan="4" class="text-center" style="width: 15%;">Kode</th>
            <th class="text-center" style="width: 55%;">Uraian</th>
            <th class="text-center" style="width: 30%;">Jumlah (Rp)</th>
        </tr>
        <tr style="background-color: #fff;">
            <th class="text-center" colspan="4">1</th>
            <th class="text-center">2</th>
            <th class="text-center">3</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center font-bold" style="width: 3.75%;">6</td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="text-center font-bold" style="width: 3.75%;"></td>
            <td class="font-bold">PEMBIAYAAN BLUD</td>
            <td class="text-right font-bold">{{ number_format($grandTotalJumlah ?? 0, 2, ',', '.') }}</td>
        </tr>

        @foreach ($klasifikasi as $key => $klas)
            @php
                $itemsInKlas = $groupedByKlasifikasi[$key] ?? collect();
                if ($itemsInKlas->isEmpty()) continue;
                
                $subtotal = $itemsInKlas->flatten()->sum('jumlah');
                $prefixParts = explode('.', $klas['prefix']);
                $p1 = $prefixParts[0] ?? '';
                $p2 = $prefixParts[1] ?? '';
                
                // Trim suffix (like label '4.1 - JASA LAYANAN' -> 'JASA LAYANAN')
                $label = trim(preg_replace('/^[0-9\.]+ - /', '', $klas['label']));
            @endphp
            
            @if ($key != 'lainnya')
            <tr>
                <td class="text-center font-bold">{{ $p1 }}</td>
                <td class="text-center font-bold">{{ $p2 }}</td>
                <td class="text-center font-bold"></td>
                <td class="text-center font-bold"></td>
                <td class="font-bold">{{ $label }}</td>
                <td class="text-right font-bold">{{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
            @else
            <tr>
                <td class="text-center font-bold">6</td>
                <td class="text-center font-bold">3</td>
                <td class="text-center font-bold"></td>
                <td class="text-center font-bold"></td>
                <td class="font-bold">{{ $label }}</td>
                <td class="text-right font-bold">{{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
            @endif

            @foreach ($itemsInKlas->flatten() as $item)
                @php
                    $parts = explode('.', $item->kode_rek);
                    $c1 = $parts[0] ?? '';
                    $c2 = $parts[1] ?? '';
                    $c3 = $parts[2] ?? '';
                    $c4 = implode('.', array_slice($parts, 3));
                @endphp
                <tr>
                    <td class="text-center">{{ $c1 }}</td>
                    <td class="text-center">{{ $c2 }}</td>
                    <td class="text-center">{{ $c3 }}</td>
                    <td class="text-center">{{ $c4 }}</td>
                    <td>{{ $item->uraian }}</td>
                    <td class="text-right">{{ number_format($item->jumlah, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        @endforeach

        <tr>
            <td colspan="4" class="font-bold"></td>
            <td class="font-bold">JUMLAH</td>
            <td class="text-right font-bold">{{ number_format($grandTotalJumlah ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<div style="page-break-inside: avoid; margin-top: 30px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td style="width: 40%; text-align: left; border: none; padding-left: 50px;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br>
                Kepala {{ $uptName ?? ($user->upt ?? '-') }}<br>
                
                <br><br><br><br>
                
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>
@endif

</body>
</html>
