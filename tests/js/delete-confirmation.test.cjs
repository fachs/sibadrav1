const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function confirmation(fire) {
    let handler;
    let sent = 0;
    let prevented = 0;
    let reported = 0;
    class Form {
        constructor(method = 'DELETE', dataset = {}, marked = false) {
            this.methodOverride = method;
            this.dataset = dataset;
            this.marked = marked;
            this.isConnected = true;
            this.valid = true;
            this.button = { disabled: false, name: 'action', value: 'delete' };
        }
        querySelector(selector) { return selector.includes('_method') ? { value: this.methodOverride } : this.button; }
        hasAttribute() { return this.marked; }
        checkValidity() { return this.valid; }
        reportValidity() { reported++; }
        requestSubmit(button) {
            assert.equal(button, this.button);
            const event = { target: this, submitter: button, preventDefault() { this.defaultPrevented = true; } };
            handler(event);
            if (!event.defaultPrevented) sent++;
        }
    }
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/components/delete-confirmation.js'), 'utf8')
        .replace(/^import Swal from 'sweetalert2';\r?\n/, '').replace(/export /g, '');
    const context = {
        Swal: { fire }, window: {}, HTMLFormElement: Form,
        document: { addEventListener(name, callback, capture) { assert.equal(name, 'submit'); assert.equal(capture, true); handler = callback; } },
    };
    vm.runInNewContext(source, context);
    return {
        form: (...args) => new Form(...args),
        submit: form => handler({ target: form, submitter: form.button, preventDefault() { prevented++; } }),
        sent: () => sent, prevented: () => prevented, reported: () => reported,
        helper: context.window.confirmDelete,
        configureUpload: Dropzone => context.configureUploadDelete(Dropzone),
    };
}

test('cancel and dismiss prevent deletion on any DELETE form', async () => {
    for (const result of [{ isConfirmed: false, isDismissed: true }, { isConfirmed: false }]) {
        const f = confirmation(async options => {
            assert.equal(options.icon, 'warning');
            assert.equal(options.confirmButtonText, 'Ya, Hapus');
            assert.equal(options.cancelButtonText, 'Batal');
            return result;
        });
        await f.submit(f.form());
        assert.equal(f.prevented(), 1);
        assert.equal(f.sent(), 0);
    }
});

test('a dynamically created DELETE form is submitted once after confirmation', async () => {
    let opened = 0;
    const f = confirmation(async () => { opened++; return { isConfirmed: true }; });
    const form = f.form();
    await f.submit(form);
    assert.equal(opened, 1);
    assert.equal(f.sent(), 1);
    assert.equal(form.button.disabled, false);
});

test('repeated submissions while dialog is open do not open another confirmation', async () => {
    let resolve;
    let opened = 0;
    const f = confirmation(() => { opened++; return new Promise(done => { resolve = done; }); });
    const form = f.form();
    const first = f.submit(form);
    await f.submit(form);
    assert.equal(opened, 1);
    resolve({ isConfirmed: true });
    await first;
    assert.equal(f.sent(), 1);
});

test('non-delete forms continue without a deletion dialog', async () => {
    const f = confirmation(() => { throw new Error('Should not confirm'); });
    await f.submit(f.form('PUT'));
    assert.equal(f.prevented(), 0);
    assert.equal(f.sent(), 0);
});

test('marked PATCH actions preserve their message and specific action label', async () => {
    const f = confirmation(async options => {
        assert.equal(options.title, 'Nolkan Perubahan?');
        assert.equal(options.text, 'Nolkan usulan perubahan ini?');
        assert.equal(options.confirmButtonText, 'Ya, Nolkan');
        return { isConfirmed: true };
    });
    await f.submit(f.form('PATCH', { confirmTitle: 'Nolkan Perubahan?', confirmMessage: 'Nolkan usulan perubahan ini?', confirmButton: 'Ya, Nolkan' }, true));
    assert.equal(f.sent(), 1);
});

test('RBA-specific confirmation text and title are preserved', async () => {
    const f = confirmation(async options => {
        assert.equal(options.title, 'Hapus Rencana Pendapatan?');
        assert.equal(options.text, 'Hapus rencana pendapatan beserta seluruh rincian pelayanannya?');
        return { isConfirmed: false };
    });
    await f.submit(f.form('DELETE', { confirmTitle: 'Hapus Rencana Pendapatan?', confirmMessage: 'Hapus rencana pendapatan beserta seluruh rincian pelayanannya?' }, true));
    assert.equal(f.sent(), 0);
});

test('disabled controls and guarded DataTables rows cannot submit or open dialog', async () => {
    const f = confirmation(() => { throw new Error('Should not confirm'); });
    await f.submit(f.form('DELETE', { deleteDisabled: '1' }, true));
    const form = f.form();
    form.button.disabled = true;
    await f.submit(form);
    assert.equal(f.sent(), 0);
});

test('validation still blocks a previously confirmed form', async () => {
    const f = confirmation(async () => ({ isConfirmed: true }));
    const form = f.form();
    form.valid = false;
    await f.submit(form);
    assert.equal(f.reported(), 1);
    assert.equal(f.sent(), 0);
});

test('a form removed from the page while alert is open is not submitted', async () => {
    const f = confirmation(async () => ({ isConfirmed: true }));
    const form = f.form();
    form.isConnected = false;
    await f.submit(form);
    assert.equal(f.sent(), 0);
});

test('upload deletion bridge removes files only after confirmation', async () => {
    for (const confirmed of [true, false]) {
        let removed = 0;
        let cancelled = 0;
        const f = confirmation(async () => ({ isConfirmed: confirmed }));
        const Dropzone = {};
        f.configureUpload(Dropzone);
        await Dropzone.confirm('Hapus file ini dari daftar unggahan?', () => removed++, () => cancelled++);
        assert.equal(removed, confirmed ? 1 : 0);
        assert.equal(cancelled, confirmed ? 0 : 1);
    }
});
