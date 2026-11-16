<div class="card border pelayanan-row" data-index="{{ $index }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Jenis Pelayanan <span class="pelayanan-number"></span></h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-pelayanan">Hapus Pelayanan</button>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="jenis_pelayanan[{{ $index }}][gunakan_cagr]" value="0">
            <input id="cagr-{{ $index }}" name="jenis_pelayanan[{{ $index }}][gunakan_cagr]" type="checkbox" class="form-check-input cagr-toggle" value="1" @checked($row['gunakan_cagr'] ?? false) aria-controls="cagr-panel-{{ $index }}">
            <label for="cagr-{{ $index }}" class="form-check-label">Hitung Proyeksi CAGR</label>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="unit-{{ $index }}" class="form-label">Unit Pelayanan</label>
                <select id="unit-{{ $index }}" name="jenis_pelayanan[{{ $index }}][unit_pelayanan]" class="form-select" required>
                    <option value="">Pilih Unit Pelayanan</option>
                    @foreach(\App\Models\JenisPelayananPendapatan::UNIT_PELAYANAN as $unitPelayanan)
                        <option value="{{ $unitPelayanan }}" @selected(($row['unit_pelayanan'] ?? '') === $unitPelayanan)>{{ $unitPelayanan }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label for="uraian-{{ $index }}" class="form-label">Uraian Jenis Pelayanan</label>
                <select id="uraian-{{ $index }}" name="jenis_pelayanan[{{ $index }}][uraian]" class="form-select pelayanan-select" required @disabled(!$rekeningTerpilih)>
                    <option value="">{{ $rekeningTerpilih ? 'Pilih Jenis Pelayanan' : 'Pilih kode rekening terlebih dahulu' }}</option>
                    @foreach($rekeningTerpilih['pelayanan'] ?? [] as $pilihan)
                        <option value="{{ $pilihan['uraian'] }}" @selected(($row['uraian'] ?? '') === $pilihan['uraian'])>{{ $pilihan['uraian'] }}</option>
                    @endforeach
                </select>
            </div>
            @for($i = 1; $i <= 4; $i++)
                <div class="col-sm-6 col-lg-3 manual-field">
                    <label for="volume-{{ $index }}-{{ $i }}" class="form-label">Volume {{ $i }}</label>
                    <input id="volume-{{ $index }}-{{ $i }}" name="jenis_pelayanan[{{ $index }}][volume_{{ $i }}]" type="number" step="0.0001" min="0" max="99999999999.9999" class="form-control volume" required value="{{ $row['volume_'.$i] ?? 1 }}">
                    <label for="satuan-{{ $index }}-{{ $i }}" class="form-label mt-2">Satuan {{ $i }}{{ $i > 1 ? ' (opsional)' : '' }}</label>
                    <input id="satuan-{{ $index }}-{{ $i }}" name="jenis_pelayanan[{{ $index }}][satuan_{{ $i }}]" class="form-control" maxlength="100" @required($i === 1) value="{{ $row['satuan_'.$i] ?? '' }}" placeholder="{{ ['Orang', 'Kali', 'Bulan', 'Tahun'][$i - 1] }}">
                </div>
            @endfor
            <div class="col-md-6 manual-field">
                <label for="harga-{{ $index }}" class="form-label">Harga Satuan (Rp)</label>
                <input id="harga-{{ $index }}" type="text" inputmode="decimal" class="form-control harga-view" required placeholder="Masukkan harga satuan">
                <input type="hidden" class="harga-value" name="jenis_pelayanan[{{ $index }}][harga_satuan]" value="{{ $row['harga_satuan'] ?? '' }}">
                
            </div>
            <div class="col-md-6">
                <label for="jumlah-{{ $index }}" class="form-label">Jumlah Pelayanan (Rp)</label>
                <input id="jumlah-{{ $index }}" class="form-control pelayanan-jumlah" readonly value="0">
            </div>
        </div>
        @php $tahunProyeksi = now()->year + 1; @endphp
        <div id="cagr-panel-{{ $index }}" class="cagr-panel bg-primary-subtle border rounded p-3 mt-3" hidden>
            <h6 class="mb-3">Proyeksi Metode CAGR — Tahun Anggaran {{ $tahunProyeksi }}</h6>
            <div class="row g-3">
                @foreach(['realisasi_tahun_3' => 'Realisasi Tahun −3 ('.($tahunProyeksi - 3).')', 'realisasi_tahun_2' => 'Realisasi Tahun −2 ('.($tahunProyeksi - 2).') — opsional', 'realisasi_jan_jun' => 'Realisasi Jan–Jun Tahun −1 ('.($tahunProyeksi - 1).')'] as $field => $label)
                    <div class="col-md-4">
                        <label for="{{ $field }}-{{ $index }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}-{{ $index }}" type="text" inputmode="decimal" class="form-control cagr-view" data-field="{{ $field }}" placeholder="0">
                        <input type="hidden" class="cagr-value" name="jenis_pelayanan[{{ $index }}][{{ $field }}]" data-field="{{ $field }}" value="{{ $row[$field] ?? '' }}">
                    </div>
                @endforeach
                <div class="col-md-4">
                    <label for="prognosis-{{ $index }}" class="form-label">Prognosis Tahun −1 (Jan–Jun × 2)</label>
                    <input id="prognosis-{{ $index }}" class="form-control cagr-prognosis" readonly>
                </div>
                <div class="col-md-4">
                    <label for="persen-{{ $index }}" class="form-label">CAGR (%)</label>
                    <input id="persen-{{ $index }}" class="form-control cagr-persen" readonly>
                </div>
                <div class="col-md-4">
                    <label for="proyeksi-{{ $index }}" class="form-label">Proyeksi Volume {{ $tahunProyeksi }}</label>
                    <input id="proyeksi-{{ $index }}" class="form-control cagr-proyeksi" readonly>
                    <button type="button" class="btn btn-primary btn-sm mt-2 use-cagr-volume" disabled>Gunakan sebagai Vol 1</button>
                </div>
            </div>
            <p class="small mt-3 mb-0">CAGR = (Prognosis tahun −1 ÷ Realisasi tahun −3)^(1/2) − 1. Proyeksi = Prognosis × (1 + CAGR), dibulatkan ke rupiah terdekat. Realisasi tahun −2 sebagai pembanding.</p>
            <p class="small mt-2 mb-0 cagr-status" role="status">Klik Gunakan sebagai Vol 1 untuk menyalin proyeksi ke Volume 1. Harga satuan, volume lain, dan satuan tetap mengikuti isian manual.</p>
        </div>
    </div>
</div>
