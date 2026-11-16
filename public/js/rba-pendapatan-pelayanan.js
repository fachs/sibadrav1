(function () {
    'use strict';

    const maxCents = 999999999999999n;
    function scaled(value, scale) {
        const text = String(value);
        if (!new RegExp('^\\d+(?:\\.\\d{1,' + scale + '})?$').test(text)) throw new Error('Nilai angka tidak valid.');
        const parts = text.split('.');
        return BigInt(parts[0] + (parts[1] || '').padEnd(scale, '0'));
    }
    function amount(cents) {
        return String(cents / 100n) + '.' + String(cents % 100n).padStart(2, '0');
    }
    function calculate(row) {
        let result = scaled(row.harga_satuan, 2);
        for (let i = 1; i <= 4; i++) result *= scaled(row['volume_' + i] ?? 1, 4);
        const divisor = 10n ** 16n;
        const cents = (result + divisor / 2n) / divisor;
        if (cents > maxCents) throw new Error('Jumlah pelayanan melebihi batas nilai.');
        return amount(cents);
    }
    function projectCagr(row) {
        const first = Number(row.realisasi_tahun_3);
        const semester = Number(row.realisasi_jan_jun);
        scaled(row.realisasi_tahun_3, 2);
        scaled(row.realisasi_jan_jun, 2);
        if (first <= 0 || semester <= 0 || first > 9999999999999.99 || semester > 9999999999999.99) {
            throw new Error('Isi realisasi tahun −3 dan Jan–Jun dengan nilai lebih besar dari nol.');
        }
        if (row.realisasi_tahun_2 !== '' && row.realisasi_tahun_2 != null) scaled(row.realisasi_tahun_2, 2);
        const prognosis = semester * 2;
        const factor = Math.sqrt(prognosis / first);
        const projection = Math.round(prognosis * factor);
        if (!Number.isFinite(projection) || projection < 1 || projection > 9999999999999.99) {
            throw new Error('Hasil proyeksi harus minimal 1 dan maksimal 9.999.999.999.999,99.');
        }
        return { prognosis, cagr: (factor - 1) * 100, projection: projection.toFixed(2) };
    }
    function withCagrVolume(row) {
        const projection = projectCagr(row).projection;
        if (Number(projection) > 99999999999.9999) throw new Error('Hasil proyeksi melebihi batas Volume 1 (99.999.999.999,9999).');
        return { ...row, volume_1: String(Number(projection)) };
    }
    function total(rows) {
        const cents = rows.reduce((sum, row) => sum + scaled(calculate(row), 2), 0n);
        if (cents > maxCents) throw new Error('Total pendapatan melebihi batas Rp9.999.999.999.999,99.');
        return amount(cents);
    }
    function referenceService(catalog, code, description) {
        return catalog.find(account => account.kode_rek === code)?.pelayanan.find(service => service.uraian === description) || null;
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { calculate, total, projectCagr, withCagrVolume, referenceService };
    if (typeof document === 'undefined') return;
    window.PendapatanPelayanan = { calculate, total };

    const form = document.getElementById('pendapatan-pelayanan-form');
    if (!form) return;
    const isPerubahan = form.dataset.changeMode === '1';
    const isLocalEditor = form.dataset.localEditor === '1';
    let localSide = null;
    const list = document.getElementById('pelayanan-list');
    const add = document.getElementById('add-pelayanan');
    const template = document.getElementById('pelayanan-template');
    const display = document.getElementById('pendapatan-total');
    const status = document.getElementById('pendapatan-status');
    const catalog = JSON.parse(document.getElementById('referensi-pendapatan')?.textContent || '[]');
    const codeSelect = document.getElementById('kode-rek');
    const descriptionSelect = document.getElementById('uraian-rekening');
    const format = value => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rows = () => Array.from(list.querySelectorAll('.pelayanan-row'));
    let nextIndex = Math.max(-1, ...rows().map(row => Number(row.dataset.index))) + 1;

    function option(value, text) {
        const element = document.createElement('option');
        element.value = value;
        element.textContent = text;
        return element;
    }
    function updateDescription() {
        if (!codeSelect || !catalog.length) return;
        const account = catalog.find(account => account.kode_rek === codeSelect.value);
        descriptionSelect.replaceChildren(option(account?.uraian || '', account?.uraian || 'Otomatis sesuai kode rekening'));
    }
    function updateServicePrice(row) {
        if (!codeSelect || !catalog.length) return;
        const service = referenceService(catalog, codeSelect.value, row.querySelector('.pelayanan-select').value);
        const price = service?.harga_satuan ?? '';
        row.querySelector('.harga-value').value = String(price);
        row.querySelector('.harga-view').value = String(price).replace('.', ',');
        syncPrice(row);
    }
    function populateServices(row, selected, preservePrice = false) {
        if (!codeSelect || !catalog.length) return;
        const savedPrice = row.querySelector('.harga-value').value;
        const account = catalog.find(account => account.kode_rek === codeSelect.value);
        const select = row.querySelector('.pelayanan-select');
        select.replaceChildren(
            option('', account ? 'Pilih Jenis Pelayanan' : 'Pilih kode rekening terlebih dahulu'),
            ...(account?.pelayanan || []).map(service => option(service.uraian, service.uraian))
        );
        select.disabled = !account;
        select.value = referenceService(catalog, codeSelect.value, selected) ? selected : '';
        if (preservePrice && select.value && savedPrice !== '') {
            row.querySelector('.harga-view').value = savedPrice.replace('.', ',');
            syncPrice(row);
        } else updateServicePrice(row);
    }

    function syncMoney(view, hidden) {
        const raw = view.value.replace(/\./g, '');
        view.setCustomValidity('');
        if (!/^\d+(?:,\d{0,2})?$/.test(raw)) {
            hidden.value = '';
            if (view.value !== '') view.setCustomValidity('Gunakan angka dan koma untuk maksimal 2 desimal.');
            return;
        }
        const caret = view.selectionStart ?? view.value.length;
        const countBefore = view.value.slice(0, caret).replace(/\./g, '').length;
        const parts = raw.split(',');
        const whole = parts[0].replace(/^0+(?=\d)/, '');
        view.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (parts.length > 1 ? ',' + parts[1] : '');
        let position = 0;
        let count = 0;
        while (position < view.value.length && count < countBefore) {
            if (view.value[position] !== '.') count++;
            position++;
        }
        view.setSelectionRange(position, position);
        hidden.value = whole + (parts[1] ? '.' + parts[1] : '');
        if (scaled(hidden.value, 2) > maxCents) {
            hidden.value = '';
            view.setCustomValidity('Nilai maksimal Rp9.999.999.999.999,99.');
        }
    }

    function syncPrice(row) {
        if (!row.querySelector('.harga-view').disabled) syncMoney(row.querySelector('.harga-view'), row.querySelector('.harga-value'));
    }

    function syncHistory(row) {
        row.querySelectorAll('.cagr-view').forEach(view => {
            if (!view.disabled) syncMoney(view, row.querySelector('.cagr-value[data-field="' + view.dataset.field + '"]'));
        });
    }

    function setMode(row) {
        const active = row.querySelector('.cagr-toggle').checked;
        row.querySelector('.cagr-toggle').setAttribute('aria-expanded', String(active));
        row.querySelector('.cagr-panel').hidden = !active;
        row.querySelectorAll('.manual-field').forEach(field => {
            field.hidden = false;
            field.querySelectorAll('input').forEach(input => { input.disabled = false; });
        });
        row.querySelectorAll('.cagr-view, .cagr-value').forEach(input => {
            input.disabled = !active;
            if (input.classList.contains('cagr-view')) input.required = active && input.dataset.field !== 'realisasi_tahun_2';
        });
    }

    function read(row) {
        const result = { harga_satuan: row.querySelector('.harga-value').value };
        row.querySelectorAll('.volume').forEach((input, index) => {
            if (!input.validity.valid) throw new Error('Periksa volume pelayanan.');
            result['volume_' + (index + 1)] = input.value;
        });
        return result;
    }
    function readHistory(row) {
        const data = {};
        row.querySelectorAll('.cagr-value').forEach(input => { data[input.dataset.field] = input.value; });
        if (Array.from(row.querySelectorAll('.cagr-view')).some(input => !input.validity.valid)) throw new Error('Periksa nilai realisasi CAGR.');
        return data;
    }
    function refresh() {
        let valid = true;
        let historyValid = true;
        const data = [];
        status.textContent = '';
        rows().forEach((row, index) => {
            row.querySelector('.pelayanan-number').textContent = index + 1;
            row.querySelector('.remove-pelayanan').disabled = rows().length === 1;
            try {
                const value = read(row);
                row.querySelector('.pelayanan-jumlah').value = format(calculate(value));
                data.push(value);
            } catch (error) {
                row.querySelector('.pelayanan-jumlah').value = '-';
                valid = false;
            }
            const useVolume = row.querySelector('.use-cagr-volume');
            const cagrStatus = row.querySelector('.cagr-status');
            useVolume.disabled = true;
            cagrStatus.textContent = 'Klik Gunakan sebagai Vol 1 untuk menyalin proyeksi ke Volume 1. Isian manual lainnya tetap dipertahankan.';
            if (row.querySelector('.cagr-toggle').checked) {
                try {
                    const projection = projectCagr(readHistory(row));
                    row.querySelector('.cagr-prognosis').value = format(projection.prognosis);
                    row.querySelector('.cagr-persen').value = format(projection.cagr) + '%';
                    row.querySelector('.cagr-proyeksi').value = format(projection.projection);
                    if (Number(projection.projection) <= 99999999999.9999) useVolume.disabled = false;
                    else cagrStatus.textContent = 'Proyeksi melebihi batas Volume 1 (99.999.999.999,9999). Sesuaikan data realisasi untuk menggunakan hasilnya.';
                } catch (error) {
                    row.querySelectorAll('.cagr-prognosis, .cagr-persen, .cagr-proyeksi').forEach(input => { input.value = '-'; });
                    cagrStatus.textContent = error.message;
                    historyValid = false;
                }
            }
        });
        add.disabled = rows().length >= 100;
        if (!valid) {
            display.textContent = '-';
        } else {
            try {
                display.textContent = 'Rp ' + format(total(data));
            } catch (error) {
                display.textContent = '-';
                status.textContent = error.message;
                valid = false;
            }
        }
        if (!historyValid) status.textContent = 'Lengkapi realisasi CAGR pada pelayanan yang mengaktifkan perhitungan CAGR.';
        return valid && historyValid;
    }
    function initialize(row) {
        populateServices(row, row.querySelector('.pelayanan-select')?.value || '', true);
        row.querySelector('.harga-view').value = row.querySelector('.harga-value').value.replace('.', ',');
        row.querySelectorAll('.cagr-view').forEach(view => {
            view.value = row.querySelector('.cagr-value[data-field="' + view.dataset.field + '"]').value.replace('.', ',');
        });
        setMode(row);
        syncPrice(row);
        syncHistory(row);
    }
    updateDescription();
    rows().forEach(initialize);
    codeSelect?.addEventListener('change', function () {
        updateDescription();
        rows().forEach(row => populateServices(row, ''));
        refresh();
    });
    list.addEventListener('input', function (event) {
        const row = event.target.closest('.pelayanan-row');
        if (!row) return;
        if (event.target.classList.contains('harga-view')) syncPrice(row);
        if (event.target.classList.contains('cagr-view')) syncHistory(row);
        refresh();
    });
    list.addEventListener('change', function (event) {
        if (event.target.classList.contains('pelayanan-select')) {
            updateServicePrice(event.target.closest('.pelayanan-row'));
            refresh();
            return;
        }
        if (!event.target.classList.contains('cagr-toggle')) return;
        const row = event.target.closest('.pelayanan-row');
        setMode(row);
        syncPrice(row);
        syncHistory(row);
        refresh();
    });
    list.addEventListener('click', async function (event) {
        const useVolume = event.target.closest('.use-cagr-volume');
        if (useVolume) {
            if (useVolume.disabled) return;
            const row = useVolume.closest('.pelayanan-row');
            try {
                const volume = row.querySelector('.volume');
                volume.value = withCagrVolume(readHistory(row)).volume_1;
                refresh();
                row.querySelector('.cagr-status').textContent = 'Hasil proyeksi telah disalin ke Volume 1. Jumlah pelayanan dihitung ulang dari seluruh volume dan harga satuan.';
                volume.focus();
            } catch (error) {
                row.querySelector('.cagr-status').textContent = error.message;
            }
            return;
        }
        const button = event.target.closest('.remove-pelayanan');
        if (!button || rows().length <= 1) return;
        if (!await window.confirmDelete?.({ title: 'Hapus Pelayanan?', text: 'Hapus jenis pelayanan ini dari rincian pendapatan?' })) return;
        if (rows().length <= 1) return;
        button.closest('.pelayanan-row').remove();
        refresh();
    });
    add.addEventListener('click', function () {
        if (rows().length >= 100) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex++)));
        const row = rows().at(-1);
        initialize(row);
        refresh();
        row.querySelector('input').focus();
    });
    form.addEventListener('submit', function (event) {
        rows().forEach(syncPrice);
        rows().forEach(syncHistory);
        if (!refresh() || !form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        if (isLocalEditor) {
            event.preventDefault();
            const services = {};
            for (const [name, value] of new FormData(form)) {
                const match = name.match(/^jenis_pelayanan\[(\d+)\]\[(\w+)\]$/);
                if (!match) continue;
                services[match[1]] ??= {};
                services[match[1]][match[2]] = match[2] === 'gunakan_cagr' ? value === '1' : value;
            }
            window.dispatchEvent(new CustomEvent('pendapatan:rincian', { detail: {
                side: localSide, services: Object.values(services),
            } }));
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    });
    const modal = document.getElementById('modalPendapatan');
    function openEditor(record = null) {
        form.reset();
        document.getElementById('pendapatan-method').value = record ? 'PUT' : 'POST';
        document.getElementById('pendapatan-id').value = record ? record.id : '';
        form.action = record ? form.dataset.updateUrl.replace(/\/0$/, '/' + record.id) : form.dataset.storeUrl;
        document.getElementById('modalPendapatanLabel').textContent = isPerubahan ? 'Usulkan Perubahan Rincian Pendapatan' : (record ? 'Kelola Pelayanan Pendapatan' : 'Tambah Rincian Pendapatan');
        document.getElementById('kode-rek').value = record?.kode_rek || '';
        const changeCode = document.getElementById('kode-rek-perubahan');
        if (changeCode) changeCode.value = record?.kode_rek || '';
        document.getElementById('uraian-rekening').value = record?.uraian || '';
        updateDescription();
        const blud = document.getElementById('nama-blud');
        if (blud) blud.value = record?.nama_blud || document.getElementById('puskesmas-filter')?.value || '';
        form.querySelectorAll('.pendapatan-errors').forEach(element => { element.hidden = true; });
        list.innerHTML = '';
        nextIndex = 0;
        const data = record?.jenis_pelayanans?.length ? record.jenis_pelayanans : [{}];
        data.forEach(service => {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex++)));
            const row = rows().at(-1);
            row.querySelectorAll('input[name], select[name]').forEach(input => {
                const key = input.name.match(/\[([^\]]+)\]$/)?.[1];
                if (input.classList.contains('cagr-toggle')) input.checked = Boolean(service.gunakan_cagr);
                else if (input.classList.contains('pelayanan-select')) populateServices(row, service.uraian || '');
                else if (key !== 'gunakan_cagr' && Object.hasOwn(service, key)) input.value = service[key] ?? '';
            });
            initialize(row);
        });
        refresh();
    }
    document.getElementById('tambah-pendapatan')?.addEventListener('click', () => openEditor());
    window.addEventListener('pendapatan:open', function (event) {
        if (!isLocalEditor) return;
        localSide = event.detail.side;
        openEditor(event.detail.record);
        document.getElementById('modalPendapatanLabel').textContent = 'Rincian Pelayanan ' + (localSide === 'sumber' ? 'Sumber' : 'Tujuan') + ' Setelah Pergeseran';
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    document.querySelectorAll('.edit-pendapatan').forEach(button => {
        button.addEventListener('click', () => openEditor(JSON.parse(button.dataset.record)));
    });
    window.addEventListener('load', function () {
        if (!modal) return;
        if (modal.dataset.openOnLoad === '1') {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }
        const editingId = new URLSearchParams(window.location.search).get('edit');
        const button = Array.from(document.querySelectorAll('.edit-pendapatan')).find(button => button.dataset.pendapatanId === editingId);
        if (button) {
            openEditor(JSON.parse(button.dataset.record));
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    refresh();
}());
