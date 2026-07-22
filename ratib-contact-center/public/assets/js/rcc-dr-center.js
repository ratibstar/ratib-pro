/**
 * RATEB Contact Center — Backup & DR UI (Phase 11).
 */
(function (global) {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function RccDrCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.route = root.getAttribute('data-route') || 'backups';
        this.panel = document.getElementById('rcc-dr-panel');
        this.status = document.getElementById('rcc-dr-status');
    }

    RccDrCenter.prototype.api = function (action, body) {
        return fetch(this.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(Object.assign({}, body || {}, { tenant_id: this.tenantId }))
        }).then(function (r) { return r.json(); });
    };

    RccDrCenter.prototype.render = function () {
        var map = { backups: this.renderBackups, restore: this.renderRestore, monitors: this.renderMonitors, clusters: this.renderClusters };
        var fn = map[this.route];
        if (fn) fn.call(this);
    };

    RccDrCenter.prototype.renderBackups = function () {
        var self = this;
        self.api('backups_list').then(function (res) {
            var html = '<button type="button" class="rcc-dr__btn" id="rcc-backup-run">Run backup now</button>';
            html += '<table class="rcc-dr__table"><tr><th>ID</th><th>Type</th><th>Status</th><th>Size</th><th>Started</th></tr>';
            (res.backups || []).forEach(function (b) {
                html += '<tr><td>' + b.id + '</td><td>' + esc(b.backup_type) + '</td><td>' + esc(b.status) + '</td><td>' + esc(b.file_size) + '</td><td>' + esc(b.started_at) + '</td></tr>';
            });
            html += '</table>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-backup-run').addEventListener('click', function () {
                self.api('backup_start', { type: 'tenant' }).then(function (r) {
                    if (self.status) self.status.textContent = r.ok ? 'Backup completed' : r.error;
                    if (r.ok) self.renderBackups();
                });
            });
        });
    };

    RccDrCenter.prototype.renderRestore = function () {
        var self = this;
        self.api('backups_list').then(function (res) {
            var html = '<p>Select backup to queue point-in-time restore (supervisor approval required).</p><ul>';
            (res.backups || []).filter(function (b) { return b.status === 'completed'; }).forEach(function (b) {
                html += '<li>#' + b.id + ' — ' + esc(b.started_at) +
                    ' <button type="button" class="rcc-dr__btn" data-bid="' + b.id + '">Queue restore</button></li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            self.panel.querySelectorAll('[data-bid]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.api('restore_queue', { backup_id: parseInt(btn.getAttribute('data-bid'), 10) }).then(function (r) {
                        if (self.status) self.status.textContent = r.ok ? 'Restore queued' : r.error;
                    });
                });
            });
        });
    };

    RccDrCenter.prototype.renderMonitors = function () {
        var self = this;
        self.api('monitors_list').then(function (res) {
            self.panel.innerHTML = '<button type="button" class="rcc-dr__btn" id="rcc-mon-run">Run all checks</button><ul id="rcc-mon-list"></ul>';
            var list = document.getElementById('rcc-mon-list');
            (res.monitors || []).forEach(function (m) {
                list.innerHTML += '<li>' + esc(m.name) + ' (' + esc(m.monitor_type) + ')</li>';
            });
            document.getElementById('rcc-mon-run').addEventListener('click', function () {
                self.api('monitors_run').then(function (r) {
                    var html = '<h4>Results</h4><ul>';
                    (r.results || []).forEach(function (x) {
                        html += '<li>' + esc(x.name || x.monitor_id) + ': <strong>' + esc(x.status) + '</strong> ' + esc(x.message || '') + '</li>';
                    });
                    html += '</ul>';
                    self.panel.innerHTML = html;
                });
            });
        });
    };

    RccDrCenter.prototype.renderClusters = function () {
        var self = this;
        self.api('clusters_list').then(function (res) {
            var html = '<button type="button" class="rcc-dr__btn" id="rcc-cluster-create">Create HA cluster</button><ul>';
            (res.clusters || []).forEach(function (c) {
                html += '<li>' + esc(c.name) + ' — ' + esc(c.status) + ' (' + esc(c.ha_mode) + ')</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
            document.getElementById('rcc-cluster-create').addEventListener('click', function () {
                self.api('cluster_create', { name: 'RCC HA ' + Date.now() }).then(function (r) {
                    if (r.ok) self.renderClusters();
                });
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rcc-dr-center');
        if (root) new RccDrCenter(root).render();
    });
})(window);
