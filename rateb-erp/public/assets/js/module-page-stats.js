(function (root) {
    'use strict';

    /** PERF-P4: metrics are progressive enhancement — never gate perceived page load.
     * Fail-soft quickly so skeleton never sticks after soft-nav (critical JS is post-DCL).
     */
    var FAILSOFT_MS = 450;
    var HARD_CLEAR_MS = 900;
    var SILENT_RETRY_MS = 8000;
    var bootTimer = null;
    var hardClearTimer = null;
    var observerBound = false;

    function renderStrip(container, metrics) {
        if (!metrics || !metrics.length) {
            renderPlaceholder(container);
            return;
        }
        var strip = document.createElement('div');
        strip.className = 'cm-strip';
        strip.setAttribute('aria-label', container.getAttribute('data-metrics-label') || 'Metrics');
        metrics.forEach(function (m) {
            var item = document.createElement('article');
            item.className = 'cm-strip__item';
            item.setAttribute('data-tone', m.tone || 'blue');
            if (m.key) {
                item.setAttribute('data-stat-key', m.key);
            }
            var lbl = document.createElement('span');
            lbl.className = 'cm-strip__lbl';
            lbl.textContent = m.label || '';
            var val = document.createElement('span');
            val.className = 'cm-strip__val';
            val.innerHTML = m.value || '0';
            item.appendChild(lbl);
            item.appendChild(val);
            if (m.trend) {
                var trend = document.createElement('span');
                trend.className = 'cm-strip__trend ' + (m.trendDir || (String(m.trend).indexOf('-') === 0 ? 'down' : 'up'));
                trend.textContent = m.trend;
                item.appendChild(trend);
            }
            strip.appendChild(item);
        });
        container.innerHTML = '';
        container.appendChild(strip);
        container.classList.remove('is-loading');
        container.setAttribute('data-rateb-metrics-ready', '1');
        container.removeAttribute('data-rateb-metrics-inflight');
    }

    /** Lightweight placeholder: remove skeleton / is-loading; show em dashes. */
    function renderPlaceholder(container) {
        if (!container) {
            return;
        }
        if (container.getAttribute('data-rateb-metrics-ready') === '1') {
            container.classList.remove('is-loading');
            container.removeAttribute('data-rateb-metrics-inflight');
            return;
        }
        var count = 4;
        try {
            var sk = container.querySelectorAll('.cm-strip__item, .cm-strip__item--skeleton');
            if (sk && sk.length) {
                count = sk.length;
            }
        } catch (eCnt) { /* default */ }
        var strip = document.createElement('div');
        strip.className = 'cm-strip';
        strip.setAttribute('aria-label', container.getAttribute('data-metrics-label') || 'Metrics');
        strip.setAttribute('data-metrics-placeholder', '1');
        var i;
        for (i = 0; i < count; i++) {
            var item = document.createElement('article');
            item.className = 'cm-strip__item';
            item.setAttribute('data-tone', 'blue');
            var lbl = document.createElement('span');
            lbl.className = 'cm-strip__lbl';
            lbl.textContent = '\u00a0';
            var val = document.createElement('span');
            val.className = 'cm-strip__val';
            val.textContent = '\u2014';
            item.appendChild(lbl);
            item.appendChild(val);
            strip.appendChild(item);
        }
        container.innerHTML = '';
        container.appendChild(strip);
        container.classList.remove('is-loading');
        container.removeAttribute('data-rateb-metrics-inflight');
    }

    function isOfflineNow() {
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return true;
            }
        } catch (e0) { /* ignore */ }
        try {
            var badge = document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge && badge.classList.contains('is-offline')) {
                return true;
            }
        } catch (e1) { /* ignore */ }
        return false;
    }

    function withCompanyId(url) {
        if (!url || /[?&]company_id=/.test(url)) {
            return url;
        }
        try {
            var u = new URL(url, root.location.href);
            var q = new URLSearchParams(root.location.search || '');
            var cid = q.get('company_id') || '';
            if (!cid) {
                var meta = document.querySelector('meta[name="rateb-ops-company-id"]');
                if (meta) {
                    cid = meta.getAttribute('content') || '';
                }
            }
            if (cid && /^\d+$/.test(cid)) {
                u.searchParams.set('company_id', cid);
            }
            return u.toString();
        } catch (eU) {
            return url;
        }
    }

    function scheduleSilentRetry(container) {
        if (!container || container.getAttribute('data-rateb-metrics-retry') === '1') {
            return;
        }
        if (isOfflineNow()) {
            return;
        }
        container.setAttribute('data-rateb-metrics-retry', '1');
        setTimeout(function () {
            if (!container.isConnected) {
                return;
            }
            if (container.getAttribute('data-rateb-metrics-ready') === '1') {
                return;
            }
            if (isOfflineNow()) {
                return;
            }
            container.removeAttribute('data-rateb-metrics-inflight');
            loadMetrics(container, { silent: true });
        }, SILENT_RETRY_MS);
    }

    function loadMetrics(container, opts) {
        opts = opts || {};
        var url = withCompanyId(container.getAttribute('data-module-metrics-url'));
        if (!url) {
            renderPlaceholder(container);
            return;
        }
        if (container.getAttribute('data-rateb-metrics-inflight') === '1') {
            return;
        }
        container.setAttribute('data-rateb-metrics-inflight', '1');

        if (isOfflineNow()) {
            renderPlaceholder(container);
            return;
        }

        var settled = false;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var failSoft = setTimeout(function () {
            if (settled) {
                return;
            }
            try { if (ctrl) ctrl.abort(); } catch (eAb) { /* ignore */ }
            renderPlaceholder(container);
            scheduleSilentRetry(container);
        }, FAILSOFT_MS);

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: ctrl ? ctrl.signal : undefined,
            cache: 'no-store'
        })
            .then(function (res) {
                if (!res || !res.ok) {
                    throw new Error('metrics_http_' + (res ? res.status : 0));
                }
                return res.json();
            })
            .then(function (data) {
                settled = true;
                clearTimeout(failSoft);
                container.removeAttribute('data-rateb-metrics-inflight');
                if (data && data.ok && data.metrics && data.metrics.length) {
                    renderStrip(container, data.metrics);
                    return;
                }
                renderPlaceholder(container);
                scheduleSilentRetry(container);
            })
            .catch(function () {
                settled = true;
                clearTimeout(failSoft);
                container.removeAttribute('data-rateb-metrics-inflight');
                renderPlaceholder(container);
                scheduleSilentRetry(container);
            });
    }

    function hardClearStuckSkeletons() {
        document.querySelectorAll('[data-module-metrics-async].is-loading').forEach(function (el) {
            if (el.getAttribute('data-rateb-metrics-ready') === '1') {
                el.classList.remove('is-loading');
                return;
            }
            renderPlaceholder(el);
        });
    }

    /**
     * Soft-nav swaps main HTML after critical scripts may already have loaded.
     * Always re-scan; never skip a fresh skeleton still needing load.
     */
    function boot() {
        document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
            if (el.getAttribute('data-rateb-metrics-ready') === '1') {
                el.classList.remove('is-loading');
                el.removeAttribute('data-rateb-metrics-inflight');
                return;
            }
            el.setAttribute('data-rateb-metrics-bound', '1');
            // Allow re-fetch after soft-nav (new node may copy nothing; clear stale flags).
            if (el.getAttribute('data-rateb-metrics-inflight') === '1'
                && !el.querySelector('.cm-strip--skeleton, .cm-strip__item--skeleton')) {
                // Inflight without skeleton — leave alone briefly.
                return;
            }
            el.removeAttribute('data-rateb-metrics-inflight');
            loadMetrics(el);
        });
        if (hardClearTimer) {
            clearTimeout(hardClearTimer);
        }
        hardClearTimer = setTimeout(hardClearStuckSkeletons, HARD_CLEAR_MS);
    }

    function scheduleBoot() {
        if (bootTimer) {
            clearTimeout(bootTimer);
        }
        bootTimer = setTimeout(function () {
            bootTimer = null;
            boot();
        }, 0);
    }

    function bindObserver() {
        if (observerBound || typeof MutationObserver === 'undefined') {
            return;
        }
        var main = document.querySelector('#rateb-main-content, main.rateb-content');
        if (!main) {
            return;
        }
        observerBound = true;
        var obs = new MutationObserver(function () {
            if (document.querySelector('[data-module-metrics-async].is-loading')) {
                scheduleBoot();
            }
        });
        try {
            obs.observe(main, { childList: true, subtree: true });
        } catch (eObs) { /* ignore */ }
    }

    root.RatebBootModulePageStats = boot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindObserver();
            scheduleBoot();
        });
    } else {
        bindObserver();
        scheduleBoot();
    }
    document.addEventListener('rateb:nav:afterEnter', function () {
        bindObserver();
        scheduleBoot();
    });
    document.addEventListener('rateb:nav:enter', function () {
        scheduleBoot();
    });
})(typeof window !== 'undefined' ? window : this);
