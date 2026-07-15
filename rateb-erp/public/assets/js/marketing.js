(function () {
    'use strict';

    var storageKey = 'rateb_mkt_theme';

    function isPortalTheme() {
        var root = document.documentElement;
        return root.getAttribute('data-portal-layout') === '1'
            || root.getAttribute('data-career-layout') === '1';
    }

    function themeKey() {
        return isPortalTheme() ? 'rateb_portal_theme' : storageKey;
    }

    function resolveMode(raw) {
        if (raw === 'auto') {
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        return raw === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(mode, stored) {
        var root = document.documentElement;
        var resolved = resolveMode(mode);
        var bs = resolved === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-bs-theme', bs);
        var activeKey = stored || mode;
        var buttons = document.querySelectorAll('[data-mkt-theme]');
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-mkt-theme') === activeKey
                || (activeKey === 'auto' && btn.getAttribute('data-mkt-theme') === resolved);
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function initTheme() {
        var isPortal = isPortalTheme();
        var saved = null;
        try {
            saved = localStorage.getItem(themeKey());
        } catch (e) {}
        var mode = saved || (isPortal ? 'auto' : 'light');
        applyTheme(mode, mode);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-mkt-theme]');
        if (!btn) return;
        var mode = btn.getAttribute('data-mkt-theme') || 'light';
        if (mode !== 'dark' && mode !== 'light' && mode !== 'auto') {
            mode = 'light';
        }
        try {
            localStorage.setItem(themeKey(), mode);
        } catch (err) {}
        applyTheme(mode, mode);
    });

    if (window.matchMedia) {
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                var saved = null;
                try {
                    saved = localStorage.getItem(themeKey());
                } catch (e) {}
                if (saved === 'auto' || (saved === null && isPortalTheme())) {
                    applyTheme('auto', 'auto');
                }
            });
        } catch (err) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
