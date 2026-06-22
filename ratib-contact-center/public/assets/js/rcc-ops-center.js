/**
 * RATIB Contact Center — Production Operations Center UI (Phase 8).
 * WebSocket-driven updates; no polling when WS available.
 */
(function (global) {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function RccOpsCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.wsUrl = (root.getAttribute('data-ws') || '').trim();
        this.route = root.getAttribute('data-route') || 'health';
        this.canManageTenants = root.getAttribute('data-can-manage-tenants') === '1';
        this.panel = document.getElementById('rcc-ops-panel');
        this.status = document.getElementById('rcc-ops-status');
        this._client = null;
    }

    RccOpsCenter.prototype._usePolling = function () {
        var u = (this.wsUrl || '').toLowerCase();
        return !u || u === 'polling' || u === 'off';
    };

    RccOpsCenter.prototype._connectRealtime = function () {
        var self = this;
        if (self._usePolling() || !global.RccRealtimeClient) {
            return;
        }
        if (self._client && self._client.disconnect) {
            self._client.disconnect();
        }
        self._client = new global.RccRealtimeClient({
            url: self.wsUrl,
            tenantId: self.tenantId,
            rooms: ['tenant:' + self.tenantId, 'dashboard:' + self.tenantId],
            onEvent: function (ev) {
                if (!ev || !ev.type || ev.type.indexOf('OPS_') !== 0) {
                    return;
                }
                self.setStatus('Live: ' + ev.type, 'info');
                if (self.route === 'health' || self.route === 'hub' || self.route === 'golive') {
                    self.renderPanel();
                }
            }
        });
        self._client.connect();
    };

    RccOpsCenter.prototype.init = function () {
        var self = this;
        self.renderTenantBar();
        self.renderPanel();
        self._connectRealtime();
    };

    RccOpsCenter.prototype.renderTenantBar = function () {
        var self = this;
        var bar = document.getElementById('rcc-ops-tenant-bar');
        if (!bar || !self.canManageTenants) {
            return;
        }
        self.api('tenants_list').then(function (res) {
            var tenants = (res && res.tenants) || [];
            if (!res.ok || tenants.length < 2) {
                return;
            }
            var html = '<label>Tenant <select id="rcc-ops-tenant-select">';
            tenants.forEach(function (t) {
                html += '<option value="' + esc(t.id) + '"' + (parseInt(t.id, 10) === self.tenantId ? ' selected' : '') + '>';
                html += esc(t.name || t.code) + '</option>';
            });
            html += '</select></label>';
            bar.innerHTML = html;
            bar.hidden = false;
            document.getElementById('rcc-ops-tenant-select').onchange = function (ev) {
                self.tenantId = parseInt(ev.target.value, 10) || self.tenantId;
                self.root.setAttribute('data-tenant', String(self.tenantId));
                self._connectRealtime();
                self.renderPanel();
            };
        });
    };

    RccOpsCenter.prototype.setStatus = function (msg, kind) {
        if (!this.status) {
            return;
        }
        this.status.className = 'rcc-ops__status rcc-ops__status--' + (kind || 'info');
        this.status.textContent = msg;
    };

    RccOpsCenter.prototype.api = function (action, body) {
        var self = this;
        var payload = Object.assign({}, body || {}, { tenant_id: self.tenantId });
        return fetch(self.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json();
        });
    };

    RccOpsCenter.prototype.renderPanel = function () {
        var self = this;
        var fn = {
            health: self.renderHealth,
            pbx: self.renderPbx,
            sip: self.renderSip,
            queues: self.renderQueues,
            ivr: self.renderIvr,
            agents: self.renderAgents,
            webrtc: self.renderWebrtc,
            ami: self.renderAmi,
            hub: self.renderHub,
            golive: self.renderGolive
        }[self.route];
        if (fn) {
            fn.call(self);
        }
    };

    RccOpsCenter.prototype.renderHealth = function () {
        var self = this;
        self.api('health_center').then(function (res) {
            if (!res.ok) {
                self.setStatus(res.error || 'Failed', 'error');
                return;
            }
            var html = '<h3>Health Center</h3><p class="rcc-ops__score">' + esc(res.percent) + '% (' + res.score + '/' + res.max + ')</p><ul class="rcc-ops__checks">';
            (res.checks || []).forEach(function (c) {
                html += '<li class="' + (c.pass ? 'pass' : 'fail') + '">' + esc(c.name) + ': ' + esc(c.detail) + '</li>';
            });
            html += '</ul><button type="button" class="rcc-ops__btn" id="rcc-ops-refresh-health">Refresh</button>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-refresh-health').onclick = function () { self.renderHealth(); };
        });
    };

    RccOpsCenter.prototype.renderPbx = function () {
        var self = this;
        self.api('pbx_list').then(function (res) {
            if (!res.ok) {
                self.panel.innerHTML = '<p class="error">' + esc(res.error) + '</p>';
                return;
            }
            var servers = res.servers || [];
            var html = '<h3>PBX Deployment Wizard</h3><p class="muted">Dialplan package: ' + esc((res.dialplan && res.dialplan.path) || '') + '</p>';
            html += '<form id="rcc-ops-pbx-form" class="rcc-ops__form">';
            html += '<label>Name <input name="name" required></label>';
            html += '<label>AMI Host <input name="ami_host" required></label>';
            html += '<label>AMI Port <input name="ami_port" type="number" value="5038"></label>';
            html += '<label>AMI User <input name="ami_username" required></label>';
            html += '<label>Secret env ref <input name="ami_secret_ref" value="RCC_AMI_PASS" required></label>';
            html += '<label>SIP Domain <input name="sip_domain" required></label>';
            html += '<label>WSS URI <input name="wss_uri"></label>';
            html += '<button type="submit" class="rcc-ops__btn">Save PBX</button></form>';
            html += '<h4>Servers</h4><ul>';
            servers.forEach(function (s) {
                html += '<li>' + esc(s.name) + ' — ' + esc(s.ami_host) + ':' + esc(s.ami_port) + ' [' + esc(s.status) + ']';
                html += ' <button type="button" data-test="' + s.id + '" class="rcc-ops__btn-sm">Test AMI</button>';
                html += ' <button type="button" data-act="' + s.id + '" class="rcc-ops__btn-sm">Activate</button></li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-pbx-form').onsubmit = function (e) {
                e.preventDefault();
                var fd = new FormData(e.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                body.ami_port = parseInt(body.ami_port, 10);
                self.api('pbx_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'PBX saved' : (r.error || 'Error'), r.ok ? 'ok' : 'error');
                    if (r.ok) { self.renderPbx(); }
                });
            };
            self.panel.querySelectorAll('[data-test]').forEach(function (btn) {
                btn.onclick = function () {
                    self.api('pbx_test', { pbx_id: parseInt(btn.getAttribute('data-test'), 10) }).then(function (r) {
                        self.setStatus(r.message || r.error || '', r.ok ? 'ok' : 'error');
                    });
                };
            });
            self.panel.querySelectorAll('[data-act]').forEach(function (btn) {
                btn.onclick = function () {
                    self.api('pbx_activate', { pbx_id: parseInt(btn.getAttribute('data-act'), 10) }).then(function (r) {
                        self.setStatus(r.ok ? 'Activated' : (r.error || 'Error'), r.ok ? 'ok' : 'error');
                        if (r.ok) { self.renderPbx(); }
                    });
                };
            });
        });
    };

    RccOpsCenter.prototype.renderSip = function () {
        var self = this;
        self.api('sip_list').then(function (res) {
            if (!res.ok) {
                self.panel.innerHTML = '<p>' + esc(res.error) + '</p>';
                return;
            }
            var html = '<h3>SIP Extensions</h3><form id="rcc-ops-sip-form" class="rcc-ops__form">';
            html += '<label>Extension <input name="extension" required></label>';
            html += '<label>Agent ID <input name="agent_id" type="number"></label>';
            html += '<label>SIP Domain <input name="sip_domain" required></label>';
            html += '<label>Password ref <input name="sip_password_ref" placeholder="RCC_SIP_EXT_xxx"></label>';
            html += '<label>WSS URI <input name="wss_uri"></label>';
            html += '<button type="submit" class="rcc-ops__btn">Add extension</button></form><ul>';
            (res.extensions || []).forEach(function (e) {
                html += '<li>' + esc(e.extension) + ' @ ' + esc(e.sip_domain) + ' [' + esc(e.status) + ']</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-sip-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('sip_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) { self.renderSip(); }
                });
            };
        });
    };

    RccOpsCenter.prototype.renderQueues = function () {
        var self = this;
        Promise.all([self.api('queue_list'), self.api('agent_list')]).then(function (results) {
            var qRes = results[0];
            var aRes = results[1];
            if (!qRes.ok) {
                self.panel.innerHTML = '<p>' + esc(qRes.error) + '</p>';
                return;
            }
            var agents = (aRes.ok ? aRes.agents : []) || [];
            var html = '<h3>Queue Manager</h3><form id="rcc-ops-queue-form" class="rcc-ops__form">';
            html += '<label>Code <input name="code" required></label>';
            html += '<label>Name <input name="name" required></label>';
            html += '<label>SLA seconds <input name="sla_target_seconds" type="number" value="300"></label>';
            html += '<button type="submit" class="rcc-ops__btn">Create queue</button></form>';
            (qRes.queues || []).forEach(function (q) {
                var members = q.member_agent_ids || [];
                html += '<div class="rcc-ops__queue-block"><h4>' + esc(q.code) + ' — ' + esc(q.name) + ' (SLA ' + esc(q.sla_target_seconds) + 's)</h4>';
                html += '<form class="rcc-ops__form rcc-ops__queue-members" data-queue-id="' + esc(q.id) + '">';
                html += '<fieldset><legend>Members</legend>';
                if (agents.length === 0) {
                    html += '<p class="muted">No agents — provision agents first.</p>';
                }
                agents.forEach(function (a) {
                    var checked = members.indexOf(parseInt(a.id, 10)) >= 0 || members.indexOf(a.id) >= 0 ? ' checked' : '';
                    html += '<label class="rcc-ops__check"><input type="checkbox" name="agent" value="' + esc(a.id) + '"' + checked + '> ';
                    html += esc(a.display_name) + ' (ext ' + esc(a.extension) + ')</label>';
                });
                html += '</fieldset><button type="submit" class="rcc-ops__btn-sm">Save members</button></form></div>';
            });
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-queue-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                body.sla_target_seconds = parseInt(body.sla_target_seconds, 10);
                self.api('queue_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Queue saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) { self.renderQueues(); }
                });
            };
            self.panel.querySelectorAll('.rcc-ops__queue-members').forEach(function (form) {
                form.onsubmit = function (ev) {
                    ev.preventDefault();
                    var queueId = parseInt(form.getAttribute('data-queue-id'), 10);
                    var ids = [];
                    form.querySelectorAll('input[name="agent"]:checked').forEach(function (cb) {
                        ids.push(parseInt(cb.value, 10));
                    });
                    self.api('queue_members_save', { queue_id: queueId, agent_ids: ids }).then(function (r) {
                        self.setStatus(r.ok ? 'Members saved' : r.error, r.ok ? 'ok' : 'error');
                        if (r.ok) { self.renderQueues(); }
                    });
                };
            });
        });
    };

    RccOpsCenter.prototype.renderIvr = function () {
        var self = this;
        self.api('ivr_list').then(function (res) {
            if (!res.ok) {
                self.panel.innerHTML = '<p>' + esc(res.error) + '</p>';
                return;
            }
            var html = '<h3>IVR Production Builder</h3>';
            html += '<form id="rcc-ops-ivr-form" class="rcc-ops__form">';
            html += '<label>Flow name <input name="name" value="Production IVR"></label>';
            html += '<label>First node type <select name="node_type"><option value="play_message">play_message</option><option value="route_call">route_call</option></select></label>';
            html += '<label>Message / queue code <input name="payload" placeholder="Welcome message or queue code"></label>';
            html += '<button type="submit" class="rcc-ops__btn">Save flow</button></form><ul>';
            (res.flows || []).forEach(function (f) {
                html += '<li>' + esc(f.name) + (f.is_active ? ' <strong>(active)</strong>' : '');
                if (!f.is_active) {
                    html += ' <button type="button" data-pub="' + f.id + '" class="rcc-ops__btn-sm">Publish</button>';
                }
                html += '</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-ivr-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var type = fd.get('node_type');
                var payload = fd.get('payload');
                var nodes = [{ type: type, payload: type === 'route_call' ? { queue_code: payload } : { message: payload }, sort_order: 0 }];
                self.api('ivr_save', { flow: { name: fd.get('name') }, nodes: nodes }).then(function (r) {
                    self.setStatus(r.ok ? 'IVR saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) { self.renderIvr(); }
                });
            };
            self.panel.querySelectorAll('[data-pub]').forEach(function (btn) {
                btn.onclick = function () {
                    self.api('ivr_publish', { flow_id: parseInt(btn.getAttribute('data-pub'), 10) }).then(function (r) {
                        self.setStatus(r.ok ? 'Published' : r.error, r.ok ? 'ok' : 'error');
                        if (r.ok) { self.renderIvr(); }
                    });
                };
            });
        });
    };

    RccOpsCenter.prototype.renderAgents = function () {
        var self = this;
        self.api('agent_list').then(function (res) {
            if (!res.ok) {
                self.panel.innerHTML = '<p>' + esc(res.error) + '</p>';
                return;
            }
            var html = '<h3>Agent Provisioning</h3><form id="rcc-ops-agent-form" class="rcc-ops__form">';
            html += '<label>Display name <input name="display_name" required></label>';
            html += '<label>Email <input name="email" type="email" required></label>';
            html += '<label>Extension <input name="extension" required></label>';
            html += '<label>SIP domain <input name="sip_domain"></label>';
            html += '<label><input type="checkbox" name="provision_sip" value="1"> Provision SIP extension</label>';
            html += '<button type="submit" class="rcc-ops__btn">Provision agent</button></form><ul>';
            (res.agents || []).forEach(function (a) {
                html += '<li>' + esc(a.display_name) + ' — ext ' + esc(a.extension) + ' [' + esc(a.status) + ']</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-agent-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                body.provision_sip = !!fd.get('provision_sip');
                self.api('agent_provision', body).then(function (r) {
                    self.setStatus(r.ok ? 'Agent provisioned' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) { self.renderAgents(); }
                });
            };
        });
    };

    RccOpsCenter.prototype.renderWebrtc = function () {
        var self = this;
        self.api('diag_webrtc', {}).then(function (res) {
            self.panel.innerHTML = '<h3>WebRTC Diagnostics</h3><pre>' + esc(JSON.stringify(res, null, 2)) + '</pre>';
        });
    };

    RccOpsCenter.prototype.renderAmi = function () {
        var self = this;
        self.api('diag_ami').then(function (res) {
            self.panel.innerHTML = '<h3>AMI Diagnostics</h3><pre>' + esc(JSON.stringify(res, null, 2)) + '</pre><button type="button" class="rcc-ops__btn" id="rcc-ops-rerun-ami">Re-run</button>';
            document.getElementById('rcc-ops-rerun-ami').onclick = function () { self.renderAmi(); };
        });
    };

    RccOpsCenter.prototype.renderHub = function () {
        var self = this;
        self.api('hub_status').then(function (res) {
            var html = '<h3>Realtime Hub Monitor</h3><pre>' + esc(JSON.stringify(res, null, 2)) + '</pre>';
            html += '<button type="button" class="rcc-ops__btn" id="rcc-ops-hub-start">Start hub</button>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-hub-start').onclick = function () {
                self.api('hub_start').then(function (r) {
                    self.setStatus(r.message || (r.running ? 'Running' : 'Not running'), r.running ? 'ok' : 'error');
                    self.renderHub();
                });
            };
        });
    };

    RccOpsCenter.prototype.renderGolive = function () {
        var self = this;
        self.api('checklist_list').then(function (res) {
            if (!res.ok) {
                self.panel.innerHTML = '<p>' + esc(res.error) + '</p>';
                return;
            }
            var sum = res.summary || {};
            var html = '<h3>Go-Live Checklist</h3><p>' + esc(sum.pass) + '/' + esc(sum.required) + ' passed';
            if (sum.ready) { html += ' <strong class="pass">READY</strong>'; }
            html += '</p><button type="button" class="rcc-ops__btn" id="rcc-ops-auto-verify">Auto-verify</button><ul class="rcc-ops__checks">';
            (res.items || []).forEach(function (item) {
                html += '<li class="' + esc(item.status) + '"><strong>' + esc(item.title) + '</strong> — ' + esc(item.status);
                html += ' <button type="button" data-slug="' + esc(item.slug) + '" data-st="pass" class="rcc-ops__btn-sm">Pass</button>';
                html += ' <button type="button" data-slug="' + esc(item.slug) + '" data-st="fail" class="rcc-ops__btn-sm">Fail</button></li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-ops-auto-verify').onclick = function () {
                self.api('checklist_auto_verify').then(function (r) {
                    self.setStatus('Auto-verify complete', 'ok');
                    self.renderGolive();
                });
            };
            self.panel.querySelectorAll('[data-slug]').forEach(function (btn) {
                btn.onclick = function () {
                    self.api('checklist_update', { step_slug: btn.getAttribute('data-slug'), status: btn.getAttribute('data-st') }).then(function () {
                        self.renderGolive();
                    });
                };
            });
        });
    };

    function boot() {
        var root = document.getElementById('rcc-ops-center');
        if (!root) {
            return;
        }
        var ops = new RccOpsCenter(root);
        ops.init();
        global.RccOpsCenter = ops;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
