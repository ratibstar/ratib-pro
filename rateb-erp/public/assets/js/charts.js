(function () {
    'use strict';

    function chartColors() {
        var style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--rateb-primary').trim() || '#3b82f6',
            accent: style.getPropertyValue('--rateb-accent').trim() || '#2dd4bf',
            muted: style.getPropertyValue('--rateb-text-muted').trim() || '#94a3b8',
            grid: style.getPropertyValue('--rateb-border').trim() || '#2a3a52'
        };
    }

    function chartLabel(el, fallback) {
        return (el && el.dataset && el.dataset.chartLabel) ? el.dataset.chartLabel : fallback;
    }

    function isRtl() {
        return document.documentElement.getAttribute('dir') === 'rtl';
    }

    function baseOptions(colors) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    rtl: isRtl(),
                    labels: { color: colors.muted, font: { family: 'Tajawal, sans-serif' } }
                }
            },
            scales: {
                x: {
                    ticks: { color: colors.muted, font: { family: 'Tajawal, sans-serif' } },
                    grid: { color: colors.grid },
                    reverse: false
                },
                y: {
                    ticks: { color: colors.muted, font: { family: 'Tajawal, sans-serif' } },
                    grid: { color: colors.grid }
                }
            }
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        var colors = chartColors();
        var revenueEl = document.getElementById('chart-revenue');
        if (revenueEl && revenueEl.dataset.labels) {
            new Chart(revenueEl, {
                type: 'line',
                data: {
                    labels: JSON.parse(revenueEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(revenueEl, 'Revenue'),
                        data: JSON.parse(revenueEl.dataset.values),
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(59,130,246,0.12)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: baseOptions(colors)
            });
        }

        var companyEl = document.getElementById('chart-companies');
        if (companyEl && companyEl.dataset.labels) {
            new Chart(companyEl, {
                type: 'bar',
                data: {
                    labels: JSON.parse(companyEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(companyEl, 'Companies'),
                        data: JSON.parse(companyEl.dataset.values),
                        backgroundColor: colors.accent
                    }]
                },
                options: baseOptions(colors)
            });
        }

        var subEl = document.getElementById('chart-subscriptions');
        if (subEl && subEl.dataset.labels) {
            new Chart(subEl, {
                type: 'line',
                data: {
                    labels: JSON.parse(subEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(subEl, 'Subscriptions'),
                        data: JSON.parse(subEl.dataset.values),
                        borderColor: colors.accent,
                        tension: 0.3
                    }]
                },
                options: baseOptions(colors)
            });
        }
    });
})();
