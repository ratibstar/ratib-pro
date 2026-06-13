(function () {
    'use strict';

    var storageKey = 'rateb_mkt_theme';

    function applyTheme(mode) {
        var root = document.documentElement;
        var bs = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        root.setAttribute('data-bs-theme', bs);
        var buttons = document.querySelectorAll('[data-mkt-theme]');
        buttons.forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-mkt-theme') === mode);
        });
    }

    function initTheme() {
        var saved = 'light';
        try {
            saved = localStorage.getItem(storageKey) || 'light';
        } catch (e) {}
        applyTheme(saved === 'dark' ? 'dark' : 'light');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-mkt-theme]');
        if (!btn) return;
        var mode = btn.getAttribute('data-mkt-theme') === 'dark' ? 'dark' : 'light';
        try {
            localStorage.setItem(storageKey, mode);
        } catch (err) {}
        applyTheme(mode);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
