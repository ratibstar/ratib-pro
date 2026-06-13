(function () {
    'use strict';

    var storageKey = 'rateb_mkt_theme';

    function themeKey() {
        return document.documentElement.getAttribute('data-portal-layout') === '1'
            ? 'rateb_portal_theme'
            : storageKey;
    }

    function applyTheme(mode) {
        var root = document.documentElement;
        var bs = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        root.setAttribute('data-bs-theme', bs);
        var buttons = document.querySelectorAll('[data-mkt-theme]');
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-mkt-theme') === mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function initTheme() {
        var isPortal = document.documentElement.getAttribute('data-portal-layout') === '1';
        var saved = null;
        try {
            saved = localStorage.getItem(themeKey());
        } catch (e) {}
        var mode = saved || (isPortal ? 'dark' : 'light');
        applyTheme(mode === 'dark' ? 'dark' : 'light');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-mkt-theme]');
        if (!btn) return;
        var mode = btn.getAttribute('data-mkt-theme') === 'dark' ? 'dark' : 'light';
        try {
            localStorage.setItem(themeKey(), mode);
        } catch (err) {}
        applyTheme(mode);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
