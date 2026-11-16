<div class="table-responsive">
    <table class="table dashboard-budget-table mb-0">
        <thead><tr><th>Uraian</th><th class="text-end">Anggaran</th><th class="text-end">Realisasi</th><th class="dashboard-percentage">%</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><div class="dashboard-account-code">{{ $row['kode_rek'] ?: 'Belum diklasifikasikan' }}</div><div>{{ $row['uraian'] }}</div></td>
                    <td class="text-end text-nowrap">{{ $number($row['anggaran']) }}</td>
                    <td class="text-end text-nowrap">{{ $number($row['realisasi']) }}</td>
                    <td>
                        <div class="dashboard-percentage-label">{{ $row['persentase'] === null ? '—' : number_format($row['persentase'], 1, ',', '.').'%' }}</div>
                        <div class="dashboard-progress" role="progressbar" aria-label="Realisasi {{ $row['uraian'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ max(0, min(100, $row['persentase'] ?? 0)) }}">
                            <span class="{{ $color }}" style="width: {{ max(0, min(100, $row['persentase'] ?? 0)) }}%"></span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="dashboard-empty">Belum ada data {{ $kind }} untuk tahun {{ $year }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
