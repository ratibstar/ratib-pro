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
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    }]
                },
                options: Object.assign({}, baseOptions(colors), {
                    plugins: { legend: { display: false } }
                })
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
                        backgroundColor: 'rgba(59,130,246,0.55)',
                        borderRadius: 4,
                        maxBarThickness: 36
                    }]
                },
                options: Object.assign({}, baseOptions(colors), {
                    plugins: { legend: { display: false } }
                })
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
                        backgroundColor: 'rgba(45,212,191,0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    }]
                },
                options: Object.assign({}, baseOptions(colors), {
                    plugins: { legend: { display: false } }
                })
            });
        }

        var acctRevEl = document.getElementById('chart-acct-revenue');
        if (acctRevEl && acctRevEl.dataset.labels) {
            new Chart(acctRevEl, {
                type: 'bar',
                data: {
                    labels: JSON.parse(acctRevEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(acctRevEl, 'Revenue'),
                        data: JSON.parse(acctRevEl.dataset.values),
                        backgroundColor: colors.primary
                    }]
                },
                options: baseOptions(colors)
            });
        }

        var acctExpEl = document.getElementById('chart-acct-expenses');
        if (acctExpEl && acctExpEl.dataset.labels) {
            new Chart(acctExpEl, {
                type: 'line',
                data: {
                    labels: JSON.parse(acctExpEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(acctExpEl, 'Expenses'),
                        data: JSON.parse(acctExpEl.dataset.values),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.12)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: baseOptions(colors)
            });
        }

        var acctArApEl = document.getElementById('chart-acct-arap');
        if (acctArApEl && acctArApEl.dataset.labels) {
            new Chart(acctArApEl, {
                type: 'doughnut',
                data: {
                    labels: JSON.parse(acctArApEl.dataset.labels),
                    datasets: [{
                        data: JSON.parse(acctArApEl.dataset.values),
                        backgroundColor: [colors.primary, colors.accent]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            rtl: isRtl(),
                            position: 'bottom',
                            labels: { color: colors.muted, font: { family: 'Tajawal, sans-serif' } }
                        }
                    }
                }
            });
        }

        var usersEl = document.getElementById('chart-users');
        if (usersEl && usersEl.dataset.labels) {
            new Chart(usersEl, {
                type: 'line',
                data: {
                    labels: JSON.parse(usersEl.dataset.labels),
                    datasets: [{
                        label: chartLabel(usersEl, 'Users'),
                        data: JSON.parse(usersEl.dataset.values),
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(59,130,246,0.06)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    }]
                },
                options: Object.assign({}, baseOptions(colors), {
                    plugins: { legend: { display: false } }
                })
            });
        }

        var statusEl = document.getElementById('chart-company-status');
        if (statusEl && statusEl.dataset.labels) {
            new Chart(statusEl, {
                type: 'doughnut',
                data: {
                    labels: JSON.parse(statusEl.dataset.labels),
                    datasets: [{
                        data: JSON.parse(statusEl.dataset.values),
                        backgroundColor: [colors.primary, colors.accent, '#f59e0b', '#ef4444', '#94a3b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            rtl: isRtl(),
                            position: 'bottom',
                            labels: { color: colors.muted, font: { family: 'Tajawal, sans-serif' } }
                        }
                    }
                }
            });
        }
    });
})();
