(function () {
    'use strict';

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderStrip(container, metrics) {
        if (!metrics || !metrics.length) {
            container.remove();
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
    }

    function loadMetrics(container) {
        var url = container.getAttribute('data-module-metrics-url');
        if (!url) {
            return;
        }
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                container.remove();
                return;
            }
        } catch (eOff) { /* continue */ }
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    container.remove();
                    return;
                }
                renderStrip(container, data.metrics || []);
            })
            .catch(function () {
                container.remove();
            });
    }

    function boot() {
        document.querySelectorAll('[data-module-metrics-async]').forEach(function (el) {
            if (el.getAttribute('data-rateb-metrics-loaded') === '1') {
                return;
            }
            el.setAttribute('data-rateb-metrics-loaded', '1');
            // PERF-P1 — defer metrics so navigation paints first.
            var run = function () { loadMetrics(el); };
            if (typeof requestIdleCallback === 'function') {
                requestIdleCallback(run, { timeout: 2500 });
            } else {
                setTimeout(run, 800);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
})();
