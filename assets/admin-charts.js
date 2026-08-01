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
import annotationPlugin from 'chartjs-plugin-annotation';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, annotationPlugin);

// Translates config.events (see Base\Admin\Widget\TimelineEventRegistry)
// into chartjs-plugin-annotation's config shape, anchored to its date via
// CategoryScale's own x-axis value (the registry already guarantees every
// event's formatted date matches one of the chart's own labels, so
// there's nothing to validate here). Two shapes:
//
// - event.url present: a small triangle sitting on the x-axis (yValue:0,
//   beginAtZero:true on the y-scale always puts that at the visual
//   bottom) rather than a full-height line - a marker meant to be
//   clicked, not a divider meant to be read at a glance. click opens the
//   url directly (window.open, not a same-tab navigation - a chart
//   click shouldn't blow away whatever the admin was doing on this
//   page); enter/leave swap the canvas cursor AND its title attribute,
//   the plain browser-native tooltip standing in for "tell me the
//   article name" on hover, since chartjs-plugin-annotation's own point
//   annotations don't paint permanent text labels the way a line
//   annotation's `label` option does.
// - no url: the original dashed vertical line + permanent label, for any
//   OTHER kind of event a provider might report (a deploy, a campaign
//   launch, ...) that has nowhere obvious to link to.
function buildAnnotations(events) {
    var annotations = {};
    (events || []).forEach(function (event, i) {
        if (event.url) {
            annotations['event' + i] = {
                type: 'point',
                xScaleID: 'x',
                xValue: event.label,
                yScaleID: 'y',
                yValue: 0,
                pointStyle: 'triangle',
                radius: 6,
                backgroundColor: event.color || '#7c3aed',
                borderColor: event.color || '#7c3aed',
                borderWidth: 1,
                click: function (context) {
                    window.open(event.url, '_blank', 'noopener');
                },
                enter: function (context) {
                    context.chart.canvas.style.cursor = 'pointer';
                    context.chart.canvas.title = event.title;
                },
                leave: function (context) {
                    context.chart.canvas.style.cursor = '';
                    context.chart.canvas.title = '';
                },
            };
            return;
        }

        annotations['event' + i] = {
            type: 'line',
            scaleID: 'x',
            value: event.label,
            borderColor: event.color || '#7c3aed',
            borderWidth: 2,
            borderDash: [4, 4],
            label: {
                display: true,
                content: event.title,
                position: 'start',
                backgroundColor: event.color || '#7c3aed',
            },
        };
    });
    return annotations;
}

function isDarkMode() {
    return document.documentElement.getAttribute('data-theme') === 'dark'
        || (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
}

// Generic dashboard chart initializer: any element carrying
// data-admin-chart="{...}" (a JSON blob: {labels: [...], datasets: [{label,
// data, color, colorDark}, ...]}) gets a Chart.js line chart rendered into
// it. One shared function rather than a chart-specific script, so a future
// dashboard widget (revenue, order volume, whatever a client wants) only
// needs to render a <canvas data-admin-chart="..."> - no new JS.
//
// colorDark is optional (falls back to color) - every dataset this app
// currently emits provides both (see dataviz's palette validation: a
// categorical hex validated against a LIGHT surface isn't automatically
// safe against a DARK one, so a straight reuse would have been unvalidated
// there even though it looks fine here in light mode).
function chartDatasets(config, isDark) {
    // A line needs at least 2 points to draw a segment - a single-day
    // range (e.g. "today") has exactly one, so with the usual
    // pointRadius:0 the chart renders nothing at all: no line to connect,
    // no dot to show for the lone point. Bumping the radius only in that
    // case draws a visible dot instead, without changing how any
    // multi-point range looks.
    var pointRadius = (config.labels || []).length <= 1 ? 4 : 0;

    return (config.datasets || []).map(function (ds) {
        var color = (isDark && ds.colorDark) ? ds.colorDark : ds.color;
        return {
            // Not a Chart.js option - carried through so a consumer (e.g.
            // the analytics widget's range picker) can read back WHICH
            // series are currently plotted from the live chart instance's
            // own data.datasets, without re-parsing the canvas's original
            // (and, after any update(), stale) data-admin-chart JSON.
            // Chart.js itself ignores unknown dataset properties.
            key: ds.key,
            label: ds.label,
            data: ds.data,
            borderColor: color,
            backgroundColor: color + '22',
            fill: true,
            tension: 0.35,
            pointRadius: pointRadius,
            pointHoverRadius: 4,
            borderWidth: 2,
            // Chart.js's own native per-dataset visibility - reading this
            // back in (rather than only ever OMITTING a hidden series from
            // config.datasets entirely) is what lets the legend re-show a
            // series that started hidden: an omitted dataset never has a
            // legend entry to click on in the first place.
            hidden: !!ds.hidden,
        };
    });
}

// Chart.js's own default legend click already toggles a dataset's
// visibility for free - this only ADDS a DOM event on top of that (a
// custom event on the canvas, not a Chart.js callback other scripts on
// the page can't reach), so a consumer like the analytics widget's own
// script (layout.html.twig, not this generic file - see the comment on
// chartDatasets()'s `key` field for why widget-specific logic stays out
// of here) can persist the new hidden/shown state wherever it needs to,
// without admin-charts.js knowing anything about what "persist" means
// for any particular widget.
function legendOnClick(e, legendItem, legend) {
    Chart.defaults.plugins.legend.onClick(e, legendItem, legend);
    legend.chart.canvas.dispatchEvent(new CustomEvent('admin-chart:legend-toggle', { bubbles: true }));
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

        var isDark = isDarkMode();
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
                datasets: chartDatasets(config, isDark),
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
                    legend: { display: (config.datasets || []).length > 1, labels: { color: textColor, boxWidth: 12 }, onClick: legendOnClick },
                    tooltip: { mode: 'index', intersect: false },
                    annotation: { annotations: buildAnnotations(config.events) },
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
    chart.data.datasets = chartDatasets(config, isDarkMode());
    chart.options.plugins.annotation.annotations = buildAnnotations(config.events);
    chart.update();
    return true;
}

window.addEventListener('load', initCharts);
// Re-init after an in-admin AJAX swap (transparentJS re-dispatches 'load'
// on every SPA navigation - see layout.html.twig's Transparent.ready()
// comment for the same pattern) - a freshly-swapped-in canvas has no
// chart yet, and dataset.chartInitialized guards against double-init on
// canvases that survive the swap.

// init() also covers a narrower case that isn't a full SPA swap: a
// single new canvas inserted via plain DOM manipulation (the dashboard
// widget palette's insertAdjacentHTML(), not a transparentJS navigation)
// never gets the synthetic 'load' event either path above relies on -
// found live, a palette-added analytics_card's canvas never initialized
// until this was exposed for the palette's own JS to call directly.
window.AdminCharts = { update: updateChart, init: initCharts };
