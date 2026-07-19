(function () {
    'use strict';

    /** PERF-P4: metrics are progressive enhancement — never gate perceived page load.
     * Fail-soft at 400ms so skeleton clears <500ms after afterEnter (acceptance).
     * Fetch may continue and upgrade — / placeholders; silent retry uses SILENT_RETRY_MS.
     */
    var FAILSOFT_MS = 400;
    var SILENT_RETRY_MS = 8000;

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
    }

    /** Lightweight placeholder: remove skeleton / is-loading; show em dashes. */
    function renderPlaceholder(container) {
        if (!container || !container.isConnected) {
            return;
        }
        if (container.getAttribute('data-rateb-metrics-ready') === '1') {
            container.classList.remove('is-loading');
            return;
        }
        var count = 5;
        try {
            var sk = container.querySelectorAll('.cm-strip__item, .cm-strip__item--skeleton');
            if (sk && sk.length) {
                count = sk.length;
            }
        } catch (eCnt) { /* default 5 */ }
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

    function scheduleSilentRetry(container) {
        if (!container || container.getAttribute('data-rateb-metrics-retry') === '1') {
            return;
        }
        // Offline: never retry — hanging XHR freezes the whole tab.
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
        var url = container.getAttribute('data-module-metrics-url');
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
            container.removeAttribute('data-rateb-metrics-inflight');
            return;
        }

        var settled = false;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var failSoft = setTimeout(function () {
            if (settled) {
                return;
            }
            // Keep page feeling finished; abort hung fetch so it cannot starve clicks.
            try { if (ctrl) ctrl.abort(); } catch (eAb) { /* ignore */ }
            renderPlaceholder(container);
            container.removeAttribute('data-rateb-metrics-inflight');
        }, FAILSOFT_MS);

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: ctrl ? ctrl.signal : undefined
        })
            .then(function (res) {
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

    /**
     * PERF-P4: start immediately on afterEnter / boot — no afterInteraction, no idle queue.
     */
    function boot() {
        document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
            if (el.getAttribute('data-rateb-metrics-bound') === '1') {
                return;
            }
            el.setAttribute('data-rateb-metrics-bound', '1');
            loadMetrics(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
})();
