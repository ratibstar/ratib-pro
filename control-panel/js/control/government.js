/**
 * Government Control — API wiring for control panel.
 */
(function () {
    var root = document.getElementById('gov-labor-page');
    if (!root) return;

    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase) return;

    var canManage = root.getAttribute('data-can-manage') === '1';

    function flash(msg, ok) {
        var el = document.getElementById('govFlash');
        if (!el) return;
        el.textContent = msg;
        el.className = 'alert mt-2 ' + (ok ? 'alert-success' : 'alert-danger');
        el.classList.remove('d-none');
        setTimeout(function () {
            el.classList.add('d-none');
        }, 5000);
    }

    function api(action, init, queryParams) {
        var qs = new URLSearchParams();
        qs.set('action', action);
        if (queryParams) {
            Object.keys(queryParams).forEach(function (k) {
                var v = queryParams[k];
                if (v != null && String(v) !== '') {
                    qs.set(k, String(v));
                }
            });
        }
        var url = apiBase + '/government.php?' + qs.toString();
        init = init || { credentials: 'same-origin' };
        if (!init.credentials) init.credentials = 'same-origin';
        return fetch(url, init).then(function (r) {
            return r.text().then(function (text) {
                var parsed = null;
                try {
                    parsed = text ? JSON.parse(text) : null;
                } catch (e) {
                    parsed = null;
                }
                if (!r.ok) {
                    var msg = (parsed && parsed.message) ? parsed.message : (text || ('HTTP ' + r.status));
                    throw new Error(msg);
                }
                if (!parsed) {
                    throw new Error('Empty API response');
                }
                return parsed;
            });
        });
    }

    function loadSummary() {
        api('summary', null, null)
            .then(function (res) {
                var t = (res.data && res.data.totals) || {};
                var meta = (res.data && res.data.meta) || {};
                var v = document.getElementById('govStatViolations');
                var b = document.getElementById('govStatBlacklist');
                var a = document.getElementById('govStatAlerts');
                if (v) v.textContent = String(t.violations != null ? t.violations : '0');
                if (b) b.textContent = String(t.blacklist_active != null ? t.blacklist_active : '0');
                if (a) a.textContent = String(t.workers_alert != null ? t.workers_alert : '0');
                if (meta.auto_agency_db_used) {
                    flash('Connected automatically to real agency DB: ' + (meta.workers_schema || 'unknown'), true);
                }
            })
            .catch(function () {
                var v = document.getElementById('govStatViolations');
                if (v) v.textContent = '—';
            });
    }

    function tbody(id) {
        var t = document.getElementById(id);
        return t ? t.querySelector('tbody') : null;
    }

    function loadInspections() {
        var c = document.getElementById('inspFilterCountry');
        var a = document.getElementById('inspFilterAgency');
        var qp = {};
        if (c && c.value.trim()) qp.country = c.value.trim();
        if (a && a.value) qp.agency_id = a.value;
        api('inspections', null, qp)
            .then(function (res) {
                var tb = tbody('tableInspections');
                if (!tb) return;
                tb.innerHTML = (res.data || []).map(function (row) {
                    return '<tr><td>' + row.id + '</td><td>' + escapeHtml(row.worker_name || ('#' + row.worker_id)) + '</td><td>' + escapeHtml(row.inspection_date || '') + '</td><td>' + escapeHtml(row.inspector_name || '') + '</td><td><span class="badge bg-secondary gov-badge">' + escapeHtml(row.status || '') + '</span></td><td>' + escapeHtml(String(row.agency_id != null ? row.agency_id : '')) + '</td><td>' + escapeHtml((row.notes || '').substring(0, 80)) + '</td></tr>';
                }).join('') || '<tr><td colspan="7" class="text-muted">No records</td></tr>';
            })
            .catch(function (e) {
                flash(e.message || 'Load failed', false);
            });
    }

    function loadViolations(workerId) {
        var qp = workerId ? { worker_id: String(workerId) } : null;
        api('violations', null, qp)
            .then(function (res) {
                var tb = tbody('tableViolations');
                if (!tb) return;
                tb.innerHTML = (res.data || []).map(function (row) {
                    return '<tr><td>' + row.id + '</td><td>' + escapeHtml(row.worker_name || ('#' + row.worker_id)) + '</td><td>' + escapeHtml(row.type || '') + '</td><td>' + escapeHtml(row.severity || '') + '</td><td>' + escapeHtml(String(row.inspection_id != null ? row.inspection_id : '')) + '</td><td>' + escapeHtml(row.created_at || '') + '</td></tr>';
                }).join('') || '<tr><td colspan="6" class="text-muted">No records</td></tr>';
            })
            .catch(function (e) {
                flash(e.message || 'Load failed', false);
            });
    }

    function loadBlacklist() {
        api('blacklist')
            .then(function (res) {
                var tb = tbody('tableBlacklist');
                if (!tb) return;
                tb.innerHTML = (res.data || []).map(function (row) {
                    var rm = '';
                    if (canManage && row.status === 'active') {
                        rm = '<td><button type="button" class="btn btn-sm btn-outline-light gov-bl-remove" data-id="' + row.id + '">Remove</button></td>';
                    } else if (canManage) {
                        rm = '<td></td>';
                    }
                    var base = '<tr><td>' + row.id + '</td><td>' + escapeHtml(row.entity_type || '') + '</td><td>' + row.entity_id + '</td><td>' + escapeHtml(row.status || '') + '</td><td>' + escapeHtml((row.reason || '').substring(0, 120)) + '</td><td>' + escapeHtml(row.worker_name || '') + '</td>';
                    return base + (canManage ? rm : '') + '</tr>';
                }).join('') || '<tr><td colspan="' + (canManage ? 7 : 6) + '" class="text-muted">No records</td></tr>';
                tb.querySelectorAll('.gov-bl-remove').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = parseInt(btn.getAttribute('data-id'), 10);
                        api(
                            'blacklist_remove',
                            {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: id }),
                                credentials: 'same-origin'
                            },
                            null
                        )
                            .then(function () {
                                flash('Blacklist entry removed', true);
                                loadBlacklist();
                                loadSummary();
                            })
                            .catch(function (e) {
                                flash(e.message || 'Error', false);
                            });
                    });
                });
            })
            .catch(function (e) {
                flash(e.message || 'Load failed', false);
            });
    }

    function loadTracking() {
        var c = document.getElementById('trackFilterCountry');
        var s = document.getElementById('trackFilterStatus');
        var qp = {};
        if (c && c.value.trim()) qp.country = c.value.trim();
        if (s && s.value) qp.status = s.value;
        api('tracking', null, Object.keys(qp).length ? qp : null)
            .then(function (res) {
                var tb = tbody('tableTracking');
                if (!tb) return;
                tb.innerHTML = (res.data || []).map(function (row) {
                    var badge = 'bg-secondary';
                    if (row.status === 'alert') badge = 'bg-danger';
                    if (row.status === 'warning') badge = 'bg-warning text-dark';
                    if (row.status === 'safe') badge = 'bg-success';
                    return '<tr><td>' + escapeHtml(row.worker_name || ('#' + row.worker_id)) + '</td><td>' + escapeHtml(row.worker_country || '') + '</td><td>' + escapeHtml(row.last_checkin || '') + '</td><td>' + escapeHtml(row.location_text || '') + '</td><td><span class="badge ' + badge + ' gov-badge">' + escapeHtml(row.status || '') + '</span></td></tr>';
                }).join('') || '<tr><td colspan="5" class="text-muted">No records</td></tr>';
            })
            .catch(function (e) {
                flash(e.message || 'Load failed', false);
            });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function formToObj(form) {
        var fd = new FormData(form);
        var o = {};
        fd.forEach(function (v, k) {
            o[k] = v;
        });
        return o;
    }

    document.getElementById('inspApplyFilter') && document.getElementById('inspApplyFilter').addEventListener('click', loadInspections);
    document.getElementById('violFilterBtn') && document.getElementById('violFilterBtn').addEventListener('click', function () {
        var w = document.getElementById('violFilterWorker');
        loadViolations(w && w.value ? parseInt(w.value, 10) : 0);
    });
    document.getElementById('trackApply') && document.getElementById('trackApply').addEventListener('click', loadTracking);
    var seedBtn = document.getElementById('govSeedDemoBtn');
    if (seedBtn) {
        seedBtn.addEventListener('click', function () {
            if (!confirm('Seed demo records for current agency database?')) return;
            seedBtn.disabled = true;
            api(
                'seed_demo',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({}),
                    credentials: 'same-origin'
                },
                null
            )
                .then(function (res) {
                    flash((res && res.message) ? res.message : 'Demo seeded', true);
                    loadSummary();
                    loadInspections();
                    loadViolations(0);
                    loadBlacklist();
                    loadTracking();
                })
                .catch(function (e) {
                    flash(e.message || 'Seed failed', false);
                })
                .finally(function () {
                    seedBtn.disabled = false;
                });
        });
    }

    var formInsp = document.getElementById('formInspection');
    if (formInsp) {
        formInsp.addEventListener('submit', function (ev) {
            ev.preventDefault();
            api(
                'inspection',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formToObj(formInsp)),
                    credentials: 'same-origin'
                },
                null
            )
                .then(function () {
                    flash('Inspection saved', true);
                    formInsp.reset();
                    loadInspections();
                    loadSummary();
                })
                .catch(function (e) {
                    flash(e.message || 'Error', false);
                });
        });
    }

    var formViol = document.getElementById('formViolation');
    if (formViol) {
        formViol.addEventListener('submit', function (ev) {
            ev.preventDefault();
            api(
                'violation',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formToObj(formViol)),
                    credentials: 'same-origin'
                },
                null
            )
                .then(function () {
                    flash('Violation saved', true);
                    formViol.reset();
                    loadViolations(0);
                    loadSummary();
                })
                .catch(function (e) {
                    flash(e.message || 'Error', false);
                });
        });
    }

    var formBl = document.getElementById('formBlacklist');
    if (formBl) {
        formBl.addEventListener('submit', function (ev) {
            ev.preventDefault();
            api(
                'blacklist',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formToObj(formBl)),
                    credentials: 'same-origin'
                },
                null
            )
                .then(function () {
                    flash('Blacklist updated', true);
                    formBl.reset();
                    loadBlacklist();
                    loadSummary();
                })
                .catch(function (e) {
                    flash(e.message || 'Error', false);
                });
        });
    }

    var formTr = document.getElementById('formTracking');
    if (formTr) {
        formTr.addEventListener('submit', function (ev) {
            ev.preventDefault();
            api(
                'tracking',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formToObj(formTr)),
                    credentials: 'same-origin'
                },
                null
            )
                .then(function () {
                    flash('Tracking saved', true);
                    loadTracking();
                    loadSummary();
                })
                .catch(function (e) {
                    flash(e.message || 'Error', false);
                });
        });
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            var id = e.target.getAttribute('data-bs-target') || '';
            if (id === '#pane-insp') loadInspections();
            if (id === '#pane-viol') loadViolations(0);
            if (id === '#pane-bl') loadBlacklist();
            if (id === '#pane-track') loadTracking();
        });
    });

    loadSummary();
    loadInspections();
})();
