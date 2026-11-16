<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; }
        .report-heading { position: relative; text-align: center; line-height: 1.5; height: 100px; font-size: 9px; font-weight: bold; }
        .report-heading img { position: absolute; left: 0; top: 2px; width: 55px; }
        .lra-report-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7px; }
        .lra-report-table th, .lra-report-table td { border: 1px solid #333; padding: 3px 4px; vertical-align: top; overflow-wrap: break-word; }
        .lra-report-table thead th { text-align: center; font-weight: bold; background: #eee; }
        .lra-report-table .lra-code { width: 17%; }
        .lra-report-table .lra-description { width: 35%; }
        .lra-report-table .lra-money { width: 13%; text-align: right; }
        .lra-report-table .lra-percent { width: 9%; text-align: right; }
        .lra-report-table thead .lra-percent { text-align: center; }
        .lra-report-table .lra-column-numbers th { font-weight: normal; }
        .lra-report-table .lra-section, .lra-report-table .lra-total { font-weight: bold; }
        .lra-report-table .lra-section { background: #eee; }
        .lra-report-table .lra-total { background: #f3f3f3; }
        .lra-report-table tr { page-break-inside: avoid; }
        .signature { width: 40%; margin: 22px 0 0 auto; text-align: center; line-height: 1.6; page-break-inside: avoid; }
        .signature-name { margin-top: 45px; font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="report-heading">
        @if(is_file(public_path('images/logo-pemkot.png')))
            <img src="{{ public_path('images/logo-pemkot.png') }}" alt="">
        @endif
        <div>PEMERINTAHAN KOTA SUKABUMI</div>
        <div>{{ strtoupper($unitLabel) }}</div>
        <div>LAPORAN REALISASI ANGGARAN PENDAPATAN DAN BELANJA DAERAH</div>
        <div>TAHUN ANGGARAN {{ $year }}</div>
        <div>{{ $periodLabel }}</div>
    </div>
    @include('laporan-penatausahaan.partials.lra-table')
    @if($selectedUnit)
        <div class="signature">
            <div>Kota Sukabumi, {{ now('Asia/Jakarta')->locale('id')->translatedFormat('j F Y') }}</div>
            <div>KEPALA {{ strtoupper($unitLabel) }}</div>
            <div class="signature-name">{{ $selectedUnit->kepala ?: '________________________' }}</div>
            <div>NIP. {{ $selectedUnit->nip_kapus ?: '________________________' }}</div>
        </div>
    @endif
</body>
</html>
