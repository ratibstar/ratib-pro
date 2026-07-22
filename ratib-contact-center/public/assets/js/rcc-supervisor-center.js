/**
 * RATEB Contact Center — Supervisor & Workforce Management UI (Phase 9).
 */
(function (global) {
    'use strict';

    var LIVE_PREFIXES = ['SUPERVISOR_', 'AGENT_', 'QUEUE_', 'SLA_'];

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function isLiveEvent(type) {
        if (!type) return false;
        for (var i = 0; i < LIVE_PREFIXES.length; i++) {
            if (type.indexOf(LIVE_PREFIXES[i]) === 0) return true;
        }
        return false;
    }

    function RccSupervisorCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.wsUrl = (root.getAttribute('data-ws') || '').trim();
        this.route = root.getAttribute('data-route') || 'dashboard';
        this.canManageTenants = root.getAttribute('data-can-manage-tenants') === '1';
        this.lang = root.getAttribute('data-lang') || 'en';
        this.panel = document.getElementById('rcc-sup-panel');
        this.status = document.getElementById('rcc-sup-status');
        this._client = null;
    }

    RccSupervisorCenter.prototype._usePolling = function () {
        var u = (this.wsUrl || '').toLowerCase();
        return !u || u === 'polling' || u === 'off';
    };

    RccSupervisorCenter.prototype._connectRealtime = function () {
        var self = this;
        if (self._usePolling() || !global.RccRealtimeClient) return;
        if (self._client && self._client.disconnect) self._client.disconnect();
        self._client = new global.RccRealtimeClient({
            url: self.wsUrl,
            tenantId: self.tenantId,
            rooms: ['tenant:' + self.tenantId, 'dashboard:' + self.tenantId, 'supervisor:' + self.tenantId],
            onEvent: function (ev) {
                if (!isLiveEvent(ev.type)) return;
                self.setStatus('Live: ' + ev.type, 'info');
                self.renderPanel();
            }
        });
        self._client.connect();
    };

    RccSupervisorCenter.prototype.init = function () {
        this.renderTenantBar();
        this.renderPanel();
        this._connectRealtime();
    };

    RccSupervisorCenter.prototype.setStatus = function (msg, kind) {
        if (!this.status) return;
        this.status.className = 'rcc-sup__status rcc-sup__status--' + (kind || 'info');
        this.status.textContent = msg;
    };

    RccSupervisorCenter.prototype.api = function (action, body) {
        var self = this;
        var payload = Object.assign({}, body || {}, { tenant_id: self.tenantId });
        return fetch(self.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); });
    };

    RccSupervisorCenter.prototype.renderTenantBar = function () {
        var self = this;
        var bar = document.getElementById('rcc-sup-tenant-bar');
        if (!bar || !self.canManageTenants) return;
        self.api('tenants_list').then(function (res) {
            var tenants = (res && res.tenants) || [];
            if (!res.ok || tenants.length < 2) return;
            var html = '<label>Tenant <select id="rcc-sup-tenant-select">';
            tenants.forEach(function (t) {
                html += '<option value="' + esc(t.id) + '"' + (parseInt(t.id, 10) === self.tenantId ? ' selected' : '') + '>' + esc(t.name || t.code) + '</option>';
            });
            html += '</select></label>';
            bar.innerHTML = html;
            bar.hidden = false;
            document.getElementById('rcc-sup-tenant-select').onchange = function (ev) {
                self.tenantId = parseInt(ev.target.value, 10) || self.tenantId;
                self.root.setAttribute('data-tenant', String(self.tenantId));
                self._connectRealtime();
                self.renderPanel();
            };
        });
    };

    RccSupervisorCenter.prototype.fetchAgents = function () {
        return this.api('agent_monitor').then(function (res) {
            return (res && res.ok ? res.agents : []) || [];
        });
    };

    RccSupervisorCenter.prototype.agentSelectHtml = function (agents, name, selectedId) {
        var html = '<label>Agent <select name="' + esc(name) + '">';
        (agents || []).forEach(function (a) {
            html += '<option value="' + esc(a.id) + '"' + (parseInt(a.id, 10) === parseInt(selectedId, 10) ? ' selected' : '') + '>' + esc(a.display_name) + ' (' + esc(a.extension) + ')</option>';
        });
        html += '</select></label>';
        return html;
    };

    RccSupervisorCenter.prototype.renderPanel = function () {
        var fn = {
            dashboard: this.renderDashboard,
            wallboard: this.renderWallboard,
            queues: this.renderQueues,
            agents: this.renderAgents,
            sla: this.renderSla,
            wfm: this.renderWfm,
            shifts: this.renderShifts,
            attendance: this.renderAttendance,
            breaks: this.renderBreaks,
            occupancy: this.renderOccupancy,
            adherence: this.renderAdherence,
            alerts: this.renderAlerts,
            reports: this.renderReports
        }[this.route];
        if (fn) fn.call(this);
    };

    RccSupervisorCenter.prototype.renderDashboard = function () {
        var self = this;
        self.api('dashboard_summary').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var a = res.agents || {};
            var q = res.queues || {};
            var html = '<h3>Supervisor Dashboard</h3><div class="rcc-sup__kpi-grid">';
            html += self.kpi('Agents ready', a.ready) + self.kpi('Agents busy', a.busy);
            html += self.kpi('Waiting calls', q.total_waiting) + self.kpi('SLA red queues', q.sla_red_queues);
            html += self.kpi('Open conversations', (res.conversations || {}).open) + self.kpi('Open alerts', (res.alerts || {}).open);
            html += '</div><button type="button" class="rcc-sup__btn" id="rcc-sup-refresh">Refresh</button>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-sup-refresh').onclick = function () { self.renderDashboard(); };
        });
    };

    RccSupervisorCenter.prototype.kpi = function (label, val) {
        return '<div class="rcc-sup__kpi"><span class="rcc-sup__kpi-val">' + esc(val == null ? '—' : val) + '</span><span class="rcc-sup__kpi-label">' + esc(label) + '</span></div>';
    };

    RccSupervisorCenter.prototype.renderWallboard = function () {
        var self = this;
        self.api('wallboard').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Live Wallboard</h3><div class="rcc-sup__wall-grid">';
            (res.queues || []).forEach(function (q) {
                var risk = q.sla_risk || 'green';
                html += '<div class="rcc-sup__wall-card rcc-sup__wall-card--' + esc(risk) + '">';
                html += '<h4>' + esc(q.queue_name || q.queue_code) + '</h4>';
                html += '<p>Waiting: <strong>' + esc(q.waiting_count) + '</strong></p>';
                html += '<p>Longest: ' + esc(q.longest_wait_seconds) + 's</p>';
                html += '<p>Agents: ' + esc(q.available_agents) + ' ready / ' + esc(q.busy_agents) + ' busy</p></div>';
            });
            html += '</div><h4>Agents</h4><div class="rcc-sup__agent-chips">';
            (res.agents || []).forEach(function (ag) {
                html += '<span class="rcc-sup__chip rcc-sup__chip--' + esc(ag.status || 'offline') + '">' + esc(ag.display_name) + ' (' + esc(ag.extension) + ')</span>';
            });
            html += '</div>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderQueues = function () {
        var self = this;
        self.api('queue_monitor').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Queue Monitor</h3>';
            (res.queues || []).forEach(function (item) {
                var s = item.snapshot || {};
                html += '<div class="rcc-sup__block"><h4>' + esc((item.queue || {}).name) + '</h4>';
                html += '<p>Waiting ' + esc(s.waiting_count) + ' · SLA risk <span class="rcc-sup__risk rcc-sup__risk--' + esc(s.sla_risk) + '">' + esc(s.sla_risk) + '</span></p><ul>';
                (item.waiting_calls || []).forEach(function (c) {
                    html += '<li>' + esc(c.caller_number) + ' — ' + esc(c.wait_seconds) + 's (' + esc(c.status) + ')</li>';
                });
                html += '</ul></div>';
            });
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderAgents = function () {
        var self = this;
        self.api('agent_monitor').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Agent Monitor</h3><table class="rcc-sup__table"><thead><tr><th>Agent</th><th>Ext</th><th>Status</th><th>Pause</th><th>Call</th></tr></thead><tbody>';
            (res.agents || []).forEach(function (a) {
                html += '<tr><td>' + esc(a.display_name) + '</td><td>' + esc(a.extension) + '</td>';
                html += '<td><span class="rcc-sup__chip rcc-sup__chip--' + esc(a.status || 'offline') + '">' + esc(a.status || 'offline') + '</span></td>';
                html += '<td>' + esc(a.pause_reason || '—') + '</td><td>' + esc(a.current_call_id || '—') + '</td></tr>';
            });
            html += '</tbody></table>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderSla = function () {
        var self = this;
        self.api('sla_dashboard').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>SLA Dashboard</h3><h4>Live queues</h4><ul class="rcc-sup__checks">';
            (res.live_queues || []).forEach(function (q) {
                html += '<li>' + esc(q.name) + ': ' + esc(q.waiting) + ' waiting, longest ' + esc(q.longest_wait) + 's (target ' + esc(q.sla_target_seconds) + 's)</li>';
            });
            html += '</ul><h4>Conversation SLA</h4><ul>';
            (res.conversation_sla || []).forEach(function (r) {
                html += '<li>' + esc(r.sla_status) + ': ' + esc(r.cnt) + '</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderWfm = function () {
        var self = this;
        self.api('wfm_overview').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var occ = res.occupancy || {};
            var adh = res.adherence || {};
            var html = '<h3>Workforce Management</h3><div class="rcc-sup__kpi-grid">';
            html += self.kpi('Shifts defined', res.shifts) + self.kpi('Assignments today', res.assignments_today);
            html += self.kpi('Occupancy %', occ.occupancy_pct) + self.kpi('Adherence %', adh.adherence_pct);
            html += self.kpi('Active breaks', (res.active_breaks || []).length) + '</div>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderShifts = function () {
        var self = this;
        var today = new Date().toISOString().slice(0, 10);
        Promise.all([
            self.api('shift_list'),
            self.api('shift_assignments', { from: today, to: today }),
            self.fetchAgents()
        ]).then(function (arr) {
            var shifts = arr[0];
            var assigns = arr[1];
            var agents = arr[2];
            if (!shifts.ok) { self.panel.innerHTML = '<p>' + esc(shifts.error) + '</p>'; return; }
            var html = '<h3>Shift Planner</h3><form id="rcc-sup-shift-form" class="rcc-sup__form">';
            html += '<label>Code <input name="code" required></label><label>Name <input name="name" required></label>';
            html += '<label>Start <input name="start_time" type="time" value="09:00"></label><label>End <input name="end_time" type="time" value="17:00"></label>';
            html += '<button type="submit" class="rcc-sup__btn">Save shift</button></form>';
            html += '<h4>Assign shift</h4><form id="rcc-sup-assign-form" class="rcc-sup__form">';
            html += '<label>Date <input name="work_date" type="date" value="' + esc(today) + '"></label>';
            html += '<label>Shift <select name="shift_id">';
            (shifts.shifts || []).forEach(function (s) {
                html += '<option value="' + esc(s.id) + '">' + esc(s.code) + ' ' + esc(s.name) + '</option>';
            });
            html += '</select></label>';
            html += self.agentSelectHtml(agents, 'agent_id');
            html += '<button type="submit" class="rcc-sup__btn">Assign</button></form>';
            html += '<h4>Shifts</h4><ul>';
            (shifts.shifts || []).forEach(function (s) {
                html += '<li>' + esc(s.code) + ' ' + esc(s.name) + ' (' + esc(s.start_time) + '–' + esc(s.end_time) + ')</li>';
            });
            html += '</ul><h4>Today assignments</h4><ul>';
            ((assigns.ok ? assigns.assignments : []) || []).forEach(function (a) {
                html += '<li>' + esc(a.agent_name) + ' → ' + esc(a.shift_name) + ' (' + esc(a.work_date) + ')</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-sup-shift-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                body.start_time = body.start_time + ':00';
                body.end_time = body.end_time + ':00';
                self.api('shift_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderShifts();
                });
            };
            document.getElementById('rcc-sup-assign-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('shift_assign', body).then(function (r) {
                    self.setStatus(r.ok ? 'Assigned' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderShifts();
                });
            };
        });
    };

    RccSupervisorCenter.prototype.renderAttendance = function () {
        var self = this;
        var today = new Date().toISOString().slice(0, 10);
        Promise.all([self.api('attendance_list', { work_date: today }), self.fetchAgents()]).then(function (arr) {
            var res = arr[0];
            var agents = arr[1];
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Attendance — ' + esc(today) + '</h3>';
            html += '<form id="rcc-sup-att-form" class="rcc-sup__form">';
            html += self.agentSelectHtml(agents, 'agent_id');
            html += '<button type="button" class="rcc-sup__btn" data-att="in">Clock in</button>';
            html += '<button type="button" class="rcc-sup__btn" data-att="out">Clock out</button>';
            html += '</form>';
            html += '<table class="rcc-sup__table"><thead><tr><th>Agent</th><th>Status</th><th>In</th><th>Out</th></tr></thead><tbody>';
            (res.records || []).forEach(function (r) {
                html += '<tr><td>' + esc(r.display_name) + '</td><td>' + esc(r.status) + '</td><td>' + esc(r.clock_in || '—') + '</td><td>' + esc(r.clock_out || '—') + '</td></tr>';
            });
            html += '</tbody></table>';
            self.panel.innerHTML = html;
            self.panel.querySelector('[data-att="in"]').onclick = function () {
                var agentId = parseInt(self.panel.querySelector('[name="agent_id"]').value, 10);
                self.api('attendance_clock_in', { agent_id: agentId }).then(function (r) {
                    self.setStatus(r.ok ? 'Clocked in' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderAttendance();
                });
            };
            self.panel.querySelector('[data-att="out"]').onclick = function () {
                var agentId = parseInt(self.panel.querySelector('[name="agent_id"]').value, 10);
                self.api('attendance_clock_out', { agent_id: agentId }).then(function (r) {
                    self.setStatus(r.ok ? 'Clocked out' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderAttendance();
                });
            };
        });
    };

    RccSupervisorCenter.prototype.renderBreaks = function () {
        var self = this;
        Promise.all([self.api('break_list'), self.fetchAgents()]).then(function (arr) {
            var res = arr[0];
            var agents = arr[1];
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Break Management</h3>';
            html += '<form id="rcc-sup-break-form" class="rcc-sup__form">';
            html += self.agentSelectHtml(agents, 'agent_id');
            html += '<label>Type <select name="break_type"><option value="lunch">Lunch</option><option value="short">Short</option><option value="other">Other</option></select></label>';
            html += '<label>Reason <input name="reason" placeholder="optional"></label>';
            html += '<button type="button" class="rcc-sup__btn" data-break="start">Start break</button>';
            html += '<button type="button" class="rcc-sup__btn" data-break="end">End break</button>';
            html += '</form><h4>Active breaks</h4><ul>';
            (res.breaks || []).forEach(function (b) {
                html += '<li>' + esc(b.display_name) + ' — ' + esc(b.break_type) + ' since ' + esc(b.started_at) + '</li>';
            });
            if (!(res.breaks || []).length) html += '<li class="muted">No active breaks</li>';
            html += '</ul>';
            self.panel.innerHTML = html;
            self.panel.querySelector('[data-break="start"]').onclick = function () {
                var fd = new FormData(self.panel.querySelector('#rcc-sup-break-form'));
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('break_start', body).then(function (r) {
                    self.setStatus(r.ok ? 'Break started' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderBreaks();
                });
            };
            self.panel.querySelector('[data-break="end"]').onclick = function () {
                var agentId = parseInt(self.panel.querySelector('[name="agent_id"]').value, 10);
                self.api('break_end', { agent_id: agentId }).then(function (r) {
                    self.setStatus(r.ok ? 'Break ended' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderBreaks();
                });
            };
        });
    };

    RccSupervisorCenter.prototype.renderOccupancy = function () {
        var self = this;
        self.api('occupancy').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Occupancy</h3><p class="rcc-sup__score">' + esc(res.occupancy_pct) + '%</p>';
            html += '<p>' + esc(res.occupied_agents) + ' / ' + esc(res.total_agents) + ' agents occupied</p>';
            html += '<table class="rcc-sup__table"><thead><tr><th>Agent</th><th>Status</th><th>Live calls</th><th>Break (s)</th></tr></thead><tbody>';
            (res.agents || []).forEach(function (a) {
                html += '<tr><td>' + esc(a.display_name) + '</td><td>' + esc(a.status) + '</td><td>' + esc(a.live_calls) + '</td><td>' + esc(a.break_seconds || '—') + '</td></tr>';
            });
            html += '</tbody></table>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderAdherence = function () {
        var self = this;
        var today = new Date().toISOString().slice(0, 10);
        self.api('adherence', { work_date: today }).then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Schedule Adherence</h3><p class="rcc-sup__score">' + esc(res.adherence_pct) + '%</p>';
            html += '<p>Present: ' + esc(res.present) + ', Late: ' + esc(res.late) + ', Absent: ' + esc(res.absent) + ' / ' + esc(res.scheduled) + ' scheduled</p>';
            self.panel.innerHTML = html;
        });
    };

    RccSupervisorCenter.prototype.renderAlerts = function () {
        var self = this;
        Promise.all([self.api('alert_list'), self.api('alert_rules_list')]).then(function (arr) {
            var res = arr[0];
            var rulesRes = arr[1];
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Supervisor Alerts</h3><ul class="rcc-sup__alerts">';
            (res.alerts || []).forEach(function (a) {
                html += '<li class="rcc-sup__alert rcc-sup__alert--' + esc(a.severity) + '"><strong>' + esc(a.title) + '</strong><br>' + esc(a.message || '');
                html += ' <button type="button" class="rcc-sup__btn-sm" data-ack="' + esc(a.id) + '">Ack</button></li>';
            });
            if (!(res.alerts || []).length) html += '<li class="muted">No open alerts</li>';
            html += '</ul>';
            html += '<h4>Alert rules</h4><table class="rcc-sup__table"><thead><tr><th>Rule</th><th>Enabled</th><th>Config</th><th></th></tr></thead><tbody>';
            ((rulesRes.ok ? rulesRes.rules : []) || []).forEach(function (rule) {
                var cfg = rule.config_json || '';
                if (typeof cfg === 'object') cfg = JSON.stringify(cfg);
                html += '<tr data-rule-key="' + esc(rule.rule_key) + '">';
                html += '<td>' + esc(rule.rule_key) + '</td>';
                html += '<td><input type="checkbox" class="rcc-sup-rule-en"' + (parseInt(rule.is_enabled, 10) ? ' checked' : '') + '></td>';
                html += '<td><input type="text" class="rcc-sup-rule-cfg" value="' + esc(cfg) + '" style="width:100%"></td>';
                html += '<td><button type="button" class="rcc-sup__btn-sm rcc-sup-rule-save">Save</button></td></tr>';
            });
            html += '</tbody></table>';
            self.panel.innerHTML = html;
            self.panel.querySelectorAll('[data-ack]').forEach(function (btn) {
                btn.onclick = function () {
                    self.api('alert_acknowledge', { alert_id: parseInt(btn.getAttribute('data-ack'), 10) }).then(function () {
                        self.renderAlerts();
                    });
                };
            });
            self.panel.querySelectorAll('.rcc-sup-rule-save').forEach(function (btn) {
                btn.onclick = function () {
                    var row = btn.closest('tr');
                    var ruleKey = row.getAttribute('data-rule-key');
                    var enabled = row.querySelector('.rcc-sup-rule-en').checked;
                    var cfgText = row.querySelector('.rcc-sup-rule-cfg').value || '{}';
                    var cfg = {};
                    try { cfg = JSON.parse(cfgText); } catch (e) { self.setStatus('Invalid JSON config', 'error'); return; }
                    self.api('alert_rules_save', { rule_key: ruleKey, is_enabled: enabled ? 1 : 0, config: cfg }).then(function (r) {
                        self.setStatus(r.ok ? 'Rule saved' : r.error, r.ok ? 'ok' : 'error');
                    });
                };
            });
        });
    };

    RccSupervisorCenter.prototype.renderReports = function () {
        var self = this;
        var from = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10) + ' 00:00:00';
        var to = new Date().toISOString().slice(0, 10) + ' 23:59:59';
        var html = '<h3>Supervisor Reports</h3><form id="rcc-sup-report-form" class="rcc-sup__form">';
        html += '<label>Type <select name="type"><option value="agents">Agents</option><option value="queues">Queues</option><option value="sla">SLA</option><option value="calls">Calls</option><option value="conversations">Conversations</option><option value="ai">AI</option></select></label>';
        html += '<label>From <input name="from" type="datetime-local" value="' + esc(from.replace(' ', 'T').slice(0, 16)) + '"></label>';
        html += '<label>To <input name="to" type="datetime-local" value="' + esc(to.replace(' ', 'T').slice(0, 16)) + '"></label>';
        html += '<button type="button" class="rcc-sup__btn" data-report="view">Run report</button>';
        html += '<button type="button" class="rcc-sup__btn" data-report="export">Export CSV</button>';
        html += '</form><div id="rcc-sup-report-out"></div>';
        self.panel.innerHTML = html;
        function runReport(exportCsv) {
            var fd = new FormData(self.panel.querySelector('#rcc-sup-report-form'));
            var body = {};
            fd.forEach(function (v, k) { body[k] = v; });
            body.from = (body.from || '').replace('T', ' ') + ':00';
            body.to = (body.to || '').replace('T', ' ') + ':59';
            if (exportCsv) body.export = 1;
            self.api('report', body).then(function (r) {
                var out = document.getElementById('rcc-sup-report-out');
                if (!r.ok) { out.innerHTML = '<p>' + esc(r.error) + '</p>'; return; }
                if (r.download_url) {
                    out.innerHTML = '<p>Exported ' + esc(r.rows) + ' rows. <a href="' + esc(r.download_url) + '" target="_blank" rel="noopener">Download CSV</a></p>';
                    return;
                }
                var rows = r.rows || [];
                if (!rows.length) { out.innerHTML = '<p class="muted">No rows</p>'; return; }
                var keys = Object.keys(rows[0]);
                var tbl = '<table class="rcc-sup__table"><thead><tr>';
                keys.forEach(function (k) { tbl += '<th>' + esc(k) + '</th>'; });
                tbl += '</tr></thead><tbody>';
                rows.slice(0, 100).forEach(function (row) {
                    tbl += '<tr>';
                    keys.forEach(function (k) { tbl += '<td>' + esc(row[k]) + '</td>'; });
                    tbl += '</tr>';
                });
                tbl += '</tbody></table>';
                if (rows.length > 100) tbl += '<p class="muted">Showing first 100 of ' + rows.length + '</p>';
                out.innerHTML = tbl;
            });
        }
        self.panel.querySelector('[data-report="view"]').onclick = function () { runReport(false); };
        self.panel.querySelector('[data-report="export"]').onclick = function () { runReport(true); };
    };

    function boot() {
        var root = document.getElementById('rcc-supervisor-center');
        if (!root) return;
        var sup = new RccSupervisorCenter(root);
        sup.init();
        global.RccSupervisorCenter = sup;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
