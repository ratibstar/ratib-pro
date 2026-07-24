(function () {
    'use strict';

    if (window.__RATEB_THEME_BOUND__) {
        try {
            if (typeof window.ratebApplyTheme === 'function') {
                window.ratebApplyTheme();
            }
        } catch (eRe) { /* ignore */ }
        return;
    }
    window.__RATEB_THEME_BOUND__ = true;

    var LEGACY_KEY = 'rateb_theme';
    var SCOPE_KEYS = { erp: 'rateb_erp_theme', pos: 'rateb_pos_theme' };
    var root = document.documentElement;

    function detectScope() {
        var scoped = root.getAttribute('data-theme-scope');
        if (scoped === 'pos' || scoped === 'erp') {
            return scoped;
        }
        var body = document.body;
        if (body && (body.classList.contains('rateb-pos-shell') || body.classList.contains('pos-v2-body'))) {
            return 'pos';
        }
        return 'erp';
    }

    function storageKey(scope) {
        return SCOPE_KEYS[scope || detectScope()] || SCOPE_KEYS.erp;
    }

    function readSaved(scope) {
        var resolved = scope || detectScope();
        var saved = null;
        try {
            saved = localStorage.getItem(storageKey(resolved));
        } catch (eLs) { /* ignore */ }
        if (saved) {
            return saved;
        }
        if (resolved === 'erp') {
            try {
                return localStorage.getItem(LEGACY_KEY) || 'dark';
            } catch (eLeg) {
                return 'dark';
            }
        }
        return 'dark';
    }

    function resolveBsTheme(mode) {
        if (mode === 'auto') {
            try {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            } catch (eMq) {
                return 'dark';
            }
        }
        return mode === 'light' ? 'light' : 'dark';
    }

    function syncThemeButtons(mode) {
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            var pick = btn.getAttribute('data-theme-choice');
            var on = pick === mode;
            btn.classList.toggle('active', on);
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function applyTheme(mode) {
        var chosen = mode || readSaved() || 'dark';
        if (chosen !== 'light' && chosen !== 'dark' && chosen !== 'auto') {
            chosen = 'dark';
        }
        var bs = resolveBsTheme(chosen);
        root.setAttribute('data-theme', chosen);
        root.setAttribute('data-bs-theme', bs);
        try {
            localStorage.setItem(storageKey(), chosen);
            if (detectScope() === 'erp') {
                localStorage.setItem(LEGACY_KEY, chosen);
            }
        } catch (eSet) { /* ignore */ }
        try {
            var link = document.getElementById('rateb-theme-css');
            if (link) {
                var next = bs === 'light'
                    ? (link.getAttribute('data-light-href') || '')
                    : (link.getAttribute('data-dark-href') || '');
                if (next && link.getAttribute('href') !== next) {
                    link.setAttribute('href', next);
                }
            }
        } catch (eThemeCss) { /* ignore */ }
        try {
            window.__RATEB_ERP_THEME_BS__ = bs;
        } catch (eWin) { /* ignore */ }
        syncThemeButtons(chosen);
        try {
            document.dispatchEvent(new CustomEvent('rateb:themechange', {
                detail: { mode: chosen, bs: bs }
            }));
        } catch (eEv) { /* ignore */ }
    }

    window.ratebApplyTheme = applyTheme;

    // Delegation — survives soft-nav and late script inject (no DCL-only bind).
    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest
            ? ev.target.closest('[data-theme-choice]')
            : null;
        if (!btn) {
            return;
        }
        ev.preventDefault();
        applyTheme(btn.getAttribute('data-theme-choice') || 'dark');
    }, true);

    function boot() {
        applyTheme(readSaved());
        if (window.matchMedia) {
            try {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    if (readSaved() === 'auto') {
                        applyTheme('auto');
                    }
                });
            } catch (eMq) { /* ignore */ }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', function () {
        syncThemeButtons(readSaved());
    });
})();
