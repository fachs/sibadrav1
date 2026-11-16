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
        .bg-light { background-color: rgba(0,0,0,0.05); }
        .bg-primary-light { background-color: #dbeafe; }
        .bg-info-light { background-color: #cff4fc; }
        .bg-success-light { background-color: #d1e7dd; }
    </style>
</head>
<body>

@php
    if (!function_exists('aggregateDataByPrefixPerubahanPdf')) {
        function aggregateDataByPrefixPerubahanPdf($groupedData, $isBelanja = false, $isPerubahan = false) {
            $aggregated = [];
            
            foreach($groupedData as $kodeGroup => $items) {
                foreach($items as $item) {
                    if ($isBelanja) {
                        $kodeFull = optional($item->akuns)->kode_rek ?? '';
                        $jumlah_sebelum = (float) $item->jumlah_harga;
                        $jumlah_setelah = $isPerubahan ? ((float) ($item->proposed_jumlah_harga ?? $item->jumlah_harga)) : ((float) $item->jumlah_harga);
                    } else {
                        $kodeFull = $item->kode_rek;
                        $jumlah_sebelum = (float) $item->jumlah;
                        $jumlah_setelah = $isPerubahan ? ((float) ($item->jumlah_perubahan ?? $item->jumlah)) : ((float) $item->jumlah);
                    }
                    
                    if (empty($kodeFull)) continue;
                    
                    $parts = explode('.', $kodeFull);
                    $prefix2 = implode('.', array_slice($parts, 0, 2));
                    $prefix3 = implode('.', array_slice($parts, 0, 3));
                    
                    if (!isset($aggregated[$prefix2])) {
                        $label2 = 'AKUMULASI ' . $prefix2;
                        if ($prefix2 == '5.1') $label2 = 'BELANJA OPERASI';
                        elseif ($prefix2 == '5.2') $label2 = 'BELANJA MODAL';
                        elseif ($prefix2 == '5.3') $label2 = 'BELANJA TAK TERDUGA';
                        elseif ($prefix2 == '5.4') $label2 = 'BELANJA TRANSFER';
                        elseif ($prefix2 == '4.1') $label2 = 'JASA LAYANAN';
                        elseif ($prefix2 == '4.2') $label2 = 'HIBAH';
                        elseif ($prefix2 == '4.3') $label2 = 'HASIL KERJASAMA';
                        elseif ($prefix2 == '4.4') $label2 = 'APBD';
                        elseif ($prefix2 == '4.5') $label2 = 'Lain-lain Pendapatan BLUD yang Sah';
                        elseif ($prefix2 == '6.1') $label2 = 'PENERIMAAN PEMBIAYAAN';
                        elseif ($prefix2 == '6.2') $label2 = 'PENGELUARAN PEMBIAYAAN';
                        
                        $aggregated[$prefix2] = [
                            'kode_rek' => $prefix2,
                            'parts' => array_slice($parts, 0, 2),
                            'uraian' => $label2,
                            'jumlah_sebelum' => 0,
                            'jumlah_setelah' => 0,
                            'children' => []
                        ];
                    }
                    
                    if (!isset($aggregated[$prefix2]['children'][$prefix3])) {
                        $label3 = 'Akumulasi ' . $prefix3;
                        if ($prefix3 == '5.1.01') $label3 = 'Belanja Pegawai BLUD';
                        elseif ($prefix3 == '5.1.02') $label3 = 'Belanja Barang dan Jasa BLUD';
                        elseif ($prefix3 == '5.2.02') $label3 = 'Belanja Modal Peralatan dan Mesin BLUD';
                        elseif ($prefix3 == '5.2.03') $label3 = 'Belanja Modal Gedung dan Bangunan BLUD';
                        elseif ($prefix3 == '5.2.04') $label3 = 'Belanja Modal Jalan, Jaringan dan Irigasi BLUD';
                        
                        if (str_starts_with($prefix3, '4.')) {
                            if ($prefix2 == '4.1') $label3 = 'Jasa Layanan BLUD';
                            elseif ($prefix2 == '4.2') $label3 = 'Hibah BLUD';
                            elseif ($prefix2 == '4.3') $label3 = 'Hasil Kerjasama BLUD';
                            elseif ($prefix2 == '4.4') $label3 = 'APBD BLUD';
                            elseif ($prefix2 == '4.5') $label3 = 'Lain-lain Pendapatan BLUD yang Sah';
                        } elseif (str_starts_with($prefix3, '6.')) {
                            if ($prefix2 == '6.1') $label3 = 'Penerimaan Pembiayaan BLUD';
                            elseif ($prefix2 == '6.2') $label3 = 'Pengeluaran Pembiayaan BLUD';
                        }
                        
                        $aggregated[$prefix2]['children'][$prefix3] = [
                            'kode_rek' => $prefix3,
                            'parts' => array_slice($parts, 0, 3),
                            'uraian' => $label3,
                            'jumlah_sebelum' => 0,
                            'jumlah_setelah' => 0
                        ];
                    }
                    
                    $aggregated[$prefix2]['jumlah_sebelum'] += $jumlah_sebelum;
                    $aggregated[$prefix2]['jumlah_setelah'] += $jumlah_setelah;
                    $aggregated[$prefix2]['children'][$prefix3]['jumlah_sebelum'] += $jumlah_sebelum;
                    $aggregated[$prefix2]['children'][$prefix3]['jumlah_setelah'] += $jumlah_setelah;
                }
            }
            
            ksort($aggregated);
            foreach($aggregated as &$group) {
                ksort($group['children']);
            }
            return $aggregated;
        }
    }
    
    $pendapatanAgg = aggregateDataByPrefixPerubahanPdf($pendapatan['grouped'], false, true);
    $totalPendapatanSebelum = array_sum(array_column($pendapatanAgg, 'jumlah_sebelum'));
    $totalPendapatanSetelah = array_sum(array_column($pendapatanAgg, 'jumlah_setelah'));

    $belanjaAgg = aggregateDataByPrefixPerubahanPdf($belanja['grouped'], true, true);
    $totalBelanjaSebelum = array_sum(array_column($belanjaAgg, 'jumlah_sebelum'));
    $totalBelanjaSetelah = array_sum(array_column($belanjaAgg, 'jumlah_setelah'));

    $pembiayaanAgg = aggregateDataByPrefixPerubahanPdf($pembiayaan['grouped'], false, true);
    
    $surplusDefisitSebelum = $totalPendapatanSebelum - $totalBelanjaSebelum;
    $surplusDefisitSetelah = $totalPendapatanSetelah - $totalBelanjaSetelah;
    
    $penerimaanPembiayaanSebelum = $pembiayaanAgg['6.1']['jumlah_sebelum'] ?? 0;
    $penerimaanPembiayaanSetelah = $pembiayaanAgg['6.1']['jumlah_setelah'] ?? 0;
    $pengeluaranPembiayaanSebelum = $pembiayaanAgg['6.2']['jumlah_sebelum'] ?? 0;
    $pengeluaranPembiayaanSetelah = $pembiayaanAgg['6.2']['jumlah_setelah'] ?? 0;
    
    $pembiayaanNettoSebelum = $penerimaanPembiayaanSebelum - $pengeluaranPembiayaanSebelum;
    $pembiayaanNettoSetelah = $penerimaanPembiayaanSetelah - $pengeluaranPembiayaanSetelah;
    
    $silpaSebelum = $surplusDefisitSebelum + $pembiayaanNettoSebelum;
    $silpaSetelah = $surplusDefisitSetelah + $pembiayaanNettoSetelah;
@endphp

<table style="width: 100%; border: 1px solid black; margin-bottom: -1px;">
    <tr>
        <td style="width: 15%; text-align: center; border-right: none;">
            @php
                $logoPath = public_path('images/logo_sukabumi.png');
                if(!file_exists($logoPath)) $logoPath = public_path('images/logo-pemkot.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" style="width: 80px;" alt="Logo">
            @endif
        </td>
        <td style="width: 70%; text-align: center; border-right: none; border-left: none;">
            <div style="font-size: 14px; font-weight: bold;">PEMERINTAH KOTA SUKABUMI</div>
            <div style="font-size: 14px; font-weight: bold;">DINAS KESEHATAN</div>
            <div style="font-size: 16px; font-weight: bold;">BLUD UPTD {{ strtoupper($uptName) }}</div>
            <div style="font-size: 12px;">{{ $penandatangan->alamat ?? 'Jl. Raya Baros km 5 no 250 kota sukabumi kode pos 43161' }}</div>
        </td>
        <td style="width: 15%; border-left: none;"></td>
    </tr>
    <tr>
        <td colspan="3" class="text-center font-bold" style="border-top: 1px solid black;">
            <div style="font-size: 14px;">RINGKASAN PERUBAHAN RENCANA BISNIS ANGGARAN</div>
            <div style="font-size: 14px;">PENDAPATAN, BELANJA DAN PEMBIAYAAN</div>
            <div style="font-size: 14px;">TAHUN ANGGARAN {{ $tahun ?? date('Y') }}</div>
        </td>
    </tr>
</table>

<table style="width: 100%; font-size: 11px;">
    <thead>
        <tr>
            <th colspan="3" class="text-center" style="width: 12%;">Kode</th>
            <th class="text-center" style="width: 38%;">Uraian</th>
            <th class="text-center" style="width: 15%;">Sebelum Perubahan (Rp)</th>
            <th class="text-center" style="width: 15%;">Setelah Perubahan (Rp)</th>
            <th class="text-center" style="width: 20%;">Selisih Bertambah/ (Berkurang)</th>
        </tr>
        <tr style="background-color: #fff;">
            <th colspan="3" class="text-center">1</th>
            <th class="text-center">2</th>
            <th class="text-center">3</th>
            <th class="text-center">4</th>
            <th class="text-center">5</th>
        </tr>
    </thead>
    <tbody>
        <!-- PENDAPATAN -->
        <tr class="font-bold bg-primary-light">
            <td class="text-center">4</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td>PENDAPATAN BLUD</td>
            <td class="text-right">{{ number_format($totalPendapatanSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalPendapatanSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalPendapatanSetelah - $totalPendapatanSebelum, 0, ',', '.') }}</td>
        </tr>
        
        @foreach($pendapatanAgg as $prefix2 => $group)
            <tr>
                <td class="text-center">{{ $group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ $group['uraian'] }}</td>
                <td class="text-right">{{ number_format($group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'] - $group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @foreach($group['children'] as $prefix3 => $data)
            <tr>
                <td class="text-center">{{ $data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][2] ?? '' }}</td>
                <td>{{ $data['uraian'] }}</td>
                <td class="text-right">{{ number_format($data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'] - $data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format($group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'] - $group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <!-- BELANJA -->
        <tr class="font-bold bg-info-light">
            <td class="text-center">5</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td>BELANJA DAERAH</td>
            <td class="text-right">{{ number_format($totalBelanjaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalBelanjaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalBelanjaSetelah - $totalBelanjaSebelum, 0, ',', '.') }}</td>
        </tr>
        
        @foreach($belanjaAgg as $prefix2 => $group)
            <tr class="font-bold">
                <td class="text-center">{{ $group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ $group['uraian'] }}</td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
            </tr>
            @foreach($group['children'] as $prefix3 => $data)
            <tr>
                <td class="text-center">{{ $data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][2] ?? '' }}</td>
                <td>{{ $data['uraian'] }}</td>
                <td class="text-right">{{ number_format($data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'] - $data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format($group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'] - $group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Total Belanja</td>
            <td class="text-right">{{ number_format($totalBelanjaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalBelanjaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($totalBelanjaSetelah - $totalBelanjaSebelum, 0, ',', '.') }}</td>
        </tr>
        
        <!-- SURPLUS DEFISIT -->
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">SURPLUS/ DEFISIT</td>
            <td class="text-right">{{ number_format($surplusDefisitSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($surplusDefisitSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($surplusDefisitSetelah - $surplusDefisitSebelum, 0, ',', '.') }}</td>
        </tr>
        
        <!-- PEMBIAYAAN -->
        <tr class="font-bold bg-success-light">
            <td class="text-center">6</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td>PEMBIAYAAN</td>
            <td class="text-right"></td>
            <td class="text-right"></td>
            <td class="text-right"></td>
        </tr>
        
        @foreach($pembiayaanAgg as $prefix2 => $group)
            <tr class="font-bold">
                <td class="text-center">{{ $group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ $group['uraian'] }}</td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
            </tr>
            @foreach($group['children'] as $prefix3 => $data)
            <tr>
                <td class="text-center">{{ $data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ $data['parts'][2] ?? '' }}</td>
                <td>{{ $data['uraian'] }}</td>
                <td class="text-right">{{ number_format($data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data['jumlah_setelah'] - $data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format($group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($group['jumlah_setelah'] - $group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Pembiayaan Neto</td>
            <td class="text-right">{{ number_format($pembiayaanNettoSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($pembiayaanNettoSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($pembiayaanNettoSetelah - $pembiayaanNettoSebelum, 0, ',', '.') }}</td>
        </tr>
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)</td>
            <td class="text-right">{{ number_format($silpaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($silpaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($silpaSetelah - $silpaSebelum, 0, ',', '.') }}</td>
        </tr></tbody>
</table>

<div style="page-break-inside: avoid; margin-top: 30px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td style="width: 40%; text-align: left; border: none; padding-left: 50px;">
                Sukabumi, {{ now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}<br>
                Kepala {{ $uptName ?? ($user->upt ?? '-') }}<br>
                
                <br><br><br><br>
                
                <strong style="text-decoration: underline;">{{ $penandatangan->kepala ?? 'Nama Lengkap' }}</strong><br>
                NIP. {{ $penandatangan->nip_kapus ?? '_______________' }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
