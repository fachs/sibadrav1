@php
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
@endphp
<div class="lpj-report-heading">
    <div>PEMERINTAH KOTA SUKABUMI</div>
    <div>SKPD : DINAS KESEHATAN</div>
    <div>UNIT : {{ strtoupper($unitLabel) }}</div>
    <div>Tahun Anggaran : {{ $year }}</div>
    <div class="lpj-report-title">LAPORAN PERTANGGUNGJAWABAN SKPD</div>
    <div>(SPJ FUNGSIONAL)</div>
    <div>Bulan : {{ $months[$lpj['month']] }}</div>
</div>
