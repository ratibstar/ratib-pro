/**
 * RATEB Contact Center — Enterprise CRM UI (Phase 10A).
 */
(function (global) {
    'use strict';

    var LIVE = ['CRM_', 'TICKET_', 'AI_'];

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function live(type) {
        for (var i = 0; i < LIVE.length; i++) {
            if (type && type.indexOf(LIVE[i]) === 0) return true;
        }
        return false;
    }

    function RccCrmCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.wsUrl = (root.getAttribute('data-ws') || '').trim();
        this.route = root.getAttribute('data-route') || 'accounts';
        this.canManageTenants = root.getAttribute('data-can-manage-tenants') === '1';
        this.panel = document.getElementById('rcc-crm-panel');
        this.status = document.getElementById('rcc-crm-status');
        this._client = null;
    }

    RccCrmCenter.prototype.api = function (action, body) {
        var self = this;
        return fetch(self.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(Object.assign({}, body || {}, { tenant_id: self.tenantId }))
        }).then(function (r) { return r.json(); });
    };

    RccCrmCenter.prototype.setStatus = function (msg, kind) {
        if (!this.status) return;
        this.status.className = 'rcc-crm__status rcc-crm__status--' + (kind || 'info');
        this.status.textContent = msg;
    };

    RccCrmCenter.prototype._connectRealtime = function () {
        var self = this;
        var u = (self.wsUrl || '').toLowerCase();
        if (!u || u === 'polling' || u === 'off' || !global.RccRealtimeClient) return;
        if (self._client && self._client.disconnect) self._client.disconnect();
        self._client = new global.RccRealtimeClient({
            url: self.wsUrl,
            tenantId: self.tenantId,
            rooms: ['tenant:' + self.tenantId, 'crm:' + self.tenantId],
            onEvent: function (ev) {
                if (!live(ev.type)) return;
                self.setStatus('Live: ' + ev.type, 'info');
                self.renderPanel();
            }
        });
        self._client.connect();
    };

    RccCrmCenter.prototype.renderPanel = function () {
        var fn = {
            accounts: this.renderAccounts,
            contacts: this.renderContacts,
            timeline: this.renderTimeline,
            tags: this.renderTags,
            documents: this.renderDocuments,
            sync: this.renderSync
        }[this.route];
        if (fn) fn.call(this);
    };

    RccCrmCenter.prototype.renderAccounts = function () {
        var self = this;
        self.api('accounts_list', { q: '' }).then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Accounts</h3><form id="rcc-crm-acc-form" class="rcc-crm__form">';
            html += '<label>Name <input name="name" required></label>';
            html += '<label>Email <input name="email" type="email"></label>';
            html += '<label>Phone <input name="phone"></label>';
            html += '<button type="submit" class="rcc-crm__btn">Save account</button></form><table class="rcc-crm__table"><thead><tr><th>No</th><th>Name</th><th>Status</th></tr></thead><tbody>';
            (res.accounts || []).forEach(function (a) {
                html += '<tr><td>' + esc(a.account_no) + '</td><td>' + esc(a.name) + '</td><td>' + esc(a.status) + '</td></tr>';
            });
            html += '</tbody></table>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-crm-acc-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('account_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderAccounts();
                });
            };
        });
    };

    RccCrmCenter.prototype.renderContacts = function () {
        var self = this;
        self.api('contacts_list').then(function (res) {
            if (!res.ok) { self.panel.innerHTML = '<p>' + esc(res.error) + '</p>'; return; }
            var html = '<h3>Contacts</h3><form id="rcc-crm-contact-form" class="rcc-crm__form">';
            html += '<label>Name <input name="full_name" required></label>';
            html += '<label>Email <input name="email"></label>';
            html += '<label>Phone <input name="phone_primary"></label>';
            html += '<button type="submit" class="rcc-crm__btn">Save contact</button></form><ul>';
            (res.contacts || []).forEach(function (c) {
                html += '<li>' + esc(c.full_name) + ' — ' + esc(c.phone_primary || c.email || '') + '</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-crm-contact-form').onsubmit = function (ev) {
                ev.preventDefault();
                var fd = new FormData(ev.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('contact_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Saved' : r.error, r.ok ? 'ok' : 'error');
                    if (r.ok) self.renderContacts();
                });
            };
        });
    };

    RccCrmCenter.prototype.renderTimeline = function () {
        var self = this;
        var html = '<h3>Contact Timeline</h3><label>Contact ID <input id="rcc-crm-cid" type="number" min="1"></label>';
        html += '<button type="button" class="rcc-crm__btn" id="rcc-crm-load-tl">Load</button><div id="rcc-crm-tl-out"></div>';
        self.panel.innerHTML = html;
        document.getElementById('rcc-crm-load-tl').onclick = function () {
            var cid = parseInt(document.getElementById('rcc-crm-cid').value, 10);
            Promise.all([self.api('timeline', { contact_id: cid }), self.api('interactions', { contact_id: cid })]).then(function (arr) {
                var out = document.getElementById('rcc-crm-tl-out');
                var html2 = '<h4>Activities</h4><ul>';
                ((arr[0].ok ? arr[0].timeline : []) || []).forEach(function (t) {
                    html2 += '<li>' + esc(t.title) + ' — ' + esc(t.occurred_at) + '</li>';
                });
                html2 += '</ul><h4>Interactions</h4><ul>';
                ((arr[1].ok ? arr[1].interactions : []) || []).forEach(function (i) {
                    html2 += '<li>' + esc(i.kind) + ' #' + esc(i.id) + ' — ' + esc(i.occurred_at) + '</li>';
                });
                html2 += '</ul>';
                out.innerHTML = html2;
            });
        };
    };

    RccCrmCenter.prototype.renderTags = function () {
        var self = this;
        var html = '<h3>Tags</h3><label>Contact ID <input id="rcc-crm-tag-cid" type="number"></label>';
        html += '<label>Tag <input id="rcc-crm-tag-val"></label>';
        html += '<button type="button" class="rcc-crm__btn" id="rcc-crm-tag-add">Add</button><div id="rcc-crm-tags-out"></div>';
        self.panel.innerHTML = html;
        document.getElementById('rcc-crm-tag-add').onclick = function () {
            var cid = parseInt(document.getElementById('rcc-crm-tag-cid').value, 10);
            var tag = document.getElementById('rcc-crm-tag-val').value;
            self.api('tag_add', { contact_id: cid, tag: tag }).then(function () {
                self.api('tags_list', { contact_id: cid }).then(function (r) {
                    var out = document.getElementById('rcc-crm-tags-out');
                    out.innerHTML = '<ul>' + (r.tags || []).map(function (t) { return '<li>' + esc(t.tag) + '</li>'; }).join('') + '</ul>';
                });
            });
        };
    };

    RccCrmCenter.prototype.renderDocuments = function () {
        var self = this;
        var html = '<h3>Documents</h3><label>Contact ID <input id="rcc-crm-doc-cid" type="number"></label>';
        html += '<button type="button" class="rcc-crm__btn" id="rcc-crm-doc-load">List</button><ul id="rcc-crm-doc-out"></ul>';
        self.panel.innerHTML = html;
        document.getElementById('rcc-crm-doc-load').onclick = function () {
            var cid = parseInt(document.getElementById('rcc-crm-doc-cid').value, 10);
            self.api('documents_list', { contact_id: cid }).then(function (r) {
                document.getElementById('rcc-crm-doc-out').innerHTML = (r.documents || []).map(function (d) {
                    return '<li>' + esc(d.file_name) + ' (' + esc(d.file_size) + ' bytes)</li>';
                }).join('');
            });
        };
    };

    RccCrmCenter.prototype.renderSync = function () {
        var self = this;
        var html = '<h3>ERP Sync</h3><label>ERP Company ID <input id="rcc-crm-erp-id" type="number" min="1"></label>';
        html += '<button type="button" class="rcc-crm__btn" id="rcc-crm-erp-sync">Sync</button><pre id="rcc-crm-erp-out"></pre>';
        self.panel.innerHTML = html;
        document.getElementById('rcc-crm-erp-sync').onclick = function () {
            var id = parseInt(document.getElementById('rcc-crm-erp-id').value, 10);
            self.api('erp_sync', { erp_company_id: id }).then(function (r) {
                document.getElementById('rcc-crm-erp-out').textContent = r.ok ? JSON.stringify(r.account, null, 2) : r.error;
            });
        };
    };

    RccCrmCenter.prototype.init = function () {
        this.renderPanel();
        this._connectRealtime();
    };

    function boot() {
        var root = document.getElementById('rcc-crm-center');
        if (!root) return;
        var crm = new RccCrmCenter(root);
        crm.init();
        global.RccCrmCenter = crm;
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})(typeof window !== 'undefined' ? window : globalThis);
