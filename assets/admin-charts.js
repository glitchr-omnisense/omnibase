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
function chartDatasets(config) {
    // A line needs at least 2 points to draw a segment - a single-day
    // range (e.g. "today") has exactly one, so with the usual
    // pointRadius:0 the chart renders nothing at all: no line to connect,
    // no dot to show for the lone point. Bumping the radius only in that
    // case draws a visible dot instead, without changing how any
    // multi-point range looks.
    var pointRadius = (config.labels || []).length <= 1 ? 4 : 0;

    return (config.datasets || []).map(function (ds) {
        return {
            label: ds.label,
            data: ds.data,
            borderColor: ds.color,
            backgroundColor: ds.color + '22',
            fill: true,
            tension: 0.35,
            pointRadius: pointRadius,
            pointHoverRadius: 4,
            borderWidth: 2,
        };
    });
}

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

        // Kept on the canvas itself (rather than a module-scoped map) so
        // updateChart() below can find it again from any other script on
        // the page - admin-charts is its own Encore entry, not a shared
        // module other inline <script> blocks (the range-picker's own) can
        // just import from.
        canvas._adminChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: config.labels || [],
                datasets: chartDatasets(config),
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

// Replaces a chart's data in place (labels + each dataset's points/color)
// instead of destroying/recreating it - keeps the same canvas, legend and
// scale config, so a range-picker swap only re-animates the line itself.
function updateChart(canvas, config) {
    var chart = canvas && canvas._adminChart;
    if (!chart) return false;

    chart.data.labels = config.labels || [];
    chart.data.datasets = chartDatasets(config);
    chart.update();
    return true;
}

window.addEventListener('load', initCharts);
// Re-init after an in-admin AJAX swap (transparentJS re-dispatches 'load'
// on every SPA navigation - see layout.html.twig's Transparent.ready()
// comment for the same pattern) - a freshly-swapped-in canvas has no
// chart yet, and dataset.chartInitialized guards against double-init on
// canvases that survive the swap.

window.AdminCharts = { update: updateChart };
