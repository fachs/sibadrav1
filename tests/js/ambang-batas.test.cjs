const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function form(initial = {}) {
    const elements = new Map();
    function get(id) {
        if (!elements.has(id)) elements.set(id, {
            id, value: '', selectionStart: null, error: '', listeners: {},
            addEventListener(name, callback) { this.listeners[name] = callback; },
            setCustomValidity(message) { this.error = message; },
            setSelectionRange(start) { this.selectionStart = start; },
        });
        return elements.get(id);
    }
    const element = get('ambang-batas-form');
    element.elements = { anggaran: get('anggaran'), realisasi: get('realisasi') };
    element.checkValidity = () => ['anggaran-view', 'realisasi-view'].every(id => get(id).value !== '' && get(id).error === '');
    element.reportValidity = () => {};
    for (const [id, value] of Object.entries(initial)) get(id).value = value;
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/ambang-batas.js'), 'utf8'), {
        document: { getElementById: get }, Intl,
    });
    return {
        get,
        input(id, value, cursor = value.length) {
            const view = get(id + '-view');
            view.value = value;
            view.selectionStart = cursor;
            view.listeners.input();
        },
        submit() {
            let prevented = false;
            element.listeners.submit({ preventDefault() { prevented = true; } });
            return prevented;
        },
    };
}

test('formats typed numbers and submits canonical numeric values', () => {
    const f = form();
    f.input('anggaran', '6000');
    f.input('realisasi', '7000');
    assert.equal(f.get('anggaran-view').value, '6.000');
    assert.equal(f.get('realisasi-view').value, '7.000');
    assert.equal(f.get('anggaran').value, '6000');
    assert.equal(f.get('realisasi').value, '7000');
    assert.equal(f.get('persentase-preview').value, '16,67%');
    assert.equal(f.submit(), false);
});

test('edit values and validation old input are formatted without changing their amounts', () => {
    const f = form({ anggaran: '650000000.00', realisasi: '700000000.50' });
    assert.equal(f.get('anggaran-view').value, '650.000.000,00');
    assert.equal(f.get('realisasi-view').value, '700.000.000,50');
    assert.equal(f.get('anggaran').value, '650000000.00');
    assert.equal(f.get('realisasi').value, '700000000.50');
    assert.equal(f.get('persentase-preview').value, '7,69%');
});

test('pasted Indonesian values retain decimals and a lower realisation gives a negative percentage', () => {
    const f = form();
    f.input('anggaran', '10.000,50');
    f.input('realisasi', '9.000,25');
    assert.equal(f.get('anggaran').value, '10000.50');
    assert.equal(f.get('realisasi').value, '9000.25');
    assert.equal(f.get('persentase-preview').value, '-10,00%');
    f.input('realisasi', '0');
    assert.equal(f.get('realisasi').value, '0');
    assert.equal(f.get('persentase-preview').value, '-100,00%');
});

test('empty invalid and out of range inputs clear stale values and prevent submission', () => {
    const f = form({ anggaran: '6000', realisasi: '7000' });
    for (const value of ['', ',', '.', '-1000', 'abc', '1,234', '10000000000000']) {
        f.input('realisasi', value);
        assert.equal(f.get('realisasi').value, '');
        assert.equal(f.get('persentase-preview').value, '-');
        assert.equal(f.submit(), true);
    }
    f.input('realisasi', '7000');
    f.input('anggaran', '0');
    assert.equal(f.get('anggaran').value, '');
    assert.equal(f.submit(), true);
});

test('formatting preserves the caret when inserting a digit in the middle', () => {
    const f = form();
    f.input('anggaran', '1.2000', 3);
    assert.equal(f.get('anggaran-view').value, '12.000');
    assert.equal(f.get('anggaran-view').selectionStart, 2);
});
