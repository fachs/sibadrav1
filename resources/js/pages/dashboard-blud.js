import Chart from 'chart.js/auto';

const payload = document.getElementById('dashboard-chart-data');
const canvas = document.getElementById('dashboard-monthly-chart');
const money = value => 'Rp ' + Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const compactMoney = value => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const tooltip = () => ({
    backgroundColor: '#1c252e',
    padding: 12,
    cornerRadius: 6,
    titleFont: { size: 12 },
    bodyFont: { size: 12 },
    callbacks: { label: context => context.dataset.label + ': ' + money(context.parsed.y) },
});
if (payload && canvas) {
    const monthly = JSON.parse(payload.textContent);
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [
                { label: 'Pendapatan', data: monthly.map(row => Number(row.pendapatan)), borderColor: '#47ad94', backgroundColor: 'rgba(71, 173, 148, .10)' },
                { label: 'Belanja', data: monthly.map(row => Number(row.belanja)), borderColor: '#604ae3', backgroundColor: 'rgba(96, 74, 227, .08)' },
            ].map(series => ({ ...series, fill: true, cubicInterpolationMode: 'monotone', borderWidth: 2, pointRadius: 0, pointHoverRadius: 5 })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400 },
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: tooltip(),
            },
            scales: {
                x: { border: { display: false }, grid: { display: false }, ticks: { color: '#8493a8', autoSkip: true, maxRotation: 0, font: { size: 12 } } },
                y: { beginAtZero: true, border: { display: false, dash: [4, 4] }, grid: { color: 'rgba(132, 147, 168, .14)', drawTicks: false }, ticks: { color: '#8493a8', maxTicksLimit: 5, padding: 8, font: { size: 12 }, callback: compactMoney } },
            },
            layout: { padding: { top: 8, bottom: 0 } },
        },
    });
}

const weeklyPayload = document.getElementById('dashboard-weekly-data');
const weeklyCanvas = document.getElementById('dashboard-weekly-chart');
if (weeklyPayload && weeklyCanvas) {
    const weekly = JSON.parse(weeklyPayload.textContent);
    const dailyTooltip = tooltip();
    dailyTooltip.callbacks.title = items => items.length ? weekly[items[0].dataIndex].title : '';
    new Chart(weeklyCanvas, {
        type: 'line',
        data: {
            labels: weekly.map(row => row.label),
            datasets: [{
                label: 'Pendapatan',
                data: weekly.map(row => Number(row.pendapatan)),
                borderColor: '#47ad94',
                backgroundColor: 'rgba(71, 173, 148, .12)',
                fill: true,
                cubicInterpolationMode: 'monotone',
                pointRadius: 3,
                pointBackgroundColor: '#47ad94',
                pointHoverRadius: 5,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400 },
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { display: false }, tooltip: dailyTooltip },
            scales: {
                x: { border: { display: false }, grid: { display: false }, ticks: { color: '#8493a8', maxRotation: 0, autoSkip: false, font: { size: 12 } } },
                y: {
                    beginAtZero: true,
                    border: { display: false, dash: [4, 4] },
                    grid: { color: 'rgba(132, 147, 168, .14)', drawTicks: false },
                    ticks: { color: '#8493a8', maxTicksLimit: 4, padding: 8, font: { size: 12 }, callback: compactMoney },
                },
            },
        },
    });
}
