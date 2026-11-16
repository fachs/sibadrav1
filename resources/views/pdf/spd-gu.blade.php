<!DOCTYPE html>
<html lang="id">
@php \Carbon\Carbon::setLocale('id'); 
$tanggalSurat = $surat->created_at ? \Carbon\Carbon::parse($surat->created_at)->translatedFormat('d F Y') : \Carbon\Carbon::now()->translatedFormat('d F Y');
$tanggalTtd = $surat->created_at ? \Carbon\Carbon::parse($surat->created_at)->translatedFormat('d') : \Carbon\Carbon::now()->translatedFormat('d');
$bulanTtd = $surat->created_at ? \Carbon\Carbon::parse($surat->created_at)->translatedFormat('F') : \Carbon\Carbon::now()->translatedFormat('F');
$tahunTtd = $surat->created_at ? \Carbon\Carbon::parse($surat->created_at)->translatedFormat('Y') : \Carbon\Carbon::now()->translatedFormat('Y');
$pejabatKeuangan = optional($surat->unitBlud)->kasubbag_tu ?? '(nama lengkap)';
$nipPejabatKeuangan = optional($surat->unitBlud)->nip_ktu ?? '........................';
@endphp

<head>
    <meta charset="UTF-8">
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Cambria, serif;
            font-size: 8pt;
            color: #000;
            padding: 1cm 2cm;
        }

        .header {
            text-align: center;
            font-weight: bold;
            line-height: 1.8;
            text-transform: uppercase;
        }

        .sub-header {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-top: 2px;
        }

        hr {
            border: none;
            border-top: 2px solid #000;
            margin: 6px 0 10px;
        }

        .blok-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .blok-info td {
            vertical-align: top;
            font-size: 11pt;
            padding: 2px 0;
        }

        .blok-info td:first-child {
            width: 34%;
        }

        .blok-info td:nth-child(2) {
            width: 3%;
            text-align: center;
        }

        .blok-info td:last-child {
            width: 63%;
        }

        .isi {
            text-align: justify;
            line-height: 1.8;
            margin: 10px 0;
        }

        table.tbl {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 11pt;
        }

        table.tbl th,
        table.tbl td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }

        table.tbl th {
            text-align: center;
            font-weight: bold;
        }

        table.tbl td.center {
            text-align: center;
        }

        table.tbl td.right {
            text-align: right;
        }

        table.tbl tfoot td {
            font-weight: bold;
        }

        .section-title {
            font-weight: bold;
            margin: 10px 0 4px;
        }

        .info-box {
            border: 1px solid #000;
            padding: 4px 8px;
            font-style: italic;
            margin: 6px 0;
            font-size: 10.5pt;
        }

        .rekap table {
            width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 11pt;
        }

        .rekap td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .rekap td:first-child {
            width: 60%;
        }

        .rekap td:nth-child(2) {
            width: 4%;
            text-align: center;
        }

        .rekap td:last-child {
            width: 36%;
            text-align: right;
            font-weight: bold;
        }

        .ttd-section {
            margin-top: 30px;
            text-align: right;
            line-height: 1.8;
        }

        .ttd-space {
            margin: 80px 0 4px;
        }
    </style>
</head>

<body>


    {{-- BLOK IDENTITAS SURAT --}}
    <div class="blok-info">
        <table style="width:100%; border-collapse:collapse; ">
            <tr>
                {{-- Kolom kiri: nama pemerintah & isi surat --}}
                <td style="width:65%; border:0.5px solid #000; vertical-align:top; padding:0;">

                    {{-- Baris 1: Nama pemerintah --}}
                    <div style="text-align:center; font-weight:bold; padding:6px 10px; border-bottom:0.5px solid #000; line-height:1;">
                        PEMERINTAH KOTA SUKABUMI
                    </div>

                    {{-- Baris 3: Nomor DBA, Tanggal DBA, Nomor OPD, Tanggal OPD, BLUD --}}
                    <div style="padding:2px;">
                        <table style="width:100%; border-collapse:collapse; padding:1px 5px; line-height:1;">
                            <tr>
                                <td style="width:32%;">Nomor DBA</td>
                                <td style="width:3%;">:</td>
                                <td>{{ $surat->nomor_dba ?? '........................................' }}</td>
                            </tr>
                            <tr>
                                <td>Tanggal DBA</td>
                                <td>:</td>
                                <td>
                                    {{ $surat->tanggal_dba
                                        ? \Carbon\Carbon::parse($surat->tanggal_dba)->translatedFormat('d F Y')
                                        : '........................................' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Nomor OPD</td>
                                <td>:</td>
                                <td>{{ optional($surat->opdGu)->nomor ?? '........................................' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Tanggal OPD</td>
                                <td>:</td>
                                <td>{{ ($surat->opdGu)->created_at
                                        ? \Carbon\Carbon::parse(($surat->opdGu)->created_at)->translatedFormat('d F Y')
                                        : '........................................' }}</td>
                            </tr>
                            <tr>
                                <td>BLUD</td>
                                <td>:</td>
                                <td>{{ optional($surat->unitBlud)->nama ?? '........................................' }}
                                </td>
                            </tr>
                        </table>
                    </div>

                </td>

                {{-- Kolom kanan: judul surat --}}
                <td style="width:35%; border:0.5px solid #000; vertical-align:top; padding:0;">

                    {{-- Judul OPD --}}
                    <div
                        style="text-align:center; font-weight:bold; font-size:10pt; border-bottom:0.5px solid #000; line-height:2;">
                        SURAT PENCAIRAN DANA
                    </div>
                    <div style="text-align:center; border-bottom:0.5px solid #000;">
                        <div style="font-weight:bold; ">
                            (SURAT-PD) GU
                        </div>
                        <div>Nomor : {{ $surat->nomor ?? '...................' }}</div>
                    </div>

                    {{-- Nomor & Dari --}}
                    <div>

                        <div>Dari : {{ $user->name ?? '...................' }}</div>
                        <br>
                        <div>Tahun Anggaran : {{ $surat->tahun_anggaran ?? date('Y') }}</div>

                    </div>

                </td>
            </tr>

            {{-- Baris bawah: Bank/Pos & kalimat pencairan (full width) --}}
            <tr>
                <td colspan="2" style="border:0.5px solid #000; padding:4px 10px; line-height:1;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:145px;">Bank/Pos</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $surat->bank_asal ?? '........................................' }}</td>
                        </tr>
                    </table>
                    Hendaklah mencairkan/memindahbukukan dari Bank Rekening Nomor<br>
                    <table style="width:100%; border-collapse:collapse; margin-top:2px;">
                        <tr>
                            <td style="width:145px;">Uang sebesar</td>
                            <td style="width:3%;">:</td>
                            <td>
                                <strong>Rp. {{ number_format($surat->jumlah ?? 0, 0, ',', '.') }},-</strong>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td>({{ terbilangRupiah($surat->jumlah ?? 0) }})</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; padding:4px 10px; line-height:1;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:20%;">Kepada</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $surat->bank_asal ?? '........................................' }}</td>
                        </tr>
                        <tr>
                            <td style="width:20%;">NPWP</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $surat->npwp ?? '........................................' }}</td>
                        </tr>
                        <tr>
                            <td style="width:20%;">No. Rekening</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $surat->no_rek_tujuan ?? '........................................' }}</td>
                        </tr>
                        <tr>
                            <td style="width:20%;">Bank/Pos</td>
                            <td style="width:3%;">:</td>
                            <td>{{ $surat->bank_tujuan ?? '........................................' }}</td>
                        </tr>
                        <tr>
                            <td style="width:20%;">Untuk</td>
                            <td style="width:3%;">:</td>
                        </tr>
                    </table>

                </td>
            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; line-height:1; ">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="border:0.5px solid #000; border-top:none; border-left:none; padding:4px 6px; text-align:center; width:40px;">No</th>
                                <th colspan="9" style="border:0.5px solid #000; border-top:none; padding:4px 6px; text-align:center;">Kode Rekening</th>
                                <th style="border:0.5px solid #000; border-top:none; padding:4px 6px; text-align:center; width:16%;">Uraian</th>
                                <th style="border:0.5px solid #000; border-top:none; border-right:none; padding:4px 6px; text-align:center; width:140px;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border:0.5px solid #000; border-left:none; padding:3px 6px; ">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:3px 6px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td style="border:0.5px solid #000; border-left:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:3px 6px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td style="border:0.5px solid #000; border-left:none; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-right:none; border-bottom:none; padding:3px 6px;">&nbsp;</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="11" style="border:0.5px solid #000; border-left:none; border-bottom:none; padding:6px 8px; text-align:center; font-weight:bold;">Total</td>
                                <td style="border:0.5px solid #000; border-right:none; border-bottom:none; padding:6px 8px; font-weight:bold; text-align:left;">Rp.</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; line-height:1;">
                    <div style="font-family:Cambria,serif; padding-left:8px;">
                        Potongan-Potongan
                    </div>
                    <table style="width:100%; border-collapse:collapse; ">
                        <thead>
                            <tr>
                                <th
                                    style="border:0.5px solid #000; border-left:none; padding:4px 8px; text-align:center; width:30px; white-space:nowrap;">
                                    NO
                                </th>
                                <th style="border:0.5px solid #000; padding:4px 8px; text-align:center; width:55%;">
                                    Uraian</th>
                                <th style="border:0.5px solid #000; padding:4px 8px; text-align:center; width:35%;">
                                    Jumlah</th>
                                <th
                                    style="border:0.5px solid #000; border-right:none; padding:4px 8px; text-align:left; width:5%;">
                                    Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    1</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">Iuran Wajib
                                    Pegawai**</td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    2</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">Tabungan
                                    Perumahan Pegawai**</td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    3</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">BPJS Kesehatan
                                </td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    4</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">Dst.***</td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">&nbsp;</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"
                                    style="border:0.5px solid #000; border-left:none; border-bottom:none; padding:4px 8px; text-align:right; font-weight:bold;">
                                    Jumlah</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:4px 8px; text-align:left;">Rp.
                                    {{ number_format($surat->potongan ?? 0, 0, ',', '.') }}</td>
                                <td
                                    style="border:0.5px solid #000; border-right:none; border-bottom:none; padding:4px 8px;">
                                    &nbsp;</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>

            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; line-height:1;">
                    <div style="font-family:Cambria, serif; padding-left:8px;">
                        Informasi : (Potongan Pajak Tidak Mengurangi Jumlah Pembayaran SURAT-PD)
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:9pt;">
                        <thead>
                            <tr>
                                <th
                                    style="border:0.5px solid #000; border-left:none; padding:4px 8px; text-align:center; width:30px; white-space:nowrap;">
                                    NO
                                </th>
                                <th style="border:0.5px solid #000; padding:4px 8px; text-align:center; width:55%;">
                                    Uraian</th>
                                <th style="border:0.5px solid #000; padding:4px 8px; text-align:center; width:35%;">
                                    Jumlah</th>
                                <th
                                    style="border:0.5px solid #000; border-right:none; padding:4px 8px; text-align:left; width:5%;">
                                    Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    1</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">Iuran Wajib
                                    Utang PPh 21/22/23</td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    2</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;">Tabungan
                                    Utang PPN</td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td
                                    style="border:0.5px solid #000; border-left:none; padding:2px 4px; text-align:center; white-space:nowrap;">
                                    3</td>
                                <td style="border:0.5px solid #000; padding:4px 8px; text-align:left;"> 
                                </td>
                                <td style="border:0.5px solid #000; padding:4px 8px;">Rp.</td>
                                <td style="border:0.5px solid #000; border-right:none; padding:4px 8px;">&nbsp;</td>
                            </tr>

                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"
                                    style="border:0.5px solid #000; border-left:none; border-bottom:none; padding:4px 8px; text-align:right; font-weight:bold;">
                                    Jumlah</td>
                                <td style="border:0.5px solid #000; border-bottom:none; padding:4px 8px; text-align:left;">Rp.
                                    </td>
                                <td
                                    style="border:0.5px solid #000; border-right:none; border-bottom:none; padding:4px 8px;">
                                    &nbsp;</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>

            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; line-height:1;">
                    <table style="width:100%; border-collapse:collapse; font-size:9pt; ">
                        <tr>
                            <td style="padding:2px 6px; width:52%; text-align:left;">SURAT-PD yang dibayarkan</td>
                            <td style="padding:2px 6px; width:3%; text-align:center;">:</td>
                            <td style="padding:2px 6px; width:8%; text-align:left;">Rp. {{ number_format($surat->jumlah ?? 0, 0, ',', '.') }},-</td>

                        </tr>
                        <tr>
                            <td style="padding:2px 6px; text-align:left;">Jumlah yang diminta</td>
                            <td style="padding:2px 6px; text-align:center;">:</td>
                            <td style="padding:2px 6px; text-align:left;">Rp. {{ number_format($surat->jumlah ?? 0, 0, ',', '.') }},-</td>

                        </tr>
                        <tr>
                            <td style="padding:2px 6px; text-align:left;">Jumlah Potongan</td>
                            <td style="padding:2px 6px; text-align:center;">:</td>
                            <td style="padding:2px 6px; text-align:left;">Rp. {{ number_format($surat->potongan ?? 0, 0, ',', '.') }},-</td>

                        </tr>
                        <tr>
                            <td style="padding:2px 6px; text-align:left;">Jumlah yang dibayarkan</td>
                            <td style="padding:2px 6px; text-align:center;">:</td>
                            <td style="padding:2px 6px; text-align:left;">Rp. {{ number_format($surat->jumlah_akhir ?? 0, 0, ',', '.') }},-</td>
                            
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border:0.5px solid #000; line-height:1.2;">
                    <table style="width:100%; border-collapse:collapse; font-size:9pt; margin-top:5px; margin-bottom:10px;">
                        <tr>
                            <td style="width:50%; vertical-align:bottom; text-align:left;">
                                {{-- <div>* coret yang tidak perlu</div>
                                <div>** menyesuaikan ketentuan lebih lanjut</div>
                                <div>*** menyesuaikan ketentuan perundang-undangan</div> --}}
                            </td>
                            <td style="width:50%; vertical-align:top; text-align:center;">
                                <div>Sukabumi, {{ $tanggalTtd }} {{ $bulanTtd }} 20{{ substr($tahunTtd, -2) }}</div>
                                <div style="margin-top:2px; font-weight:bold;">Pejabat Keuangan BLUD</div>
                                <div style="margin-top:35px;"> </div>
                                <div style="margin-top:18px;">{{ $pejabatKeuangan }}</div>
                                <div style="margin-top:2px;">NIP. {{ $nipPejabatKeuangan }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>


</body>

</html>
