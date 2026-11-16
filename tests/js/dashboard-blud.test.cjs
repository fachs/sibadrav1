const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/pages/dashboard-blud.js'), 'utf8').replace(/^import Chart from 'chart.js\/auto';\s*/, '');

test('chart uses real monthly values with rupiah tooltips and respects reduced motion', () => {
    const rows = Array.from({ length: 12 }, (_, index) => ({ pendapatan: String(index * 1000), belanja: String(index * 500) }));
    let config;
    vm.runInNewContext(source, {
        Chart: class { constructor(canvas, options) { config = options; } },
        document: { getElementById: id => id === 'dashboard-chart-data' ? { textContent: JSON.stringify(rows) } : id === 'dashboard-monthly-chart' ? {} : null },
        window: { matchMedia: () => ({ matches: true }) },
    });
    assert.equal(config.data.labels.length, 12);
    assert.equal(config.data.datasets[0].data[2], 2000);
    assert.equal(config.data.datasets[1].data[11], 5500);
    assert.equal(config.options.animation.duration, 0);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Pendapatan' }, parsed: { y: 6000 } }), 'Pendapatan: Rp 6.000');
});

test('weekly chart shows six daily amounts from Monday through Saturday with date tooltips', () => {
    const rows = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'].map((label, index) => ({
        label, title: label + ', ' + (index + 4) + ' Jan 2027', pendapatan: index === 0 ? '1250.50' : '0.00',
    }));
    let config;
    vm.runInNewContext(source, {
        Chart: class { constructor(canvas, options) { config = options; } },
        document: { getElementById: id => id === 'dashboard-weekly-data' ? { textContent: JSON.stringify(rows) } : id === 'dashboard-weekly-chart' ? {} : null },
        window: { matchMedia: () => ({ matches: true }) },
    });
    assert.equal(config.type, 'line');
    assert.deepEqual(Array.from(config.data.labels), rows.map(row => row.label));
    assert.deepEqual(Array.from(config.data.datasets[0].data), [1250.5, 0, 0, 0, 0, 0]);
    assert.equal(config.options.scales.x.ticks.autoSkip, false);
    assert.equal(config.options.plugins.tooltip.callbacks.title([{ dataIndex: 0 }]), 'Senin, 4 Jan 2027');
    assert.equal(config.options.plugins.tooltip.callbacks.title([]), '');
    assert.equal(config.options.animation.duration, 0);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Pendapatan' }, parsed: { y: 1250.5 } }), 'Pendapatan: Rp 1.250,5');
    assert.match(config.options.scales.y.ticks.callback(1000000), /^Rp /);
});

test('chart is safe on other pages that do not contain dashboard elements', () => {
    let created = false;
    vm.runInNewContext(source, {
        Chart: class { constructor() { created = true; } },
        document: { getElementById: () => null },
    });
    assert.equal(created, false);
});
