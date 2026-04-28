/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/header-support-alerts.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/header-support-alerts.js`.
 */
(function() {
    'use strict';

    function resolveControlApiBase() {
        var cfg = document.getElementById('control-config');
        var s = (cfg && cfg.getAttribute('data-api-base')) || '';
        s = String(s).replace(/\/+$/, '');
        if (s) return s;
        var appCfg = document.getElementById('app-config');
        s = (appCfg && appCfg.getAttribute('data-control-api-path')) || '';
        s = String(s).replace(/\/+$/, '');
        if (s) return s;
        var bu = (appCfg && appCfg.getAttribute('data-base-url')) || '';
        bu = String(bu).replace(/\/+$/, '');
        if (bu) return bu + '/api/control';
        var path = window.location.pathname || '';
        var pagesIdx = path.indexOf('/pages/');
        if (pagesIdx !== -1) {
            return window.location.origin + path.substring(0, pagesIdx) + '/api/control';
        }
        return '';
    }

    function esc(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function updateSidebarBadge(unread) {
        var sb = document.getElementById('sidebarSupportChatsBadge');
        if (!sb) return;
        if (unread > 0) {
            sb.style.display = '';
            sb.textContent = unread > 99 ? '99+' : String(unread);
        } else {
            sb.style.display = 'none';
            sb.textContent = '0';
        }
    }

    function runInit() {
        var apiBase = resolveControlApiBase();
        var btn = document.getElementById('supportAlertsBtn');
        var badge = document.getElementById('supportAlertsBadge');
        var dropdown = document.getElementById('supportAlertsDropdown');
        var list = document.getElementById('supportAlertsList');
        if (!apiBase || !btn || !badge || !dropdown || !list) return;
        if (btn.getAttribute('data-support-alerts-bound') === '1') return;
        btn.setAttribute('data-support-alerts-bound', '1');

        var pollMs = 2000;

        function render(items, unread) {
            if (unread > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = unread > 99 ? '99+' : String(unread);
            } else {
                badge.style.display = 'none';
            }
            updateSidebarBadge(unread);

            if (!items || items.length === 0) {
                list.innerHTML = '<div class="header-alert-empty">No unread chats.</div>';
                return;
            }

            var html = '';
            for (var i = 0; i < items.length; i++) {
                var c = items[i] || {};
                var id = parseInt(c.id, 10) || 0;
                var src = esc(c.source_page || 'Unknown');
                var country = esc(c.country_name || (c.country_id ? ('Country #' + c.country_id) : 'Not set'));
                var unreadCount = parseInt(c.unread_count || 0, 10) || 0;
                var href = 'pages/control/support-chats.php?status=open&control=1';
                if (c.country_id) href += '&country_id=' + encodeURIComponent(c.country_id);
                html += '<a class="header-alert-item" href="' + href.replace(/"/g, '%22') + '">' +
                    '<strong>#' + id + ' ' + src + '</strong>' +
                    '<small>' + country + ' - unread: ' + unreadCount + '</small>' +
                    '</a>';
            }
            list.innerHTML = html;
        }

        function load() {
            var url = apiBase + '/support-chats.php?status=open&page=1&limit=5&_=' + Date.now();
            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.success) return;
                    var unread = parseInt(data.unread_total || 0, 10) || 0;
                    var items = (data.list || []).filter(function(c) {
                        return (parseInt(c.unread_count || 0, 10) || 0) > 0;
                    });
                    render(items, unread);
                })
                .catch(function() { /* ignore */ });
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            dropdown.style.display = (dropdown.style.display === 'none' || !dropdown.style.display) ? 'block' : 'none';
            load();
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        load();
        setInterval(load, pollMs);
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') load();
        });
        window.addEventListener('focus', load);
    }

    function scheduleInit() {
        if (window.UserPermissions && window.UserPermissions.loaded) {
            runInit();
            return;
        }
        var n = 0;
        var iv = setInterval(function() {
            n++;
            if (window.UserPermissions && window.UserPermissions.loaded) {
                clearInterval(iv);
                runInit();
            } else if (n >= 80) {
                clearInterval(iv);
                runInit();
            }
        }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleInit);
    } else {
        scheduleInit();
    }
})();
