@php
    $oldId = (int) old('pembiayaan_id', 0);
    $editing = $isPerubahan || $oldId > 0;
    $updateRoute = $isPerubahan ? 'rba-perubahan.pembiayaan.update' : 'rba.pembiayaan.update';
@endphp
<div class="modal fade" id="modalPembiayaan" tabindex="-1" aria-labelledby="modalPembiayaanLabel" aria-hidden="true" data-open-on-load="{{ $errors->any() ? '1' : '0' }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" id="pembiayaan-form" action="{{ $editing ? route($updateRoute, ['id' => $oldId] + $pembiayaanFilters) : route('rba.pembiayaan.store', $pembiayaanFilters) }}" data-create-url="{{ route('rba.pembiayaan.store', $pembiayaanFilters) }}" data-perubahan="{{ $isPerubahan ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahunRba }}">
            <input type="hidden" name="_method" value="PUT" @disabled(!$editing)>
            <input type="hidden" name="pembiayaan_id" value="{{ $oldId ?: '' }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPembiayaanLabel">{{ $isPerubahan ? 'Usulkan Perubahan Rencana Pembiayaan' : ($oldId ? 'Edit Rencana Pembiayaan' : 'Buat Rencana Pembiayaan') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <div class="mb-3">
                        <label for="pembiayaan-kode" class="form-label">Kode rekening</label>
                        <select id="pembiayaan-kode" name="kode_rek" class="form-select" required>
                            <option value="">Pilih kode rekening</option>
                            @foreach(\App\Support\ReferensiPembiayaan::REKENING as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach($options as $code => $label)<option value="{{ $code }}" data-uraian="{{ $label }}" @selected(old('kode_rek') === $code)>{{ $code }} - {{ $label }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label for="pembiayaan-uraian" class="form-label">Uraian</label><input id="pembiayaan-uraian" name="uraian" class="form-control" maxlength="255" value="{{ old('uraian') }}" required></div>
                    <div class="row">
                        @for($i = 1; $i <= 4; $i++)
                            <div class="col-sm-6 mb-3"><label for="pembiayaan-volume-{{ $i }}" class="form-label">Volume {{ $i }}</label><input type="number" id="pembiayaan-volume-{{ $i }}" name="volume_{{ $i }}" class="form-control" min="0" max="99999999999.9999" step="0.0001" value="{{ old('volume_'.$i, 1) }}" @required($i === 1)></div>
                            <div class="col-sm-6 mb-3"><label for="pembiayaan-satuan-{{ $i }}" class="form-label">Satuan {{ $i }}</label><input id="pembiayaan-satuan-{{ $i }}" name="satuan_{{ $i }}" class="form-control" maxlength="100" value="{{ old('satuan_'.$i) }}" @required($i === 1)></div>
                        @endfor
                    </div>
                    <div class="mb-3">
                        <label for="pembiayaan-harga" class="form-label">Harga satuan (Rp)</label>
                        <input type="text" id="pembiayaan-harga" class="form-control" inputmode="decimal" autocomplete="off" value="{{ old('harga_satuan') }}" required>
                        <input type="hidden" name="harga_satuan" value="{{ old('harga_satuan') }}">
                    </div>
                    <div class="bg-light rounded p-3">
                        <div class="small text-muted">Jumlah = harga satuan × volume 1 × volume 2 × volume 3 × volume 4</div>
                        <output id="pembiayaan-total" class="fw-bold fs-5" aria-live="polite">Rp 0,00</output>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-save">{{ $isPerubahan ? 'Simpan Usulan' : 'Simpan' }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
