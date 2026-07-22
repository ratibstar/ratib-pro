/**
 * RATEB Contact Center — SaaS Billing UI (Phase 11).
 */
(function (global) {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function RccBillingCenter(root) {
        this.root = root;
        this.tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        this.apiBase = root.getAttribute('data-api') || '';
        this.route = root.getAttribute('data-route') || 'dashboard';
        this.lang = root.getAttribute('data-lang') || 'en';
        this.panel = document.getElementById('rcc-billing-panel');
        this.status = document.getElementById('rcc-billing-status');
    }

    RccBillingCenter.prototype.api = function (action, body) {
        return fetch(this.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(Object.assign({}, body || {}, { tenant_id: this.tenantId }))
        }).then(function (r) { return r.json(); });
    };

    RccBillingCenter.prototype.setStatus = function (msg, ok) {
        if (!this.status) return;
        this.status.className = 'rcc-billing__status' + (ok === false ? ' rcc-billing__status--error' : ' rcc-billing__status--ok');
        this.status.textContent = msg;
    };

    RccBillingCenter.prototype.render = function () {
        var map = {
            dashboard: this.renderDashboard,
            plans: this.renderPlans,
            subscriptions: this.renderSubscription,
            invoices: this.renderInvoices,
            payments: this.renderPayments,
            licenses: this.renderLicenses,
            whitelabel: this.renderWhitelabel,
            reseller: this.renderReseller,
            provision: this.renderProvision
        };
        var fn = map[this.route];
        if (fn) fn.call(this);
    };

    RccBillingCenter.prototype.renderDashboard = function () {
        var self = this;
        self.api('dashboard').then(function (res) {
            if (!res.ok) { self.setStatus(res.error, false); return; }
            var sub = res.subscription || {};
            var html = '<div class="rcc-billing__kpis">';
            html += '<div class="rcc-billing__kpi"><strong>' + esc(sub.plan_name || '—') + '</strong><span>Plan</span></div>';
            html += '<div class="rcc-billing__kpi"><strong>' + esc(res.open_invoices) + '</strong><span>Open invoices</span></div>';
            html += '<div class="rcc-billing__kpi"><strong>' + esc(res.open_invoices_total) + ' SAR</strong><span>Due</span></div>';
            html += '</div>';
            self.panel.innerHTML = html;
            self.setStatus('Billing dashboard loaded', true);
        });
    };

    RccBillingCenter.prototype.renderPlans = function () {
        var self = this;
        self.api('plans_list').then(function (res) {
            if (!res.ok) { self.setStatus(res.error, false); return; }
            var html = '<table class="rcc-billing__table"><tr><th>Plan</th><th>Price</th><th>Agents</th><th></th></tr>';
            (res.plans || []).forEach(function (p) {
                var name = self.lang === 'ar' && p.name_ar ? p.name_ar : p.name;
                html += '<tr><td>' + esc(name) + '</td><td>' + esc(p.price_amount) + ' ' + esc(p.currency) + '</td><td>' + esc(p.max_agents) + '</td>';
                html += '<td><button type="button" class="rcc-billing__btn" data-plan="' + p.id + '">Subscribe</button></td></tr>';
            });
            html += '</table>';
            self.panel.innerHTML = html;
            self.panel.querySelectorAll('[data-plan]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.api('subscription_subscribe', { plan_id: parseInt(btn.getAttribute('data-plan'), 10) }).then(function (r) {
                        self.setStatus(r.ok ? 'Subscribed' : r.error, r.ok);
                        if (r.ok) self.renderSubscription();
                    });
                });
            });
        });
    };

    RccBillingCenter.prototype.renderSubscription = function () {
        var self = this;
        self.api('subscription_get').then(function (res) {
            var s = res.subscription;
            if (!s) { self.panel.innerHTML = '<p>No active subscription.</p>'; return; }
            self.panel.innerHTML = '<p><strong>' + esc(s.plan_name) + '</strong> — ' + esc(s.status) + '</p>' +
                '<p>Period: ' + esc(s.current_period_start) + ' → ' + esc(s.current_period_end) + '</p>' +
                '<button type="button" class="rcc-billing__btn" id="rcc-cancel-sub">Cancel at period end</button>';
            var btn = document.getElementById('rcc-cancel-sub');
            if (btn) btn.addEventListener('click', function () {
                self.api('subscription_cancel', { at_period_end: 1 }).then(function (r) {
                    self.setStatus(r.ok ? 'Cancellation scheduled' : r.error, r.ok);
                });
            });
        });
    };

    RccBillingCenter.prototype.renderInvoices = function () {
        var self = this;
        self.api('invoices_list').then(function (res) {
            var html = '<table class="rcc-billing__table"><tr><th>No</th><th>Status</th><th>Total</th><th></th></tr>';
            (res.invoices || []).forEach(function (i) {
                html += '<tr><td>' + esc(i.invoice_no) + '</td><td>' + esc(i.status) + '</td><td>' + esc(i.total_amount) + '</td>';
                if (i.status === 'open') {
                    html += '<td><button type="button" class="rcc-billing__btn" data-inv="' + i.id + '">Pay (Moyasar)</button></td>';
                } else html += '<td></td>';
                html += '</tr>';
            });
            html += '</table><button type="button" class="rcc-billing__btn" id="rcc-run-cycle">Run billing cycle</button>';
            self.panel.innerHTML = html;
            self.panel.querySelectorAll('[data-inv]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.api('payment_initiate', {
                        invoice_id: parseInt(btn.getAttribute('data-inv'), 10),
                        gateway: 'moyasar',
                        return_url: window.location.href
                    }).then(function (r) {
                        if (r.redirect_url) window.location.href = r.redirect_url;
                        else self.setStatus(r.error || 'Payment started', r.ok);
                    });
                });
            });
            var cycle = document.getElementById('rcc-run-cycle');
            if (cycle) cycle.addEventListener('click', function () {
                self.api('billing_cycle_run').then(function (r) { self.setStatus(r.ok ? 'Invoice created' : r.error, r.ok); });
            });
        });
    };

    RccBillingCenter.prototype.renderPayments = function () {
        var self = this;
        self.api('gateways_list').then(function (res) {
            var html = '<h4>Payment gateways</h4><ul>';
            (res.gateways || []).forEach(function (g) {
                html += '<li>' + esc(g.display_name) + ' — ' + (g.is_enabled ? 'enabled' : 'disabled') + '</li>';
            });
            html += '</ul>';
            self.panel.innerHTML = html;
        });
    };

    RccBillingCenter.prototype.renderLicenses = function () {
        var self = this;
        self.api('licenses_list').then(function (res) {
            var html = '<table class="rcc-billing__table"><tr><th>Key</th><th>Seats</th><th>Status</th></tr>';
            (res.licenses || []).forEach(function (l) {
                html += '<tr><td><code>' + esc(l.license_key) + '</code></td><td>' + esc(l.seats) + '</td><td>' + esc(l.status) + '</td></tr>';
            });
            html += '</table><button type="button" class="rcc-billing__btn" id="rcc-issue-lic">Issue license</button>';
            self.panel.innerHTML = html;
            var btn = document.getElementById('rcc-issue-lic');
            if (btn) btn.addEventListener('click', function () {
                self.api('license_issue', { seats: 10 }).then(function (r) {
                    self.setStatus(r.ok ? 'License issued' : r.error, r.ok);
                    if (r.ok) self.renderLicenses();
                });
            });
        });
    };

    RccBillingCenter.prototype.renderWhitelabel = function () {
        var self = this;
        self.api('whitelabel_branding_get').then(function (res) {
            var b = res.branding || {};
            self.panel.innerHTML = '<form class="rcc-billing__form" id="rcc-wl-form">' +
                '<label>Company name <input name="company_name" value="' + esc(b.company_name || '') + '"></label>' +
                '<label>Company name (AR) <input name="company_name_ar" value="' + esc(b.company_name_ar || '') + '" dir="auto"></label>' +
                '<label>Primary color <input name="primary_color" value="' + esc(b.primary_color || '#2563eb') + '"></label>' +
                '<label>Logo URL <input name="logo_url" value="' + esc(b.logo_url || '') + '"></label>' +
                '<button type="submit" class="rcc-billing__btn">Save branding</button></form>';
            document.getElementById('rcc-wl-form').addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(e.target);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                self.api('whitelabel_branding_save', body).then(function (r) {
                    self.setStatus(r.ok ? 'Branding saved' : r.error, r.ok);
                });
            });
        });
    };

    RccBillingCenter.prototype.renderReseller = function () {
        var self = this;
        self.api('reseller_get').then(function (res) {
            if (res.reseller) {
                self.panel.innerHTML = '<p><strong>' + esc(res.reseller.name) + '</strong> — ' + esc(res.reseller.commission_rate) + '% commission</p>';
                self.api('reseller_commissions').then(function (c) {
                    self.panel.innerHTML += '<pre>' + esc(JSON.stringify(c.commissions || [], null, 2)) + '</pre>';
                });
            } else {
                self.panel.innerHTML = '<form class="rcc-billing__form" id="rcc-res-form">' +
                    '<label>Name <input name="name" required></label>' +
                    '<label>Email <input name="contact_email" type="email" required></label>' +
                    '<button type="submit" class="rcc-billing__btn">Register as reseller</button></form>';
                document.getElementById('rcc-res-form').addEventListener('submit', function (e) {
                    e.preventDefault();
                    var fd = new FormData(e.target);
                    var body = {};
                    fd.forEach(function (v, k) { body[k] = v; });
                    self.api('reseller_register', body).then(function (r) {
                        self.setStatus(r.ok ? 'Reseller registered' : r.error, r.ok);
                        if (r.ok) self.renderReseller();
                    });
                });
            }
        });
    };

    RccBillingCenter.prototype.renderProvision = function () {
        var self = this;
        self.panel.innerHTML = '<form class="rcc-billing__form" id="rcc-prov-form">' +
            '<label>Tenant code <input name="code" required></label>' +
            '<label>Name <input name="name" required></label>' +
            '<label>Admin email <input name="admin_email" type="email" required></label>' +
            '<label>Plan ID <input name="plan_id" type="number" value="1"></label>' +
            '<button type="submit" class="rcc-billing__btn">Provision tenant</button></form>';
        document.getElementById('rcc-prov-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(e.target);
            var body = {};
            fd.forEach(function (v, k) { body[k] = k === 'plan_id' ? parseInt(v, 10) : v; });
            self.api('tenant_provision', body).then(function (r) {
                self.setStatus(r.ok ? 'Tenant ' + (r.tenant && r.tenant.code) + ' created' : r.error, r.ok);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rcc-billing-center');
        if (root) new RccBillingCenter(root).render();
    });
})(window);
