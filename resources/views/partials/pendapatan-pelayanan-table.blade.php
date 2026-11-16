<div class="table-responsive mt-3">
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr>
            <th>Unit Pelayanan</th><th>Uraian Jenis Pelayanan</th>
            <th>Volume 1 / Satuan 1</th>
            <th>Harga Satuan (Rp)</th><th>Jumlah (Rp)</th>
        </tr></thead>
        <tbody>
            @foreach($pelayanans as $pelayanan)
                <tr>
                    <td>{{ $pelayanan->unit_pelayanan }}</td><td>{{ $pelayanan->uraian }}@if($pelayanan->gunakan_cagr)<div class="small text-muted">Proyeksi CAGR tersedia</div>@endif</td>
                    <td class="text-nowrap">{{ rtrim(rtrim(number_format($pelayanan->volume_1, 4, ',', '.'), '0'), ',') }} {{ $pelayanan->satuan_1 }}</td>
                    <td class="text-end text-nowrap">{{ number_format($pelayanan->harga_satuan, 2, ',', '.') }}</td>
                    <td class="text-end text-nowrap">{{ number_format($pelayanan->jumlah, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
