/**
 * RATIB Contact Center — Executive Command Center (Phase 10H).
 */
(function (global) {
    'use strict';

    var LIVE = ['SUPERVISOR_', 'QUEUE_', 'SLA_', 'TICKET_', 'AI_', 'CRM_', 'ANALYTICS_'];

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function RccCommandCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.wsUrl = (root.getAttribute('data-ws') || '').trim();
        this.widgets = document.getElementById('rcc-cmd-widgets');
        this.wall = document.getElementById('rcc-cmd-wall');
        this.status = document.getElementById('rcc-cmd-status');
        this._client = null;
    }

    RccCommandCenter.prototype.api = function (action, body) {
        return fetch(this.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(Object.assign({}, body || {}, { tenant_id: this.tenantId }))
        }).then(function (r) { return r.json(); });
    };

    RccCommandCenter.prototype.setStatus = function (msg) {
        if (this.status) this.status.textContent = msg;
    };

    RccCommandCenter.prototype._connectRealtime = function () {
        var self = this;
        var u = (self.wsUrl || '').toLowerCase();
        if (!u || u === 'polling' || !global.RccRealtimeClient) return;
        if (self._client && self._client.disconnect) self._client.disconnect();
        self._client = new global.RccRealtimeClient({
            url: self.wsUrl,
            tenantId: self.tenantId,
            rooms: ['tenant:' + self.tenantId, 'dashboard:' + self.tenantId, 'command:' + self.tenantId],
            onEvent: function (ev) {
                for (var i = 0; i < LIVE.length; i++) {
                    if (ev.type && ev.type.indexOf(LIVE[i]) === 0) {
                        self.setStatus('Live: ' + ev.type);
                        self.refresh();
                        return;
                    }
                }
            }
        });
        self._client.connect();
    };

    RccCommandCenter.prototype.kpiCard = function (label, val, status) {
        return '<div class="rcc-cmd__kpi rcc-cmd__kpi--' + esc(status || 'ok') + '"><span class="rcc-cmd__kpi-val">' + esc(val) + '</span><span class="rcc-cmd__kpi-label">' + esc(label) + '</span></div>';
    };

    RccCommandCenter.prototype.refresh = function () {
        var self = this;
        self.api('executive_dashboard').then(function (res) {
            if (!res.ok) {
                self.widgets.innerHTML = '<p>' + esc(res.error) + '</p>';
                return;
            }
            var live = res.live || {};
            var agents = live.agents || {};
            var queues = live.queues || {};
            var html = '';
            html += self.kpiCard('Agents ready', agents.ready, 'ok');
            html += self.kpiCard('Agents busy', agents.busy, 'warn');
            html += self.kpiCard('Waiting calls', queues.total_waiting, queues.total_waiting > 0 ? 'warn' : 'ok');
            html += self.kpiCard('SLA red queues', queues.sla_red_queues, queues.sla_red_queues > 0 ? 'critical' : 'ok');
            html += self.kpiCard('Occupancy %', live.occupancy_pct, 'ok');
            html += self.kpiCard('Open tickets', live.open_tickets, live.open_tickets > 50 ? 'warn' : 'ok');
            html += self.kpiCard('CRM accounts', live.crm_accounts, 'ok');
            self.widgets.innerHTML = html;

            var kpiHtml = '<h3>KPI Status</h3><ul>';
            (res.kpis || []).forEach(function (k) {
                kpiHtml += '<li class="rcc-cmd__kpi-li--' + esc(k.status) + '">' + esc(k.kpi.name) + ': ' + esc(k.current) + ' ' + esc(k.kpi.unit || '') + '</li>';
            });
            kpiHtml += '</ul>';
            self.wall.innerHTML = kpiHtml;
            self.setStatus('Updated ' + (res.timestamp || ''));
        });
    };

    RccCommandCenter.prototype.init = function () {
        this.refresh();
        this._connectRealtime();
        var self = this;
        setInterval(function () {
            if ((self.wsUrl || '').toLowerCase() === 'polling' || !self.wsUrl) self.refresh();
        }, 30000);
    };

    function boot() {
        var root = document.getElementById('rcc-command-center');
        if (!root) return;
        var cmd = new RccCommandCenter(root);
        cmd.init();
        global.RccCommandCenter = cmd;
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})(typeof window !== 'undefined' ? window : globalThis);
