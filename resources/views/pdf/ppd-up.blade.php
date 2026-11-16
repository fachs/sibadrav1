<!DOCTYPE html>
<html lang="id">
    @php \Carbon\Carbon::setLocale('id'); @endphp
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            color: #000;
            padding: 2cm 2.5cm;
        }

        .header {
            text-align: center;
            font-weight: bold;
            line-height: 1.6;
            margin-bottom: 6px;
        }

        hr {
            border: none;
            border-top: 1.5px solid #000;
            margin: 8px 0 14px;
        }

        .nomor {
            text-align: center;
            margin-bottom: 20px;
        }

        .isi {
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 16px;
        }

        .detail-table {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 6px;
            border-collapse: collapse;
        }

        .detail-table td {
            vertical-align: top;
            padding: 3px 0;
            font-size: 12pt;
        }

        .detail-table td:first-child { width: 38%; }
        .detail-table td:nth-child(2) { width: 4%; }
        .detail-table td:last-child   { width: 58%; }

        .ttd-section {
            margin-top: 40px;
            text-align: right;
            line-height: 1.8;
        }

        .ttd-space { margin: 70px 0 4px; }
    </style>
</head>
<body>

    <div class="header">
        PEMERINTAH KOTA SUKABUMI<br>
        SURAT PERMINTAAN PENCAIRAN DANA UANG PERSEDIAAN<br>
        (SURAT-PPD-UP)
    </div>

    <hr>

    <div class="nomor">
        NOMOR &nbsp; {{ $surat->nomor ?? '...' }}
    </div>

    <div class="isi">
        Berdasarkan Keputusan Pemimpin BLUD {{ optional($surat->unitBlud)->nama ?? '..............................' }} 
        Nomor {{ $surat->no_sk ?? '...' }}
        Tanggal {{ $surat->tanggal_sk ? \Carbon\Carbon::parse($surat->tanggal_sk)->translatedFormat('d F Y') : '....' }}
        tentang Penetapan Jumlah Uang Persediaan untuk
        BLUD {{ optional($surat->unitBlud)->nama ?? '..............................' }},
        bersama ini kami mengajukan Surat Permintaan Pencairan Dana Uang Persediaan
        sejumlah <strong>Rp. {{ number_format($surat->jumlah ?? 0, 0, ',', '.') }},-</strong>
    </div>

    <table class="detail-table">
        <tr>
            <td>Terbilang</td>
            <td>:</td>
            <td>{{ terbilangRupiah($surat->jumlah) }}</td>
        </tr>
        <tr>
            <td>Nama Bendahara dan Nomor<br>Rekening Bank</td>
            <td>:</td>
            <td>
                {{ $surat->nama_bendahara ?? '..........' }} /
                {{ $surat->no_rek ?? '...........' }}
            </td>
        </tr>
    </table>

    <div class="ttd-section">
        Sukabumi,
        {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}
        <br>
        Bendahara Pengeluaran BLUD
        <div class="ttd-space"></div>
        <strong>{{ $surat->nama_bendahara ?? '(nama lengkap)' }}</strong><br>
        NIP. {{ $user->nip ?? '.........................' }}
    </div>

</body>
</html>