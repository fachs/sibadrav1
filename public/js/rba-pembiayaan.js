(function () {
    'use strict';

    function scaled(value, scale, fallback) {
        const input = String(value ?? '').trim() || fallback;
        if (!new RegExp('^\\d+(?:\\.\\d{0,' + scale + '})?$').test(input)) return null;
        const [whole, fraction = ''] = input.split('.');
        return BigInt(whole + fraction.padEnd(scale, '0'));
    }

    function calculate(data) {
        let product = scaled(data.harga_satuan, 2, '0');
        if (product === null) return null;
        for (let i = 1; i <= 4; i++) {
            const volume = scaled(data['volume_' + i], 4, '1');
            if (volume === null) return null;
            product *= volume;
        }
        const divisor = 10n ** 16n;
        const cents = (product + divisor / 2n) / divisor;
        const digits = cents.toString().padStart(3, '0');
        return digits.slice(0, -2) + '.' + digits.slice(-2);
    }

    function rupiah(value) {
        if (value === null) return 'Periksa harga dan volume';
        const [whole, fraction] = value.split('.');
        return 'Rp ' + whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + fraction;
    }

    function formatPrice(value) {
        if (value === '' || value === null || value === undefined) return '';
        const [whole, fraction] = String(value).split('.');
        return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction === undefined ? '' : ',' + fraction);
    }

    function parsePrice(value) {
        const input = String(value).trim();
        if (input === '') return '';
        if (!/^\d[\d.]*(?:,\d{0,2})?$/.test(input)) return null;
        return input.replace(/\./g, '').replace(',', '.').replace(/\.$/, '');
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { calculate, rupiah, formatPrice, parsePrice };
    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('pembiayaan-form');
        if (!form) return;
        const modal = document.getElementById('modalPembiayaan');
        const method = form.elements.namedItem('_method');
        const title = document.getElementById('modalPembiayaanLabel');
        const priceView = document.getElementById('pembiayaan-harga');
        const priceValue = form.elements.namedItem('harga_satuan');
        const isPerubahan = form.dataset.perubahan === '1';
        const fields = ['kode_rek', 'uraian', 'harga_satuan'];
        for (let i = 1; i <= 4; i++) fields.push('volume_' + i, 'satuan_' + i);

        function refresh() {
            const values = {};
            fields.forEach(name => { values[name] = form.elements.namedItem(name).value; });
            document.getElementById('pembiayaan-total').textContent = rupiah(calculate(values));
        }

        function syncPrice() {
            const input = priceView.value;
            const parsed = parsePrice(input);
            priceView.setCustomValidity(parsed === null ? 'Gunakan angka, titik untuk ribuan, dan koma untuk desimal (maksimal 2 angka).' : '');
            priceValue.value = parsed ?? '';
            if (parsed !== null) {
                const cursor = priceView.selectionStart;
                priceView.value = formatPrice(parsed) + (input.endsWith(',') ? ',' : '');
                if (cursor !== null && typeof priceView.setSelectionRange === 'function') {
                    const position = Math.max(0, cursor + priceView.value.length - input.length);
                    priceView.setSelectionRange(position, position);
                }
            }
            refresh();
            if (parsed === null) document.getElementById('pembiayaan-total').textContent = rupiah(null);
        }

        form.addEventListener('input', function (event) {
            if (event?.target === priceView) syncPrice();
            else refresh();
        });
        form.elements.namedItem('kode_rek').addEventListener('change', function () {
            const description = form.elements.namedItem('uraian');
            if (!description.value.trim()) description.value = this.selectedOptions[0]?.dataset.uraian || '';
        });

        document.addEventListener('click', function (event) {
            const edit = event.target.closest('[data-pembiayaan-edit]');
            const create = event.target.closest('[data-pembiayaan-create]');
            if (!edit && !create) return;
            const record = edit ? JSON.parse(edit.dataset.record) : {};
            fields.forEach(name => {
                form.elements.namedItem(name).value = record[name] ?? (name.startsWith('volume_') ? '1' : '');
            });
            priceView.value = formatPrice(priceValue.value);
            priceView.setCustomValidity('');
            form.elements.namedItem('pembiayaan_id').value = edit ? edit.dataset.id : '';
            method.disabled = !edit;
            form.action = edit ? edit.dataset.action : form.dataset.createUrl;
            title.textContent = isPerubahan ? 'Usulkan Perubahan Rencana Pembiayaan' : (edit ? 'Edit Rencana Pembiayaan' : 'Buat Rencana Pembiayaan');
            refresh();
        });

        priceView.value = formatPrice(priceValue.value);
        syncPrice();
        if (modal.dataset.openOnLoad === '1' && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
}());
