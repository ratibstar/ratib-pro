(function () {
    'use strict';

    var charts = {};
    var pendingInits = {};

    function chartColors() {
        var style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--rateb-primary').trim() || '#10b981',
            accent: style.getPropertyValue('--rateb-accent').trim() || '#38bdf8',
            muted: style.getPropertyValue('--rateb-text-muted').trim() || '#6b7a8f',
            grid: style.getPropertyValue('--rateb-border').trim() || '#252f3d',
            surface: style.getPropertyValue('--rateb-surface-elevated').trim() || '#0f1419',
            text: style.getPropertyValue('--rateb-text').trim() || '#e8edf4'
        };
    }

    function palette(colors) {
        return {
            revenue: '#34d399',
            revenueSoft: 'rgba(52,211,153,0.08)',
            expense: '#fb7185',
            expenseSoft: 'rgba(251,113,133,0.08)',
            primary: colors.primary,
            primarySoft: 'rgba(16,185,129,0.1)',
            accent: colors.accent,
            accentSoft: 'rgba(56,189,248,0.1)',
            series: [
                'rgba(16,185,129,0.85)',
                'rgba(56,189,248,0.85)',
                'rgba(167,139,250,0.85)',
                'rgba(251,191,36,0.85)',
                'rgba(251,113,133,0.85)',
                'rgba(107,122,143,0.75)'
            ],
            seriesHover: [
                '#10b981',
                '#38bdf8',
                '#a78bfa',
                '#fbbf24',
                '#fb7185',
                '#94a3b8'
            ]
        };
    }

    function chartLabel(el, fallback) {
        return (el && el.dataset && el.dataset.chartLabel) ? el.dataset.chartLabel : fallback;
    }

    function isRtl() {
        return document.documentElement.getAttribute('dir') === 'rtl';
    }

    function fontFamily() {
        return 'system-ui, -apple-system, "Segoe UI", sans-serif';
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

    function parseNums(raw) {
        try {
            return JSON.parse(raw || '[]').map(function (v) {
                return Number(v) || 0;
            });
        } catch (e) {
            return [];
        }
    }

    function hasLabels(el) {
        try {
            var labels = JSON.parse(el.dataset.labels || '[]');
            return Array.isArray(labels) && labels.length > 0;
        } catch (e) {
            return false;
        }
    }

    function hasMultiDataset(el) {
        return hasLabels(el) || el.dataset.revenue || el.dataset.success;
    }

    function dataMax(values) {
        var max = 0;
        values.forEach(function (v) {
            if (v > max) {
                max = v;
            }
        });
        return max;
    }

    function suggestedCeil(values) {
        var max = dataMax(values);
        if (max <= 0) {
            return 5;
        }
        return Math.ceil(max * 1.15);
    }

    function lightAnim(stagger) {
        return {
            duration: 1100,
            easing: 'easeOutQuart',
            delay: function (ctx) {
                if (!stagger || ctx.type !== 'data' || ctx.mode !== 'default') {
                    return 0;
                }
                return ctx.dataIndex * 55 + ctx.datasetIndex * 90;
            }
        };
    }

    function areaGradient(ctx, height, color) {
        var g = ctx.createLinearGradient(0, 0, 0, height || 200);
        g.addColorStop(0, color);
        g.addColorStop(1, 'rgba(0,0,0,0)');
        return g;
    }

    function tooltipOptions(colors) {
        return {
            backgroundColor: 'rgba(8,11,18,0.92)',
            titleColor: colors.text,
            bodyColor: colors.muted,
            borderColor: 'rgba(148,163,184,0.2)',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 8,
            titleFont: { family: fontFamily(), size: 14, weight: '600' },
            bodyFont: { family: fontFamily(), size: 13 },
            displayColors: true,
            boxWidth: 6,
            boxHeight: 6,
            boxPadding: 3,
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
                font: { family: fontFamily(), size: 14, weight: '500' },
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 12,
                boxWidth: 5,
                boxHeight: 5
            }
        };
    }

    function scaleX(colors) {
        return {
            grid: { display: false, drawBorder: false },
            ticks: {
                color: colors.muted,
                font: { family: fontFamily(), size: 13 },
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 7
            },
            border: { display: false }
        };
    }

    function scaleY(colors, money, values) {
        var ceil = suggestedCeil(values || []);
        return {
            beginAtZero: true,
            suggestedMax: ceil,
            grid: {
                color: 'rgba(148,163,184,0.06)',
                drawBorder: false,
                lineWidth: 1
            },
            ticks: {
                color: colors.muted,
                font: { family: fontFamily(), size: 13 },
                padding: 6,
                maxTicksLimit: 5,
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
            animation: lightAnim(true),
            plugins: {
                legend: opts.legend || { display: false },
                tooltip: tooltipOptions(colors)
            },
            scales: {
                x: scaleX(colors),
                y: scaleY(colors, opts.money, opts.values)
            }
        };
    }

    function doughnutOptions(colors, legend) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            spacing: 2,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 900,
                easing: 'easeOutQuart',
                delay: function (ctx) {
                    return ctx.dataIndex * 70;
                }
            },
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
        var selectors = ['.cm-pane', '.nx-tab-pane', '.rp-chart-pane', '.rdx-chart-pane', '.rateb-dash-chart-pane'];
        for (var i = 0; i < selectors.length; i++) {
            var pane = el.closest(selectors[i]);
            if (pane && !pane.classList.contains('is-active')) {
                return true;
            }
        }
        return false;
    }

    function deferInit(el, fn) {
        if (isDeferred(el)) {
            el.setAttribute('data-rateb-defer', '1');
            pendingInits[el.id] = fn;
            return;
        }
        fn();
    }

    function lineDataset(label, data, borderColor, bgColor, sparse) {
        return {
            label: label,
            data: data,
            borderColor: borderColor,
            backgroundColor: bgColor,
            fill: true,
            tension: 0.38,
            pointRadius: sparse ? 4 : 0,
            pointHoverRadius: 6,
            pointHoverBorderWidth: 2,
            pointBackgroundColor: borderColor,
            pointBorderColor: '#080b12',
            pointBorderWidth: 1.5,
            borderWidth: 1.75
        };
    }

    function initLineChart(el, colors, pal, label, values, colorKey) {
        var border = pal[colorKey] || pal.primary;
        var soft = pal[colorKey + 'Soft'] || pal.primarySoft;
        var data = parseNums(values);
        var sparse = data.length <= 14;
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 200;
        mount(el.id, new Chart(el, {
            type: 'line',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [lineDataset(label, data, border, areaGradient(ctx, h, soft), sparse)]
            },
            options: baseCartesian(colors, { values: data })
        }));
    }

    function initDualLineChart(el, colors, pal) {
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 220;
        var rev = parseNums(el.dataset.revenue);
        var exp = parseNums(el.dataset.expenses);
        var all = rev.concat(exp);
        var sparse = (JSON.parse(el.dataset.labels || '[]')).length <= 14;
        mount(el.id, new Chart(el, {
            type: 'line',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [
                    lineDataset(
                        el.dataset.labelRevenue || 'Revenue',
                        rev,
                        pal.revenue,
                        areaGradient(ctx, h, pal.revenueSoft),
                        sparse
                    ),
                    lineDataset(
                        el.dataset.labelExpenses || 'Expenses',
                        exp,
                        pal.expense,
                        areaGradient(ctx, h, pal.expenseSoft),
                        sparse
                    )
                ]
            },
            options: Object.assign({}, baseCartesian(colors, { money: true, values: all }), {
                plugins: {
                    legend: legendOptions('top'),
                    tooltip: tooltipOptions(colors)
                }
            })
        }));
    }

    function initBarChart(el, colors, pal, label) {
        var data = parseNums(el.dataset.values);
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 200;
        mount(el.id, new Chart(el, {
            type: 'bar',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: data.map(function (_, i) {
                        return pal.series[i % pal.series.length];
                    }),
                    hoverBackgroundColor: data.map(function (_, i) {
                        return pal.seriesHover[i % pal.seriesHover.length];
                    }),
                    borderRadius: 5,
                    borderSkipped: false,
                    maxBarThickness: 28,
                    borderWidth: 0
                }]
            },
            options: Object.assign({}, baseCartesian(colors, { values: data }), {
                animation: lightAnim(true)
            })
        }));
    }

    function initHorizontalBarChart(el, colors, pal, label) {
        var data = parseNums(el.dataset.values);
        mount(el.id, new Chart(el, {
            type: 'bar',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: pal.series[0],
                    hoverBackgroundColor: pal.seriesHover[0],
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 18,
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: lightAnim(true),
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipOptions(colors)
                },
                scales: {
                    x: scaleY(colors, false, data),
                    y: scaleX(colors)
                }
            }
        }));
    }

    function initMultiLineChart(el, colors, pal) {
        var ctx = el.getContext('2d');
        var h = el.parentElement ? el.parentElement.offsetHeight : 280;
        var d1 = parseNums(el.dataset.dataset1);
        var d2 = parseNums(el.dataset.dataset2);
        var d3 = parseNums(el.dataset.dataset3);
        var all = d1.concat(d2).concat(d3);
        var series = [
            { data: d1, label: el.dataset.label1 || 'A', color: pal.primary, soft: pal.primarySoft },
            { data: d2, label: el.dataset.label2 || 'B', color: pal.accent, soft: pal.accentSoft },
            { data: d3, label: el.dataset.label3 || 'C', color: '#a78bfa', soft: 'rgba(167,139,250,0.1)' }
        ];
        mount(el.id, new Chart(el, {
            type: 'line',
            data: {
                labels: JSON.parse(el.dataset.labels || '[]'),
                datasets: series.map(function (s) {
                    return lineDataset(s.label, s.data, s.color, areaGradient(ctx, h, s.soft), true);
                })
            },
            options: Object.assign({}, baseCartesian(colors, { values: all }), {
                plugins: {
                    legend: legendOptions('top'),
                    tooltip: tooltipOptions(colors)
                }
            })
        }));
    }

    function initStackedBarChart(el, colors, pal) {
        var success = parseNums(el.dataset.success);
        var failed = parseNums(el.dataset.failed);
        var all = success.concat(failed);
        mount(el.id, new Chart(el, {
            type: 'bar',
            data: {
                labels: JSON.parse(el.dataset.labels || '[]'),
                datasets: [
                    {
                        label: el.dataset.labelSuccess || 'Success',
                        data: success,
                        backgroundColor: 'rgba(16,185,129,0.75)',
                        hoverBackgroundColor: '#10b981',
                        borderRadius: 4,
                        borderSkipped: false,
                        maxBarThickness: 22
                    },
                    {
                        label: el.dataset.labelFailed || 'Failed',
                        data: failed,
                        backgroundColor: 'rgba(251,113,133,0.75)',
                        hoverBackgroundColor: '#fb7185',
                        borderRadius: 4,
                        borderSkipped: false,
                        maxBarThickness: 22
                    }
                ]
            },
            options: Object.assign({}, baseCartesian(colors, { values: all }), {
                plugins: {
                    legend: legendOptions('top'),
                    tooltip: tooltipOptions(colors)
                },
                scales: {
                    x: Object.assign({}, scaleX(colors), { stacked: true }),
                    y: Object.assign({}, scaleY(colors, false, all), { stacked: true })
                }
            })
        }));
    }

    function initDoughnut(el, colors, pal, valuesKey) {
        var values = parseNums(el.dataset[valuesKey || 'values']);
        mount(el.id, new Chart(el, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(el.dataset.labels),
                datasets: [{
                    data: values,
                    backgroundColor: pal.series.slice(0, values.length),
                    hoverBackgroundColor: pal.seriesHover.slice(0, values.length),
                    borderWidth: 0,
                    hoverBorderWidth: 0,
                    hoverOffset: 10
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
        if (companyEl && hasLabels(companyEl)) {
            deferInit(companyEl, function () {
                initBarChart(companyEl, colors, pal, chartLabel(companyEl, 'Companies'));
            });
        }

        var subEl = document.getElementById('chart-subscriptions');
        if (subEl && subEl.dataset.labels) {
            deferInit(subEl, function () {
                initBarChart(subEl, colors, pal, chartLabel(subEl, 'Subscriptions'));
            });
        }

        var usersEl = document.getElementById('chart-users');
        if (usersEl && usersEl.dataset.labels) {
            deferInit(usersEl, function () {
                initBarChart(usersEl, colors, pal, chartLabel(usersEl, 'Users'));
            });
        }

        var acctRevEl = document.getElementById('chart-acct-revenue');
        if (acctRevEl && acctRevEl.dataset.labels) {
            initBarChart(acctRevEl, colors, pal, chartLabel(acctRevEl, 'Revenue'));
        }

        var acctExpEl = document.getElementById('chart-acct-expenses');
        if (acctExpEl && acctExpEl.dataset.labels) {
            initBarChart(acctExpEl, colors, pal, chartLabel(acctExpEl, 'Expenses'));
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

        var overviewEl = document.getElementById('chart-platform-overview');
        if (overviewEl && overviewEl.dataset.labels) {
            initMultiLineChart(overviewEl, colors, pal);
        }

        var planEl = document.getElementById('chart-plan-distribution');
        if (planEl && planEl.dataset.labels) {
            initBarChart(planEl, colors, pal, chartLabel(planEl, 'Plans'));
        }

        var subStatEl = document.getElementById('chart-subscription-status');
        if (subStatEl && subStatEl.dataset.labels) {
            initDoughnut(subStatEl, colors, pal);
        }

        var loginEl = document.getElementById('chart-login-activity');
        if (loginEl && loginEl.dataset.labels) {
            initStackedBarChart(loginEl, colors, pal);
        }

        var topCoEl = document.getElementById('chart-top-companies');
        if (topCoEl && topCoEl.dataset.labels) {
            initHorizontalBarChart(topCoEl, colors, pal, chartLabel(topCoEl, 'Users'));
        }

        var topCustEl = document.getElementById('chart-top-customers');
        if (topCustEl && topCustEl.dataset.labels) {
            initHorizontalBarChart(topCustEl, colors, pal, chartLabel(topCustEl, 'Total'));
        }

        var topItemsEl = document.getElementById('chart-top-items');
        if (topItemsEl && topItemsEl.dataset.labels) {
            initHorizontalBarChart(topItemsEl, colors, pal, chartLabel(topItemsEl, 'Total'));
        }

        var journalEl = document.getElementById('chart-journal-activity');
        if (journalEl && journalEl.dataset.labels) {
            initBarChart(journalEl, colors, pal, chartLabel(journalEl, 'Entries'));
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
