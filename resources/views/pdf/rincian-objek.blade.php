<!DOCTYPE html>
<html>
    @php \Carbon\Carbon::setLocale('id'); @endphp
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; }
        th { background: #eee; }
        .text-center { text-align: center; }
        .no-border, .no-border td { border: none !important; }
        .unit-label {
            margin-top: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>

@php
    $selectedUnit = $namaUnit ?? null;
    $grandTotal = 0;
@endphp

<div class="text-center">
    <h3>RINCIAN RENCANA KEBUTUHAN</h3>
    <div>BLUD {{ $uptName ?? ($user->upt ?? 'BLUD Puskesmas') }} Pemerintah Kota Sukabumi</div>
    <div>Tahun Anggaran {{ $tahunRba }}</div>
    @if(!empty($selectedUnit))
        <div class="unit-label">Unit: {{ $selectedUnit }}</div>
    @else
        <div class="unit-label">Seluruh Unit</div>
    @endif
</div>

<br><br>

<table class="no-border" style="font-size: 12px;">
    <tr>
        <td style="width: 150px;"><strong>Program</strong></td>
        <td style="width: 10px;">:</td>
        <td>1.02.01 - PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA</td>
    </tr>
    <tr>
        <td><strong>Kegiatan</strong></td>
        <td>:</td>
        <td>1.02.01.2.10 - Peningkatan Pelayanan BLUD</td>
    </tr>
    <tr>
        <td><strong>Sub Kegiatan</strong></td>
        <td>:</td>
        <td>1.02.01.2.10.0001 - Pelayanan dan Penunjang Pelayanan BLUD</td>
    </tr>
</table>

<br>

<table style="font-size: 11px;">
    <thead>
        <tr style="background: #ddd;">
            <th>Nama Unit</th>
            <th>Uraian</th>
            <th>Koefisien</th>
            <th>Harga Satuan (Rp)</th>
            <th>Jumlah Harga (Rp)</th>
            <th>Satuan</th>
            <th>Ruang</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($grouped as $unit => $items)
            @php
                $subtotal = $items->sum('jumlah_harga');
                $grandTotal += $subtotal;
            @endphp

            <tr>
                <td colspan="8" style="background: #f2f2f2;">
                    <strong>{{ $unit }}</strong>
                </td>
            </tr>

            @foreach ($items as $item)
                <tr>
                    <td></td>
                    <td>
                        {{ $item->uraian }}<br>
                        <small>Spesifikasi: {{ $item->spesifikasi }}</small>
                    </td>
                    <td>{{ $item->jumlah }}</td>
                    <td>{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                    <td>{{ number_format($item->jumlah_harga, 0, ',', '.') }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td>{{ $item->ruang }}</td>
                    <td>{{ $item->keterangan }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="4" style="text-align: center;">
                    <strong>Subtotal {{ $unit }}</strong>
                </td>
                <td><strong>{{ number_format($subtotal, 0, ',', '.') }}</strong></td>
                <td colspan="3"></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center;">Tidak ada data.</td>
            </tr>
        @endforelse

        <tr>
            <td colspan="4" style="text-align: center; background: #ddd;">
                <strong>Total</strong>
            </td>
            <td style="background: #ddd;">
                <strong>{{ number_format($grandTotal, 0, ',', '.') }}</strong>
            </td>
            <td colspan="3" style="background: #ddd;"></td>
        </tr>
    </tbody>
</table>

<div style="page-break-inside: avoid;">
    <table class="no-border" style="margin-top: 30px; text-align: center;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <br><br>
                Kepala {{ $uptName ?? ($user->upt ?? '-') }}<br>
            </td>
            <td style="width: 50%; vertical-align: top;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br><br>
                
                @if(($uptName ?? $user->upt) == 'Labkes Kota Sukabumi')
                    Kasubbag Tata Usaha<br>
                @else
                    Penanggungjawab Klaster 1<br>
                @endif
            </td>
        </tr>
        <tr>
            <td style="height: 80px;"></td>
            <td></td>
        </tr>
        <tr>
            <td>
                <strong>{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
            <td>
                <strong>{{ $penandatangan->kasubbag_tu ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_ktu ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
