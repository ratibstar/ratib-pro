(function () {
    'use strict';
    document.documentElement.setAttribute('data-pos-module', '1');

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.documentElement;

        function applyPosTheme(mode) {
            var chosen = mode === 'light' ? 'light' : 'dark';
            root.setAttribute('data-theme', chosen);
            root.setAttribute('data-bs-theme', chosen);
            try {
                localStorage.setItem('rateb_pos_theme', chosen);
            } catch (e) {}
            document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
                var on = btn.getAttribute('data-theme-choice') === chosen;
                btn.classList.toggle('active', on);
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        var saved = 'dark';
        try {
            saved = localStorage.getItem('rateb_pos_theme') || root.getAttribute('data-theme') || 'dark';
        } catch (e) {}
        applyPosTheme(saved);

        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                applyPosTheme(btn.getAttribute('data-theme-choice'));
            });
        });
    });
})();
