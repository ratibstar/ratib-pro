/**
 * Hydrate admin dashboard charts after lite HTML paint.
 */
(function () {
    'use strict';

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

    function boot() {
        var root = document.querySelector('[data-cm-dash="v5c"]');
        var url = root && root.getAttribute('data-charts-url');
        if (!url) {
            return;
        }
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, 20000);
        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (!data || !data.ok) {
                return;
            }
            applyCharts(data.charts || {});
            if (typeof window.ratebChartsBoot === 'function') {
                window.ratebChartsBoot();
            } else if (typeof window.ratebChartInitPane === 'function') {
                window.ratebChartInitPane(document);
            }
        }).catch(function () { /* ignore */ }).finally(function () {
            clearTimeout(timer);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
