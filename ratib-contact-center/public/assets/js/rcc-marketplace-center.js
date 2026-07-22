/**
 * RATEB Contact Center — Marketplace UI (Phase 11).
 */
(function () {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function RccMarketplace(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.lang = root.getAttribute('data-lang') || 'en';
        this.catalog = document.getElementById('rcc-marketplace-catalog');
        this.active = document.getElementById('rcc-marketplace-active');
        this.status = document.getElementById('rcc-marketplace-status');
    }

    RccMarketplace.prototype.api = function (action, body) {
        return fetch(this.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(Object.assign({}, body || {}, { tenant_id: this.tenantId }))
        }).then(function (r) { return r.json(); });
    };

    RccMarketplace.prototype.refresh = function () {
        var self = this;
        self.api('catalog').then(function (res) {
            var html = '';
            (res.addons || []).forEach(function (a) {
                var name = self.lang === 'ar' && a.name_ar ? a.name_ar : a.name;
                html += '<article class="rcc-marketplace__card"><h4>' + esc(name) + '</h4><p>' + esc(a.category) + '</p>';
                html += '<p><strong>' + esc(a.price_amount) + ' ' + esc(a.currency) + '</strong> / ' + esc(a.billing_type) + '</p>';
                html += '<button type="button" data-aid="' + a.id + '">Subscribe</button></article>';
            });
            self.catalog.innerHTML = html;
            self.catalog.querySelectorAll('[data-aid]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.api('subscribe', { addon_id: parseInt(btn.getAttribute('data-aid'), 10) }).then(function (r) {
                        if (self.status) self.status.textContent = r.ok ? 'Subscribed' : r.error;
                        if (r.ok) self.renderActive();
                    });
                });
            });
        });
        self.renderActive();
    };

    RccMarketplace.prototype.renderActive = function () {
        var self = this;
        self.api('subscribed').then(function (res) {
            var html = '<ul>';
            (res.addons || []).forEach(function (a) {
                var name = self.lang === 'ar' && a.name_ar ? a.name_ar : a.name;
                html += '<li>' + esc(name) + ' — ' + esc(a.status) + '</li>';
            });
            html += '</ul>';
            self.active.innerHTML = html;
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rcc-marketplace-center');
        if (root) new RccMarketplace(root).refresh();
    });
})();
