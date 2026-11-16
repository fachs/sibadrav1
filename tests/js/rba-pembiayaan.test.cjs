const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const sandbox = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/rba-pembiayaan.js'), 'utf8'), sandbox);
const { calculate, rupiah, formatPrice, parsePrice } = sandbox.module.exports;

test('all four volume factors and price determine the preview', () => {
    assert.equal(calculate({ harga_satuan: '1000.25', volume_1: '2', volume_2: '3', volume_3: '4', volume_4: '5' }), '120030.00');
    assert.equal(rupiah('120030.00'), 'Rp 120.030,00');
});

test('preview uses decimal half-up rounding including large amounts', () => {
    assert.equal(calculate({ harga_satuan: '0.05', volume_1: '6.7' }), '0.34');
    assert.equal(calculate({ harga_satuan: '9999999999999.99', volume_1: '1' }), '9999999999999.99');
    assert.equal(rupiah('9999999999999.99'), 'Rp 9.999.999.999.999,99');
});

test('empty optional volumes default to one while explicit zero is retained', () => {
    assert.equal(calculate({ harga_satuan: '2500.00', volume_1: '2', volume_2: '', volume_3: null }), '5000.00');
    assert.equal(calculate({ harga_satuan: '2500.00', volume_1: '2', volume_4: '0' }), '0.00');
});

test('negative and malformed values do not show a misleading total', () => {
    for (const value of ['-1', 'text', '1,5', '1.23456']) {
        assert.equal(calculate({ harga_satuan: '1000', volume_1: value }), null);
    }
    assert.equal(calculate({ harga_satuan: '1.001' }), null);
    assert.equal(rupiah(null), 'Periksa harga dan volume');
});

test('editing loads all detail fields and creating resets the form and request method', () => {
    const callbacks = {};
    const controls = {};
    for (const name of ['kode_rek', 'uraian', 'harga_satuan', '_method', 'pembiayaan_id']) controls[name] = { value: '', addEventListener: () => {} };
    for (let i = 1; i <= 4; i++) {
        controls['volume_' + i] = { value: '1' };
        controls['satuan_' + i] = { value: '' };
    }
    const form = { elements: { namedItem: name => controls[name] }, dataset: { perubahan: '0', createUrl: '/rba/pembiayaan' },
        addEventListener: (name, callback) => { callbacks[name] = callback; } };
    const total = {};
    const priceView = { value: '', selectionStart: null, setCustomValidity: () => {} };
    const nodes = { 'pembiayaan-form': form, 'pembiayaan-total': total, 'pembiayaan-harga': priceView, 'modalPembiayaan': { dataset: { openOnLoad: '0' } }, 'modalPembiayaanLabel': {} };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/rba-pembiayaan.js'), 'utf8'), {
        document: { getElementById: name => nodes[name], addEventListener: (name, callback) => { callbacks[name] = callback; } },
    });
    callbacks.DOMContentLoaded();
    const record = { kode_rek: '6.1.01.01.01.0001', uraian: 'SiLPA', harga_satuan: '1000.25',
        volume_1: '2', volume_2: '3', volume_3: '4', volume_4: '5', satuan_4: 'Kegiatan' };
    const edit = { dataset: { id: '12', action: '/rba/pembiayaan/12', record: JSON.stringify(record) } };
    callbacks.click({ target: { closest: selector => selector === '[data-pembiayaan-edit]' ? edit : null } });
    assert.equal(form.action, '/rba/pembiayaan/12');
    assert.equal(controls._method.disabled, false);
    assert.equal(controls.volume_4.value, '5');
    assert.equal(controls.satuan_4.value, 'Kegiatan');
    assert.equal(priceView.value, '1.000,25');
    assert.equal(total.textContent, 'Rp 120.030,00');
    priceView.value = '1.500.000,50';
    callbacks.input({ target: priceView });
    assert.equal(controls.harga_satuan.value, '1500000.50');
    assert.equal(total.textContent, 'Rp 180.000.060,00');
    controls.volume_1.value = '0';
    callbacks.input();
    assert.equal(total.textContent, 'Rp 0,00');
    callbacks.click({ target: { closest: selector => selector === '[data-pembiayaan-create]' ? {} : null } });
    assert.equal(form.action, '/rba/pembiayaan');
    assert.equal(controls._method.disabled, true);
    assert.equal(controls.pembiayaan_id.value, '');
    assert.equal(controls.volume_4.value, '1');
    assert.equal(controls.satuan_4.value, '');
    assert.equal(priceView.value, '');
});

test('price uses Indonesian thousands and decimal separators without changing saved numeric values', () => {
    for (const [value, formatted] of [['1000000', '1.000.000'], ['1000.25', '1.000,25'], ['0.50', '0,50'], ['9999999999999.99', '9.999.999.999.999,99']]) {
        assert.equal(formatPrice(value), formatted);
        assert.equal(parsePrice(formatted), value);
    }
    assert.equal(parsePrice('1000,'), '1000');
    assert.equal(formatPrice(''), '');
    assert.equal(parsePrice(''), '');
    assert.equal(parsePrice('-1.000'), null);
    assert.equal(parsePrice('1.000,123'), null);
});
