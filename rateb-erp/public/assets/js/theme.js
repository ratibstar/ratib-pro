(function () {
    'use strict';

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
        var saved = localStorage.getItem(storageKey(resolved));
        if (saved) {
            return saved;
        }
        if (resolved === 'erp') {
            return localStorage.getItem(LEGACY_KEY) || 'dark';
        }
        return 'dark';
    }

    function resolveBsTheme(mode) {
        if (mode === 'auto') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
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
        var chosen = mode || 'dark';
        root.setAttribute('data-theme', chosen);
        root.setAttribute('data-bs-theme', resolveBsTheme(chosen));
        localStorage.setItem(storageKey(), chosen);
        syncThemeButtons(chosen);
    }

    function initTheme() {
        applyTheme(readSaved());
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyTheme(btn.getAttribute('data-theme-choice'));
            });
        });
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (readSaved() === 'auto') {
                    applyTheme('auto');
                }
            });
        }
    });
})();
