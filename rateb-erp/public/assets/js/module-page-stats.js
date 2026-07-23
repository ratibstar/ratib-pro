(function (root) {
    'use strict';

    /** PERF-P4 / Fix10: metrics are progressive enhancement — never gate perceived page load.
     * One request per navigation URL; abort on leave; reuse cache when available.
     */
    var FAILSOFT_MS = 450;
    var HARD_CLEAR_MS = 900;
    var SILENT_RETRY_MS = 8000;
    var bootTimer = null;
    var hardClearTimer = null;
    var observerBound = false;
    /** @type {Object.<string, {metrics: Array, at: number}>} */
    var cacheByUrl = {};
    /** @type {Object.<string, {ctrl: AbortController|null, waiters: Array}>} */
    var inflightByUrl = {};
    var navGen = 0;

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

    function cacheKey(url) {
        try {
            var u = new URL(url, root.location.href);
            u.searchParams.delete('_');
            return u.pathname + u.search;
        } catch (eK) {
            return String(url || '');
        }
    }

    function abortAllInflight() {
        Object.keys(inflightByUrl).forEach(function (k) {
            var slot = inflightByUrl[k];
            if (slot && slot.ctrl) {
                try { slot.ctrl.abort(); } catch (eAb) { /* ignore */ }
            }
            delete inflightByUrl[k];
        });
    }

    function scheduleSilentRetry(container, url) {
        if (!container || container.getAttribute('data-rateb-metrics-retry') === '1') {
            return;
        }
        if (isOfflineNow()) {
            return;
        }
        container.setAttribute('data-rateb-metrics-retry', '1');
        var gen = navGen;
        setTimeout(function () {
            if (gen !== navGen) {
                return;
            }
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
            loadMetrics(container, { silent: true, url: url });
        }, SILENT_RETRY_MS);
    }

    function loadMetrics(container, opts) {
        opts = opts || {};
        var url = withCompanyId(opts.url || container.getAttribute('data-module-metrics-url'));
        if (!url) {
            renderPlaceholder(container);
            return;
        }
        var key = cacheKey(url);
        var cached = cacheByUrl[key];
        if (cached && cached.metrics && cached.metrics.length) {
            renderStrip(container, cached.metrics);
            return;
        }
        if (container.getAttribute('data-rateb-metrics-inflight') === '1' && inflightByUrl[key]) {
            return;
        }
        if (inflightByUrl[key]) {
            /* Join in-flight request for same URL — do not start a duplicate. */
            container.setAttribute('data-rateb-metrics-inflight', '1');
            inflightByUrl[key].waiters.push(container);
            return;
        }
        container.setAttribute('data-rateb-metrics-inflight', '1');

        if (isOfflineNow()) {
            renderPlaceholder(container);
            return;
        }

        var myGen = navGen;
        var settled = false;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        inflightByUrl[key] = { ctrl: ctrl, waiters: [container] };

        var failSoft = setTimeout(function () {
            if (settled || myGen !== navGen) {
                return;
            }
            try { if (ctrl) ctrl.abort(); } catch (eAb) { /* ignore */ }
            var slot = inflightByUrl[key];
            delete inflightByUrl[key];
            (slot && slot.waiters ? slot.waiters : [container]).forEach(function (el) {
                if (el && el.isConnected) {
                    renderPlaceholder(el);
                    scheduleSilentRetry(el, url);
                }
            });
        }, FAILSOFT_MS);

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: ctrl ? ctrl.signal : undefined,
            cache: 'default'
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
                var slot = inflightByUrl[key];
                delete inflightByUrl[key];
                if (myGen !== navGen) {
                    return;
                }
                var waiters = (slot && slot.waiters) ? slot.waiters : [container];
                if (data && data.ok && data.metrics && data.metrics.length) {
                    cacheByUrl[key] = { metrics: data.metrics, at: Date.now() };
                    waiters.forEach(function (el) {
                        if (el && el.isConnected) {
                            renderStrip(el, data.metrics);
                        }
                    });
                    return;
                }
                waiters.forEach(function (el) {
                    if (el && el.isConnected) {
                        renderPlaceholder(el);
                        scheduleSilentRetry(el, url);
                    }
                });
            })
            .catch(function (err) {
                settled = true;
                clearTimeout(failSoft);
                delete inflightByUrl[key];
                if (myGen !== navGen) {
                    return;
                }
                if (err && err.name === 'AbortError') {
                    return;
                }
                renderPlaceholder(container);
                scheduleSilentRetry(container, url);
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
            el.removeAttribute('data-rateb-metrics-retry');
            /* Do not clear inflight for a URL that is already fetching — join that flight. */
            var url = withCompanyId(el.getAttribute('data-module-metrics-url'));
            var key = url ? cacheKey(url) : '';
            if (key && inflightByUrl[key]) {
                el.setAttribute('data-rateb-metrics-inflight', '1');
                if (inflightByUrl[key].waiters.indexOf(el) === -1) {
                    inflightByUrl[key].waiters.push(el);
                }
                return;
            }
            if (key && cacheByUrl[key] && cacheByUrl[key].metrics) {
                renderStrip(el, cacheByUrl[key].metrics);
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
    /* Fix10: single soft-nav hook (afterEnter only) — enter + afterEnter caused duplicate fetches. */
    document.addEventListener('rateb:nav:beforeLeave', function () {
        navGen += 1;
        abortAllInflight();
    });
    document.addEventListener('rateb:nav:afterEnter', function () {
        bindObserver();
        scheduleBoot();
    });
})(typeof window !== 'undefined' ? window : this);
