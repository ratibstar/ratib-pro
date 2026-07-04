(function () {
    'use strict';

    var root = document.getElementById('acc-control-app');
    if (!root) return;

    var i18nEl = document.getElementById('acc-control-i18n');
    var I18N = {};
    try {
        I18N = i18nEl ? JSON.parse(i18nEl.textContent || '{}') : {};
    } catch (e) {
        I18N = {};
    }

    var locale = I18N.locale || root.dataset.lang || 'en';
    var isAr = locale === 'ar' || String(locale).indexOf('ar') === 0;
    var numLocale = isAr ? 'ar-SA' : 'en-US';
    var dateLocale = isAr ? 'ar-SA-u-ca-gregory' : 'en-US';

    var apiBase = root.dataset.apiBase || '';
    var csrf = root.dataset.csrf || '';
    var section = root.dataset.section || 'dashboard';
    var charts = {};
    var EASTERN = ['\u0660', '\u0661', '\u0662', '\u0663', '\u0664', '\u0665', '\u0666', '\u0667', '\u0668', '\u0669'];
    var PERSIAN = ['\u06F0', '\u06F1', '\u06F2', '\u06F3', '\u06F4', '\u06F5', '\u06F6', '\u06F7', '\u06F8', '\u06F9'];

    function toWestern(s) {
        var str = String(s || '');
        for (var i = 0; i < 10; i++) {
            str = str.split(EASTERN[i]).join(String(i));
            str = str.split(PERSIAN[i]).join(String(i));
        }
        return str;
    }

    function toEastern(s) {
        return String(s || '').replace(/\d/g, function (ch) {
            return EASTERN[parseInt(ch, 10)] || ch;
        });
    }

    function syncNumInput(el) {
        if (!el) return;
        var w = toWestern(el.value).replace(/\D+/g, '');
        el.value = w ? (isAr ? toEastern(w) : w) : '';
    }

    function initLocaleInputs() {
        var inputLang = isAr ? 'ar-SA' : 'en-US';
        root.querySelectorAll('.acc-locale-num').forEach(function (el) {
            el.setAttribute('lang', inputLang);
            el.setAttribute('dir', 'ltr');
            el.setAttribute('translate', 'no');
            el.classList.toggle('rateb-ltr-num', !isAr);
            syncNumInput(el);
        });
        root.querySelectorAll('.acc-locale-date').forEach(function (el) {
            el.setAttribute('lang', inputLang);
            el.setAttribute('dir', 'ltr');
            el.setAttribute('translate', 'no');
            el.classList.toggle('rateb-ltr-date', !isAr);
        });
    }

    function bindLocaleInputs() {
        root.querySelectorAll('.acc-locale-num').forEach(function (el) {
            if (el.getAttribute('data-acc-num-bound') === '1') return;
            el.setAttribute('data-acc-num-bound', '1');
            el.addEventListener('input', function () {
                var w = toWestern(el.value).replace(/\D+/g, '');
                if (isAr && w !== toWestern(el.value)) {
                    el.value = toEastern(w);
                } else if (!isAr) {
                    el.value = w;
                }
            });
            el.addEventListener('blur', function () { syncNumInput(el); });
            el.addEventListener('change', function () { syncNumInput(el); });
        });
    }

    function t(path, fallback, vars) {
        var parts = String(path).split('.');
        var cur = I18N;
        for (var i = 0; i < parts.length; i++) {
            if (!cur || typeof cur !== 'object') {
                cur = null;
                break;
            }
            cur = cur[parts[i]];
        }
        var out = cur != null && cur !== '' ? String(cur) : (fallback != null ? String(fallback) : path);
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                out = out.replace(':' + k, vars[k]);
            });
        }
        return out;
    }

    function tCard(key) {
        return (I18N.cards && I18N.cards[key]) ? I18N.cards[key] : key.replace(/_/g, ' ');
    }

    function fmtNum(n) {
        if (n == null || n === '' || isNaN(n)) return t('nullValue', '—');
        try {
            return Number(n).toLocaleString(numLocale);
        } catch (e) {
            return String(n);
        }
    }

    function fmtDate(val) {
        if (!val) return '';
        var s = String(val);
        var d = new Date(s.length === 10 ? s + 'T00:00:00' : s);
        if (isNaN(d.getTime())) return s;
        try {
            return d.toLocaleDateString(dateLocale, { year: 'numeric', month: '2-digit', day: '2-digit' });
        } catch (e) {
            return s;
        }
    }

    function fmtDateTime(val) {
        if (!val) return '';
        var d = new Date(String(val));
        if (isNaN(d.getTime())) return String(val);
        try {
            return d.toLocaleString(dateLocale, {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return String(val);
        }
    }

    function fmtLabel(val) {
        if (val == null || val === '') return t('nullValue', '—');
        var s = String(val);
        if (/^\d{4}-\d{2}(-\d{2})?$/.test(s)) {
            var parts = s.split('-');
            if (parts.length === 2) {
                return fmtDate(parts[0] + '-' + parts[1] + '-01').replace(/\d{2}$/, '') || s;
            }
            return fmtDate(s);
        }
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) return fmtDate(s);
        if (/^-?\d+(\.\d+)?$/.test(s)) return fmtNum(Number(s));
        return s;
    }

    function fmtStatus(val) {
        if (val == null || val === '' || String(val).toLowerCase() === 'null') {
            return t('nullValue', '—');
        }
        var key = String(val).toLowerCase();
        return (I18N.status && I18N.status[key]) ? I18N.status[key] : String(val);
    }

    function fmtSeverity(val) {
        var key = String(val || 'low').toLowerCase();
        return (I18N.severity && I18N.severity[key]) ? I18N.severity[key] : String(val || '');
    }

    function fmtPeriod(from, to) {
        return t('periodRange', ':from → :to', {
            from: fmtDate(from) || '—',
            to: fmtDate(to) || '—'
        });
    }

    function fmtValue(val) {
        if (val == null || val === '' || String(val).toLowerCase() === 'null') {
            return t('nullValue', '—');
        }
        if (typeof val === 'number') return fmtNum(val);
        var s = String(val);
        if (I18N.status && I18N.status[s.toLowerCase()]) return I18N.status[s.toLowerCase()];
        if (/^-?\d+$/.test(s)) return fmtNum(Number(s));
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) return fmtDateTime(s);
        return s;
    }

    function chartBaseOpts(showLegend) {
        return {
            responsive: true,
            plugins: { legend: { display: !!showLegend } },
            scales: {
                y: {
                    ticks: {
                        callback: function (v) { return fmtNum(v); }
                    }
                }
            }
        };
    }

    function filters() {
        return {
            company_id: val('.acc-filter-company', true) || root.dataset.companyId || '',
            branch_id: val('.acc-filter-branch', true) || '',
            period_from: val('.acc-filter-from') || '',
            period_to: val('.acc-filter-to') || '',
            from_date: val('.acc-filter-from') || '',
            to_date: val('.acc-filter-to') || '',
            event_uuid: val('.acc-filter-uuid') || '',
            status: val('.acc-filter-status') || '',
            source_system: val('.acc-filter-system') || '',
            action: val('.acc-filter-action') || '',
            severity: val('.acc-filter-severity') || '',
            page: 1,
            per_page: 50
        };
    }

    function val(sel, numeric) {
        var el = root.querySelector(sel);
        if (!el) return '';
        var v = String(el.value).trim();
        if (numeric) {
            return toWestern(v).replace(/\D+/g, '');
        }
        return v;
    }

    function alertMsg(msg, type) {
        var box = document.getElementById('acc-alert');
        if (!box) return;
        box.className = 'alert alert-' + (type || 'info');
        box.textContent = msg;
        box.classList.remove('d-none');
    }

    function api(resource, opts) {
        opts = opts || {};
        var base = String(apiBase || '').replace(/\/+$/, '');
        var qIdx = base.indexOf('?');
        var basePath = qIdx >= 0 ? base.slice(0, qIdx) : base;
        var preset = qIdx >= 0 ? base.slice(qIdx + 1) : '';
        var extra = buildQuery(opts.query || {}).replace(/^\?/, '');
        var query = [preset, extra].filter(Boolean).join('&');
        var url = basePath + '/' + encodeURIComponent(resource) + (query ? '?' + query : '');
        var init = {
            method: opts.method || 'GET',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
            credentials: 'same-origin'
        };
        if (opts.body) {
            init.method = opts.method || 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(Object.assign({ _csrf: csrf }, opts.body));
        }
        return fetch(url, init).then(function (r) { return r.json(); });
    }

    function buildQuery(q) {
        var parts = [];
        Object.keys(q).forEach(function (k) {
            if (q[k] !== '' && q[k] != null) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(q[k]));
        });
        return parts.length ? '?' + parts.join('&') : '';
    }

    function showJson(data) {
        var pre = document.querySelector('#accJsonModal .acc-json-viewer');
        if (pre) pre.textContent = JSON.stringify(data, null, 2);
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(document.getElementById('accJsonModal')).show();
    }

    function confirmAction(message, onOk) {
        var modal = document.getElementById('accConfirmModal');
        var msg = modal && modal.querySelector('.acc-confirm-message');
        var btn = modal && modal.querySelector('.acc-confirm-proceed');
        if (!modal || !btn) { if (window.confirm(message)) onOk(); return; }
        if (msg) msg.textContent = message;
        var inst = bootstrap.Modal.getOrCreateInstance(modal);
        var handler = function () {
            btn.removeEventListener('click', handler);
            inst.hide();
            onOk();
        };
        btn.addEventListener('click', handler);
        inst.show();
    }

    function loadSection() {
        switch (section) {
            case 'dashboard': loadDashboard(); break;
            case 'events': loadEvents(); break;
            case 'replay': loadReplayHistory(); break;
            case 'audit': loadAudit(); break;
            case 'projections': break;
            case 'consolidation': break;
            case 'drift': loadDriftReports(); break;
            case 'reconciliation': loadReconciliation(); break;
            case 'integrity': loadIntegrity(); break;
            case 'settings': loadSettings(); break;
            case 'health': loadHealth(); break;
        }
    }

    function loadDashboard() {
        api('dashboard', { query: filters() }).then(function (res) {
            if (!res.ok) { alertMsg(res.message || t('msg.error', 'Error'), 'danger'); return; }
            renderDashboardCards(res.data.cards || {});
            renderCharts(res.data.charts || {});
        });
    }

    function renderDashboardCards(cards) {
        var wrap = root.querySelector('.acc-cards');
        if (!wrap) return;
        var keys = Object.keys(cards);
        wrap.innerHTML = keys.map(function (k) {
            return '<div class="col-6 col-md-4 col-lg-3"><div class="acc-card"><div class="acc-card-value">' +
                escapeHtml(fmtValue(cards[k])) + '</div><div class="acc-card-label">' + escapeHtml(tCard(k)) + '</div></div></div>';
        }).join('');
    }

    function renderCharts(ch) {
        barChart('acc-chart-daily-events',
            (ch.daily_events || []).map(function (x) { return fmtLabel(x.date); }),
            (ch.daily_events || []).map(function (x) { return x.count; }),
            t('charts.daily_events', 'Daily events'));
        barChart('acc-chart-monthly-posting',
            (ch.monthly_posting || []).map(function (x) { return fmtLabel(x.month); }),
            (ch.monthly_posting || []).map(function (x) { return x.count; }),
            t('charts.monthly_posting', 'Monthly posting'));
        doughnutChart('acc-chart-replay-rate',
            [t('charts.replay_success', 'Success'), t('charts.replay_failed', 'Failed')],
            [ch.replay_success_rate && ch.replay_success_rate.processed || 0, ch.replay_success_rate && ch.replay_success_rate.failed || 0]);
        lineChart('acc-chart-drift-trend',
            (ch.drift_trend || []).map(function (x) { return fmtLabel(x.date); }),
            (ch.drift_trend || []).map(function (x) { return x.count; }),
            t('charts.drift_trend', 'Drift trend'));
        barChart('acc-chart-company-activity',
            (ch.company_activity || []).map(function (x) { return fmtLabel(x.key) || t('nullValue', '—'); }),
            (ch.company_activity || []).map(function (x) { return x.count; }),
            t('charts.companies', 'Companies'));
        barChart('acc-chart-branch-activity',
            (ch.branch_activity || []).map(function (x) { return fmtLabel(x.key) || t('nullValue', '—'); }),
            (ch.branch_activity || []).map(function (x) { return x.count; }),
            t('charts.branches', 'Branches'));
    }

    function barChart(id, labels, data, label) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        var opts = chartBaseOpts(!!label);
        charts[id] = new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: label || '', data: data, backgroundColor: '#3b82f6' }] },
            options: opts
        });
    }

    function lineChart(id, labels, data, label) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        var opts = chartBaseOpts(false);
        if (label) opts.plugins.legend.display = true;
        charts[id] = new Chart(el, {
            type: 'line',
            data: { labels: labels, datasets: [{ label: label || '', data: data, borderColor: '#f59e0b', fill: false }] },
            options: opts
        });
    }

    function doughnutChart(id, labels, data) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#22c55e', '#ef4444'] }] },
            options: { responsive: true, plugins: { legend: { display: true } } }
        });
    }

    function loadEvents(page) {
        var q = filters();
        q.page = page || 1;
        api('events', { query: q }).then(function (res) {
            if (!res.ok) { alertMsg(res.message || t('msg.error', 'Error'), 'danger'); return; }
            var tbody = root.querySelector('.acc-data-table tbody');
            if (!tbody) return;
            tbody.innerHTML = (res.data.rows || []).map(function (row) {
                return '<tr data-search="' + escapeHtml((row.event_uuid + ' ' + row.source_system + ' ' + row.event_type + ' ' + row.status).toLowerCase()) + '"><td><code class="small">' + escapeHtml(row.event_uuid) + '</code></td><td>' + escapeHtml(row.source_system) +
                    '</td><td>' + escapeHtml(row.event_type) + '</td><td><span class="badge bg-secondary">' + escapeHtml(fmtStatus(row.status)) +
                    '</span></td><td>' + escapeHtml(fmtValue(row.company_id)) + '</td><td>' + escapeHtml(fmtValue(row.branch_id)) +
                    '</td><td>' + escapeHtml(fmtDateTime(row.created_at || '')) + '</td><td><button type="button" class="btn btn-sm btn-link acc-view-json">' + escapeHtml(t('btn.json', 'JSON')) + '</button> ' +
                    '<button type="button" class="btn btn-sm btn-link acc-replay-one" data-uuid="' + escapeHtml(row.event_uuid) + '">' + escapeHtml(t('btn.replay', 'Replay')) + '</button></td></tr>';
            }).join('');
            bindJsonButtons(res.data.rows || []);
            bindEventReplayButtons();
            renderPagination('.acc-pagination', res.data.page || 1, res.data.total || 0, res.data.per_page || 50, loadEvents);
        });
    }

    function bindEventReplayButtons() {
        root.querySelectorAll('.acc-replay-one').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uuid = btn.dataset.uuid || '';
                if (!uuid) return;
                confirmAction(t('confirm.replay', 'Replay event :uuid?', { uuid: uuid }), function () {
                    api('replay', { method: 'POST', body: { event_uuid: uuid, confirm: 1 } }).then(showJson);
                });
            });
        });
    }

    function renderPagination(sel, page, total, perPage, loader) {
        var nav = root.querySelector(sel);
        if (!nav) return;
        var pages = Math.max(1, Math.ceil(total / perPage));
        if (pages <= 1) { nav.innerHTML = ''; return; }
        var html = '<ul class="pagination pagination-sm mb-0">';
        for (var p = 1; p <= pages && p <= 20; p++) {
            html += '<li class="page-item' + (p === page ? ' active' : '') + '"><button type="button" class="page-link" data-page="' + p + '">' + fmtNum(p) + '</button></li>';
        }
        html += '</ul>';
        nav.innerHTML = html;
        nav.querySelectorAll('[data-page]').forEach(function (btn) {
            btn.addEventListener('click', function () { loader(parseInt(btn.dataset.page, 10)); });
        });
    }

    function bindJsonButtons(rows) {
        root.querySelectorAll('.acc-view-json').forEach(function (btn, i) {
            btn.addEventListener('click', function () { showJson(rows[i] && rows[i].payload ? rows[i] : rows[i]); });
        });
    }

    function loadReplayHistory() {
        api('audit', { query: { action: 'replay_complete', per_page: 30 } }).then(function (res) {
            if (!res.ok) return;
            var rows = (res.data.logs && res.data.logs.rows) || [];
            var tbody = root.querySelector('.acc-replay-history tbody');
            if (!tbody) return;
            tbody.innerHTML = rows.map(function (r) {
                return '<tr><td>' + escapeHtml(fmtDateTime(r.created_at || '')) + '</td><td><code class="small">' + escapeHtml(r.event_uuid || '') +
                    '</code></td><td>' + escapeHtml(r.action) + '</td><td>' + escapeHtml(fmtStatus(r.status)) + '</td></tr>';
            }).join('');
        });
    }

    function runReplay(dryRun, mode) {
        var q = filters();
        if (mode === 'failed') q.status = 'failed';
        if (mode === 'single' && !q.event_uuid) {
            alertMsg(t('msg.enter_uuid', 'Enter event UUID in filters'), 'warning');
            return;
        }
        if (mode === 'company' && !q.company_id) {
            alertMsg(t('msg.enter_company', 'Enter company ID in filters'), 'warning');
            return;
        }
        if (mode === 'branch' && !q.branch_id) {
            alertMsg(t('msg.enter_branch', 'Enter branch ID in filters'), 'warning');
            return;
        }
        var pre = root.querySelector('.acc-replay-result');
        var prog = root.querySelector('.acc-replay-progress');
        if (prog) prog.classList.toggle('d-none', dryRun);
        var exec = function () {
            var req = dryRun
                ? api('replay', { query: q })
                : api('replay', { method: 'POST', body: Object.assign({}, q, { confirm: 1 }) });
            req.then(function (res) {
                if (prog) prog.classList.add('d-none');
                if (pre) pre.textContent = JSON.stringify(res, null, 2);
                loadReplayHistory();
            });
        };
        if (dryRun) exec();
        else confirmAction(t('confirm.replay_filters', 'Replay events for selected filters.'), exec);
    }

    function loadAudit() {
        api('audit', { query: filters() }).then(function (res) {
            if (!res.ok) return;
            var rows = (res.data.logs && res.data.logs.rows) || [];
            var tbody = root.querySelector('.acc-data-table tbody');
            if (tbody) {
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + escapeHtml(fmtDateTime(r.created_at || '')) + '</td><td><code class="small">' + escapeHtml(r.event_uuid || '') +
                        '</code></td><td>' + escapeHtml(r.action) + '</td><td>' + escapeHtml(r.system) + '</td><td>' + escapeHtml(fmtStatus(r.status)) +
                        '</td><td><button type="button" class="btn btn-sm btn-link acc-audit-json">' + escapeHtml(t('btn.json', 'JSON')) + '</button></td></tr>';
                }).join('');
                tbody.querySelectorAll('.acc-audit-json').forEach(function (btn, i) {
                    btn.addEventListener('click', function () { showJson(rows[i]); });
                });
            }
            renderEvidence(res.data.evidence_packs && res.data.evidence_packs.rows || []);
        });
    }

    function renderEvidence(rows) {
        root.querySelectorAll('.acc-evidence-table tbody').forEach(function (tbody) {
            tbody.innerHTML = rows.map(function (r) {
                return '<tr><td>' + escapeHtml(fmtValue(r.id)) + '</td><td>' + escapeHtml(fmtPeriod(r.period_from, r.period_to)) +
                    '</td><td><code class="small">' + escapeHtml(r.certification_hash || '') + '</td><td>' + escapeHtml(fmtDateTime(r.created_at || '')) +
                    '</td><td><button type="button" class="btn btn-sm btn-link acc-ev-json">' + escapeHtml(t('btn.view', 'View')) + '</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-ev-json').forEach(function (btn, i) {
                btn.addEventListener('click', function () { showJson(rows[i]); });
            });
        });
    }

    function loadProjection() {
        var type = val('.acc-projection-type') || 'trial_balance';
        api('projections', { query: Object.assign({}, filters(), { type: type }) }).then(function (res) {
            if (!res.ok) { alertMsg(res.message || t('msg.error', 'Error'), 'danger'); return; }
            var closure = root.querySelector('.acc-period-closure');
            if (closure) {
                closure.textContent = res.data.period_closure
                    ? JSON.stringify(res.data.period_closure)
                    : t('periodOpen', 'Period open');
            }
            var tbody = root.querySelector('.acc-projection-table tbody');
            if (tbody) {
                tbody.innerHTML = (res.data.rows || []).slice(0, 200).map(function (row, i) {
                    return '<tr><td>' + fmtNum(i + 1) + '</td><td><button type="button" class="btn btn-sm btn-link acc-proj-row">' + escapeHtml(t('btn.view', 'View')) + '</button></td></tr>';
                }).join('');
                var rows = res.data.rows || [];
                tbody.querySelectorAll('.acc-proj-row').forEach(function (btn, i) {
                    btn.addEventListener('click', function () { showJson(rows[i]); });
                });
            }
        });
    }

    function rebuildSnapshot() {
        confirmAction(t('confirm.rebuild', 'Rebuild snapshots for the selected period?'), function () {
            api('projections', { method: 'POST', body: Object.assign({}, filters(), { action: 'rebuild', confirm: 1 }) }).then(function (res) {
                alertMsg(res.ok ? t('msg.rebuild_ok', 'Rebuild complete') : (res.message || t('msg.rebuild_fail', 'Failed')), res.ok ? 'success' : 'danger');
                loadProjection();
            });
        });
    }

    function loadConsolidation() {
        var type = val('.acc-consolidation-type') || 'trial_balance';
        api('consolidation', { query: Object.assign({}, filters(), { type: type }) }).then(function (res) {
            if (!res.ok) return;
            var tbody = root.querySelector('.acc-consolidation-table tbody');
            if (!tbody) return;
            var rows = res.data.rows || [];
            tbody.innerHTML = rows.map(function (r) {
                return '<tr><td>' + escapeHtml(String(r.consolidation_run_id || '')) + '</td><td>' + escapeHtml(fmtValue(r.company_id)) + '</td><td>' +
                    escapeHtml(fmtPeriod(r.period_from, r.period_to)) + '</td><td>…</td><td><button type="button" class="btn btn-sm btn-link acc-cons-row">' + escapeHtml(t('btn.json', 'JSON')) + '</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-cons-row').forEach(function (btn, i) {
                btn.addEventListener('click', function () { showJson(rows[i]); });
            });
        });
    }

    function runConsolidation() {
        confirmAction(t('confirm.consolidation', 'Run consolidation for selected period?'), function () {
            api('consolidation', { method: 'POST', body: Object.assign({}, filters(), { confirm: 1 }) }).then(function (res) {
                alertMsg(res.ok ? t('msg.consolidation_ok', 'Consolidation complete') : (res.message || t('msg.consolidation_fail', 'Failed')), res.ok ? 'success' : 'danger');
                loadConsolidation();
            });
        });
    }

    function loadDriftReports() {
        api('drift', { query: filters() }).then(function (res) {
            if (!res.ok) return;
            var rows = (res.data.reports && res.data.reports.rows) || [];
            var sev = val('.acc-filter-severity');
            if (sev) rows = rows.filter(function (r) { return String(r.severity || '').toLowerCase() === sev; });
            var counts = { high: 0, medium: 0, low: 0 };
            rows.forEach(function (r) {
                var s = String(r.severity || 'low').toLowerCase();
                if (counts[s] != null) counts[s]++;
            });
            doughnutChart('acc-chart-drift-severity',
                [t('severity.high', 'High'), t('severity.medium', 'Medium'), t('severity.low', 'Low')],
                [counts.high, counts.medium, counts.low]);
            var tbody = root.querySelector('.acc-drift-table tbody');
            if (tbody) {
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + escapeHtml(fmtValue(r.id)) + '</td><td>' + escapeHtml(fmtPeriod(r.period_from, r.period_to)) +
                        '</td><td><span class="badge bg-warning">' + escapeHtml(fmtSeverity(r.severity || 'low')) + '</span></td><td>…</td>' +
                        '<td><button type="button" class="btn btn-sm btn-link acc-drift-json">' + escapeHtml(t('btn.view', 'View')) + '</button></td></tr>';
                }).join('');
                tbody.querySelectorAll('.acc-drift-json').forEach(function (btn, i) {
                    btn.addEventListener('click', function () { showJson(rows[i]); });
                });
            }
        });
    }

    function runDrift() {
        api('drift', { method: 'POST', body: filters() }).then(function (res) {
            showJson(res.data || res);
            loadDriftReports();
        });
    }

    function loadReconciliation() {
        api('reconciliation', { query: filters() }).then(function (res) {
            if (!res.ok) return;
            var rows = res.data.rows || [];
            var tbody = root.querySelector('.acc-recon-table tbody');
            if (!tbody) return;
            tbody.innerHTML = rows.map(function (r) {
                var payload = r.payload || {};
                var summary = payload.summary || {};
                return '<tr><td>' + escapeHtml(fmtValue(r.id)) + '</td><td>' + escapeHtml(String(r.risk_level || '')) + '</td><td>' +
                    escapeHtml(fmtPeriod(r.period_from, r.period_to)) + '</td><td>' +
                    escapeHtml(fmtValue(summary.drift_count || 0)) + '</td><td>' + escapeHtml(fmtValue(summary.correction_count || 0)) +
                    '</td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary acc-exec-dry" data-id="' + r.id + '">' + escapeHtml(t('btn.dry_run', 'Dry run')) + '</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-success acc-exec-live" data-id="' + r.id + '">' + escapeHtml(t('btn.execute', 'Execute')) + '</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-danger acc-reject-row" data-id="' + r.id + '">' + escapeHtml(t('btn.reject', 'Reject')) + '</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-exec-dry, .acc-exec-live').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = rows.find(function (x) { return String(x.id) === btn.dataset.id; }) || {};
                    var prop = row.payload && row.payload.correction_suggestions && row.payload.correction_suggestions[0];
                    if (!prop) { alertMsg(t('msg.no_correction', 'No correction proposal'), 'warning'); return; }
                    var live = btn.classList.contains('acc-exec-live');
                    confirmAction(live ? t('confirm.correction_live', 'Execute correction live?') : t('confirm.correction_dry', 'Dry-run correction?'), function () {
                        api('reconciliation', { method: 'POST', body: { action: 'execute', proposal: prop, dry_run: live ? 0 : 1, approved: 1, confirm: 1 } }).then(function (res) {
                            showJson(res);
                            if (res.ok && live) loadReconciliation();
                        });
                    });
                });
            });
            tbody.querySelectorAll('.acc-reject-row').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    alertMsg(t('msg.rejected', 'Correction rejected'), 'info');
                });
            });
        });
    }

    function runReconcile() {
        api('reconciliation', { method: 'POST', body: filters() }).then(function (res) {
            showJson(res.data || res);
            loadReconciliation();
        });
    }

    function loadIntegrity() {
        api('integrity', { query: filters() }).then(function (res) {
            if (!res.ok) return;
            var ov = res.data.overview || {};
            var scoreEl = root.querySelector('.acc-integrity-score .display-4');
            if (scoreEl) {
                scoreEl.textContent = ov.integrity_score != null ? fmtValue(ov.integrity_score) : t('nullValue', '—');
            }
            var sum = root.querySelector('.acc-golden-summary');
            if (sum) sum.textContent = JSON.stringify(ov.golden_ledger && ov.golden_ledger.totals || {}, null, 2);
            var locks = ov.locked_periods || [];
            var ltbody = root.querySelector('.acc-integrity-locks tbody');
            if (ltbody) {
                ltbody.innerHTML = locks.map(function (l) {
                    return '<tr><td>' + escapeHtml(fmtPeriod(l.period_from, l.period_to)) + '</td><td>' +
                        escapeHtml(fmtStatus(l.status || '')) + '</td><td>' + escapeHtml(fmtDateTime(l.created_at || '')) + '</td></tr>';
                }).join('');
            }
            renderEvidence((res.data.evidence_packs && res.data.evidence_packs.rows) || []);
        });
    }

    function loadSettings() {
        api('settings').then(function (res) {
            if (!res.ok) return;
            var tbody = root.querySelector('.acc-settings-table tbody');
            if (!tbody) return;
            tbody.innerHTML = Object.keys(res.data).map(function (k) {
                var on = res.data[k];
                var label = on ? t('on', 'ON') : t('off', 'OFF');
                return '<tr><td><code>' + escapeHtml(k) + '</code></td><td>' + escapeHtml(label) + '</td></tr>';
            }).join('');
        });
    }

    function fmtHealthBlock(st) {
        if (st == null) return t('nullValue', '—');
        if (typeof st !== 'object') return fmtStatus(String(st));
        return Object.keys(st).map(function (k) {
            var raw = st[k];
            var val;
            if (raw === true || raw === 'true' || raw === 1 || raw === '1') {
                val = t('on', 'ON');
            } else if (raw === false || raw === 'false' || raw === 0 || raw === '0') {
                val = t('off', 'OFF');
            } else {
                val = fmtStatus(String(raw));
            }
            return k + ': ' + val;
        }).join(' · ');
    }

    function loadHealth() {
        api('health').then(function (res) {
            if (!res.ok) return;
            var grid = root.querySelector('.acc-health-grid');
            if (grid) {
                var blocks = ['gateway', 'pipeline', 'event_store', 'replay', 'projection', 'consolidation', 'integrity', 'drift', 'database', 'queue'];
                grid.innerHTML = blocks.map(function (b) {
                    var st = res.data[b];
                    var title = (I18N.health && I18N.health[b]) ? I18N.health[b] : b;
                    return '<div class="col-md-4"><div class="acc-card"><strong>' + escapeHtml(title) + '</strong><div class="small">' + escapeHtml(fmtHealthBlock(st)) + '</div></div></div>';
                }).join('');
            }
            var list = root.querySelector('.acc-migration-list');
            if (list && res.data.migrations) {
                list.innerHTML = Object.keys(res.data.migrations).map(function (k) {
                    var ok = res.data.migrations[k];
                    return '<li class="list-group-item d-flex justify-content-between"><span>' + escapeHtml(k) + '</span><span>' + (ok ? '✓' : '✗') + '</span></li>';
                }).join('');
            }
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    root.querySelector('.acc-btn-refresh') && root.querySelector('.acc-btn-refresh').addEventListener('click', loadSection);
    root.querySelector('.acc-btn-apply-filters') && root.querySelector('.acc-btn-apply-filters').addEventListener('click', loadSection);
    root.querySelector('.acc-btn-print') && root.querySelector('.acc-btn-print').addEventListener('click', function () { window.print(); });
    root.querySelector('.acc-btn-export') && root.querySelector('.acc-btn-export').addEventListener('click', function () {
        if (section === 'events') window.location = apiBase + '/events' + buildQuery(Object.assign(filters(), { export: 'csv' }));
    });

    root.querySelectorAll('.acc-replay-preview').forEach(function (btn) {
        btn.addEventListener('click', function () { runReplay(true, btn.dataset.mode); });
    });
    root.querySelectorAll('.acc-replay-run').forEach(function (btn) {
        btn.addEventListener('click', function () { runReplay(false, btn.dataset.mode); });
    });
    root.querySelector('.acc-load-projection') && root.querySelector('.acc-load-projection').addEventListener('click', loadProjection);
    root.querySelector('.acc-rebuild-snapshot') && root.querySelector('.acc-rebuild-snapshot').addEventListener('click', rebuildSnapshot);
    root.querySelector('.acc-load-consolidation') && root.querySelector('.acc-load-consolidation').addEventListener('click', loadConsolidation);
    root.querySelector('.acc-run-consolidation') && root.querySelector('.acc-run-consolidation').addEventListener('click', runConsolidation);
    root.querySelector('.acc-run-drift') && root.querySelector('.acc-run-drift').addEventListener('click', runDrift);
    root.querySelector('.acc-run-reconcile') && root.querySelector('.acc-run-reconcile').addEventListener('click', runReconcile);

    root.querySelector('.acc-filter-severity') && root.querySelector('.acc-filter-severity').addEventListener('change', loadDriftReports);
    root.querySelector('.acc-global-search') && root.querySelector('.acc-global-search').addEventListener('input', function (e) {
        var term = String(e.target.value || '').toLowerCase();
        root.querySelectorAll('.acc-data-table tbody tr[data-search]').forEach(function (tr) {
            tr.classList.toggle('d-none', term !== '' && tr.dataset.search.indexOf(term) === -1);
        });
    });

    bindLocaleInputs();
    initLocaleInputs();
    setTimeout(initLocaleInputs, 0);
    setTimeout(initLocaleInputs, 150);

    loadSection();
    setInterval(function () {
        if (section === 'events' || section === 'dashboard') loadSection();
    }, 60000);
})();
