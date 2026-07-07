(function () {
    'use strict';
    document.documentElement.setAttribute('data-pos-module', '1');

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.documentElement;
        var syncThemeButtons = function () {
            var mode = localStorage.getItem('rateb_pos_theme') || root.getAttribute('data-theme') || 'dark';
            document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
                var choice = btn.getAttribute('data-theme-choice');
                var active = choice === mode;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };
        syncThemeButtons();
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTimeout(syncThemeButtons, 0);
            });
        });
    });
})();
