(function () {
    'use strict';

    var STORAGE_KEY = 'rateb_theme';
    var root = document.documentElement;

    function resolveBsTheme(mode) {
        if (mode === 'auto') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return mode === 'light' ? 'light' : 'dark';
    }

    function applyTheme(mode) {
        var chosen = mode || 'dark';
        root.setAttribute('data-theme', chosen);
        root.setAttribute('data-bs-theme', resolveBsTheme(chosen));
        localStorage.setItem(STORAGE_KEY, chosen);
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            var pick = btn.getAttribute('data-theme-choice');
            var on = pick === chosen;
            btn.classList.toggle('active', on);
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function initTheme() {
        var saved = localStorage.getItem(STORAGE_KEY) || 'dark';
        applyTheme(saved);
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
                if ((localStorage.getItem(STORAGE_KEY) || 'dark') === 'auto') {
                    applyTheme('auto');
                }
            });
        }
    });
})();
