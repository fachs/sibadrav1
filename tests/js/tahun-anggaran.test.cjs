const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/tahun-anggaran.js'), 'utf8');

function setup() {
    const sent = [];
    const selector = { dataset: { globalYear: '2026' } };
    const postForm = { method: 'post', querySelector: () => null, appendChild: input => { postForm.marker = input; } };
    const getForm = { method: 'get', appendChild: () => assert.fail('GET filters should not receive mutation markers') };
    const window = { location: { href: 'https://sibadra.test/rba/pendapatan', origin: 'https://sibadra.test' },
        fetch: (input, options) => { sent.push({ input, options }); return Promise.resolve({ ok: true }); } };
    const document = { addEventListener: (name, callback) => callback(), getElementById: () => selector,
        querySelectorAll: () => [selector, postForm, getForm], createElement: () => ({}) };
    vm.runInNewContext(script, { document, window, URL, Headers });
    return { sent, window, postForm };
}

test('forms carry the year that was active when the page loaded', () => {
    const { postForm } = setup();
    assert.equal(postForm.marker.type, 'hidden');
    assert.equal(postForm.marker.name, '_tahun_aktif');
    assert.equal(postForm.marker.value, '2026');
});

test('same origin mutations carry year context while read requests and external services do not', async () => {
    const { sent, window } = setup();
    await window.fetch('/angkas/update-modal', { method: 'POST', headers: { 'X-CSRF-TOKEN': 'token' } });
    assert.equal(sent[0].options.headers.get('X-Tahun-Anggaran'), '2026');
    assert.equal(sent[0].options.headers.get('X-CSRF-TOKEN'), 'token');
    await window.fetch('/api/akun');
    assert.equal(sent[1].options.headers, undefined);
    await window.fetch(new URL('https://outside.example/upload'), { method: 'POST' });
    assert.equal(sent[2].options.headers, undefined);
});
