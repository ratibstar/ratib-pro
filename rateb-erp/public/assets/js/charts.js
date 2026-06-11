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
                        label: 'Revenue',
                        data: JSON.parse(revenueEl.dataset.values),
                        borderColor: colors.primary,
                        backgroundColor: 'rgba(13,110,253,0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: colors.muted }, grid: { color: colors.grid } },
                        y: { ticks: { color: colors.muted }, grid: { color: colors.grid } }
                    }
                }
            });
        }

        var companyEl = document.getElementById('chart-companies');
        if (companyEl && companyEl.dataset.labels) {
            new Chart(companyEl, {
                type: 'bar',
                data: {
                    labels: JSON.parse(companyEl.dataset.labels),
                    datasets: [{
                        label: 'Companies',
                        data: JSON.parse(companyEl.dataset.values),
                        backgroundColor: colors.accent
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: colors.muted }, grid: { color: colors.grid } },
                        y: { ticks: { color: colors.muted }, grid: { color: colors.grid } }
                    }
                }
            });
        }

        var subEl = document.getElementById('chart-subscriptions');
        if (subEl && subEl.dataset.labels) {
            new Chart(subEl, {
                type: 'line',
                data: {
                    labels: JSON.parse(subEl.dataset.labels),
                    datasets: [{
                        label: 'Subscriptions',
                        data: JSON.parse(subEl.dataset.values),
                        borderColor: colors.accent,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: colors.muted }, grid: { color: colors.grid } },
                        y: { ticks: { color: colors.muted }, grid: { color: colors.grid } }
                    }
                }
            });
        }
    });
})();
