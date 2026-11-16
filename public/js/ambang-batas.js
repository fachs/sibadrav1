(function () {
    'use strict';

    const form = document.getElementById('ambang-batas-form');
    if (!form) return;

    const anggaran = form.elements.anggaran;
    const realisasi = form.elements.realisasi;
    const fields = [anggaran, realisasi].map(value => ({
        value, view: document.getElementById(value.id + '-view'),
    }));
    const selisihPreview = document.getElementById('selisih-preview');
    const persentasePreview = document.getElementById('persentase-preview');
    const rupiah = new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2,
    });
    const persen = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function updatePreview() {
        const budget = Number(anggaran.value);
        const actual = Number(realisasi.value);
        if (anggaran.value === '' || realisasi.value === '' || !Number.isFinite(budget) ||
            !Number.isFinite(actual) || budget <= 0 || actual < 0) {
            selisihPreview.value = '-';
            persentasePreview.value = '-';
            return;
        }
        const selisih = Math.round(actual * 100) / 100 - Math.round(budget * 100) / 100;
        selisihPreview.value = rupiah.format(selisih);
        persentasePreview.value = persen.format(selisih / budget * 100) + '%';
    }

    function syncField(field) {
        const input = field.view;
        const raw = input.value.replace(/\./g, '');
        input.setCustomValidity('');
        if (!/^\d*(,\d{0,2})?$/.test(raw)) {
            input.setCustomValidity('Gunakan angka dengan titik pemisah ribuan dan koma untuk desimal (maksimal 2 angka).');
            field.value.value = '';
            return;
        }
        if (raw === '' || raw === ',') {
            if (input.value !== '') input.setCustomValidity('Masukkan nilai berupa angka.');
            field.value.value = '';
            return;
        }

        const position = input.selectionStart ?? input.value.length;
        const beforeCursor = input.value.slice(0, position).replace(/\./g, '').length;
        const parts = raw.split(',');
        const whole = (parts[0] || '0').replace(/^0+(?=\d)/, '');
        input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (parts.length > 1 ? ',' + parts[1] : '');
        let cursor = 0;
        let count = 0;
        while (cursor < input.value.length && count < beforeCursor) {
            if (input.value[cursor] !== '.') count++;
            cursor++;
        }
        input.setSelectionRange(cursor, cursor);

        const numeric = whole + (parts[1] ? '.' + parts[1] : '');
        const amount = Number(numeric);
        if (amount > 9999999999999.99 || (field.value === anggaran && amount <= 0)) {
            input.setCustomValidity(field.value === anggaran && amount <= 0
                ? 'Anggaran harus lebih besar dari nol.' : 'Nilai maksimal adalah Rp9.999.999.999.999,99.');
            field.value.value = '';
            return;
        }
        field.value.value = numeric;
    }

    fields.forEach(field => {
        field.view.value = field.value.value.replace('.', ',');
        syncField(field);
        field.view.addEventListener('input', function () {
            syncField(field);
            updatePreview();
        });
    });
    form.addEventListener('submit', function (event) {
        fields.forEach(syncField);
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
    updatePreview();
}());
