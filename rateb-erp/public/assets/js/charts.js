(function () {
    'use strict';

    var charts = {};
    var pendingInits = {};

    function chartColors() {
        var style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--rateb-primary').trim() || '#3b82f6',
            accent: style.getPropertyValue('--rateb-accent').trim() || '#2dd4bf',
            muted: style.getPropertyValue('--rateb-text-muted').trim() || '#94a3b8',
            grid: style.getPropertyValue('--rateb-border').trim() || '#2a3a52',
            surface: style.getPropertyValue('--rateb-surface-elevated').trim() || '#1e293b',
            text: style.getPropertyValue('--rateb-text').trim() || '#f1f5f9'
        };
    }

    function palette(colors) {
        return {
            revenue: '#34d399',
            revenueSoft: 'rgba(52,211,153,0.14)',
            expense: '#f87171',
            expenseSoft: 'rgba(248,113,113,0.14)',
            primary: colors.primary,
            primarySoft: 'rgba(59,130,246,0.14)',
            accent: colors.accent,
            accentSoft: 'rgba(45,212,191,0.14)',
            series: [colors.primary, colors.accent, '#a78bfa', '#fbbf24', '#f87171', '#94a3b8']
        };
    }

    function chartLabel(el, fallback) {
        return (el && el.dataset && el.dataset.chartLabel) ? el.dataset.chartLabel : fallback;
    }

    function isRtl() {
        return document.documentElement.getAttribute('dir') === 'rtl';
    }

    function fontFamily() {
        return 'Tajawal, system-ui, sans-serif';
    }

    function formatTick(value) {
        if (typeof value !== 'number') {
            return value;
        }
        if (value >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M';
        }
        if (value >= 1000) {
            return (value / 1000).toFixed(1) + 'K';
        }
        return value;
    }

    function formatTooltip(value) {
        if (typeof value !== 'number') {
            return value;
        }
        return new Intl.NumberFormat(document.documentElement.lang || 'ar', {
            maximumFractionDigits: 2
        }).format(value);
    }

    function areaGradient(ctx, height, color) {
        var g = ctx.createLinearGradient(0, 0, 0, height || 240);
        g.addColorStop(0, color);
        g.addColorStop(1, 'rgba(0,0,0,0)');
        return g;
    }

    function tooltipOptions(colors) {
        return {
            backgroundColor: 'rgba(15,23,42,0.94)',
            titleColor: colors.text,
            bodyColor: colors.muted,
            borderColor: colors.grid,
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            boxWidth: 8,
            boxHeight: 8,
            boxPadding: 4,
            usePointStyle: true,
            callbacks: {
                label: function (ctx) {
                    var label = ctx.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (ctx.parsed.y !== null && ctx.parsed.y !== undefined) {
                        label += formatTooltip(ctx.parsed.y);
                    } else if (ctx.parsed !== null) {
                        label += formatTooltip(ctx.parsed);
                    }
                    return label;
                }
            }
        };
    }

    function legendOptions(position) {
        return {
            display: true,
            position: position || 'top',
            align: 'end',
            rtl: isRtl(),
            labels: {
                color: chartColors().muted,
                font: { family: fontFamily(), size: 11 },
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 14,
                boxWidth: 6,
                boxHeight: 6
            }
        };
    }

    function scaleX(colors) {
        return {
            grid: { display: false, drawBorder: false },
            ticks: {
                color: colors.muted,
                font: { family: fontFamily(), size: 11 },
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 8
            },
            border: { display: false }
        };
    }

    function scaleY(colors, money) {
        return {
            beginAtZero: true,
            grid: {
                color: 'rgba(148,163,184,0.12)',
                drawBorder: false,
                lineWidth: 1
            },
            ticks: {
                color: colors.muted,
                font: { family: fontFamily(), size: 11 },
                padding: 8,
                callback: money ? formatTick : undefined
            },
            border: { display: false }
        };
    }

    function baseCartesian(colors, opts) {
        opts = opts || {};
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 600, easing: 'easeOutQuart' },
            plugins: {
                legend: opts.legend || { display: false },
                tooltip: tooltipOptions(colors)
            },
            scales: {
                x: scaleX(colors),
                y: scaleY(colors, opts.money)
            }
        };
    }

    function doughnutOptions(colors, legend) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            spacing: 3,
            animation: { animateRotate: true, duration: 700, easing: 'easeOutQuart' },
            plugins: {
                legend: legend || legendOptions('bottom'),
                tooltip: Object.assign({}, tooltipOptions(colors), {
                    callbacks: {
                        label: function (ctx) {
                            var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                            var val = ctx.parsed;
                            var pct = total > 0 ? Math.round((val / total) * 100) : 0;
                            return ctx.label + ': ' + formatTooltip(val) + ' (' + pct + '%)';
                        }
                    }
                })
            }
        };
    }

    function mount(id, instance) {
        if (charts[id]) {
            charts[id].destroy();
        }
        charts[id] = instance;
    }

    function isDeferred(el) {
        var pane = el.closest('.rateb-dash-chart-pane');
        return pane && !pane.classList.contains('is-active');
    }

    function deferInit(el, fn) {
        if (isDeferred(el)) {
            el.setAttribute('data-rateb-defer', '1');
            pendingInits[el.id] = fn;
            return;
        }
        fn();
    }

    function lineDataset(label, data, borderColor, bgColor) {
        return {
            label: label,
            data: data,
            borderColor: borderColor,
            backgroundColor: bgColor,
            fill: true,
            tension: 0.42,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBorderWidth: 2,
            pointBackgroundColor: borderColor,
            pointBorderColor: '#0f172a',
            borderWidth: 2.5
        };
    }

    function initLineChart(el, colors, pal, label, values, colorKey) {
        var border = pal[colorKey] || pal.primary;
        var soft = pal[colorKey + 'Soft'] || pal.primarySoft;
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 240;
        mount(el.id, new Chart(el, {
            type: 'line',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [lineDataset(
                    label,
                    JSON.parse(values),
                    border,
                    areaGradient(ctx, h, soft)
                )]
            },
            options: baseCartesian(colors)
        }));
    }

    function initDualLineChart(el, colors, pal) {
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 260;
        mount(el.id, new Chart(el, {
            type: 'line',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [
                    lineDataset(
                        el.dataset.labelRevenue || 'Revenue',
                        JSON.parse(el.dataset.revenue),
                        pal.revenue,
                        areaGradient(ctx, h, pal.revenueSoft)
                    ),
                    lineDataset(
                        el.dataset.labelExpenses || 'Expenses',
                        JSON.parse(el.dataset.expenses),
                        pal.expense,
                        areaGradient(ctx, h, pal.expenseSoft)
                    )
                ]
            },
            options: Object.assign({}, baseCartesian(colors, { money: true }), {
                plugins: {
                    legend: legendOptions('top'),
                    tooltip: tooltipOptions(colors)
                }
            })
        }));
    }

    function initBarChart(el, colors, pal, label) {
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 240;
        var grad = ctx.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, 'rgba(59,130,246,0.9)');
        grad.addColorStop(1, 'rgba(59,130,246,0.45)');
        mount(el.id, new Chart(el, {
            type: 'bar',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [{
                    label: label,
                    data: JSON.parse(el.dataset.values),
                    backgroundColor: grad,
                    hoverBackgroundColor: pal.primary,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 32
                }]
            },
            options: baseCartesian(colors)
        }));
    }

    function initDoughnut(el, colors, pal, valuesKey) {
        var values = JSON.parse(el.dataset[valuesKey || 'values']);
        mount(el.id, new Chart(el, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [{
                    data: values,
                    backgroundColor: pal.series.slice(0, values.length),
                    borderColor: colors.surface || '#1e293b',
                    borderWidth: 2,
                    hoverBorderColor: colors.surface || '#1e293b',
                    hoverOffset: 6
                }]
            },
            options: doughnutOptions(colors)
        }));
    }

    function initAll() {
        if (typeof Chart === 'undefined') {
            return;
        }

        var colors = chartColors();
        var pal = palette(colors);

        var revenueEl = document.getElementById('chart-revenue');
        if (revenueEl && revenueEl.dataset.labels) {
            deferInit(revenueEl, function () {
                initLineChart(revenueEl, colors, pal, chartLabel(revenueEl, 'Revenue'), revenueEl.dataset.values, 'primary');
            });
        }

        var companyEl = document.getElementById('chart-companies');
        if (companyEl && companyEl.dataset.labels) {
            deferInit(companyEl, function () {
                initLineChart(companyEl, colors, pal, chartLabel(companyEl, 'Companies'), companyEl.dataset.values, 'primary');
            });
        }

        var subEl = document.getElementById('chart-subscriptions');
        if (subEl && subEl.dataset.labels) {
            deferInit(subEl, function () {
                initLineChart(subEl, colors, pal, chartLabel(subEl, 'Subscriptions'), subEl.dataset.values, 'accent');
            });
        }

        var usersEl = document.getElementById('chart-users');
        if (usersEl && usersEl.dataset.labels) {
            deferInit(usersEl, function () {
                initLineChart(usersEl, colors, pal, chartLabel(usersEl, 'Users'), usersEl.dataset.values, 'primary');
            });
        }

        var acctRevEl = document.getElementById('chart-acct-revenue');
        if (acctRevEl && acctRevEl.dataset.labels) {
            initBarChart(acctRevEl, colors, pal, chartLabel(acctRevEl, 'Revenue'));
        }

        var acctExpEl = document.getElementById('chart-acct-expenses');
        if (acctExpEl && acctExpEl.dataset.labels) {
            deferInit(acctExpEl, function () {
                initLineChart(acctExpEl, colors, pal, chartLabel(acctExpEl, 'Expenses'), acctExpEl.dataset.values, 'expense');
            });
        }

        var acctArApEl = document.getElementById('chart-acct-arap');
        if (acctArApEl && acctArApEl.dataset.labels) {
            initDoughnut(acctArApEl, colors, pal);
        }

        var revExpEl = document.getElementById('chart-revenue-expenses');
        if (revExpEl && revExpEl.dataset.labels) {
            initDualLineChart(revExpEl, colors, pal);
        }

        var expBdEl = document.getElementById('chart-expense-breakdown');
        if (expBdEl && expBdEl.dataset.labels) {
            initDoughnut(expBdEl, colors, pal);
        }

        var statusEl = document.getElementById('chart-company-status');
        if (statusEl && statusEl.dataset.labels) {
            initDoughnut(statusEl, colors, pal);
        }
    }

    window.ratebChartResize = function (root) {
        var scope = root || document;
        scope.querySelectorAll('canvas[id^="chart-"]').forEach(function (el) {
            if (charts[el.id]) {
                charts[el.id].resize();
            }
        });
    };

    window.ratebChartInitPane = function (pane) {
        if (!pane) {
            return;
        }
        pane.querySelectorAll('canvas[data-rateb-defer]').forEach(function (el) {
            if (pendingInits[el.id]) {
                pendingInits[el.id]();
                delete pendingInits[el.id];
                el.removeAttribute('data-rateb-defer');
            }
        });
        window.ratebChartResize(pane);
    };

    document.addEventListener('DOMContentLoaded', initAll);
})();
