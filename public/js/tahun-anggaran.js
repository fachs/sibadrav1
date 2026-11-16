document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('global-budget-year-form');
    if (!selector) return;
    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch;
        window.fetch = function (input, options = {}) {
            const url = new URL(typeof input === 'string' ? input : (input instanceof URL ? input.href : input.url), window.location.href);
            const method = (options.method || input.method || 'GET').toUpperCase();
            if (url.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                const headers = new Headers(options.headers || input.headers);
                headers.set('X-Tahun-Anggaran', selector.dataset.globalYear);
                options = { ...options, headers };
            }
            return originalFetch.call(this, input, options);
        };
    }
    document.querySelectorAll('form').forEach(function (form) {
        if (form === selector || (form.method || 'get').toLowerCase() === 'get') return;
        if (form.querySelector('[name="_tahun_aktif"]')) return;
        const marker = document.createElement('input');
        marker.type = 'hidden';
        marker.name = '_tahun_aktif';
        marker.value = selector.dataset.globalYear;
        form.appendChild(marker);
    });
});
