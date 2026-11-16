<!DOCTYPE html>
<html lang="id">
    @php \Carbon\Carbon::setLocale('id'); @endphp
<head>
    <meta charset="UTF-8">
    <title>Buku Penerimaan & Penyetoran</title>
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        h2, h4 { margin: 0; }
        .text-center { text-align: center; }
        .mb-8 { margin-bottom: 8px; }
        .mb-16 { margin-bottom: 16px; }
        .header-title { text-align: center; margin-bottom: 24px; }
        .header-title .line { font-size: 39px; font-weight: 700; line-height: 1.2; }
        .meta { margin-bottom: 12px; }
        .meta td { padding: 4px 6px; font-size: 30px; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; padding-top: 6px;
            padding-bottom: 6px;}
        th { background: #eee; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .no-border, .no-border td { border: none !important; }
        .table-main th { text-align: center; }
        .colnum th { font-weight: 400; }
        tr { page-break-inside: avoid; }
        .footer-wrap { margin-top: 24px; font-size: 11px; }
        .footer-table td { border: none; padding: 2px 0; }
        .footer-left { width: 46%; }
        .ttd { width: 100%; margin-top: 14px; }
        .ttd td { border: none; vertical-align: top; }
        .ttd .space { height: 50px; }
        .ttd-left { text-align: center; padding-left: 8px; padding-right: 200px;}
        .ttd-right { text-align: left; padding-left: 280px; }

        /* Hilangkan garis horizontal (atas & bawah) di isi tabel */
        .table-main tbody td {
            border-top: none;
            border-bottom: none;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        /* Opsional: tetap pertahankan garis penutup di baris terakhir */
        .table-main tbody tr:last-child td {
            border-bottom: 1px solid black;
        }
    </style>
</head>
<body>

    <div class="text-center">
        <h3>DINAS KESEHATAN KOTA SUKABUMI</h3>
        <h3>BUKU KAS UMUM PENERIMAAN & PENYETORAN</h3>
        <h3>TAHUN ANGGARAN {{ $tahun }}</h3>
    </div>

    <table class="no-border" style="font-size: 10px;">
        <tr>
            <td style="width:140px;"><strong>Unit BLUD</strong></td>
            <td style="width:10px;">:</td>
            <td>
                <strong>
                    @if (strtolower(trim((string) $user->upt)) === 'dinas kesehatan')
                        {{ $selectedUnitBludName ?? 'Semua Unit BLUD' }}
                    @else
                        {{ $user->upt }}
                    @endif
                </strong>
            </td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td>:</td>
            <td><strong>{{ strtoupper($selectedPeriode ?: 'SEMUA PERIODE') }}</strong></td>
        </tr>
    </table>

<table class="table-main" style="font-size: 9px; margin-top: 8px;">
        <thead>
            <tr>
                <th rowspan="2" style="width:60px;">Nomor</th>
                <th colspan="5">Penerimaan</th>
                <th colspan="3">Penyetoran</th>
                <th rowspan="2" style="width:120px;">Keterangan</th>
            </tr>
            <tr>
                <th style="width:90px;">Tanggal Terima</th>
                <th style="width:105px;">Cara Pembayaran</th>
                <th style="width:90px;">Kode Rekening</th>
                <th style="width:100px;">Uraian</th>
                <th style="width:95px;">Jumlah</th>
                <th style="width:90px;">Tanggal Setor</th>
                <th style="width:105px;">No. Bukti (TBP)</th>
                <th style="width:95px;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse($penerimaans as $item)
                <tr>
                    <td>{{ $item->nomor_terima }}</td>
                    <td class="text-center">
                        {{ $item->tanggal_terima ? \Carbon\Carbon::parse($item->tanggal_terima)->translatedFormat('d F Y') : '' }}
                    </td>
                    <td>{{ $item->cara_pembayaran }}</td>
                    <td>{{ $item->kode_rekening }}</td>
                    <td>{{ $item->uraian }}</td>
                    <td class="text-end">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>

                    <td class="text-center">
                        {{ $item->tanggal_setor ? \Carbon\Carbon::parse($item->tanggal_setor)->translatedFormat('d F Y') : '' }}
                    </td>
                    <td>{{ $item->nomor_tbp ?? '' }}</td>
                    <td class="text-end">
                        {{ $item->tanggal_setor ? 'Rp ' . number_format($item->jumlah, 0, ',', '.') : '' }}
                    </td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center">Tidak ada data penerimaan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-wrap" style="font-size: 10px;">
        <div><strong>Kas di Bendahara Penerimaan</strong></div>
        <table class="footer-table footer-left no-border">
            <tr>
                <td style="width: 55%;">Saldo Awal</td>
                <td style="width: 5%;">:</td>
                <td style="width: 10%;">Rp.</td>
                <td style="width: 30%;" class="text-end">-</td>
            </tr>
            <tr>
                <td>Jumlah Penerimaan</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">{{ number_format($totalPenerimaan, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Jumlah yang Disetorkan</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">{{ number_format($totalPenyetoran, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Pengembalian</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">{{ number_format($totalPengembalian, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Saldo Akhir</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">{{ number_format($selisih2, 2, ',', '.') }}</td>
            </tr>
        </table>

        <div style="margin-top: 6px;">Terdiri atas :</div>
        <table class="footer-table footer-left no-border" style="margin-top: 2px;">
            <tr>
                <td style="width: 55%;">a. Tunai Sebesar</td>
                <td style="width: 5%;">:</td>
                <td style="width: 10%;">Rp.</td>
                <td style="width: 30%;" class="text-end">-</td>
            </tr>
            <tr>
                <td>b. Bank Sebesar</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">-</td>
            </tr>
            <tr>
                <td>c. lainnya</td>
                <td>:</td>
                <td>Rp.</td>
                <td class="text-end">-</td>
            </tr>
        </table>

        <div style="page-break-inside: avoid;">
        @php
            $kapus = \App\Models\User::where('role', 'Kepala Puskesmas')
                                     ->where('upt', $user->upt)
                                     ->first();
            $bendahara = \App\Models\User::where('role', 'Bendahara Penerimaan')
                                         ->where('upt', $user->upt)
                                         ->first();
        @endphp
        <table class="ttd no-border">
            <tr>
                <td class="ttd-left" style="width: 50%; text-align: center; vertical-align: top;">
                    <br>
                    <br>
                    <strong>Mengetahui/Menyetujui</strong><br>
                    <strong>Kepala Puskesmas</strong>
                </td>
                <td class="ttd-right" style="width: 50%; vertical-align: top;">
                    <br>
                    Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br><br>
                    <strong>Bendahara Penerimaan</strong>
                </td>
            </tr>
            <tr><td class="space"></td><td></td></tr>
            <tr>
                <td class="ttd-left">
                    <u>{{ strtoupper($kapus->name ?? '-') }}</u><br>
                    NIP . {{ $kapus->nip ?? '-' }}
                </td>
                <td class="ttd-right">
                    <u>{{ strtoupper($bendahara->name ?? '-') }}</u><br>
                    NIP . {{ $bendahara->nip ?? '-' }}
                </td>
            </tr>
        </table>
        </div>
    </div>

</body>
</html>