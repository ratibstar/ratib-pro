/**
 * Phase 7 — Enterprise Accounting Control Center enhancements.
 * Requires control-center.js (window.AccControl).
 */
(function () {
    'use strict';

    var C = window.AccControl;
    if (!C || !C.root) return;

    var root = C.root;

    function t(key, fb) {
        return C.t ? C.t(key, fb) : (fb || key);
    }

    function showLoading(on) {
        root.querySelectorAll('.acc-loading').forEach(function (el) {
            el.classList.toggle('d-none', !on);
        });
    }

    function showEmpty(on) {
        root.querySelectorAll('.acc-empty').forEach(function (el) {
            el.classList.toggle('d-none', !on);
        });
    }

    function setUpdated(ts) {
        root.querySelectorAll('.acc-last-updated').forEach(function (el) {
            el.textContent = (t('lastUpdated', 'Last updated') + ': ' + (C.fmtDateTime ? C.fmtDateTime(ts || new Date().toISOString()) : String(ts || '')));
        });
    }

    function tCard(key) {
        return C.tCard ? C.tCard(key) : key.replace(/_/g, ' ');
    }

    function renderKpis(cards) {
        if (!cards) return;
        root.querySelectorAll('.acc-section-kpis').forEach(function (wrap) {
            wrap.innerHTML = Object.keys(cards).map(function (k) {
                return '<div class="col-6 col-md-4 col-lg-3"><div class="acc-card"><div class="acc-card-value">' +
                    C.escapeHtml(C.fmtValue(cards[k])) + '</div><div class="acc-card-label">' + C.escapeHtml(tCard(k)) + '</div></div></div>';
            }).join('');
        });
    }

    function sectionResource() {
        var map = {
            dashboard: 'dashboard', events: 'events', replay: 'replay', audit: 'audit',
            projections: 'projections', consolidation: 'consolidation', drift: 'drift',
            reconciliation: 'reconciliation', integrity: 'integrity', timeline: 'timeline'
        };
        return map[C.section] || C.section;
    }

    function exportFmt(fmt) {
        var res = sectionResource();
        if (res === 'dashboard' || res === 'settings' || res === 'health' || res === 'diagnostics') {
            C.alertMsg(t('msg.error', 'Export not available for this section'), 'warning');
            return;
        }
        var query = Object.assign({}, C.filters(), { export: fmt });
        if (res === 'projections' || res === 'consolidation') query.detail = 1;
        var type = val('.acc-projection-type') || val('.acc-consolidation-type') || '';
        if (type) query.type = type;
        var url = C.buildApiUrl ? C.buildApiUrl(res, query) : (C.apiBase + '/' + encodeURIComponent(res) + C.buildQuery(query));
        window.open(url, '_blank');
    }

    function val(sel) {
        var el = root.querySelector(sel);
        return el ? String(el.value).trim() : '';
    }

    function loadSectionKpis() {
        if (C.section === 'dashboard' || C.section === 'settings' || C.section === 'health') return;
        C.api('section', { query: Object.assign({}, C.filters(), { section: C.section }) }).then(function (res) {
            if (res.ok && res.data && res.data.cards) renderKpis(res.data.cards);
            if (res.updated_at || (res.data && res.data.updated_at)) setUpdated(res.updated_at || res.data.updated_at);
        });
    }

    function loadProjections() {
        showLoading(true);
        showEmpty(false);
        var type = val('.acc-projection-type') || 'trial_balance';
        C.api('projections', { query: Object.assign({}, C.filters(), { type: type, detail: 1 }) }).then(function (res) {
            showLoading(false);
            if (!res.ok) { C.alertMsg(res.message || t('msg.error', 'Error'), 'danger'); return; }
            var data = res.data || {};
            setUpdated(data.updated_at);
            if (data.kpis) renderKpis(data.kpis);
            var closure = root.querySelector('.acc-period-closure');
            if (closure) closure.textContent = data.period_closure ? JSON.stringify(data.period_closure) : t('periodOpen', 'Period open');
            var hist = root.querySelector('.acc-snapshot-history');
            if (hist) {
                hist.innerHTML = (data.history || []).map(function (h) {
                    return '<li class="list-group-item">' + C.escapeHtml(C.fmtPeriod(h.period_from, h.period_to)) + ' · ' + C.escapeHtml(C.fmtDateTime(h.created_at)) + '</li>';
                }).join('') || '<li class="list-group-item text-muted">' + t('noHistory', 'No history') + '</li>';
            }
            var rows = data.parsed_rows || [];
            var tbody = root.querySelector('.acc-projection-table tbody');
            if (!tbody) return;
            if (!rows.length) { showEmpty(true); tbody.innerHTML = ''; return; }
            tbody.innerHTML = rows.map(function (r, i) {
                return '<tr data-search="' + C.escapeHtml((r.account_code + ' ' + r.account_name).toLowerCase()) + '"><td>' + C.escapeHtml(r.account_code || '') +
                    '</td><td>' + C.escapeHtml(r.account_name || '') + '</td><td>' + C.escapeHtml(C.fmtValue(r.debit)) +
                    '</td><td>' + C.escapeHtml(C.fmtValue(r.credit)) + '</td><td>' + C.escapeHtml(C.fmtValue(r.amount)) +
                    '</td><td><button type="button" class="btn btn-sm btn-link acc-proj-drill" data-i="' + i + '">' + t('btn.view', 'View') + '</button></td></tr>';
            }).join('');
            tbody.querySelectorAll('.acc-proj-drill').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var i = parseInt(btn.getAttribute('data-i'), 10);
                    C.showJson(rows[i]);
                });
            });
        });
    }

    function loadConsolidation() {
        showLoading(true);
        var type = val('.acc-consolidation-type') || 'trial_balance';
        C.api('consolidation', { query: Object.assign({}, C.filters(), { type: type, detail: 1 }) }).then(function (res) {
            showLoading(false);
            if (!res.ok) return;
            var data = res.data || {};
            setUpdated(data.updated_at);
            if (data.kpis) renderKpis(data.kpis);
            var tbody = root.querySelector('.acc-consolidation-table tbody');
            var rows = data.parsed_rows || [];
            if (tbody) {
                if (!rows.length) { showEmpty(true); tbody.innerHTML = ''; }
                else {
                    showEmpty(false);
                    tbody.innerHTML = rows.slice(0, 500).map(function (r, i) {
                        return '<tr><td>' + C.escapeHtml(String(r.consolidation_run_id || '')) + '</td><td>' + C.escapeHtml(r.account_code || '') +
                            '</td><td>' + C.escapeHtml(r.account_name || '') + '</td><td>' + C.escapeHtml(C.fmtValue(r.debit)) +
                            '</td><td>' + C.escapeHtml(C.fmtValue(r.credit)) + '</td><td><button type="button" class="btn btn-sm btn-link acc-cons-drill" data-i="' + i + '">JSON</button></td></tr>';
                    }).join('');
                    tbody.querySelectorAll('.acc-cons-drill').forEach(function (btn) {
                        btn.addEventListener('click', function () { C.showJson(rows[parseInt(btn.getAttribute('data-i'), 10)]); });
                    });
                }
            }
            var elim = root.querySelector('.acc-eliminations-list');
            if (elim) {
                elim.innerHTML = (data.eliminations || []).slice(0, 50).map(function (e) {
                    return '<li class="list-group-item"><code class="small">' + C.escapeHtml(e.event_uuid || '') + '</code></li>';
                }).join('');
            }
            var runs = root.querySelector('.acc-execution-history tbody');
            if (runs) {
                runs.innerHTML = (data.execution_history || []).map(function (r) {
                    return '<tr><td>' + C.escapeHtml(r.run_id) + '</td><td>' + C.escapeHtml(C.fmtPeriod(r.period_from, r.period_to)) +
                        '</td><td>' + C.escapeHtml(C.fmtValue(r.row_count)) + '</td><td>' + C.escapeHtml(C.fmtDateTime(r.created_at)) + '</td></tr>';
                }).join('');
            }
            var hier = root.querySelector('.acc-hierarchy-tree');
            if (hier && data.hierarchy && data.hierarchy.companies) {
                hier.innerHTML = data.hierarchy.companies.map(function (co) {
                    return '<div class="mb-2"><strong>' + t('company', 'Company') + ' ' + C.escapeHtml(C.fmtValue(co.company_id)) + '</strong><ul>' +
                        (co.branches || []).map(function (b) {
                            return '<li>' + t('branch', 'Branch') + ' ' + C.escapeHtml(C.fmtValue(b.branch_id)) + ' (' + C.escapeHtml(C.fmtValue(b.rows)) + ')</li>';
                        }).join('') + '</ul></div>';
                }).join('');
            }
        });
    }

    function loadTimeline() {
        showLoading(true);
        C.api('timeline', { query: C.filters() }).then(function (res) {
            showLoading(false);
            if (!res.ok) return;
            setUpdated(res.data && res.data.updated_at);
            var ul = root.querySelector('.acc-timeline');
            if (!ul) return;
            var items = (res.data && res.data.items) || [];
            if (!items.length) { showEmpty(true); ul.innerHTML = ''; return; }
            showEmpty(false);
            ul.innerHTML = items.map(function (it) {
                return '<li class="acc-timeline-item mb-3 ps-3 border-start border-3"><div class="small text-muted">' +
                    C.escapeHtml(C.fmtDateTime(it.created_at)) + '</div><span class="badge bg-secondary me-1">' + C.escapeHtml(it.kind) +
                    '</span><strong>' + C.escapeHtml(it.title) + '</strong> <span class="text-muted">' + C.escapeHtml(it.status || '') + '</span></li>';
            }).join('');
        });
    }

    function loadNotifications() {
        showLoading(true);
        C.api('notifications', { query: C.filters() }).then(function (res) {
            showLoading(false);
            if (!res.ok) return;
            var list = root.querySelector('.acc-notifications-list');
            var items = (res.data && res.data.items) || [];
            if (list) {
                list.innerHTML = items.length ? items.map(function (n) {
                    return '<div class="list-group-item"><div class="d-flex justify-content-between"><strong>' + C.escapeHtml(n.type) +
                        '</strong><span class="small text-muted">' + C.escapeHtml(C.fmtDateTime(n.created_at)) + '</span></div><div class="small">' +
                        C.escapeHtml(n.action || '') + ' · ' + C.escapeHtml(n.status || '') + '</div></div>';
                }).join('') : '<div class="list-group-item text-muted">' + t('empty', 'No notifications') + '</div>';
            }
            var badge = root.querySelector('.acc-notif-badge');
            if (badge) {
                var n = (res.data && res.data.unread) || 0;
                badge.textContent = String(n);
                badge.classList.toggle('d-none', n < 1);
            }
        });
    }

    function loadDiagnostics() {
        showLoading(true);
        C.api('diagnostics').then(function (res) {
            showLoading(false);
            if (!res.ok) return;
            var data = res.data || {};
            var overall = root.querySelector('.acc-diag-overall');
            if (overall) {
                var s = data.overall || 'WARN';
                overall.className = 'alert acc-diag-overall mb-3 alert-' + (s === 'PASS' ? 'success' : s === 'FAIL' ? 'danger' : 'warning');
                overall.textContent = s + ' — PASS: ' + (data.summary && data.summary.pass) + ' WARN: ' + (data.summary && data.summary.warn) + ' FAIL: ' + (data.summary && data.summary.fail);
            }
            var tbody = root.querySelector('.acc-diagnostics-table tbody');
            if (tbody) {
                tbody.innerHTML = (data.checks || []).map(function (c) {
                    var cls = c.status === 'PASS' ? 'success' : c.status === 'FAIL' ? 'danger' : 'warning';
                    return '<tr><td><code>' + C.escapeHtml(c.id) + '</code></td><td><span class="badge bg-' + cls + '">' + C.escapeHtml(c.status) + '</span></td><td>' + C.escapeHtml(c.label) + '</td></tr>';
                }).join('');
            }
            setUpdated(data.generated_at);
        });
    }

    function globalSearch(q) {
        if (!q || q.length < 2) {
            var box = root.querySelector('.acc-search-results');
            if (box) { box.classList.add('d-none'); box.innerHTML = ''; }
            return;
        }
        C.api('search', { query: Object.assign({}, C.filters(), { q: q }) }).then(function (res) {
            var box = root.querySelector('.acc-search-results');
            if (!box || !res.ok) return;
            var results = (res.data && res.data.results) || [];
            box.classList.remove('d-none');
            box.innerHTML = results.length ? ('<div class="list-group list-group-flush">' + results.map(function (r) {
                return '<div class="list-group-item py-2"><span class="badge bg-secondary me-2">' + C.escapeHtml(r.kind) +
                    '</span>' + C.escapeHtml(r.title || r.ref) + ' <span class="text-muted small">' + C.escapeHtml(C.fmtDateTime(r.created_at)) + '</span></div>';
            }).join('') + '</div>') : '<div class="alert alert-light mb-0">' + t('empty', 'No results') + '</div>';
        });
    }

    function initTheme() {
        var btn = root.querySelector('.acc-btn-theme');
        var stored = localStorage.getItem('acc-theme') || '';
        if (stored === 'light') document.documentElement.setAttribute('data-bs-theme', 'light');
        if (btn) {
            btn.addEventListener('click', function () {
                var cur = document.documentElement.getAttribute('data-bs-theme');
                var next = cur === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-bs-theme', next);
                localStorage.setItem('acc-theme', next);
                btn.querySelector('i').className = next === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            });
        }
    }

    function enhanceDrift() {
        if (C.section !== 'drift') return;
        C.api('drift', { query: Object.assign({}, C.filters(), { detail: 1 }) }).then(function (res) {
            if (!res.ok || !res.data) return;
            setUpdated(res.data.updated_at);
            if (res.data.kpis || res.data.breakdown) renderKpis(Object.assign({}, res.data.breakdown || {}, res.data.severity_counts || {}));
            var actions = root.querySelector('.acc-drift-actions');
            if (actions && res.data.recommended_actions) {
                actions.innerHTML = res.data.recommended_actions.map(function (a) {
                    return '<li class="list-group-item">' + C.escapeHtml(a.action) + ' (' + C.escapeHtml(C.fmtValue(a.count)) + ')</li>';
                }).join('');
            }
            if (res.data.trend && C.lineChart) {
                C.lineChart('acc-chart-drift-detail',
                    res.data.trend.map(function (x) { return C.fmtDate(x.date); }),
                    res.data.trend.map(function (x) { return x.count; }),
                    t('charts.drift_trend', 'Drift trend'));
            }
        });
    }

    function enhanceReplay() {
        if (C.section !== 'replay') return;
        C.api('replay', { query: Object.assign({}, C.filters(), { detail: 1 }) }).then(function (res) {
            if (!res.ok || !res.data) return;
            setUpdated(res.data.updated_at);
            var qtbody = root.querySelector('.acc-replay-queue tbody');
            if (qtbody) {
                qtbody.innerHTML = (res.data.queue || []).map(function (r) {
                    return '<tr><td><code class="small">' + C.escapeHtml(r.event_uuid) + '</code></td><td>' + C.escapeHtml(r.status) +
                        '</td><td>' + C.escapeHtml(C.fmtDateTime(r.created_at)) + '</td></tr>';
                }).join('');
            }
            if (res.data.stats) renderKpis(res.data.stats);
        });
    }

    function enhanceIntegrity() {
        if (C.section !== 'integrity') return;
        C.api('integrity', { query: Object.assign({}, C.filters(), { detail: 1 }) }).then(function (res) {
            if (!res.ok || !res.data) return;
            var d = res.data;
            setUpdated(d.updated_at);
            var readiness = d.audit_readiness || {};
            renderKpis({
                integrity_score: readiness.score,
                evidence_count: readiness.evidence_count,
                locked_periods: readiness.locked_periods
            });
            var conflicts = root.querySelector('.acc-conflict-timeline tbody');
            if (conflicts) {
                conflicts.innerHTML = (d.conflict_timeline || []).map(function (c) {
                    return '<tr><td>' + C.escapeHtml(c.type || '') + '</td><td>' + C.escapeHtml(c.detail || c.account_code || '') + '</td></tr>';
                }).join('');
            }
            var corr = root.querySelector('.acc-correction-history tbody');
            if (corr) {
                corr.innerHTML = (d.correction_history || []).map(function (c) {
                    return '<tr><td>' + C.escapeHtml(c.status) + '</td><td>' + C.escapeHtml(C.fmtDateTime(c.created_at)) +
                        '</td><td><button type="button" class="btn btn-sm btn-link acc-corr-json">' + t('btn.view', 'View') + '</button></td></tr>';
                }).join('');
            }
            var hashes = root.querySelector('.acc-hash-verification');
            if (hashes && d.overview && d.overview.snapshot_hashes) {
                hashes.textContent = JSON.stringify(d.overview.snapshot_hashes, null, 2);
            }
        });
    }

    function enhanceReconciliation() {
        if (C.section !== 'reconciliation') return;
        C.api('reconciliation', { query: Object.assign({}, C.filters(), { detail: 1 }) }).then(function (res) {
            if (!res.ok || !res.data) return;
            setUpdated(res.data.updated_at);
            if (res.data.workflow) renderKpis(res.data.workflow);
            var tl = root.querySelector('.acc-correction-timeline tbody');
            if (tl) {
                tl.innerHTML = (res.data.timeline || []).map(function (c) {
                    return '<tr><td>' + C.escapeHtml(c.status) + '</td><td>' + C.escapeHtml(C.fmtDateTime(c.created_at)) +
                        '</td><td>' + C.escapeHtml(c.executed_at ? C.fmtDateTime(c.executed_at) : '—') + '</td></tr>';
                }).join('');
            }
        });
    }

    root.querySelectorAll('.acc-export-fmt').forEach(function (btn) {
        btn.addEventListener('click', function () { exportFmt(btn.getAttribute('data-fmt')); });
    });

    var searchInput = root.querySelector('.acc-global-search');
    var searchTimer;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { globalSearch(String(searchInput.value || '').trim()); }, 350);
        });
    }

    initTheme();
    loadSectionKpis();
    if (C.section === 'projections') loadProjections();
    if (C.section === 'consolidation') loadConsolidation();
    enhanceDrift();
    enhanceReplay();
    enhanceIntegrity();
    enhanceReconciliation();

    C.api('notifications', { query: { per_page: 5 } }).then(function (res) {
        if (!res.ok) return;
        var badge = root.querySelector('.acc-notif-badge');
        if (badge && res.data) {
            badge.textContent = String(res.data.unread || 0);
            badge.classList.toggle('d-none', (res.data.unread || 0) < 1);
        }
    });

    window.AccPhase7 = {
        loadProjections: loadProjections,
        loadConsolidation: loadConsolidation,
        loadTimeline: loadTimeline,
        loadNotifications: loadNotifications,
        loadDiagnostics: loadDiagnostics,
        exportFmt: exportFmt,
        globalSearch: globalSearch
    };
})();
