(function () {
    'use strict';

    function prefix(code) { return code.split('.').slice(0, 3).join('.'); }
    function tahap(sumber, tujuan) {
        return prefix(sumber) === prefix(tujuan) ? 'Kepala Puskesmas' : 'Kepala Puskesmas → Dinas Kesehatan';
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { prefix, tahap };
    if (typeof document === 'undefined') return;
    const form = document.getElementById('pergeseran-pendapatan-form');
    if (!form) return;
    const records = JSON.parse(document.getElementById('pergeseran-pendapatan-records').textContent);
    const catalog = JSON.parse(document.getElementById('referensi-pendapatan').textContent);
    const source = document.getElementById('sumber-pendapatan');
    const destination = document.getElementById('tujuan-pendapatan');
    const type = document.getElementById('tipe-tujuan');
    const code = document.getElementById('tujuan-kode-rek');
    const state = { sumber: null, tujuan: null };
    const copy = value => JSON.parse(JSON.stringify(value));
    const money = value => 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const selectRecord = id => records.find(record => String(record.id) === String(id));
    const restored = {};
    for (const side of ['sumber', 'tujuan']) {
        try { restored[side] = JSON.parse(document.getElementById(side + '-rincian').value); } catch (_) { restored[side] = null; }
    }

    function option(value, text) {
        const node = document.createElement('option');
        node.value = value;
        node.textContent = text;
        return node;
    }
    function table(services) {
        const node = document.createElement('table');
        node.className = 'table table-sm table-bordered mb-0';
        const header = node.createTHead().insertRow();
        ['Unit Pelayanan', 'Uraian Jenis Pelayanan', 'Volume 1 / Satuan 1', 'Harga Satuan', 'Jumlah'].forEach(label => {
            const th = document.createElement('th');
            th.textContent = label;
            header.appendChild(th);
        });
        const body = node.createTBody();
        services.forEach(service => {
            const row = body.insertRow();
            let amount = '-';
            try { amount = money(window.PendapatanPelayanan.calculate(service)); } catch (_) { /* Lengkapi melalui modal. */ }
            [service.unit_pelayanan || '', service.uraian || '',
                Number(service.volume_1 ?? 1).toLocaleString('id-ID', { maximumFractionDigits: 4 }) + ' ' + (service.satuan_1 || ''),
                money(service.harga_satuan || 0), amount].forEach(value => { row.insertCell().textContent = value; });
        });
        return node;
    }
    function render() {
        let valid = true;
        const totals = {};
        for (const side of ['sumber', 'tujuan']) {
            const record = state[side];
            document.getElementById('edit-rincian-' + side).disabled = !record;
            document.getElementById(side + '-awal').textContent = record ? money(record.jumlah_awal || 0) : '-';
            document.getElementById(side + '-rincian').value = record ? JSON.stringify(record.jenis_pelayanans) : '';
            document.getElementById(side + '-preview').replaceChildren(...(record ? [table(record.jenis_pelayanans)] : []));
            try {
                if (!record?.jenis_pelayanans.length) throw new Error();
                totals[side] = window.PendapatanPelayanan.total(record.jenis_pelayanans);
                document.getElementById(side + '-total').textContent = money(totals[side]);
            } catch (_) {
                valid = false;
                document.getElementById(side + '-total').textContent = record ? 'Lengkapi rincian pelayanan' : '-';
            }
        }
        const both = state.sumber && state.tujuan;
        const before = both ? Number(state.sumber.jumlah_awal || 0) + Number(state.tujuan.jumlah_awal || 0) : 0;
        const after = valid ? Number(totals.sumber) + Number(totals.tujuan) : 0;
        document.getElementById('pergeseran-total-awal').textContent = both ? money(before) : '-';
        document.getElementById('pergeseran-total-akhir').textContent = valid ? money(after) : '-';
        document.getElementById('pergeseran-selisih').textContent = valid ? money(after - before) : '-';
        document.getElementById('pergeseran-tahap').textContent = both
            ? 'Alur verifikasi: ' + tahap(state.sumber.kode_rek, state.tujuan.kode_rek) : 'Pilih rekening sumber dan tujuan.';
        document.getElementById('simpan-pergeseran').disabled = !valid || !both || state.sumber.kode_rek === state.tujuan.kode_rek;
    }
    function chooseDestination() {
        const existing = type.value === 'existing';
        document.getElementById('tujuan-existing-panel').hidden = !existing;
        document.getElementById('tujuan-baru-panel').hidden = existing;
        destination.required = existing;
        code.required = !existing;
        code.disabled = existing;
        if (!destination.hasAttribute('data-locked')) destination.disabled = !existing;
        state.tujuan = null;
        if (state.sumber) {
            if (existing) {
                const selected = selectRecord(destination.value);
                if (selected && selected.id !== state.sumber.id && selected.nama_blud === state.sumber.nama_blud) state.tujuan = copy(selected);
            } else {
                const account = catalog.find(account => account.kode_rek === code.value);
                if (account && account.kode_rek !== state.sumber.kode_rek) state.tujuan = {
                    id: 0, kode_rek: account.kode_rek, uraian: account.uraian,
                    nama_blud: state.sumber.nama_blud, jumlah_awal: '0.00', jenis_pelayanans: [],
                };
            }
        }
        render();
    }
    function chooseSource(selectedDestination = '') {
        const selected = selectRecord(source.value);
        state.sumber = selected ? copy(selected) : null;
        const choices = state.sumber ? records.filter(record => record.id !== state.sumber.id && record.nama_blud === state.sumber.nama_blud) : [];
        destination.replaceChildren(option('', 'Pilih Rekening Tujuan'), ...choices.map(record => option(record.id, record.kode_rek + ' — ' + record.uraian)));
        destination.value = selectedDestination;
        chooseDestination();
    }
    source.addEventListener('change', () => chooseSource());
    destination.addEventListener('change', chooseDestination);
    type.addEventListener('change', chooseDestination);
    code.addEventListener('change', chooseDestination);
    for (const side of ['sumber', 'tujuan']) {
        document.getElementById('edit-rincian-' + side).addEventListener('click', function () {
            if (state[side]) window.dispatchEvent(new CustomEvent('pendapatan:open', { detail: { side, record: state[side] } }));
        });
    }
    window.addEventListener('pendapatan:rincian', function (event) {
        const { side, services } = event.detail;
        if (!Object.hasOwn(state, side) || !state[side]) return;
        state[side].jenis_pelayanans = services;
        render();
    });
    form.addEventListener('submit', function (event) {
        render();
        if (document.getElementById('simpan-pergeseran').disabled) {
            event.preventDefault();
            document.getElementById('pergeseran-tahap').textContent = 'Pilih rekening berbeda dan lengkapi rincian pelayanan sumber serta tujuan.';
        }
    });
    if (destination.disabled) destination.setAttribute('data-locked', '1');
    chooseSource(destination.dataset.selected);
    for (const side of ['sumber', 'tujuan']) {
        if (state[side] && Array.isArray(restored[side])) state[side].jenis_pelayanans = restored[side];
    }
    render();
}());
