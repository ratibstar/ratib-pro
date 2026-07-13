/**
 * RATEB ERP — topbar Online / Offline indicator.
 * Uses navigator.onLine + optional RatebOfflineConnectivity probe events.
 * Branch / local appliance: auto-switch admin URL with connection state.
 *   Offline (red)  → http://127.0.0.1:8088/admin
 *   Online  (green) → https://rateb.sa/rateb-erp/public/admin/
 *
 * Cross-origin handoff: local → cloud adds ?rateb_branch=1 so rateb.sa can
 * remember branch mode in localStorage (sessionStorage does not cross origins).
 */
(function () {
    'use strict';

    var DEFAULT_LOCAL = 'http://127.0.0.1:8088/admin';
    var DEFAULT_CLOUD = 'https://rateb.sa/rateb-erp/public/admin/';
    var FLAG = 'rateb-branch-url-switch';
    var HANDOFF = 'rateb_branch';
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

    function storageGet() {
        try {
            if (localStorage.getItem(FLAG) === '1') {
                return true;
            }
        } catch (e1) { /* ignore */ }
        try {
            if (sessionStorage.getItem(FLAG) === '1') {
                return true;
            }
        } catch (e2) { /* ignore */ }
        return false;
    }

    function storageSet() {
        try {
            localStorage.setItem(FLAG, '1');
        } catch (e1) { /* ignore */ }
        try {
            sessionStorage.setItem(FLAG, '1');
        } catch (e2) { /* ignore */ }
    }

    /** Capture ?rateb_branch=1 from local→cloud redirect (per-origin localStorage). */
    function captureHandoff() {
        try {
            var params = new URLSearchParams(location.search || '');
            if (params.get(HANDOFF) !== '1') {
                return;
            }
            storageSet();
            params.delete(HANDOFF);
            var q = params.toString();
            var clean = location.pathname + (q ? '?' + q : '') + (location.hash || '');
            if (typeof history !== 'undefined' && history.replaceState) {
                history.replaceState(null, '', clean);
            }
        } catch (e) { /* ignore */ }
    }

    function rememberIfBranchHost() {
        if (isLocalHost() || cfg('data-rateb-url-switch', '') === '1') {
            storageSet();
        }
    }

    function urlSwitchEnabled() {
        if (cfg('data-rateb-url-switch', '') === '1') {
            return true;
        }
        if (isLocalHost()) {
            return true;
        }
        return storageGet();
    }

    function adminSuffix() {
        var path = location.pathname || '';
        var idx = path.indexOf('/admin');
        if (idx < 0) {
            return '';
        }
        return path.slice(idx + '/admin'.length);
    }

    function stripHandoffSearch(search) {
        try {
            var params = new URLSearchParams(search || '');
            params.delete(HANDOFF);
            var q = params.toString();
            return q ? '?' + q : '';
        } catch (e) {
            return search || '';
        }
    }

    function buildTarget(base) {
        var clean = String(base || '').replace(/\/+$/, '');
        return clean + adminSuffix() + stripHandoffSearch(location.search || '') + (location.hash || '');
    }

    function withHandoff(url) {
        try {
            var u = new URL(url, location.href);
            u.searchParams.set(HANDOFF, '1');
            return u.toString();
        } catch (e) {
            var join = url.indexOf('?') >= 0 ? '&' : '?';
            return url + join + HANDOFF + '=1';
        }
    }

    function alreadyOnTarget(online) {
        if (online) {
            return /rateb\.sa$/i.test(location.hostname || '');
        }
        return isLocalHost();
    }

    function localServerUp(localAdminUrl) {
        var url = String(localAdminUrl || DEFAULT_LOCAL).replace(/\/admin\/?.*$/, '/') || 'http://127.0.0.1:8088/';
        // Mixed content: cannot probe http://127.0.0.1 from https://rateb.sa — navigate anyway.
        if (location.protocol === 'https:' && /^http:/i.test(url)) {
            return Promise.resolve(true);
        }
        return new Promise(function (resolve) {
            var done = false;
            var timer = setTimeout(function () {
                if (done) return;
                done = true;
                resolve(false);
            }, 1800);
            fetch(url, { method: 'GET', cache: 'no-store', credentials: 'omit', mode: 'cors' })
                .then(function (res) {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    resolve(!!(res && (res.ok || res.status === 401 || res.status === 302 || res.status === 301)));
                })
                .catch(function () {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    resolve(false);
                });
        });
    }

    function go(target, toOnline) {
        storageSet();
        if (toOnline && isLocalHost()) {
            target = withHandoff(target);
        }
        location.assign(target);
    }

    function connectionStillMatches(wantOnline) {
        if (wantOnline) {
            var still = typeof navigator === 'undefined' || navigator.onLine !== false;
            var conn = window.RatebOfflineConnectivity;
            if (conn && typeof conn.isOnline === 'function') {
                still = !!conn.isOnline();
            }
            return still;
        }
        // Offline → local: prefer navigator.offLine so a stale connectivity probe
        // cannot block returning to the branch appliance.
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            return true;
        }
        var c = window.RatebOfflineConnectivity;
        if (c && typeof c.isOnline === 'function') {
            return !c.isOnline();
        }
        return typeof navigator === 'undefined' || navigator.onLine === false;
    }

    function maybeRedirect(online) {
        if (!urlSwitchEnabled()) {
            return;
        }
        // Stay on local branch when internet returns — sync in background.
        // Only auto-navigate cloud → local when offline (so work continues on appliance).
        if (online) {
            return;
        }
        if (alreadyOnTarget(false)) {
            return;
        }

        var localBase = cfg('data-rateb-local-admin', DEFAULT_LOCAL);
        var target = buildTarget(localBase);

        if (redirectTimer) {
            clearTimeout(redirectTimer);
            redirectTimer = null;
        }

        redirectTimer = setTimeout(function () {
            redirectTimer = null;
            if (!connectionStillMatches(false)) {
                return;
            }
            if (alreadyOnTarget(false)) {
                return;
            }
            localServerUp(localBase).then(function (up) {
                if (!up) {
                    return;
                }
                if (!connectionStillMatches(false)) {
                    return;
                }
                if (alreadyOnTarget(false)) {
                    return;
                }
                go(target, false);
            });
        }, 500);
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
        captureHandoff();
        rememberIfBranchHost();

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
