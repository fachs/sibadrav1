<!DOCTYPE html>
<html lang="id">
    @php \Carbon\Carbon::setLocale('id'); @endphp
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Cambria, serif;
            font-size: 9pt;
            color: #000;
            padding: 2cm 2cm;
        }
        .header {
            text-align: center;
            font-weight: bold;
            line-height: 1.3;
            margin-bottom: 6px;
        }
        hr {
            border: none;
            border-top: 1.5px solid #000;
            margin: 8px 0 14px;
        }
        .nomor {
            text-align: center;
            margin-bottom: 10px;
        }
        table.ringkasan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9pt;
        }
        table.ringkasan th,
        table.ringkasan td {
            border: 0.5px solid #000;
            padding: 4px 8px;
            vertical-align: top;
        }
        table.ringkasan th {
            text-align: center;
            font-weight: bold;
            /* background-color: #ffffff; */
        }
        table.ringkasan td.label {
            text-align: right;
            width: 60%;
        }
        table.ringkasan td.nilai {
            text-align: left;
            white-space: nowrap;
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 14px 0 6px;
        }
        table.rincian {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        table.rincian th,
        table.rincian td {
            border: 0.5px solid #000;
            padding: 4px 8px;
            vertical-align: top;
        }
        table.rincian th {
            text-align: center;
            font-weight: bold;
            /* background-color: #f0f0f0; */
        }
        table.rincian td.kode   { width: 20%; text-align: center; }
        table.rincian td.uraian { width: 55%; }
        table.rincian td.jumlah { width: 25%; text-align: right; }
        .terbilang-row td {
            padding: 4px 8px;
        }
        .detail-table {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 6px;
            border-collapse: collapse;
        }
        .detail-table td {
            vertical-align: top;
            padding: 3px 0;
            font-size: 9pt;
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
        .program-info {
            margin: 10px 0 4px;
            font-size: 9pt;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        PEMERINTAH KOTA SUKABUMI<br>
        SURAT PERMINTAAN PENCAIRAN DANA GANTI UANG PERSEDIAAN<br>
        (SURAT-PPD GU)
    </div>
    <div class="nomor">
        NOMOR &nbsp; {{ $surat->nomor ?? '............................................' }}
    </div>

    {{-- NOMOR --}}
    

    {{-- RINGKASAN --}}
    <div class="section-title">RINGKASAN</div>

    <table class="ringkasan">
        {{-- Ringkasan DBA/DBAP --}}
        <tr>
            <th colspan="3">Ringkasan DBA/DBAP</th>
        </tr>
        <tr>
            <td colspan="2" class="label">Jumlah dana DBA/DBAP</td>
            <td class="nilai">Rp. {{ number_format($surat->jumlah_dba ?? 0, 0, ',', '.') }}</td>
        </tr>

        {{-- Ringkasan Anggaran Kas --}}
        {{-- Ringkasan Anggaran Kas --}}
<tr>
    <th colspan="3">Ringkasan Anggaran Kas</th>
</tr>
<tr>
    <th style="width: 10%;">No.</th>
    <th style="width: 60%;">Anggaran Kas</th>
    <th style="width: 30%;">Jumlah</th>
</tr>
<tr>
    <td style="text-align:center;">1</td>
    <td>Triwulan 1</td>
    <td class="nilai">Rp. {{ number_format($surat->triwulan_1 ?? 0, 0, ',', '.') }}</td>
</tr>
<tr>
    <td style="text-align:center;">2</td>
    <td>Triwulan 2</td>
    <td class="nilai">Rp. {{ number_format($surat->triwulan_2 ?? 0, 0, ',', '.') }}</td>
</tr>
<tr>
    <td style="text-align:center;">3</td>
    <td>Triwulan 3</td>
    <td class="nilai">Rp. {{ number_format($surat->triwulan_3 ?? 0, 0, ',', '.') }}</td>
</tr>
<tr>
    <td style="text-align:center;">4</td>
    <td>Triwulan 4</td>
    <td class="nilai">Rp. {{ number_format($surat->triwulan_4 ?? 0, 0, ',', '.') }}</td>
</tr>
<tr>
    <td colspan="2" style="text-align:right; font-weight:bold; font-style:italic;">
        Jumlah
    </td>
    <td class="nilai">
        <strong>Rp. {{ number_format(($surat->triwulan_1 ?? 0) + ($surat->triwulan_2 ?? 0) + ($surat->triwulan_3 ?? 0) + ($surat->triwulan_4 ?? 0), 0, ',', '.') }}</strong>
        &nbsp;<em>(II)</em>
    </td>
</tr>
<tr>
    <td colspan="2" style="text-align:right; font-weight:bold; font-style:italic;">
        <em>Sisa dana diluar anggaran kas berkenaan (I-II)</em>
    </td>
    <td class="nilai">Rp. {{ number_format($surat->sisa_dana ?? 0, 0, ',', '.') }}</td>
</tr>
<tr>
    <td colspan="2">
        &nbsp;
    </td>
    <td >&nbsp;</td>
</tr>


        {{-- Ringkasan Belanja --}}
        <tr>
            <th colspan="3">Ringkasan Belanja</th>
        </tr>
        <tr>
            <td colspan="2" class="label" style="text-align:left;">Belanja GU</td>
            <td class="nilai">Rp. {{ number_format($surat->belanja_up_gu ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="2" class="label" style="text-align:left;">Belanja LS</td>
            <td class="nilai">Rp. {{ number_format($surat->belanja_ls ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="2" class="label" style="font-style:italic;">Jumlah</td>
            <td class="nilai"><strong>Rp. {{ number_format(($surat->belanja_up_gu ?? 0) + ($surat->belanja_ls ?? 0), 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td colspan="2" class="label" style="font-style:italic;">Sisa Anggaran Kas Triwulan bersangkutan yang belum dibelanjakan (II - III)</td>
            <td class="nilai">Rp. {{ number_format($surat->sisa_anggaran_kas ?? 0, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Program / Kegiatan / Sub Kegiatan --}}
    <div class="program-info">Program : {{ $surat->program ?? '....' }}</div>
    <div class="program-info">Kegiatan : {{ $surat->kegiatan ?? '....' }}</div>
    <div class="program-info">Sub Kegiatan : {{ $surat->sub_kegiatan ?? '....' }}</div>

    {{-- RINCIAN PENGGUNAAN --}}
    <div class="section-title" style="margin-top: 14px;">RINCIAN PENGGUNAAN</div>

    <table class="rincian">
        <tr>
            <th>Kode Rekening</th>
            <th>Uraian</th>
            <th>Jumlah</th>
        </tr>
        @forelse ($surat->rincian ?? [] as $item)
        <tr>
            <td class="kode">{{ $item->kode_rekening }}</td>
            <td class="uraian">{{ $item->uraian }}</td>
            <td class="jumlah">Rp. {{ number_format($item->jumlah ?? 0, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr>
            <td class="kode">&nbsp;</td>
            <td class="uraian">&nbsp;</td>
            <td class="jumlah">&nbsp;</td>
        </tr>
        <tr>
            <td class="kode">&nbsp;</td>
            <td class="uraian">&nbsp;</td>
            <td class="jumlah">&nbsp;</td>
        </tr>
        <tr>
            <td class="kode">&nbsp;</td>
            <td class="uraian">&nbsp;</td>
            <td class="jumlah">&nbsp;</td>
        </tr>
        @endforelse
        <tr>
            <td colspan="2" style="text-align:right; font-weight:bold;">Total</td>
            <td class="jumlah"><strong>Rp. {{ number_format($surat->jumlah ?? 0, 0, ',', '.') }},-</strong></td>
        </tr>
    </table>

    {{-- Terbilang & Rekening --}}
    <table class="detail-table">
        <tr>
            <td>Terbilang</td>
            <td>:</td>
            <td><em>{{ terbilangRupiah($surat->jumlah ?? 0) }}</em></td>
        </tr>
        <tr>
            <td>Nama dan Nomor Rekening Bank</td>
            <td>:</td>
            <td>
                {{ $surat->nama_bendahara ?? '..............' }} /
                {{ $surat->no_rek ?? '..............' }}
            </td>
        </tr>
    </table>

    {{-- TANDA TANGAN --}}
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