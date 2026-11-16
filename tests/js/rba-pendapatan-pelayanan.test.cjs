const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const context = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/rba-pendapatan-pelayanan.js'), 'utf8'), context);
const { calculate, total, projectCagr, withCagrVolume, referenceService } = context.module.exports;
const workbookCatalog = JSON.parse(fs.readFileSync(path.join(__dirname, '../../resources/data/referensi-pendapatan.json'), 'utf8')).rekening;
const completeCatalog = [...workbookCatalog, ...JSON.parse(fs.readFileSync(path.join(__dirname, '../../resources/data/referensi-pendapatan-tambahan.json'), 'utf8'))];

test('multiplies price by all four volumes and sums each service', () => {
    const first = { harga_satuan: '6000.50', volume_1: 10, volume_2: 2, volume_3: 12, volume_4: 1 };
    const second = { ...first, harga_satuan: '10000' };
    assert.equal(calculate(first), '1440120.00');
    assert.equal(total([first, second]), '3840120.00');
});

test('uses exact decimals and rounds each service before adding', () => {
    const row = { harga_satuan: '0.05', volume_1: '0.1' };
    assert.equal(calculate(row), '0.01');
    assert.equal(total([row, row]), '0.02');
    assert.equal(calculate({ harga_satuan: '0.10', volume_1: '0.2' }), '0.02');
});

test('zero volume stays zero and unused volumes default to one', () => {
    assert.equal(calculate({ harga_satuan: '6000', volume_1: 0 }), '0.00');
    assert.equal(calculate({ harga_satuan: '6000', volume_1: 2 }), '12000.00');
});

test('rejects invalid numbers and overflow at line and aggregate levels', () => {
    for (const value of ['', '-1', 'abc', '1,50', '1e6', '1.001']) {
        assert.throws(() => calculate({ harga_satuan: value, volume_1: 1 }));
    }
    assert.throws(() => calculate({ harga_satuan: '9999999999999.99', volume_1: 2 }));
    const large = { harga_satuan: '9999999999999.99' };
    assert.equal(calculate(large), '9999999999999.99');
    assert.throws(() => total([large, large]));
});

test('CAGR remains a projection helper until explicitly copied into volume one', () => {
    const row = { gunakan_cagr: true, realisasi_tahun_3: '1000000', realisasi_jan_jun: '605000', harga_satuan: '999', volume_1: 999 };
    const projection = projectCagr(row);
    assert.equal(projection.prognosis, 1210000);
    assert.ok(Math.abs(projection.cagr - 10) < 0.00001);
    assert.equal(calculate(row), '998001.00');
    assert.equal(total([row, { harga_satuan: '6000', volume_1: 10 }]), '1058001.00');
    assert.equal(projectCagr({ ...row, realisasi_tahun_2: '1100000' }).projection, '1331000.00');
    assert.equal(projectCagr({ ...row, realisasi_jan_jun: '320000' }).projection, '512000.00');
    const copied = withCagrVolume(row);
    assert.equal(copied.volume_1, '1331000');
    assert.equal(copied.harga_satuan, '999');
    assert.equal(row.volume_1, 999);
    assert.equal(calculate(copied), '1329669000.00');
});

test('CAGR rejects missing zero invalid and out-of-range forecasts', () => {
    const row = { gunakan_cagr: true, realisasi_tahun_3: '1000000', realisasi_jan_jun: '605000' };
    for (const first of ['', '0', '-1', 'abc']) assert.throws(() => projectCagr({ ...row, realisasi_tahun_3: first }));
    for (const semester of ['', '0', '-1', 'abc', '0.01']) assert.throws(() => projectCagr({ ...row, realisasi_jan_jun: semester }));
    assert.throws(() => projectCagr({ ...row, realisasi_tahun_3: '0.01', realisasi_jan_jun: '9999999999999.99' }));
    assert.equal(projectCagr({ ...row, realisasi_tahun_3: '100', realisasi_jan_jun: '60.5' }).projection, '133.00');
    assert.throws(() => withCagrVolume({ ...row, realisasi_tahun_3: '100000000000', realisasi_jan_jun: '50000000000' }));
});

function editor(reference = null, count = 1, confirmDelete = async () => true, changeRecord = null, localEditor = false) {
    const make = (value = '', classes = []) => ({
        value, disabled: false, hidden: false, required: false, dataset: {}, listeners: {},
        classList: { contains(name) { return classes.includes(name); } },
        get validity() { return { valid: !this.error && (!this.required || this.value !== '') }; },
        setCustomValidity(message) { this.error = message; },
        setSelectionRange() {}, setAttribute() {}, focus() {},
        replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        closest(selector) {
            if (selector === '.pelayanan-row') return row;
            return classes.includes(selector.slice(1)) ? this : null;
        },
    });
    const volumes = ['10', '2', '3', '4'].map(value => make(value, ['volume']));
    const hargaView = make('', ['harga-view']);
    const hargaValue = make(reference ? reference.savedPrice ?? '' : '5', ['harga-value']);
    const toggle = make('1', ['cagr-toggle']);
    toggle.checked = true;
    const names = ['realisasi_tahun_3', 'realisasi_tahun_2', 'realisasi_jan_jun'];
    const historyViews = names.map(field => Object.assign(make('', ['cagr-view']), { dataset: { field } }));
    const historyValues = names.map((field, index) => Object.assign(make(['100', '', '60.5'][index], ['cagr-value']), { dataset: { field } }));
    const manualFields = volumes.map(input => ({ hidden: false, querySelectorAll() { return [input]; } }));
    manualFields.push({ hidden: false, querySelectorAll() { return [hargaView, hargaValue]; } });
    const selectors = {
        '.harga-view': hargaView, '.harga-value': hargaValue, '.cagr-toggle': toggle,
        '.cagr-panel': make(), '.cagr-status': make(), '.use-cagr-volume': make('', ['use-cagr-volume']),
        '.pelayanan-number': make(), '.remove-pelayanan': make('', ['remove-pelayanan']), '.pelayanan-jumlah': make(),
        '.cagr-prognosis': make(), '.cagr-persen': make(), '.cagr-proyeksi': make(), '.volume': volumes[0],
        '.pelayanan-select': make(reference?.service || '', ['pelayanan-select']),
    };
    const row = {
        dataset: { index: '0' },
        querySelector(selector) {
            const field = selector.match(/data-field="([^"]+)"/);
            return field ? historyValues.find(input => input.dataset.field === field[1]) : selectors[selector];
        },
        querySelectorAll(selector) {
            if (selector === '.volume') return volumes;
            if (selector === '.manual-field') return manualFields;
            if (selector === '.cagr-view') return historyViews;
            if (selector === '.cagr-value') return historyValues;
            if (selector === '.cagr-view, .cagr-value') return [...historyViews, ...historyValues];
            return selector.split(', ').map(key => selectors[key]).filter(Boolean);
        },
    };
    const activeRows = Array.from({ length: count }, (_, index) => index === 0 ? row : { ...row });
    row.remove = () => { activeRows.splice(activeRows.indexOf(row), 1); };
    const list = make();
    list.querySelectorAll = () => activeRows;
    const form = make();
    form.checkValidity = () => [...volumes, hargaView, ...historyViews].every(input => input.disabled || input.validity.valid);
    form.reportValidity = () => {};
    const ids = {
        'pendapatan-pelayanan-form': form, 'pelayanan-list': list, 'add-pelayanan': make(),
        'pelayanan-template': make(), 'pendapatan-total': make(), 'pendapatan-status': make(),
    };
    if (reference) {
        ids['referensi-pendapatan'] = { textContent: JSON.stringify(completeCatalog) };
        ids['kode-rek'] = make(reference.code || '');
        ids['uraian-rekening'] = make();
    }
    let editButton;
    if (changeRecord) {
        form.dataset = { changeMode: '1', localEditor: localEditor ? '1' : '0', updateUrl: '/rba-perubahan/pendapatan/0' };
        form.reset = () => {};
        form.querySelectorAll = () => [];
        ids['pendapatan-method'] = make();
        ids['pendapatan-id'] = make();
        ids['kode-rek-perubahan'] = make();
        ids['modalPendapatanLabel'] = make();
        ids['kode-rek'].disabled = true;
        ids['pelayanan-template'].innerHTML = '__INDEX__';
        Object.defineProperty(list, 'innerHTML', { set() { activeRows.length = 0; } });
        list.insertAdjacentHTML = () => { activeRows.push(row); };
        volumes.forEach((input, index) => { input.name = 'jenis_pelayanan[0][volume_' + (index + 1) + ']'; });
        hargaValue.name = 'jenis_pelayanan[0][harga_satuan]';
        selectors['.pelayanan-select'].name = 'jenis_pelayanan[0][uraian]';
        toggle.name = 'jenis_pelayanan[0][gunakan_cagr]';
        historyValues.forEach(input => { input.name = 'jenis_pelayanan[0][' + input.dataset.field + ']'; });
        const query = row.querySelectorAll;
        row.querySelectorAll = selector => selector === 'input[name], select[name]'
            ? [...volumes, selectors['.pelayanan-select'], hargaValue, toggle]
            : query(selector);
        editButton = make();
        editButton.dataset.record = JSON.stringify(changeRecord);
    }
    const events = {};
    const dispatched = [];
    let hidden = 0;
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/rba-pendapatan-pelayanan.js'), 'utf8'), {
        document: { getElementById: id => ids[id] || null, querySelectorAll: () => editButton ? [editButton] : [], createElement: () => make() },
        window: {
            addEventListener(type, callback) { events[type] = callback; }, confirmDelete,
            dispatchEvent(event) { dispatched.push(event); },
            bootstrap: { Modal: { getOrCreateInstance() { return { show() {}, hide() { hidden++; } }; } } },
        },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        FormData: class {
            *[Symbol.iterator]() {
                for (const input of [...volumes, selectors['.pelayanan-select'], hargaValue, ...historyValues, toggle]) {
                    if (input.disabled || (input === toggle && !toggle.checked)) continue;
                    yield [input.name, input.value];
                }
            }
        },
    });
    return {
        volumes, manualFields, hargaView, hargaValue, toggle, selectors, ids,
        open() { editButton.listeners.click(); },
        openLocal(side) { events['pendapatan:open']({ detail: { side, record: changeRecord } }); },
        submit() {
            let prevented = false;
            form.listeners.submit({ preventDefault() { prevented = true; } });
            return { prevented, dispatched, hidden };
        },
        copy() { list.listeners.click({ target: selectors['.use-cagr-volume'] }); },
        history(value) {
            historyViews[2].value = value;
            list.listeners.input({ target: historyViews[2] });
        },
        disableCagr() { toggle.checked = false; list.listeners.change({ target: toggle }); },
        account(value) { ids['kode-rek'].value = value; ids['kode-rek'].listeners.change(); },
        service(value) {
            selectors['.pelayanan-select'].value = value;
            list.listeners.change({ target: selectors['.pelayanan-select'] });
        },
        price(value) { hargaView.value = value; list.listeners.input({ target: hargaView }); },
        remove() { return list.listeners.click({ target: selectors['.remove-pelayanan'] }); },
        count: () => activeRows.length,
    };
}

test('change modal posts proposed services to change route while retaining account and manual prices', () => {
    const account = workbookCatalog[0];
    const record = {
        id: 17, kode_rek: account.kode_rek, uraian: account.uraian,
        jenis_pelayanans: [{
            uraian: account.pelayanan[0].uraian, harga_satuan: '7.25',
            volume_1: '2', volume_2: '3', volume_3: '4', volume_4: '5', gunakan_cagr: false,
        }],
    };
    const e = editor({ code: account.kode_rek, service: account.pelayanan[0].uraian }, 1, async () => true, record);
    e.open();
    assert.equal(e.ids['pendapatan-pelayanan-form'].action, '/rba-perubahan/pendapatan/17');
    assert.equal(e.ids['pendapatan-method'].value, 'PUT');
    assert.equal(e.ids['pendapatan-id'].value, 17);
    assert.equal(e.ids['kode-rek-perubahan'].value, account.kode_rek);
    assert.equal(e.ids['kode-rek'].disabled, true);
    assert.equal(e.hargaValue.value, '7.25');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 870,00');
    assert.equal(e.ids['modalPendapatanLabel'].textContent, 'Usulkan Perubahan Rincian Pendapatan');
});

test('shift modal saves local services with CAGR and manual volumes without posting an individual account', () => {
    const account = workbookCatalog[0];
    const service = {
        uraian: account.pelayanan[0].uraian, harga_satuan: '5', volume_1: '10',
        volume_2: '2', volume_3: '3', volume_4: '4', gunakan_cagr: true,
    };
    const record = { id: 17, kode_rek: account.kode_rek, jenis_pelayanans: [service] };
    const e = editor({ code: account.kode_rek, service: service.uraian }, 1, async () => true, record, true);
    e.openLocal('tujuan');
    e.copy();
    const result = e.submit();
    assert.equal(result.prevented, true);
    assert.equal(result.hidden, 1);
    assert.equal(result.dispatched.length, 1);
    assert.equal(result.dispatched[0].type, 'pendapatan:rincian');
    assert.equal(result.dispatched[0].detail.side, 'tujuan');
    const saved = result.dispatched[0].detail.services[0];
    assert.equal(saved.gunakan_cagr, true);
    assert.equal(saved.volume_1, '133');
    assert.equal(saved.volume_2, '2');
    assert.equal(saved.volume_3, '3');
    assert.equal(saved.volume_4, '4');
    assert.equal(saved.harga_satuan, '5');
    assert.equal(saved.realisasi_tahun_3, '100');
});

test('shift modal keeps invalid CAGR visible without replacing the staged services', () => {
    const account = workbookCatalog[0];
    const service = { uraian: account.pelayanan[0].uraian, harga_satuan: '5', gunakan_cagr: true };
    const record = { id: 17, kode_rek: account.kode_rek, jenis_pelayanans: [service] };
    const e = editor({ code: account.kode_rek, service: service.uraian }, 1, async () => true, record, true);
    e.openLocal('sumber');
    e.history('0');
    const result = e.submit();
    assert.equal(result.prevented, true);
    assert.equal(result.hidden, 0);
    assert.equal(result.dispatched.length, 0);
});

test('active CAGR keeps manual fields enabled and copies only volume one on click', () => {
    const e = editor();
    assert.ok(e.manualFields.every(field => !field.hidden));
    assert.ok(e.volumes.every(input => !input.disabled));
    assert.equal(e.hargaView.disabled, false);
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 1.200,00');
    assert.equal(e.selectors['.cagr-proyeksi'].value, '133,00');
    assert.equal(e.volumes[0].value, '10');
    e.copy();
    assert.deepEqual(e.volumes.map(input => input.value), ['133', '2', '3', '4']);
    assert.equal(e.hargaValue.value, '5');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 15.960,00');
    e.history('50');
    assert.equal(e.selectors['.cagr-proyeksi'].value, '100,00');
    assert.equal(e.volumes[0].value, '133');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 15.960,00');
});

test('invalid CAGR disables copying while manual calculation remains visible', () => {
    const e = editor();
    e.history('');
    assert.equal(e.selectors['.use-cagr-volume'].disabled, true);
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 1.200,00');
    e.copy();
    assert.equal(e.volumes[0].value, '10');
    e.disableCagr();
    assert.ok(e.manualFields.every(field => !field.hidden));
    assert.equal(e.hargaValue.value, '5');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 1.200,00');
});

test('account filters service options and fills canonical description and tariff', () => {
    const e = editor({});
    assert.equal(e.selectors['.pelayanan-select'].disabled, true);
    e.account('4.1.02.01.01.0001');
    assert.equal(e.ids['uraian-rekening'].value, 'Pendapatan Pelayanan Rawat Jalan');
    assert.equal(e.selectors['.pelayanan-select'].disabled, false);
    assert.equal(e.selectors['.pelayanan-select'].options.length, 8);
    assert.ok(!e.selectors['.pelayanan-select'].options.some(option => option.value === 'Hemoglobin (Fotometri)'));
    e.service('Pelayanan Rawat Jalan');
    assert.equal(e.hargaValue.value, '15000.00');
    assert.equal(e.hargaView.value, '15.000,00');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 3.600.000,00');
    e.account('4.1.02.02.01.0001');
    assert.equal(e.ids['uraian-rekening'].value, 'Pendapatan Pelayanan Penunjang Diagnosis');
    assert.equal(e.selectors['.pelayanan-select'].value, '');
    assert.equal(e.hargaValue.value, '');
    assert.ok(!e.selectors['.pelayanan-select'].options.some(option => option.value === 'Pelayanan Rawat Jalan'));
    e.service('Hemoglobin (Fotometri)');
    assert.equal(e.hargaValue.value, '8000.00');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 1.920.000,00');
});

test('restored service receives reference tariff and zero tariff stays zero', () => {
    const e = editor({ code: '4.1.02.01.01.0001', service: 'Pelayanan Rawat Jalan' });
    assert.equal(e.selectors['.pelayanan-select'].value, 'Pelayanan Rawat Jalan');
    assert.equal(e.hargaValue.value, '15000.00');
    e.account('4.1.02.01.04.0001');
    e.service('Injeksi Obat');
    assert.equal(e.hargaValue.value, '0.00');
    assert.equal(e.hargaView.value, '0,00');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 0,00');
    assert.equal(referenceService(workbookCatalog, '4.1.02.01.01.0001', 'Hemoglobin (Fotometri)'), null);
});

test('manual tariff updates totals and saved overrides survive reopening', () => {
    const e = editor({ code: '4.1.02.01.01.0001', service: 'Pelayanan Rawat Jalan', savedPrice: '20000.25' });
    assert.equal(e.hargaValue.value, '20000.25');
    assert.equal(e.hargaView.value, '20.000,25');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 4.800.060,00');
    e.price('25.000,75');
    assert.equal(e.hargaValue.value, '25000.75');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 6.000.180,00');
    e.service('Pemeriksaan Kesehatan Anak Sekolah');
    assert.equal(e.hargaValue.value, '1000.00');
    const zero = editor({ code: '4.1.02.01.01.0001', service: 'Pelayanan Rawat Jalan', savedPrice: '0.00' });
    assert.equal(zero.hargaValue.value, '0.00');
    assert.equal(zero.ids['pendapatan-total'].textContent, 'Rp 0,00');
});

test('additional account without preset tariff allows manual entry without inventing a default', () => {
    const e = editor({});
    e.account('4.2.01.01.01.0001');
    assert.equal(e.ids['uraian-rekening'].value, 'Pendapatan Hibah');
    e.service('Pendapatan Hibah');
    assert.equal(e.hargaValue.value, '');
    assert.equal(e.hargaView.value, '');
    e.price('50.000');
    assert.equal(e.hargaValue.value, '50000');
    assert.equal(e.ids['pendapatan-total'].textContent, 'Rp 12.000.000,00');
});

test('removing an unsaved service requires confirmation and keeps at least one row', async () => {
    let alerts = 0;
    const cancelled = editor(null, 2, async () => { alerts++; return false; });
    await cancelled.remove();
    assert.equal(cancelled.count(), 2);
    const confirmed = editor(null, 2, async () => { alerts++; return true; });
    await confirmed.remove();
    assert.equal(confirmed.count(), 1);
    await confirmed.remove();
    assert.equal(confirmed.count(), 1);
    assert.equal(alerts, 2);
});
