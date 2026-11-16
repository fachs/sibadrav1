<?php
$f = 'c:/laragon/www/Sibadra_v1/resources/views/pdf/rba-ringkasan-perubahan.blade.php';
$c = file_get_contents($f);

// 1. Update the aggregate function
$old_function = <<<EOT
                    if (\$isBelanja) {
                        \$kodeFull = optional(\$item->akuns)->kode_rek ?? '';
                        \$jumlah = \$isPerubahan ? ((float) (\$item->proposed_jumlah_harga ?? \$item->jumlah_harga)) : ((float) \$item->jumlah_harga);
                    } else {
                        \$kodeFull = \$item->kode_rek;
                        \$jumlah = \$isPerubahan ? ((float) (\$item->jumlah_perubahan ?? \$item->jumlah)) : ((float) \$item->jumlah);
                    }
EOT;
$new_function = <<<EOT
                    if (\$isBelanja) {
                        \$kodeFull = optional(\$item->akuns)->kode_rek ?? '';
                        \$jumlah_sebelum = (float) \$item->jumlah_harga;
                        \$jumlah_setelah = \$isPerubahan ? ((float) (\$item->proposed_jumlah_harga ?? \$item->jumlah_harga)) : ((float) \$item->jumlah_harga);
                    } else {
                        \$kodeFull = \$item->kode_rek;
                        \$jumlah_sebelum = (float) \$item->jumlah;
                        \$jumlah_setelah = \$isPerubahan ? ((float) (\$item->jumlah_perubahan ?? \$item->jumlah)) : ((float) \$item->jumlah);
                    }
EOT;
$c = str_replace($old_function, $new_function, $c);

// 2. Add jumlah_sebelum and jumlah_setelah to arrays
$c = str_replace("'jumlah' => 0,", "'jumlah_sebelum' => 0,\n                            'jumlah_setelah' => 0,", $c);
$c = str_replace("\$aggregated[\$prefix2]['jumlah'] += \$jumlah;", "\$aggregated[\$prefix2]['jumlah_sebelum'] += \$jumlah_sebelum;\n                    \$aggregated[\$prefix2]['jumlah_setelah'] += \$jumlah_setelah;", $c);
$c = str_replace("\$aggregated[\$prefix2]['children'][\$prefix3]['jumlah'] += \$jumlah;", "\$aggregated[\$prefix2]['children'][\$prefix3]['jumlah_sebelum'] += \$jumlah_sebelum;\n                    \$aggregated[\$prefix2]['children'][\$prefix3]['jumlah_setelah'] += \$jumlah_setelah;", $c);

// 3. Update the variables
$old_vars = <<<EOT
    \$surplusDefisit = \$pendapatan['grandTotalJumlah'] - \$belanja['grandTotalJumlah'];
    
    \$pembiayaanAgg = aggregateDataByPrefixPdf(\$pembiayaan['grouped'], false, true);
    \$penerimaanPembiayaan = \$pembiayaanAgg['6.1']['jumlah'] ?? 0;
    \$pengeluaranPembiayaan = \$pembiayaanAgg['6.2']['jumlah'] ?? 0;
    \$pembiayaanNetto = \$penerimaanPembiayaan - \$pengeluaranPembiayaan;
    
    \$silpa = \$surplusDefisit + \$pembiayaanNetto;
EOT;
$new_vars = <<<EOT
    \$pendapatanAgg = aggregateDataByPrefixPdf(\$pendapatan['grouped'], false, true);
    \$totalPendapatanSebelum = array_sum(array_column(\$pendapatanAgg, 'jumlah_sebelum'));
    \$totalPendapatanSetelah = array_sum(array_column(\$pendapatanAgg, 'jumlah_setelah'));

    \$belanjaAgg = aggregateDataByPrefixPdf(\$belanja['grouped'], true, true);
    \$totalBelanjaSebelum = array_sum(array_column(\$belanjaAgg, 'jumlah_sebelum'));
    \$totalBelanjaSetelah = array_sum(array_column(\$belanjaAgg, 'jumlah_setelah'));

    \$pembiayaanAgg = aggregateDataByPrefixPdf(\$pembiayaan['grouped'], false, true);
    
    \$surplusDefisitSebelum = \$totalPendapatanSebelum - \$totalBelanjaSebelum;
    \$surplusDefisitSetelah = \$totalPendapatanSetelah - \$totalBelanjaSetelah;
    
    \$penerimaanPembiayaanSebelum = \$pembiayaanAgg['6.1']['jumlah_sebelum'] ?? 0;
    \$penerimaanPembiayaanSetelah = \$pembiayaanAgg['6.1']['jumlah_setelah'] ?? 0;
    \$pengeluaranPembiayaanSebelum = \$pembiayaanAgg['6.2']['jumlah_sebelum'] ?? 0;
    \$pengeluaranPembiayaanSetelah = \$pembiayaanAgg['6.2']['jumlah_setelah'] ?? 0;
    
    \$pembiayaanNettoSebelum = \$penerimaanPembiayaanSebelum - \$pengeluaranPembiayaanSebelum;
    \$pembiayaanNettoSetelah = \$penerimaanPembiayaanSetelah - \$pengeluaranPembiayaanSetelah;
    
    \$silpaSebelum = \$surplusDefisitSebelum + \$pembiayaanNettoSebelum;
    \$silpaSetelah = \$surplusDefisitSetelah + \$pembiayaanNettoSetelah;
EOT;
$c = str_replace($old_vars, $new_vars, $c);

// 5. Update table headers
$old_th = <<<EOT
    <thead>
        <tr>
            <th colspan="3" class="text-center" style="width: 15%;">Kode</th>
            <th class="text-center" style="width: 59%;">Uraian</th>
            <th class="text-center" style="width: 26%;">Jumlah (Rp)</th>
        </tr>
        <tr style="background-color: #fff;">
            <th colspan="3" class="text-center">1</th>
            <th class="text-center">2</th>
            <th class="text-center">3</th>
        </tr>
    </thead>
EOT;
$new_th = <<<EOT
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
EOT;
$c = str_replace($old_th, $new_th, $c);

// 6. Update table body structure
// Replace everything between <tbody> and </tbody>
$old_tbody_start = "<tbody>";
$old_tbody_end = "</tbody>";
$start_pos = strpos($c, $old_tbody_start);
$end_pos = strpos($c, $old_tbody_end, $start_pos);

if ($start_pos !== false && $end_pos !== false) {
    $new_tbody = <<<EOT
<tbody>
        <!-- PENDAPATAN -->
        <tr class="font-bold bg-primary-light">
            <td class="text-center">4</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td>PENDAPATAN BLUD</td>
            <td class="text-right">{{ number_format(\$totalPendapatanSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalPendapatanSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalPendapatanSetelah - \$totalPendapatanSebelum, 0, ',', '.') }}</td>
        </tr>
        
        @foreach(\$pendapatanAgg as \$prefix2 => \$group)
            <tr>
                <td class="text-center">{{ \$group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ \$group['uraian'] }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @foreach(\$group['children'] as \$prefix3 => \$data)
            <tr>
                <td class="text-center">{{ \$data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][2] ?? '' }}</td>
                <td>{{ \$data['uraian'] }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <!-- BELANJA -->
        <tr class="font-bold bg-info-light">
            <td class="text-center">5</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td>BELANJA DAERAH</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSetelah - \$totalBelanjaSebelum, 0, ',', '.') }}</td>
        </tr>
        
        @foreach(\$belanjaAgg as \$prefix2 => \$group)
            <tr class="font-bold">
                <td class="text-center">{{ \$group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ \$group['uraian'] }}</td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
            </tr>
            @foreach(\$group['children'] as \$prefix3 => \$data)
            <tr>
                <td class="text-center">{{ \$data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][2] ?? '' }}</td>
                <td>{{ \$data['uraian'] }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Total Belanja</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$totalBelanjaSetelah - \$totalBelanjaSebelum, 0, ',', '.') }}</td>
        </tr>
        
        <!-- SURPLUS DEFISIT -->
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">SURPLUS/ DEFISIT</td>
            <td class="text-right">{{ number_format(\$surplusDefisitSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$surplusDefisitSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$surplusDefisitSetelah - \$surplusDefisitSebelum, 0, ',', '.') }}</td>
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
        
        @foreach(\$pembiayaanAgg as \$prefix2 => \$group)
            <tr class="font-bold">
                <td class="text-center">{{ \$group['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$group['parts'][1] ?? '' }}</td>
                <td class="text-center"></td>
                <td>{{ \$group['uraian'] }}</td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
            </tr>
            @foreach(\$group['children'] as \$prefix3 => \$data)
            <tr>
                <td class="text-center">{{ \$data['parts'][0] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][1] ?? '' }}</td>
                <td class="text-center">{{ \$data['parts'][2] ?? '' }}</td>
                <td>{{ \$data['uraian'] }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="font-bold bg-light">
                <td colspan="3"></td>
                <td class="text-center">Jumlah</td>
                <td class="text-right">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Pembiayaan Neto</td>
            <td class="text-right">{{ number_format(\$pembiayaanNettoSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$pembiayaanNettoSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$pembiayaanNettoSetelah - \$pembiayaanNettoSebelum, 0, ',', '.') }}</td>
        </tr>
        <tr class="font-bold">
            <td colspan="3"></td>
            <td class="text-center">Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)</td>
            <td class="text-right">{{ number_format(\$silpaSebelum, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$silpaSetelah, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format(\$silpaSetelah - \$silpaSebelum, 0, ',', '.') }}</td>
        </tr>
EOT;
    $c = substr_replace($c, $new_tbody, $start_pos, $end_pos - $start_pos);
}

file_put_contents($f, $c);
echo "done pdf view";
