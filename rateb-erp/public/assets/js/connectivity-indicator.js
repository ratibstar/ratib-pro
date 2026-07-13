/**
 * RATEB ERP — topbar Online / Offline indicator.
 * Uses navigator.onLine + optional RatebOfflineConnectivity probe events.
 * Branch / local appliance: auto-switch admin URL with connection state.
 *   Offline (red)  → http://127.0.0.1:8088/admin
 *   Online  (green) → https://rateb.sa/rateb-erp/public/admin/
 */
(function () {
    'use strict';

    var DEFAULT_LOCAL = 'http://127.0.0.1:8088/admin';
    var DEFAULT_CLOUD = 'https://rateb.sa/rateb-erp/public/admin/';
    var FLAG = 'rateb-branch-url-switch';
    var redirectTimer = null;
    var lastApplied = null;

    function el() {
        return document.querySelector('[data-rateb-connection-status]')
            || document.getElementById('rateb-connection-indicator');
    }

    function isLocalHost(hostname) {
        var h = hostname || (location && location.hostname) || '';
        return h === '127.0.0.1' || h === 'localhost' || h === '[::1]';
    }

    function cfg(name, fallback) {
        var body = document.body;
        if (body) {
            var v = body.getAttribute(name);
            if (v) {
                return v;
            }
        }
        var node = el();
        if (node) {
            var a = node.getAttribute(name);
            if (a) {
                return a;
            }
        }
        return fallback;
    }

    function urlSwitchEnabled() {
        if (cfg('data-rateb-url-switch', '') === '1') {
            return true;
        }
        if (isLocalHost()) {
            return true;
        }
        try {
            return sessionStorage.getItem(FLAG) === '1';
        } catch (e) {
            return false;
        }
    }

    function adminSuffix() {
        var path = location.pathname || '';
        var idx = path.indexOf('/admin');
        if (idx < 0) {
            return '';
        }
        return path.slice(idx + '/admin'.length);
    }

    function buildTarget(base) {
        var clean = String(base || '').replace(/\/+$/, '');
        return clean + adminSuffix() + (location.search || '') + (location.hash || '');
    }

    function alreadyOnTarget(online) {
        if (online) {
            return /rateb\.sa$/i.test(location.hostname || '');
        }
        return isLocalHost();
    }

    function maybeRedirect(online) {
        if (!urlSwitchEnabled()) {
            return;
        }
        if (alreadyOnTarget(online)) {
            return;
        }

        var localBase = cfg('data-rateb-local-admin', DEFAULT_LOCAL);
        var cloudBase = cfg('data-rateb-cloud-admin', DEFAULT_CLOUD);
        var target = buildTarget(online ? cloudBase : localBase);

        if (redirectTimer) {
            clearTimeout(redirectTimer);
            redirectTimer = null;
        }

        // Debounce so brief flaps do not bounce the tab.
        var delay = online ? 1600 : 600;
        redirectTimer = setTimeout(function () {
            redirectTimer = null;
            var still = typeof navigator === 'undefined' || navigator.onLine !== false;
            var conn = window.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                still = !!conn.isOnline();
            }
            if (!!still !== !!online) {
                return;
            }
            if (alreadyOnTarget(online)) {
                return;
            }
            try {
                sessionStorage.setItem(FLAG, '1');
            } catch (e) { /* ignore */ }
            location.assign(target);
        }, delay);
    }

    function apply(online) {
        var node = el();
        if (!node) {
            return;
        }
        var on = !!online;
        var labelOn = node.getAttribute('data-label-online') || 'Online';
        var labelOff = node.getAttribute('data-label-offline') || 'Offline';
        var label = on ? labelOn : labelOff;
        node.classList.toggle('is-online', on);
        node.classList.toggle('is-offline', !on);
        node.setAttribute('title', label);
        node.setAttribute('aria-label', label);
        var text = node.querySelector('.rateb-connection-indicator__label');
        if (text) {
            text.textContent = label;
        }
        if (lastApplied === on) {
            return;
        }
        lastApplied = on;
        maybeRedirect(on);
    }

    function fromNavigator() {
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function boot() {
        apply(fromNavigator());

        window.addEventListener('online', function () { apply(true); });
        window.addEventListener('offline', function () { apply(false); });

        document.addEventListener('rateb-offline-connectivity', function (ev) {
            var detail = ev && ev.detail ? ev.detail : null;
            if (detail && typeof detail.online === 'boolean') {
                apply(detail.online);
            }
        });

        var conn = window.RatebOfflineConnectivity;
        if (conn && typeof conn.subscribe === 'function') {
            conn.subscribe(function (online) { apply(online); });
        } else if (conn && typeof conn.isOnline === 'function') {
            apply(conn.isOnline());
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
