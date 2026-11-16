<?php
$f = 'c:/laragon/www/Sibadra_v1/resources/views/rba-ringkasan-perubahan.blade.php';
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
    
    \$pembiayaanAgg = aggregateDataByPrefix(\$pembiayaan['grouped'], false, true);
    \$penerimaanPembiayaan = \$pembiayaanAgg['6.1']['jumlah'] ?? 0;
    \$pengeluaranPembiayaan = \$pembiayaanAgg['6.2']['jumlah'] ?? 0;
    \$pembiayaanNetto = \$penerimaanPembiayaan - \$pengeluaranPembiayaan;
    
    \$silpa = \$surplusDefisit + \$pembiayaanNetto;
EOT;
$new_vars = <<<EOT
    \$pendapatanAgg = aggregateDataByPrefix(\$pendapatan['grouped'], false, true);
    \$totalPendapatanSebelum = array_sum(array_column(\$pendapatanAgg, 'jumlah_sebelum'));
    \$totalPendapatanSetelah = array_sum(array_column(\$pendapatanAgg, 'jumlah_setelah'));

    \$belanjaAgg = aggregateDataByPrefix(\$belanja['grouped'], true, true);
    \$totalBelanjaSebelum = array_sum(array_column(\$belanjaAgg, 'jumlah_sebelum'));
    \$totalBelanjaSetelah = array_sum(array_column(\$belanjaAgg, 'jumlah_setelah'));

    \$pembiayaanAgg = aggregateDataByPrefix(\$pembiayaan['grouped'], false, true);
    
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

// 4. Update the summary cards
$c = str_replace("{{ number_format(\$surplusDefisit, 2, ',', '.') }}", "{{ number_format(\$surplusDefisitSetelah, 2, ',', '.') }}", $c);
$c = str_replace("{{ number_format(\$pembiayaanNetto, 2, ',', '.') }}", "{{ number_format(\$pembiayaanNettoSetelah, 2, ',', '.') }}", $c);
$c = str_replace("{{ number_format(\$silpa, 2, ',', '.') }}", "{{ number_format(\$silpaSetelah, 2, ',', '.') }}", $c);

// 5. Update table headers
$old_th = <<<EOT
                                <th>Kode Rekening</th>
                                <th>Uraian</th>
                                <th class="text-end">Jumlah Setelah Perubahan (Rp)</th>
EOT;
$new_th = <<<EOT
                                <th>Kode Rekening</th>
                                <th>Uraian</th>
                                <th class="text-end">Sebelum Perubahan (Rp)</th>
                                <th class="text-end">Setelah Perubahan (Rp)</th>
                                <th class="text-end">Selisih (Tambah/Kurang)</th>
EOT;
$c = str_replace($old_th, $new_th, $c);

// 6. Update table body structure
// We can use regex to update all rows that show {{ number_format($jumlah, 0, ',', '.') }} or similar.
// Actually, it's easier to just recreate the body entirely or do a regex replace.
// Let's replace the whole <tbody> content.

$old_tbody_start = "<tbody>";
$old_tbody_end = "</tbody>";
$start_pos = strpos($c, $old_tbody_start);
$end_pos = strpos($c, $old_tbody_end, $start_pos);

if ($start_pos !== false && $end_pos !== false) {
    $new_tbody = <<<EOT
<tbody>
                            <!-- PENDAPATAN -->
                            <tr class="fw-bold bg-primary bg-opacity-10">
                                <td>4</td>
                                <td>PENDAPATAN BLUD</td>
                                <td class="text-end">{{ number_format(\$totalPendapatanSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalPendapatanSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalPendapatanSetelah - \$totalPendapatanSebelum, 0, ',', '.') }}</td>
                            </tr>
                            
                            @foreach(\$pendapatanAgg as \$prefix2 => \$group)
                                <tr>
                                    <td>{{ \$prefix2 }}</td>
                                    <td>{{ \$group['uraian'] }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                                @foreach(\$group['children'] as \$prefix3 => \$data)
                                <tr>
                                    <td class="ps-4">{{ \$prefix3 }}</td>
                                    <td class="ps-4">{{ \$data['uraian'] }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                                <tr class="fw-bold bg-light">
                                    <td colspan="2" class="text-end">Jumlah</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            
                            <!-- BELANJA -->
                            <tr class="fw-bold bg-info bg-opacity-10">
                                <td>5</td>
                                <td>BELANJA DAERAH</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSetelah - \$totalBelanjaSebelum, 0, ',', '.') }}</td>
                            </tr>
                            
                            @foreach(\$belanjaAgg as \$prefix2 => \$group)
                                <tr class="fw-bold">
                                    <td>{{ \$prefix2 }}</td>
                                    <td>{{ \$group['uraian'] }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                @foreach(\$group['children'] as \$prefix3 => \$data)
                                <tr>
                                    <td class="ps-4">{{ \$prefix3 }}</td>
                                    <td class="ps-4">{{ \$data['uraian'] }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                                <tr class="fw-bold bg-light">
                                    <td colspan="2" class="text-end">Jumlah</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            
                            <tr class="fw-bold bg-light">
                                <td colspan="2" class="text-end">Total Belanja</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$totalBelanjaSetelah - \$totalBelanjaSebelum, 0, ',', '.') }}</td>
                            </tr>
                            
                            <!-- SURPLUS DEFISIT -->
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">SURPLUS/ DEFISIT</td>
                                <td class="text-end">{{ number_format(\$surplusDefisitSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$surplusDefisitSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$surplusDefisitSetelah - \$surplusDefisitSebelum, 0, ',', '.') }}</td>
                            </tr>
                            
                            <!-- PEMBIAYAAN -->
                            <tr class="fw-bold bg-success bg-opacity-10">
                                <td>6</td>
                                <td>PEMBIAYAAN</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            
                            @foreach(\$pembiayaanAgg as \$prefix2 => \$group)
                                <tr class="fw-bold">
                                    <td>{{ \$prefix2 }}</td>
                                    <td>{{ \$group['uraian'] }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                @foreach(\$group['children'] as \$prefix3 => \$data)
                                <tr>
                                    <td class="ps-4">{{ \$prefix3 }}</td>
                                    <td class="ps-4">{{ \$data['uraian'] }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$data['jumlah_setelah'] - \$data['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                                <tr class="fw-bold bg-light">
                                    <td colspan="2" class="text-end">Jumlah</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format(\$group['jumlah_setelah'] - \$group['jumlah_sebelum'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Pembiayaan Neto</td>
                                <td class="text-end">{{ number_format(\$pembiayaanNettoSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$pembiayaanNettoSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$pembiayaanNettoSetelah - \$pembiayaanNettoSebelum, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Sisa Lebih Pembiayaan Anggaran Tahun Berkenaan (SiLPA)</td>
                                <td class="text-end">{{ number_format(\$silpaSebelum, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$silpaSetelah, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format(\$silpaSetelah - \$silpaSebelum, 0, ',', '.') }}</td>
                            </tr>
                        
EOT;
    $c = substr_replace($c, $new_tbody, $start_pos, $end_pos - $start_pos);
}

file_put_contents($f, $c);
echo "done web view";
