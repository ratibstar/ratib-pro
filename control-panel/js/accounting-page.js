/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/accounting-page.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/accounting-page.js`.
 */
/**
 * Control Panel – accounting.php page helpers (no inline handlers in PHP).
 */
(function () {
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList || !t.classList.contains('cp-acc-country-select')) return;
        var form = t.closest('form');
        if (form) form.submit();
    });

    function parseJsonScript(id) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) return null;
        try {
            return JSON.parse(el.textContent.trim());
        } catch (e) {
            return null;
        }
    }

    function initRegRevenueCharts() {
        var data = parseJsonScript('cp-acc-reg-revenue-data');
        if (!data || typeof Chart === 'undefined') return;
        var planData = data.plan || [];
        var planDataR = data.planR || [];
        var monthData = data.month || [];
        var el1 = document.getElementById('regRevenueByPlanChart');
        if (el1 && planData.length) {
            new Chart(el1, {
                type: 'doughnut',
                data: {
                    labels: planData.map(function (d) { return d.plan; }),
                    datasets: [{
                        data: planData.map(function (d) { return d.total; }),
                        backgroundColor: ['#667eea', '#10b981', '#f59e0b', '#ec4899', '#6366f1']
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }
        var el2 = document.getElementById('regRevenueByPlanChartRecognized');
        if (el2 && planDataR.length) {
            new Chart(el2, {
                type: 'doughnut',
                data: {
                    labels: planDataR.map(function (d) { return d.plan; }),
                    datasets: [{
                        data: planDataR.map(function (d) { return d.total; }),
                        backgroundColor: ['#0ea5e9', '#22c55e', '#eab308', '#a855f7', '#14b8a6']
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }
        var el3 = document.getElementById('regRevenueByMonthChart');
        if (el3 && monthData.length) {
            new Chart(el3, {
                type: 'line',
                data: {
                    labels: monthData.map(function (d) { return d.label; }),
                    datasets: [
                        {
                            label: 'Collected (SAR)',
                            data: monthData.map(function (d) { return d.collected; }),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.15)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Recognized (SAR)',
                            data: monthData.map(function (d) { return d.recognized; }),
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });
        }
    }

    function getControlApiBase() {
        var cfg = document.getElementById('control-config') || document.getElementById('accountingContent');
        var base = cfg ? String(cfg.getAttribute('data-api-base') || '').trim() : '';
        if (!base) {
            try {
                var p = window.location.pathname || '';
                var ix = p.toLowerCase().indexOf('/control-panel');
                if (ix !== -1) {
                    base = window.location.origin + p.slice(0, ix + '/control-panel'.length) + '/api/control';
                }
            } catch (e) {}
        }
        return base;
    }
    function getControlCsrfToken() {
        var cfg = document.getElementById('accountingContent') || document.getElementById('control-config');
        return cfg ? String(cfg.getAttribute('data-csrf-token') || '').trim() : '';
    }

    function initRegistrationPaidSync() {
        var syncBtn = document.getElementById('btnCpAccSyncRegistrationPaid');
        if (!syncBtn) return;
        var content = document.getElementById('accountingContent');
        var canManage = content && content.getAttribute('data-can-manage') === '1';
        if (!canManage) return;
        syncBtn.addEventListener('click', function () {
            var base = getControlApiBase();
            if (!base) {
                alert('API base not configured.');
                return;
            }
            if (!window.confirm('Create missing receipts and journal drafts for paid registrations (up to 2000 rows)?')) return;
            syncBtn.disabled = true;
            var url = base.replace(/\/$/, '') + '/accounting.php?action=sync_registration_accounting&control=1';
            var headers = { 'Content-Type': 'application/json' };
            var csrf = getControlCsrfToken();
            if (csrf) headers['X-CSRF-Token'] = csrf;
            fetch(url, { method: 'POST', headers: headers, body: '{}', credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success && res.result) {
                        alert('Done. Receipts created: ' + res.result.receipts_created + ', Journal entries: ' + res.result.journals_created + ' (scanned ' + res.result.processed + ' paid rows). Reload the page to refresh Receipts / Ledger / Approvals modals.');
                    } else {
                        alert((res && res.message) ? res.message : 'Sync failed.');
                    }
                })
                .catch(function () { alert('Request failed.'); })
                .finally(function () { syncBtn.disabled = false; });
        });
    }

    function boot() {
        initRegRevenueCharts();
        initRegistrationPaidSync();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
