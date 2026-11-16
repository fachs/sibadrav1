<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Kas Umum Pejabat Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 10px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 25px; }
        
        .header { text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 20px; }
        
        .info-table { width: 100%; margin-bottom: 10px; font-weight: bold; }
        .info-table td { padding: 2px 0; }
        .info-table .label { width: 120px; }
        .info-table .colon { width: 10px; }

        table.bku-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.bku-table th, table.bku-table td { border: 1px solid #000; padding: 4px; }
        table.bku-table th { text-align: center; }
        
        .col-no { width: 3%; text-align: center; }
        .col-transaksi { width: 10%; }
        .col-tanggal { width: 8%; text-align: center; }
        .col-rekening { width: 12%; }
        .col-uraian { width: 17%; }
        .col-penerimaan { width: 10%; }
        .col-pengeluaran { width: 10%; }
        .col-giro { width: 10%; }
        .col-saldo { width: 10%; }

        .summary-block { margin-bottom: 15px; }
        .summary-table { width: 60%; }
        .summary-table td { padding: 2px 0; }
        
        .signature-table { width: 100%; margin-top: 5px; text-align: center; }
        .signature-table td { width: 50%; vertical-align: bottom; height: 80px; }
    </style>
</head>
<body>
    <div class="header">
        <div>PEMERINTAH KOTA SUKABUMI</div>
        <div>BUKU KAS UMUM</div>
        <div>PEJABAT KEUANGAN</div>
        <div>BLUD UPTD {{ strtoupper($namaPuskesmas) }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Tahun Anggaran</td>
            <td class="colon">:</td>
            <td>{{ $tahun }}</td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td class="colon">:</td>
            <td>{{ $selectedBulan }}</td>
        </tr>
    </table>

    <table class="bku-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-transaksi">No. Transaksi</th>
                <th class="col-tanggal">Tanggal</th>
                <th class="col-rekening">Kode Rekening</th>
                <th class="col-uraian">Uraian</th>
                <th class="col-penerimaan">Penerimaan</th>
                <th class="col-pengeluaran">Pengeluaran</th>
                <th class="col-giro">Giro</th>
                <th class="col-saldo">Saldo</th>
            </tr>
            <tr>
                <th>1</th>
                <th>2</th>
                <th>3</th>
                <th>4</th>
                <th>5</th>
                <th>6</th>
                <th>7</th>
                <th>8</th>
                <th>9</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bkuList as $index => $bku)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $bku->no_transaksi ?: '-' }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($bku->tanggal)->format('d/m/Y') }}</td>
                    <td>{{ $bku->kode_rekening ?: '-' }}</td>
                    <td>{{ $bku->uraian }}</td>
                    <td class="text-end">{{ $bku->is_saldo_awal ? '-' : number_format($bku->penerimaan, 0, ',', '.') }}</td>
                    <td class="text-end">{{ $bku->is_saldo_awal ? '-' : number_format($bku->pengeluaran, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($bku->giro, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($bku->saldo, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="fw-bold">
                <td colspan="5" class="text-center">JUMLAH</td>
                <td class="text-end">{{ number_format($totalPenerimaan, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalGiro, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($saldoAkhir, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary-block">
        <table class="summary-table" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%">Jumlah saat ini, tanggal {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</td>
                <td style="width: 10%; text-align: left; padding-right: 4px;">Rp {{ number_format($jumlahSaatIni, 0, ',', '.') }}</td>
                <td style="width: 40%"></td>
            </tr>
            <tr>
                <td style="width: 50%">Jumlah s/d Bulan Lalu</td>
                <td style="width: 10%; text-align: left; padding-right: 4px; border-bottom: 1px solid #000;">Rp {{ number_format($jumlahSdTanggalLalu, 0, ',', '.') }}</td>
                <td style="width: 40%"></td>
            </tr>
            <tr>
                <td style="width: 50%">Jumlah s/d tanggal {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</td>
                <td style="width: 10%; text-align: left; padding-right: 4px;">Rp {{ number_format($jumlahSaatIni + $jumlahSdTanggalLalu, 0, ',', '.') }}</td>
                <td style="width: 40%"></td>
            </tr>
        </table>
    </div>

    <div class="summary-block mt-3">
        Sisa Kas :<br>
        Pada hari ini tanggal {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}, Buku Kas Umum ditutup oleh kami dan didapat dalam Kas Pejabat Keuangan sebesar: Rp {{ number_format($saldoAkhir, 0, ',', '.') }}<br>
        ({{ terbilangRupiah($saldoAkhir) }})
    </div>

    <div class="mt-3">
        Terdiri Dari :<br>
        <table style="width: 40%">
            <tr>
                <td style="width: 10%">-</td>
                <td style="width: 40%">Saldo Bank</td>
                <td style="width: 5%">:</td>
                <td style="width: 45%">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>-</td>
                <td>Pajak</td>
                <td>:</td>
                <td>-</td>
            </tr>
        </table>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                Mengetahui/Menyetujui,<br>
                Kepala Puskesmas
            </td>
            <td>
                <br>
                Pejabat Keuangan
            </td>
        </tr>
        <tr>
            <td>
                <u>{{ $namaKapus }}</u><br>
                NIP. {{ $nipKapus }}
            </td>
            <td>
                <u>{{ $namaKtu }}</u><br>
                NIP. {{ $nipKtu }}
            </td>
        </tr>
    </table>
</body>
</html>
