(function () {
    'use strict';

    var STORAGE_KEY = 'rateb_theme';
    var root = document.documentElement;

    function applyTheme(mode) {
        root.setAttribute('data-theme', mode || 'auto');
        localStorage.setItem(STORAGE_KEY, mode || 'auto');
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-theme-choice') === (mode || 'auto'));
        });
    }

    function initTheme() {
        var saved = localStorage.getItem(STORAGE_KEY) || 'auto';
        applyTheme(saved);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyTheme(btn.getAttribute('data-theme-choice'));
            });
        });
    });
})();
