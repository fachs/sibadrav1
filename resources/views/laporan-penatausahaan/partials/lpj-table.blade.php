@php
    $money = fn ($value) => 'Rp'.number_format((float) $value, 2, ',', '.');
    $add = fn ($a, $b) => (string) \Brick\Math\BigDecimal::of($a)->plus($b);
    $minus = fn ($a, $b) => (string) \Brick\Math\BigDecimal::of($a)->minus($b);
    $amounts = fn ($row) => [
        $row['budget'], $row['gaji_lalu'], $row['gaji_ini'], $add($row['gaji_lalu'], $row['gaji_ini']),
        $row['barang_lalu'], $row['barang_ini'], $add($row['barang_lalu'], $row['barang_ini']),
        $row['up_lalu'], $row['up_ini'], $add($row['up_lalu'], $row['up_ini']),
        $add($add($add($row['gaji_lalu'], $row['gaji_ini']), $add($row['barang_lalu'], $row['barang_ini'])), $add($row['up_lalu'], $row['up_ini'])),
    ];
    $total = $lpj['total'];
    $programRows = [];
    foreach (['1.02.01' => 'PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA', '1.02.01.2.10' => 'Peningkatan Pelayanan BLUD', '1.02.01.2.10.0001' => 'Pelayanan dan Penunjang Pelayanan BLUD'] as $code => $label) {
        $programRows[] = array_merge($total, ['code' => $code, 'label' => $label]);
    }
    $taxes = ['Pajak Pertambahan Nilai', 'Pajak Penghasilan Ps 22', 'PPH 21', 'Belanja Tunjangan PPh/Tunjangan Khusus PNS', 'Belanja Tunjangan PPh/Tunjangan Khusus PPPK', 'Pajak Penghasilan Ps 23', 'Pajak Penghasilan Ps 25', 'Pajak Penghasilan Ps 26', 'Pajak Penghasilan Ps 4 (2)', 'Pajak Penghasilan Ps 15', 'Pajak Daerah'];
    $summary = [
        ['section' => 'Penerimaan :'], ['section' => '- SP2D:'],
        ['label' => 'UP', 'group' => 'advance'], ['label' => 'GU', 'group' => 'up'], ['label' => 'TU'],
        ['label' => 'LS', 'group' => 'ls'], ['label' => 'GU-KKPD'], ['label' => 'Total Potongan', 'group' => 'tax_total'],
        ['section' => '- Pajak:'],
    ];
    foreach ($taxes as $index => $tax) $summary[] = ['label' => ($index + 1).'. '.$tax, 'group' => 'tax', 'tax' => $tax];
    $summary[] = ['label' => 'Total Pajak', 'group' => 'tax_total'];
    $summary[] = ['label' => 'JUMLAH PENERIMAAN SP2D BRUTO', 'group' => 'receipts'];
    $summary[] = ['spacer' => true];
    $summary[] = ['section' => '- Pengeluaran:'];
    $summary[] = ['section' => '- SPJ:'];
    foreach ([['UP/GU', 'up'], ['TU', null], ['LS', 'ls'], ['GU-KKPD', null]] as [$label, $group]) $summary[] = ['label' => $label, 'group' => $group];
    $summary[] = ['section' => '- Pajak:'];
    foreach ($taxes as $index => $tax) $summary[] = ['label' => ($index + 1).'. '.$tax, 'group' => 'tax', 'tax' => $tax];
    $summary[] = ['label' => 'Total Pajak', 'group' => 'tax_total'];
    $summary[] = ['section' => '- SPJ (Neto):'];
    foreach ([['UP/GU', 'up'], ['TU', null], ['LS', 'ls'], ['GU-KKPD', null]] as [$label, $group]) $summary[] = ['label' => $label, 'group' => $group];
    foreach (['Pengembalian ke Kasda (STS)', 'Pengembalian ke Kasda (STS KKPD)'] as $label) $summary[] = ['label' => $label];
    $summary[] = ['label' => 'JUMLAH PENGELUARAN', 'group' => 'all'];
    $summary[] = ['spacer' => true];
    $summary[] = ['label' => 'Saldo Kas UP/GU/TU', 'group' => 'cash'];
    $summary[] = ['label' => 'Saldo Kas KKPD'];
    $summary[] = ['label' => 'Saldo Kas', 'group' => 'cash'];
@endphp
<table class="lpj-report-table">
    <colgroup>
        <col class="lpj-col-code"><col class="lpj-col-label">
        @for($i = 0; $i < 12; $i++)<col class="lpj-col-money">@endfor
    </colgroup>
    <thead>
        <tr>
            <th rowspan="2">Kode Rekening</th><th rowspan="2">Uraian</th><th rowspan="2">Jumlah Anggaran</th>
            <th colspan="3">SPJ - LS Gaji</th><th colspan="3">SPJ - LS Barang &amp; Jasa</th><th colspan="3">SPJ - UP/GU/TU</th>
            <th rowspan="2">Jumlah SPJ (LS + UP/GU/TU) s.d Bulan ini</th><th rowspan="2">Sisa Pagu Anggaran</th>
        </tr>
        <tr>
            @for($i = 0; $i < 3; $i++)<th>s.d. Bulan Lalu</th><th>Bulan Ini</th><th>s.d. Bulan Ini</th>@endfor
        </tr>
        <tr class="lpj-column-numbers">
            @foreach(['1','2','3','4','5','6 = (4+5)','7','8','9 = (7+8)','10','11','12 = (10+11)','13 = (6+9+12)','14 = (3-13)'] as $number)<th>{{ $number }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach(array_merge([array_merge($total, ['code' => $unitLabel, 'label' => $unitLabel])], $programRows, $lpj['rows'], [$total]) as $row)
            @php $values = $amounts($row); @endphp
            <tr @class(['lpj-group' => $row['code'] === $unitLabel || ($row['code'] !== '' && substr_count($row['code'], '.') <= 2), 'lpj-total' => $row['code'] === ''])>
                <td class="lpj-code">{{ $row['code'] }}</td><td class="lpj-label">{{ $row['label'] }}</td>
                @foreach($values as $value)<td class="lpj-money">{{ $money($value) }}</td>@endforeach
                <td class="lpj-money">{{ $money($minus($row['budget'], $values[10])) }}</td>
            </tr>
        @endforeach
        <tr class="lpj-spacer"><td colspan="14"></td></tr>
        @foreach($summary as $line)
            @if(isset($line['spacer']))
                <tr class="lpj-spacer"><td colspan="14"></td></tr>
            @elseif(isset($line['section']))
                <tr class="lpj-section"><th colspan="14">{{ $line['section'] }}</th></tr>
            @else
                @php
                    $values = array_fill(0, 11, '0.00');
                    $group = $line['group'] ?? null;
                    if ($group === 'all') $values = $amounts($total);
                    if ($group === 'advance') {
                        $values[7] = $lpj['sp2dUp']['lalu']; $values[8] = $lpj['sp2dUp']['ini'];
                        $values[9] = $add($values[7], $values[8]); $values[10] = $values[9];
                    }
                    if ($group === 'up') {
                        $values[7] = $total['up_lalu']; $values[8] = $total['up_ini'];
                        $values[9] = $add($values[7], $values[8]); $values[10] = $values[9];
                    }
                    if ($group === 'ls') {
                        $values[1] = $total['gaji_lalu']; $values[2] = $total['gaji_ini']; $values[3] = $add($values[1], $values[2]);
                        $values[4] = $total['barang_lalu']; $values[5] = $total['barang_ini']; $values[6] = $add($values[4], $values[5]);
                        $values[10] = $add($values[3], $values[6]);
                    }
                    if ($group === 'receipts') {
                        $values = $amounts($total);
                        $values[7] = $add($values[7], $lpj['sp2dUp']['lalu']);
                        $values[8] = $add($values[8], $lpj['sp2dUp']['ini']);
                        $values[9] = $add($values[7], $values[8]);
                        $values[10] = $add($add($values[3], $values[6]), $values[9]);
                    }
                    if ($group === 'cash') {
                        $values[7] = $minus($lpj['sp2dUp']['lalu'], $total['up_lalu']);
                        $values[8] = $minus($lpj['sp2dUp']['ini'], $total['up_ini']);
                        $values[9] = $add($values[7], $values[8]); $values[10] = $values[9];
                    }
                    if ($group === 'tax' || $group === 'tax_total') {
                        $taxAmounts = $group === 'tax' ? $lpj['taxes'][$line['tax']] : ['lalu' => '0.00', 'ini' => '0.00'];
                        if ($group === 'tax_total') {
                            foreach ($lpj['taxes'] as $item) {
                                $taxAmounts['lalu'] = $add($taxAmounts['lalu'], $item['lalu']);
                                $taxAmounts['ini'] = $add($taxAmounts['ini'], $item['ini']);
                            }
                        }
                        $values[7] = $taxAmounts['lalu']; $values[8] = $taxAmounts['ini'];
                        $values[9] = $add($values[7], $values[8]); $values[10] = $values[9];
                    }
                    $values[0] = null;
                @endphp
                <tr @class(['lpj-total' => str_starts_with($line['label'], 'JUMLAH') || in_array($line['label'], ['Total Pajak', 'Saldo Kas'])])>
                    <td colspan="2" class="lpj-label">{{ $line['label'] }}</td>
                    @foreach($values as $value)<td class="lpj-money">{{ $value === null ? '' : $money($value) }}</td>@endforeach
                    <td class="lpj-money">{{ $money(0) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
