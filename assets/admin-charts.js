import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend);

// Generic dashboard chart initializer: any element carrying
// data-admin-chart="{...}" (a JSON blob: {labels: [...], datasets: [{label, data, color}, ...]})
// gets a Chart.js line chart rendered into it. One shared function rather
// than a chart-specific script, so a future dashboard widget (revenue,
// order volume, whatever a client wants) only needs to render a <canvas
// data-admin-chart="..."> - no new JS.
function initCharts() {
    document.querySelectorAll('[data-admin-chart]').forEach(function (canvas) {
        if (canvas.dataset.chartInitialized) return;
        canvas.dataset.chartInitialized = '1';

        var config;
        try {
            config = JSON.parse(canvas.dataset.adminChart);
        } catch (e) {
            return;
        }

        var isDark = document.documentElement.getAttribute('data-theme') === 'dark'
            || (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
        var textColor = isDark ? '#8b94a1' : '#6b7683';

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: config.labels || [],
                datasets: (config.datasets || []).map(function (ds) {
                    return {
                        label: ds.label,
                        data: ds.data,
                        borderColor: ds.color,
                        backgroundColor: ds.color + '22',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2,
                    };
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, precision: 0 } },
                },
                plugins: {
                    legend: { display: (config.datasets || []).length > 1, labels: { color: textColor, boxWidth: 12 } },
                    tooltip: { mode: 'index', intersect: false },
                },
            },
        });
    });
}

window.addEventListener('load', initCharts);
// Re-init after an in-admin AJAX swap (transparentJS re-dispatches 'load'
// on every SPA navigation - see layout.html.twig's Transparent.ready()
// comment for the same pattern) - a freshly-swapped-in canvas has no
// chart yet, and dataset.chartInitialized guards against double-init on
// canvases that survive the swap.
