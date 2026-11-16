<div class="rba-service-list mt-3">
    @forelse($pelayanans as $pelayanan)
        <div class="rba-service-card">
            <table class="table table-sm rba-service-vertical">
                <caption>Jenis Pelayanan {{ $loop->iteration }}</caption>
                <colgroup><col class="rba-service-label"><col></colgroup>
                <tbody>
                    <tr>
                        <th scope="row">Unit Pelayanan</th>
                        <td>{{ $pelayanan->unit_pelayanan }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Uraian Jenis Pelayanan</th>
                        <td>{{ $pelayanan->uraian }}@if($pelayanan->gunakan_cagr)<div class="small text-body-secondary">Proyeksi CAGR tersedia</div>@endif</td>
                    </tr>
                    <tr>
                        <th scope="row">Volume 1 / Satuan 1</th>
                        <td>{{ rtrim(rtrim(number_format($pelayanan->volume_1, 4, ',', '.'), '0'), ',') }} {{ $pelayanan->satuan_1 }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Harga Satuan (Rp)</th>
                        <td class="rba-service-value">{{ number_format($pelayanan->harga_satuan, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Jumlah (Rp)</th>
                        <td class="rba-service-value fw-semibold">{{ number_format($pelayanan->jumlah, 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p class="small text-body-secondary mb-0">Belum ada rincian jenis pelayanan.</p>
    @endforelse
</div>
