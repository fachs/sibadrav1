@php
    $formatAmount = function ($value) {
        $number = (float) $value;
        $digits = abs($number - round($number)) < 0.005 ? 0 : 2;
        $formatted = number_format(abs($number), $digits, ',', '.');
        return $number < 0 ? '('.$formatted.')' : $formatted;
    };
@endphp
<table class="lra-report-table">
    <thead>
        <tr>
            <th class="lra-code">Kode Rekening</th>
            <th class="lra-description">URAIAN</th>
            <th class="lra-money">ANGGARAN</th>
            <th class="lra-money">REALISASI {{ $year }}</th>
            <th class="lra-percent">% {{ $year }}</th>
            <th class="lra-money">REALISASI {{ $year - 1 }}</th>
        </tr>
        <tr class="lra-column-numbers">
            <th>1</th><th>2</th><th>3</th><th>4</th><th>5 = (4/3)*100</th><th>6</th>
        </tr>
    </thead>
    <tbody>
        @forelse($reportRows as $row)
            <tr class="lra-{{ $row['type'] }}">
                <td class="lra-code">{{ $row['code'] }}</td>
                <td class="lra-description">{{ $row['label'] }}</td>
                <td class="lra-money">{{ $formatAmount($row['budget']) }}</td>
                <td class="lra-money">{{ $formatAmount($row['actual']) }}</td>
                <td class="lra-percent">{{ number_format($row['percentage'] ?? 0, 2, ',', '.') }}</td>
                <td class="lra-money">{{ $formatAmount($row['previous']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data RBA untuk tahun anggaran dan unit ini.</td></tr>
        @endforelse
    </tbody>
</table>
