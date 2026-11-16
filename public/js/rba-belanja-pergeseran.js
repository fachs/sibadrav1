(function () {
    'use strict';

    const form = document.getElementById('formPergeseran');
    if (!form) return;

    const source = document.getElementById('sumber_renbut_id');
    const target = document.getElementById('tujuan_akun_id');
    const targetType = document.getElementById('tipe-tujuan');
    const newAccount = document.getElementById('tujuan_akun_id_baru');
    const existingPanel = document.getElementById('form-tujuan-existing');
    const newPanel = document.getElementById('form-tujuan-baru');
    const currency = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const requiredExisting = ['tujuan_akun_id', 'tujuan_uraian', 'tujuan_jumlah', 'tujuan_satuan', 'tujuan_harga_satuan'];
    const requiredNew = ['tujuan_akun_id_baru', 'tujuan_uraian_baru', 'tujuan_jumlah_baru', 'tujuan_satuan_baru', 'tujuan_harga_satuan_baru'];

    function write(id, value) {
        document.getElementById(id).textContent = value;
    }

    function money(value) {
        return value === null ? '-' : 'Rp ' + currency.format(value);
    }

    function selected(select) {
        return select.value ? select.options[select.selectedIndex] : null;
    }

    function number(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function amount(volumeId, priceId) {
        return number(document.getElementById(volumeId).value) * number(document.getElementById(priceId).value);
    }

    function updateTotals() {
        const sourceOption = selected(source);
        const isNew = targetType.value === 'baru';
        const targetOption = selected(target);
        const targetReady = isNew ? Boolean(newAccount.value) : Boolean(targetOption);
        const ready = Boolean(sourceOption) && targetReady;
        const sourceAfter = sourceOption ? amount('sumber_jumlah', 'sumber_harga_satuan') : null;
        const targetAfter = targetReady
            ? (isNew ? amount('tujuan_jumlah_baru', 'tujuan_harga_satuan_baru') : amount('tujuan_jumlah', 'tujuan_harga_satuan'))
            : null;

        write('sumber_jumlah_harga_display', money(sourceAfter));
        write('tujuan_jumlah_harga_display', money(!isNew ? targetAfter : null));
        write('tujuan_jumlah_harga_display_baru', money(isNew ? targetAfter : null));

        const before = ready ? number(sourceOption.dataset.total) + (isNew ? 0 : number(targetOption.dataset.total)) : null;
        const after = ready ? sourceAfter + targetAfter : null;
        const difference = ready ? after - before : null;
        write('pergeseran-total-awal', money(before));
        write('pergeseran-total-akhir', money(after));
        write('pergeseran-selisih', money(difference));
        const delta = document.getElementById('pergeseran-selisih');
        delta.classList.toggle('rba-delta-negative', difference !== null && difference < 0);
        delta.classList.toggle('rba-delta-positive', difference !== null && difference > 0);
        write('pergeseran-tahap', ready
            ? 'Pengajuan akan diperiksa Kepala Puskesmas terlebih dahulu.'
            : 'Pilih rekening sumber dan tujuan.');
        document.getElementById('simpan-pergeseran').disabled = !ready;
    }

    function syncSource(fillFields) {
        const option = selected(source);
        const data = option ? option.dataset : {};
        document.getElementById('preview-sumber').classList.toggle('d-none', !option);
        write('sumber-awal', money(option ? number(data.total) : null));
        write('sumber-pagu-rekening', money(option ? number(data.paguRekening) : null));
        write('sumber-realisasi-bku', money(option ? number(data.realisasiBku) : null));
        write('sumber-sisa-pagu', money(option ? number(data.sisaPagu) : null));
        write('prev-sumber-uraian', data.uraian || '-');
        write('prev-sumber-jumlah', data.jumlah || '-');
        write('prev-sumber-satuan', data.satuan || '');
        write('prev-sumber-harga', money(option ? number(data.harga) : null));
        if (fillFields) {
            for (const [field, key] of [['uraian', 'uraian'], ['jumlah', 'jumlah'], ['satuan', 'satuan'], ['harga_satuan', 'harga']]) {
                document.getElementById('sumber_' + field).value = data[key] ?? '';
            }
        }
        updateTotals();
    }

    function syncTarget(fillFields) {
        const option = selected(target);
        const data = option ? option.dataset : {};
        document.getElementById('preview-existing').classList.toggle('d-none', !option);
        document.getElementById('tujuan_renbut_id').value = data.renbutId || '';
        write('tujuan-awal', money(option ? number(data.total) : null));
        write('prev-uraian', data.uraian || '-');
        write('prev-jumlah', data.jumlah || '-');
        write('prev-satuan', data.satuan || '');
        write('prev-harga', money(option ? number(data.harga) : null));
        if (fillFields) {
            for (const [field, key] of [['uraian', 'uraian'], ['jumlah', 'jumlah'], ['satuan', 'satuan'], ['harga_satuan', 'harga']]) {
                document.getElementById('tujuan_' + field).value = data[key] ?? '';
            }
        }
        updateTotals();
    }

    function toggleTarget() {
        const isNew = targetType.value === 'baru';
        existingPanel.classList.toggle('d-none', isNew);
        newPanel.classList.toggle('d-none', !isNew);
        for (const id of requiredExisting) document.getElementById(id).required = !isNew;
        for (const id of requiredNew) document.getElementById(id).required = isNew;
        updateTotals();
    }

    source.addEventListener('change', function () { syncSource(true); });
    target.addEventListener('change', function () { syncTarget(true); });
    targetType.addEventListener('change', toggleTarget);
    newAccount.addEventListener('change', updateTotals);
    form.addEventListener('input', updateTotals);

    const fillInitialFields = form.dataset.hasOldInput !== '1';
    syncSource(fillInitialFields);
    syncTarget(fillInitialFields);
    toggleTarget();

    // Search remains optional; native selects work when Select2 is unavailable.
    if (window.jQuery && window.jQuery.fn.select2) {
        const $ = window.jQuery;
        for (const select of [source, target, newAccount]) {
            $(select).select2({
                theme: 'bootstrap-5',
                dropdownParent: $(form),
                width: '100%',
                placeholder: select.options[0].text,
            }).on('select2:select select2:unselect', function () {
                this.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    }
})();
