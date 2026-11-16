import Swal from 'sweetalert2';

export async function confirmDelete(options = {}) {
    const result = await Swal.fire({
        title: options.title || 'Hapus Data?',
        text: options.text || 'Yakin ingin menghapus data ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText || 'Ya, Hapus',
        cancelButtonText: 'Batal',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger ms-2',
            cancelButton: 'btn btn-cancel',
        },
    });

    return result.isConfirmed === true;
}

export function configureUploadDelete(Dropzone) {
    Dropzone.confirm = async (question, accepted, rejected) => {
        const cancelUpload = /batalkan|cancel/i.test(question);
        if (await confirmDelete({
            title: cancelUpload ? 'Batalkan Unggahan?' : 'Hapus File?',
            text: question,
            confirmButtonText: cancelUpload ? 'Ya, Batalkan' : 'Ya, Hapus',
        })) accepted();
        else if (rejected) rejected();
    };
}

window.confirmDelete = confirmDelete;
const pending = new WeakSet();
const approved = new WeakSet();

// Delegasi juga mencakup form dari DataTables dan konten yang dimuat belakangan.
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const method = form.querySelector('input[name="_method"]')?.value.toUpperCase();
    if (method !== 'DELETE' && !form.hasAttribute('data-confirm-delete')) return;
    if (approved.has(form)) return;

    event.preventDefault();
    const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    if (pending.has(form) || form.dataset.deleteDisabled === '1' || submitter?.disabled) return;
    pending.add(form);

    try {
        if (!await confirmDelete({
            title: form.dataset.confirmTitle,
            text: form.dataset.confirmMessage,
            confirmButtonText: form.dataset.confirmButton,
        })) return;
        if (form.isConnected === false || form.dataset.deleteDisabled === '1' || submitter?.disabled) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        approved.add(form);
        // requestSubmit mempertahankan validasi serta name/value tombol pengirim.
        HTMLFormElement.prototype.requestSubmit.call(form, submitter || undefined);
    } finally {
        approved.delete(form);
        pending.delete(form);
    }
}, true);
