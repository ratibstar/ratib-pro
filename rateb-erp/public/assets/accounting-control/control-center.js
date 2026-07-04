(function () {
    'use strict';

    var root = document.getElementById('acc-control-app');
    if (!root) return;

    var apiBase = root.dataset.apiBase || '';
    var csrf = root.dataset.csrf || '';
    var section = root.dataset.section || 'dashboard';
    var charts = {};

    function filters() {
        return {
            company_id: val('.acc-filter-company') || root.dataset.companyId || '',
            branch_id: val('.acc-filter-branch') || '',
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

    function val(sel) {
        var el = root.querySelector(sel);
        return el ? String(el.value).trim() : '';
    }

    function alert(msg, type) {
        var box = document.getElementById('acc-alert');
        if (!box) return;
        box.className = 'alert alert-' + (type || 'info');
        box.textContent = msg;
        box.classList.remove('d-none');
    }

    function api(resource, opts) {
        opts = opts || {};
        var url = apiBase + '/' + resource + buildQuery(opts.query || {});
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
            if (!res.ok) { alert(res.message || 'Error', 'danger'); return; }
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
                escapeHtml(String(cards[k])) + '</div><div class="acc-card-label">' + escapeHtml(k.replace(/_/g, ' ')) + '</div></div></div>';
        }).join('');
    }

    function renderCharts(ch) {
        barChart('acc-chart-daily-events', (ch.daily_events || []).map(function (x) { return x.date; }), (ch.daily_events || []).map(function (x) { return x.count; }), 'Daily Events');
        barChart('acc-chart-monthly-posting', (ch.monthly_posting || []).map(function (x) { return x.month; }), (ch.monthly_posting || []).map(function (x) { return x.count; }), 'Monthly Posting');
        doughnutChart('acc-chart-replay-rate', ['Success', 'Failed'], [ch.replay_success_rate && ch.replay_success_rate.processed || 0, ch.replay_success_rate && ch.replay_success_rate.failed || 0]);
        lineChart('acc-chart-drift-trend', (ch.drift_trend || []).map(function (x) { return x.date; }), (ch.drift_trend || []).map(function (x) { return x.count; }));
        barChart('acc-chart-company-activity', (ch.company_activity || []).map(function (x) { return x.key || '—'; }), (ch.company_activity || []).map(function (x) { return x.count; }), 'Companies');
        barChart('acc-chart-branch-activity', (ch.branch_activity || []).map(function (x) { return x.key || '—'; }), (ch.branch_activity || []).map(function (x) { return x.count; }), 'Branches');
    }

    function barChart(id, labels, data, label) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, { type: 'bar', data: { labels: labels, datasets: [{ label: label || '', data: data, backgroundColor: '#3b82f6' }] }, options: { responsive: true, plugins: { legend: { display: !!label } } } });
    }

    function lineChart(id, labels, data) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, { type: 'line', data: { labels: labels, datasets: [{ data: data, borderColor: '#f59e0b', fill: false }] }, options: { responsive: true, plugins: { legend: { display: false } } } });
    }

    function doughnutChart(id, labels, data) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, { type: 'doughnut', data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#22c55e', '#ef4444'] }] }, options: { responsive: true } });
    }

    function loadEvents(page) {
        var q = filters();
        q.page = page || 1;
        api('events', { query: q }).then(function (res) {
            if (!res.ok) { alert(res.message || 'Error', 'danger'); return; }
            var tbody = root.querySelector('.acc-data-table tbody');
            if (!tbody) return;
            tbody.innerHTML = (res.data.rows || []).map(function (row) {
                return '<tr data-search="' + escapeHtml((row.event_uuid + ' ' + row.source_system + ' ' + row.event_type + ' ' + row.status).toLowerCase()) + '"><td><code class="small">' + escapeHtml(row.event_uuid) + '</code></td><td>' + escapeHtml(row.source_system) +
                    '</td><td>' + escapeHtml(row.event_type) + '</td><td><span class="badge bg-secondary">' + escapeHtml(row.status) +
                    '</span></td><td>' + escapeHtml(String(row.company_id || '')) + '</td><td>' + escapeHtml(String(row.branch_id || '')) +
                    '</td><td>' + escapeHtml(row.created_at || '') + '</td><td><button type="button" class="btn btn-sm btn-link acc-view-json">JSON</button> ' +
                    '<button type="button" class="btn btn-sm btn-link acc-replay-one" data-uuid="' + escapeHtml(row.event_uuid) + '">Replay</button></td></tr>';
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
                confirmAction('Replay event ' + uuid + '?', function () {
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
            html += '<li class="page-item' + (p === page ? ' active' : '') + '"><button type="button" class="page-link" data-page="' + p + '">' + p + '</button></li>';
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
                return '<tr><td>' + escapeHtml(r.created_at || '') + '</td><td><code class="small">' + escapeHtml(r.event_uuid || '') +
                    '</code></td><td>' + escapeHtml(r.action) + '</td><td>' + escapeHtml(r.status) + '</td></tr>';
            }).join('');
        });
    }

    function runReplay(dryRun, mode) {
        var q = filters();
        if (mode === 'failed') q.status = 'failed';
        if (mode === 'single' && !q.event_uuid) {
            alert('Enter Event UUID in filters', 'warning');
            return;
        }
        if (mode === 'company' && !q.company_id) {
            alert('Enter Company ID in filters', 'warning');
            return;
        }
        if (mode === 'branch' && !q.branch_id) {
            alert('Enter Branch ID in filters', 'warning');
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
        else confirmAction('Replay events for selected filters. This re-processes through the pipeline.', exec);
    }

    function loadAudit() {
        api('audit', { query: filters() }).then(function (res) {
            if (!res.ok) return;
            var rows = (res.data.logs && res.data.logs.rows) || [];
            var tbody = root.querySelector('.acc-data-table tbody');
            if (tbody) {
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + escapeHtml(r.created_at || '') + '</td><td><code class="small">' + escapeHtml(r.event_uuid || '') +
                        '</code></td><td>' + escapeHtml(r.action) + '</td><td>' + escapeHtml(r.system) + '</td><td>' + escapeHtml(r.status) +
                        '</td><td><button type="button" class="btn btn-sm btn-link acc-audit-json">JSON</button></td></tr>';
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
                return '<tr><td>' + r.id + '</td><td>' + escapeHtml((r.period_from || '') + ' → ' + (r.period_to || '')) +
                    '</td><td><code class="small">' + escapeHtml(r.certification_hash || '') + '</td><td>' + escapeHtml(r.created_at || '') +
                    '</td><td><button type="button" class="btn btn-sm btn-link acc-ev-json">View</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-ev-json').forEach(function (btn, i) {
                btn.addEventListener('click', function () { showJson(rows[i]); });
            });
        });
    }

    function loadProjection() {
        var type = val('.acc-projection-type') || 'trial_balance';
        api('projections', { query: Object.assign({}, filters(), { type: type }) }).then(function (res) {
            if (!res.ok) { alert(res.message || 'Error', 'danger'); return; }
            var closure = root.querySelector('.acc-period-closure');
            if (closure) closure.textContent = res.data.period_closure ? ('Period: ' + JSON.stringify(res.data.period_closure)) : 'Period open';
            var tbody = root.querySelector('.acc-projection-table tbody');
            if (tbody) {
                tbody.innerHTML = (res.data.rows || []).slice(0, 200).map(function (row, i) {
                    return '<tr><td>' + i + '</td><td><button type="button" class="btn btn-sm btn-link acc-proj-row">View</button></td></tr>';
                }).join('');
                var rows = res.data.rows || [];
                tbody.querySelectorAll('.acc-proj-row').forEach(function (btn, i) {
                    btn.addEventListener('click', function () { showJson(rows[i]); });
                });
            }
        });
    }

    function rebuildSnapshot() {
        confirmAction('Rebuild snapshots for the selected period?', function () {
            api('projections', { method: 'POST', body: Object.assign({}, filters(), { action: 'rebuild', confirm: 1 }) }).then(function (res) {
                alert(res.ok ? 'Rebuild complete' : (res.message || 'Failed'), res.ok ? 'success' : 'danger');
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
            tbody.innerHTML = rows.map(function (r, i) {
                return '<tr><td>' + escapeHtml(r.consolidation_run_id || '') + '</td><td>' + r.company_id + '</td><td>' +
                    escapeHtml((r.period_from || '') + ' → ' + (r.period_to || '')) + '</td><td>…</td><td><button type="button" class="btn btn-sm btn-link acc-cons-row">JSON</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-cons-row').forEach(function (btn, i) {
                btn.addEventListener('click', function () { showJson(rows[i]); });
            });
        });
    }

    function runConsolidation() {
        confirmAction('Run consolidation for selected period?', function () {
            api('consolidation', { method: 'POST', body: Object.assign({}, filters(), { confirm: 1 }) }).then(function (res) {
                alert(res.ok ? 'Consolidation complete' : (res.message || 'Failed'), res.ok ? 'success' : 'danger');
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
            doughnutChart('acc-chart-drift-severity', ['High', 'Medium', 'Low'], [counts.high, counts.medium, counts.low]);
            var tbody = root.querySelector('.acc-drift-table tbody');
            if (tbody) {
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + r.id + '</td><td>' + escapeHtml((r.period_from || '') + ' → ' + (r.period_to || '')) +
                        '</td><td><span class="badge bg-warning">' + escapeHtml(r.severity || 'low') + '</span></td><td>…</td>' +
                        '<td><button type="button" class="btn btn-sm btn-link acc-drift-json">View</button></td></tr>';
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
                return '<tr><td>' + r.id + '</td><td>' + escapeHtml(r.risk_level || '') + '</td><td>' +
                    escapeHtml((r.period_from || '') + ' → ' + (r.period_to || '')) + '</td><td>' +
                    (summary.drift_count || 0) + '</td><td>' + (summary.correction_count || 0) +
                    '</td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary acc-exec-dry" data-id="' + r.id + '">Dry Run</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-success acc-exec-live" data-id="' + r.id + '">Execute</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-danger acc-reject-row" data-id="' + r.id + '">Reject</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-exec-dry, .acc-exec-live').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = rows.find(function (x) { return String(x.id) === btn.dataset.id; }) || {};
                    var prop = row.payload && row.payload.correction_suggestions && row.payload.correction_suggestions[0];
                    if (!prop) { alert('No correction proposal', 'warning'); return; }
                    var live = btn.classList.contains('acc-exec-live');
                    confirmAction(live ? 'Execute correction LIVE?' : 'Dry-run correction?', function () {
                        api('reconciliation', { method: 'POST', body: { action: 'execute', proposal: prop, dry_run: live ? 0 : 1, approved: 1, confirm: 1 } }).then(function (res) {
                            showJson(res);
                            if (res.ok && live) loadReconciliation();
                        });
                    });
                });
            });
            tbody.querySelectorAll('.acc-reject-row').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    alert('Correction rejected (no server write)', 'info');
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
            if (scoreEl) scoreEl.textContent = String(ov.integrity_score != null ? ov.integrity_score : '—');
            var sum = root.querySelector('.acc-golden-summary');
            if (sum) sum.textContent = JSON.stringify(ov.golden_ledger && ov.golden_ledger.totals || {}, null, 2);
            var locks = ov.locked_periods || [];
            var ltbody = root.querySelector('.acc-integrity-locks tbody');
            if (ltbody) {
                ltbody.innerHTML = locks.map(function (l) {
                    return '<tr><td>' + escapeHtml((l.period_from || '') + ' → ' + (l.period_to || '')) + '</td><td>' +
                        escapeHtml(l.status || '') + '</td><td>' + escapeHtml(l.created_at || '') + '</td></tr>';
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
                return '<tr><td><code>' + escapeHtml(k) + '</code></td><td>' + (res.data[k] ? '✓ ON' : '✗ OFF') + '</td></tr>';
            }).join('');
        });
    }

    function loadHealth() {
        api('health').then(function (res) {
            if (!res.ok) return;
            var grid = root.querySelector('.acc-health-grid');
            if (grid) {
                var blocks = ['gateway', 'pipeline', 'event_store', 'replay', 'projection', 'consolidation', 'integrity', 'drift', 'database', 'queue'];
                grid.innerHTML = blocks.map(function (b) {
                    var st = res.data[b];
                    var label = typeof st === 'object' ? JSON.stringify(st) : String(st);
                    return '<div class="col-md-4"><div class="acc-card"><strong>' + escapeHtml(b) + '</strong><div class="small">' + escapeHtml(label) + '</div></div></div>';
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

    loadSection();
    setInterval(function () {
        if (section === 'events' || section === 'dashboard') loadSection();
    }, 60000);
})();
