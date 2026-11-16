document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-user-password-toggle]').forEach(function (button) {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;

        function setVisible(visible) {
            input.type = visible ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) icon.className = visible ? 'ri-eye-off-line' : 'ri-eye-line';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', (visible ? 'Sembunyikan' : 'Tampilkan') + ' password');
            button.setAttribute('title', (visible ? 'Sembunyikan' : 'Tampilkan') + ' password');
        }

        button.addEventListener('click', function () {
            setVisible(input.type === 'password');
        });
        button.closest('.modal')?.addEventListener('hidden.bs.modal', function () {
            setVisible(false);
        });
    });

    document.querySelectorAll('[data-role-assignment]').forEach(function (group) {
        const selects = Array.from(group.querySelectorAll('select[name="roles[]"]'));
        function refresh() {
            selects.forEach(function (select) {
                const others = selects.filter(input => input !== select).map(input => input.value).filter(Boolean);
                Array.from(select.options).forEach(option => {
                    option.disabled = option.value !== '' && option.value !== select.value && others.includes(option.value);
                });
            });
        }
        selects.forEach(select => select.addEventListener('change', refresh));
        refresh();
    });
    document.querySelectorAll('[data-user-error-modal="1"]').forEach(function (modal) {
        if (window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
});
