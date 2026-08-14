/**
 * Phase I — Employee Master 360 lazy tabs (Admin Web only).
 * Loads secondary tab HTML from data-tab-endpoint?tab=…
 */
(function () {
    'use strict';

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.prototype.slice.call(root.querySelectorAll(sel));
    }

    function setActive(root, tab) {
        qsa(root, '[data-rateb-emp360-tab]').forEach(function (btn) {
            var on = btn.getAttribute('data-rateb-emp360-tab') === tab;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        root.setAttribute('data-active-tab', tab);
        try {
            var url = new URL(window.location.href);
            if (tab === 'overview') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', tab);
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) { /* ignore */ }
    }

    function showOverview(root) {
        var ov = qs(root, '[data-rateb-emp360-overview]');
        var lazy = qs(root, '[data-rateb-emp360-lazy]');
        if (ov) {
            ov.hidden = false;
        }
        if (lazy) {
            lazy.hidden = true;
        }
    }

    function showLazy(root) {
        var ov = qs(root, '[data-rateb-emp360-overview]');
        var lazy = qs(root, '[data-rateb-emp360-lazy]');
        if (ov) {
            ov.hidden = true;
        }
        if (lazy) {
            lazy.hidden = false;
        }
    }

    function cacheGet(root, tab) {
        if (!root._ratebEmp360Cache) {
            root._ratebEmp360Cache = {};
        }
        return root._ratebEmp360Cache[tab] || null;
    }

    function cacheSet(root, tab, html) {
        if (!root._ratebEmp360Cache) {
            root._ratebEmp360Cache = {};
        }
        root._ratebEmp360Cache[tab] = html;
    }

    function renderHtml(root, html) {
        var loading = qs(root, '[data-rateb-emp360-loading]');
        var content = qs(root, '[data-rateb-emp360-content]');
        if (loading) {
            loading.hidden = true;
        }
        if (content) {
            content.hidden = false;
            content.innerHTML = html;
        }
    }

    function loadTab(root, tab) {
        setActive(root, tab);
        if (tab === 'overview') {
            showOverview(root);
            return;
        }
        showLazy(root);
        var cached = cacheGet(root, tab);
        if (cached) {
            renderHtml(root, cached);
            return;
        }
        var endpoint = root.getAttribute('data-tab-endpoint') || '';
        if (!endpoint) {
            return;
        }
        var loading = qs(root, '[data-rateb-emp360-loading]');
        var content = qs(root, '[data-rateb-emp360-content]');
        if (loading) {
            loading.hidden = false;
        }
        if (content) {
            content.hidden = true;
            content.innerHTML = '';
        }
        var sep = endpoint.indexOf('?') >= 0 ? '&' : '?';
        var url = endpoint + sep + 'tab=' + encodeURIComponent(tab) + '&format=html';
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('tab_failed');
            }
            return res.text();
        }).then(function (html) {
            cacheSet(root, tab, html);
            if (root.getAttribute('data-active-tab') === tab) {
                renderHtml(root, html);
            }
        }).catch(function () {
            if (loading) {
                loading.hidden = true;
            }
            if (content) {
                content.hidden = false;
                content.innerHTML = '<p class="text-muted mb-0">—</p>';
            }
        });
    }

    function bind(root) {
        qsa(root, '[data-rateb-emp360-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-rateb-emp360-tab') || 'overview';
                loadTab(root, tab);
            });
        });
        var initial = root.getAttribute('data-active-tab') || 'overview';
        if (initial !== 'overview') {
            loadTab(root, initial);
        }
    }

    function boot() {
        qsa(document, '[data-rateb-emp360]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
