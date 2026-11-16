<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A3 landscape; margin: 8mm 6mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111; }
        .lpj-report-heading { text-align: center; line-height: 1.35; margin-bottom: 14px; }
        .lpj-report-title { font-weight: bold; font-size: 9px; margin-top: 10px; }
        .lpj-report-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 6px; }
        .lpj-report-table th, .lpj-report-table td { border: 1px solid #333; padding: 2px 3px; vertical-align: middle; }
        .lpj-report-table thead th { background: #eee; text-align: center; }
        .lpj-report-table .lpj-column-numbers th { font-weight: normal; }
        .lpj-report-table .lpj-col-code { width: 7%; }
        .lpj-report-table .lpj-col-label { width: 16%; }
        .lpj-report-table .lpj-col-money { width: 6.416%; }
        .lpj-report-table .lpj-money { text-align: right; white-space: nowrap; }
        .lpj-report-table .lpj-label { text-align: left; }
        .lpj-report-table .lpj-group, .lpj-report-table .lpj-section { background: #eee; font-weight: bold; }
        .lpj-report-table .lpj-total { background: #f3f3f3; font-weight: bold; }
        .lpj-report-table .lpj-section th { border-left: 0; border-right: 0; text-align: left; }
        .lpj-report-table .lpj-spacer td { height: 10px; border: 0; }
        .lpj-report-table tr { page-break-inside: avoid; }
        .lpj-signatures { width: 100%; margin-top: 20px; page-break-inside: avoid; }
        .lpj-signature { display: inline-block; width: 45%; text-align: center; line-height: 1.45; vertical-align: top; }
        .lpj-signature + .lpj-signature { margin-left: 8%; }
        .lpj-signature-name { margin-top: 44px; font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    @include('laporan-penatausahaan.partials.lpj-heading')
    @include('laporan-penatausahaan.partials.lpj-table')
    @include('laporan-penatausahaan.partials.lpj-signatures')
</body>
</html>
