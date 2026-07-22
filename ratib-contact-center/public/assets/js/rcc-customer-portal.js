/**
 * RATEB Customer Portal (Phase 11).
 */
(function () {
    'use strict';

    var TOKEN_KEY = 'rcc_portal_token';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function api(base, action, body, token) {
        var headers = { 'Content-Type': 'application/json' };
        if (token) headers['X-RCC-Portal-Token'] = token;
        return fetch(base + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(body || {})
        }).then(function (r) { return r.json(); });
    }

    function showApp(root, token, apiBase) {
        var panel = document.getElementById('rcc-portal-panel');
        var nav = document.getElementById('rcc-portal-nav');
        var routes = ['dashboard', 'tickets', 'conversations', 'crm_profile', 'invoices', 'payments', 'recordings', 'knowledge', 'sla_dashboard'];
        nav.innerHTML = routes.map(function (r) {
            return '<button type="button" class="rcc-portal__nav-btn" data-route="' + r + '">' + r.replace('_', ' ') + '</button>';
        }).join('') + '<button type="button" id="rcc-portal-logout">Logout</button>';

        function load(route) {
            api(apiBase, route, { portal_token: token }, token).then(function (res) {
                panel.innerHTML = res.ok ? '<pre dir="auto">' + esc(JSON.stringify(res, null, 2)) + '</pre>' : '<p>' + esc(res.error) + '</p>';
            });
        }

        nav.querySelectorAll('[data-route]').forEach(function (btn) {
            btn.addEventListener('click', function () { load(btn.getAttribute('data-route')); });
        });
        document.getElementById('rcc-portal-logout').addEventListener('click', function () {
            api(apiBase, 'logout', { portal_token: token }, token);
            localStorage.removeItem(TOKEN_KEY);
            location.reload();
        });
        load('dashboard');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('rcc-portal');
        if (!root) return;
        var apiBase = root.getAttribute('data-api') || '';
        var tenantId = parseInt(root.getAttribute('data-tenant'), 10) || 0;
        var token = localStorage.getItem(TOKEN_KEY);
        var login = document.getElementById('rcc-portal-login');
        var app = document.getElementById('rcc-portal-app');

        if (token) {
            login.hidden = true;
            app.hidden = false;
            showApp(root, token, apiBase);
            return;
        }

        login.hidden = false;
        app.hidden = true;
        document.getElementById('rcc-portal-login-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(e.target);
            api(apiBase, 'login', {
                tenant_id: tenantId,
                email: fd.get('email'),
                password: fd.get('password')
            }).then(function (res) {
                if (res.ok && res.portal_token) {
                    localStorage.setItem(TOKEN_KEY, res.portal_token);
                    location.reload();
                } else {
                    document.getElementById('rcc-portal-login-error').textContent = res.error || 'Login failed';
                }
            });
        });
    });
})();
