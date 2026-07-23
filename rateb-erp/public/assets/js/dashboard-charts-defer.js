/**
 * Hydrate admin dashboard charts after lite HTML paint.
 * Soft-nav safe: single boot pipeline, aborts stale fetches, debounced.
 */
(function () {
    'use strict';

    if (window.__RATEB_DASH_CHARTS_DEFER_BOUND__) {
        return;
    }
    window.__RATEB_DASH_CHARTS_DEFER_BOUND__ = true;

    var bootGen = 0;
    var bootTimer = null;
    var inflightCtrl = null;

    function setJsonAttr(el, name, value) {
        if (!el) {
            return;
        }
        el.setAttribute(name, typeof value === 'string' ? value : JSON.stringify(value));
    }

    function months(rows, key) {
        return (rows || []).map(function (r) { return r[key] || r.month || ''; });
    }

    function nums(rows, key) {
        return (rows || []).map(function (r) { return parseInt(r[key] || r.total || r.value || 0, 10) || 0; });
    }

    function applyCharts(c) {
        c = c || {};
        var co = c.company_growth || [];
        var sub = c.subscription_growth || [];
        var users = c.user_growth || [];
        var status = c.company_status || [];
        var plans = c.plan_distribution || [];
        var subStat = c.subscription_status || [];
        var login = c.login_activity || [];

        var coLabels = months(co, 'month');
        var coValues = nums(co, 'total');
        var subLabels = months(sub, 'month');
        var subValues = nums(sub, 'total');
        var userLabels = months(users, 'month');
        var userValues = nums(users, 'total');

        setJsonAttr(document.getElementById('chart-companies'), 'data-labels', coLabels);
        setJsonAttr(document.getElementById('chart-companies'), 'data-values', coValues);
        setJsonAttr(document.getElementById('chart-subscriptions'), 'data-labels', subLabels);
        setJsonAttr(document.getElementById('chart-subscriptions'), 'data-values', subValues);
        setJsonAttr(document.getElementById('chart-users'), 'data-labels', userLabels);
        setJsonAttr(document.getElementById('chart-users'), 'data-values', userValues);

        var overview = document.getElementById('chart-platform-overview');
        setJsonAttr(overview, 'data-labels', coLabels);
        setJsonAttr(overview, 'data-dataset-1', coValues);
        setJsonAttr(overview, 'data-dataset-2', subValues);
        setJsonAttr(overview, 'data-dataset-3', userValues);

        setJsonAttr(document.getElementById('chart-company-status'), 'data-labels', (status || []).map(function (r) { return r.label || ''; }));
        setJsonAttr(document.getElementById('chart-company-status'), 'data-values', nums(status, 'value'));
        setJsonAttr(document.getElementById('chart-plan-distribution'), 'data-labels', (plans || []).map(function (r) { return r.label || ''; }));
        setJsonAttr(document.getElementById('chart-plan-distribution'), 'data-values', nums(plans, 'value'));
        setJsonAttr(document.getElementById('chart-subscription-status'), 'data-labels', (subStat || []).map(function (r) { return r.label || ''; }));
        setJsonAttr(document.getElementById('chart-subscription-status'), 'data-values', nums(subStat, 'value'));

        var loginEl = document.getElementById('chart-login-activity');
        setJsonAttr(loginEl, 'data-labels', months(login, 'month'));
        setJsonAttr(loginEl, 'data-success', nums(login, 'success_total'));
        setJsonAttr(loginEl, 'data-failed', nums(login, 'failed_total'));
    }

    function markCharts(state) {
        try {
            document.querySelectorAll('[data-chart-slot], .cm-chart').forEach(function (el) {
                if (state === 'loading') {
                    el.classList.add('is-loading');
                    el.classList.remove('is-empty');
                } else if (state === 'ready') {
                    el.classList.remove('is-loading');
                    el.classList.remove('is-empty');
                } else if (state === 'empty') {
                    el.classList.remove('is-loading');
                    el.classList.add('is-empty');
                }
            });
        } catch (eMark) { /* ignore */ }
    }

    function paintCharts() {
        try {
            if (typeof window.ratebChartsBoot === 'function') {
                window.ratebChartsBoot();
            } else if (typeof window.ratebChartInitPane === 'function') {
                window.ratebChartInitPane(document);
            }
            markCharts('ready');
        } catch (ePaint) { /* ignore */ }
    }

    function loadChartLibs(rootEl) {
        return new Promise(function (resolve) {
            if (typeof window.Chart !== 'undefined' && typeof window.ratebChartsBoot === 'function') {
                resolve();
                return;
            }
            var chartjs = rootEl.getAttribute('data-rateb-chartjs') || '';
            var charts = rootEl.getAttribute('data-rateb-charts') || '';
            if (!chartjs || !charts) {
                resolve();
                return;
            }
            var load = function (src) {
                return new Promise(function (res) {
                    if (!src) {
                        res();
                        return;
                    }
                    if (typeof window.Chart !== 'undefined' && src.indexOf('chart.umd') !== -1) {
                        res();
                        return;
                    }
                    if (typeof window.ratebChartsBoot === 'function' && src.indexOf('charts.js') !== -1) {
                        res();
                        return;
                    }
                    var exists = document.querySelector('script[src="' + src.replace(/"/g, '') + '"]');
                    if (exists) {
                        // Script tag present but may still be loading — wait briefly.
                        var n = 0;
                        var wait = setInterval(function () {
                            n++;
                            if ((src.indexOf('chart') !== -1 && typeof window.Chart !== 'undefined')
                                || (src.indexOf('charts.js') !== -1 && typeof window.ratebChartsBoot === 'function')
                                || n > 40) {
                                clearInterval(wait);
                                res();
                            }
                        }, 50);
                        return;
                    }
                    var el = document.createElement('script');
                    el.src = src;
                    el.async = true;
                    el.onload = el.onerror = function () { res(); };
                    (document.body || document.documentElement).appendChild(el);
                });
            };
            load(chartjs).then(function () { return load(charts); }).then(resolve);
        });
    }

    function abortInflight() {
        if (inflightCtrl) {
            try { inflightCtrl.abort(); } catch (eAb) { /* ignore */ }
            inflightCtrl = null;
        }
    }

    function bootNow() {
        var root = document.querySelector('[data-cm-dash="v5c"]');
        if (!root || !document.querySelector('canvas[id^="chart-"]')) {
            return;
        }
        abortInflight();
        var myGen = ++bootGen;
        var url = root.getAttribute('data-charts-url');
        markCharts('loading');

        loadChartLibs(root).then(function () {
            if (myGen !== bootGen) {
                return;
            }
            if (typeof window.Chart === 'undefined') {
                markCharts('empty');
                return;
            }
            paintCharts();
            if (!url) {
                markCharts('ready');
                return;
            }
            try {
                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    markCharts('ready');
                    return;
                }
            } catch (eOff) { /* continue */ }

            var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            inflightCtrl = ctrl;
            var timer = setTimeout(function () {
                if (ctrl) {
                    try { ctrl.abort(); } catch (e) { /* ignore */ }
                }
            }, 6000);

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: ctrl ? ctrl.signal : undefined
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (myGen !== bootGen) {
                    return;
                }
                if (!data || !data.ok) {
                    markCharts('empty');
                    return;
                }
                applyCharts(data.charts || {});
                setTimeout(function () {
                    if (myGen !== bootGen) {
                        return;
                    }
                    paintCharts();
                }, 0);
            }).catch(function () {
                if (myGen === bootGen) {
                    markCharts('empty');
                }
            }).finally(function () {
                clearTimeout(timer);
                if (inflightCtrl === ctrl) {
                    inflightCtrl = null;
                }
            });
        });
    }

    function boot() {
        if (bootTimer) {
            clearTimeout(bootTimer);
        }
        bootTimer = setTimeout(bootNow, 80);
    }

    window.ratebDashboardChartsBoot = boot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
    document.addEventListener('rateb:nav:beforeLeave', abortInflight);
})();
