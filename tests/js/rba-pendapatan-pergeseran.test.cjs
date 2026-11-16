const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
function exported(file) {
    const context = { module: { exports: {} } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/' + file), 'utf8'), context);
    return context.module.exports;
}
const workflow = exported('rba-pendapatan-pergeseran.js');
const calculation = exported('rba-pendapatan-pelayanan.js');

function page(options = {}) {
    function element() {
        return {
            value: '', disabled: false, hidden: false, required: false, dataset: {}, listeners: {}, attrs: {}, children: [],
            addEventListener(type, fn) { this.listeners[type] = fn; },
            hasAttribute(name) { return Object.hasOwn(this.attrs, name); },
            setAttribute(name, value) { this.attrs[name] = value; },
            replaceChildren(...children) { this.children = children; },
            appendChild(child) { this.children.push(child); },
            insertCell() { const child = element(); this.children.push(child); return child; },
            insertRow() { const child = element(); this.children.push(child); return child; },
            createTHead() { const child = element(); this.children.push(child); return child; },
            createTBody() { const child = element(); this.children.push(child); return child; },
        };
    }
    const service = { unit_pelayanan: 'Klaster 1', uraian: 'Pelayanan', harga_satuan: '5', volume_1: '10', satuan_1: 'Pasien' };
    const records = [
        { id: 1, kode_rek: '4.1.02.01', uraian: 'Jasa', nama_blud: 'A', jumlah_awal: '50', jenis_pelayanans: [service] },
        { id: 2, kode_rek: '4.1.02.02', uraian: 'Jasa Lain', nama_blud: 'A', jumlah_awal: '50', jenis_pelayanans: [service] },
        { id: 3, kode_rek: '4.1.02.03', uraian: 'BLUD Lain', nama_blud: 'B', jumlah_awal: '50', jenis_pelayanans: [service] },
    ];
    const catalog = [{ kode_rek: '4.2.01.01', uraian: 'Hibah' }, { kode_rek: '4.1.02.01', uraian: 'Jasa' }];
    const ids = {};
    for (const id of ['pergeseran-pendapatan-form', 'pergeseran-pendapatan-records', 'referensi-pendapatan',
        'sumber-pendapatan', 'tujuan-pendapatan', 'tipe-tujuan', 'tujuan-kode-rek', 'tujuan-existing-panel', 'tujuan-baru-panel',
        'sumber-rincian', 'tujuan-rincian', 'sumber-awal', 'tujuan-awal', 'sumber-total', 'tujuan-total', 'sumber-preview', 'tujuan-preview',
        'edit-rincian-sumber', 'edit-rincian-tujuan', 'pergeseran-total-awal', 'pergeseran-total-akhir', 'pergeseran-selisih',
        'pergeseran-tahap', 'simpan-pergeseran']) ids[id] = element();
    ids['pergeseran-pendapatan-records'].textContent = JSON.stringify(records);
    ids['referensi-pendapatan'].textContent = JSON.stringify(catalog);
    ids['sumber-pendapatan'].value = '1';
    ids['tipe-tujuan'].value = 'existing';
    ids['tujuan-pendapatan'].dataset.selected = '2';
    ids['tujuan-pendapatan'].disabled = Boolean(options.locked);
    if (options.restored) ids['sumber-rincian'].value = JSON.stringify(options.restored);
    const events = {};
    const dispatched = [];
    const window = {
        PendapatanPelayanan: calculation,
        addEventListener(type, fn) { events[type] = fn; },
        dispatchEvent(event) { dispatched.push(event); events[event.type]?.(event); },
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/rba-pendapatan-pergeseran.js'), 'utf8'), {
        document: { getElementById: id => ids[id], createElement: element }, window,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    });
    return {
        ids, dispatched, service,
        change(id, value) { ids[id].value = value; ids[id].listeners.change(); },
        save(side, services) { events['pendapatan:rincian']({ detail: { side, services } }); },
    };
}

test('verification follows the same first-three-code-parts rule as belanja', () => {
    assert.equal(workflow.tahap('4.1.02.01.01.0001', '4.1.02.02.01.0001'), 'Kepala Puskesmas');
    assert.equal(workflow.tahap('4.1.02.01.01.0001', '4.2.01.01.01.0001'), 'Kepala Puskesmas → Dinas Kesehatan');
});

test('existing destination is limited to another account in the same BLUD and totals use service rows', () => {
    const p = page();
    assert.deepEqual(p.ids['tujuan-pendapatan'].children.map(option => option.value), ['', 2]);
    assert.equal(p.ids['pergeseran-total-awal'].textContent, 'Rp 100,00');
    assert.equal(p.ids['pergeseran-total-akhir'].textContent, 'Rp 100,00');
    assert.equal(p.ids['simpan-pergeseran'].disabled, false);
    p.save('sumber', [{ ...p.service, volume_1: '0' }]);
    assert.equal(p.ids['sumber-total'].textContent, 'Rp 0,00');
    assert.equal(p.ids['tujuan-total'].textContent, 'Rp 50,00');
    assert.equal(p.ids['pergeseran-selisih'].textContent, 'Rp -50,00');
    assert.equal(JSON.parse(p.ids['sumber-rincian'].value)[0].volume_1, '0');
    p.change('tujuan-pendapatan', '3');
    assert.equal(p.ids['simpan-pergeseran'].disabled, true);
});

test('new destination opens existing modal and saves local details before enabling submission', () => {
    const p = page();
    p.change('tipe-tujuan', 'baru');
    p.change('tujuan-kode-rek', '4.2.01.01');
    assert.equal(p.ids['tujuan-baru-panel'].hidden, false);
    assert.equal(p.ids['tujuan-existing-panel'].hidden, true);
    assert.equal(p.ids['simpan-pergeseran'].disabled, true);
    p.ids['edit-rincian-tujuan'].listeners.click();
    const opened = p.dispatched.at(-1);
    assert.equal(opened.type, 'pendapatan:open');
    assert.equal(opened.detail.side, 'tujuan');
    assert.equal(opened.detail.record.kode_rek, '4.2.01.01');
    p.save('tujuan', [{ ...p.service, uraian: 'Hibah', harga_satuan: '10', volume_1: '7' }]);
    assert.equal(p.ids['tujuan-total'].textContent, 'Rp 70,00');
    assert.equal(p.ids['simpan-pergeseran'].disabled, false);
    assert.equal(p.ids['pergeseran-tahap'].textContent, 'Alur verifikasi: Kepala Puskesmas → Dinas Kesehatan');
    assert.equal(JSON.parse(p.ids['tujuan-rincian'].value)[0].harga_satuan, '10');
});

test('restored rows survive initialization and paired edit keeps account selection locked', () => {
    const row = { unit_pelayanan: 'Klaster 4', uraian: 'Pelayanan', harga_satuan: '7.25', volume_1: '3' };
    const p = page({ restored: [row], locked: true });
    assert.equal(p.ids['sumber-total'].textContent, 'Rp 21,75');
    assert.equal(JSON.parse(p.ids['sumber-rincian'].value)[0].unit_pelayanan, 'Klaster 4');
    assert.equal(p.ids['tujuan-pendapatan'].disabled, true);
    assert.equal(p.ids['tujuan-pendapatan'].attrs['data-locked'], '1');
});

test('same account and missing details cannot submit a shift', () => {
    const p = page();
    p.change('tipe-tujuan', 'baru');
    p.change('tujuan-kode-rek', '4.1.02.01');
    assert.equal(p.ids['edit-rincian-tujuan'].disabled, true);
    let prevented = false;
    p.ids['pergeseran-pendapatan-form'].listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.match(p.ids['pergeseran-tahap'].textContent, /Pilih rekening berbeda/);
});
