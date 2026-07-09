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

        document.querySelectorAll('[data-pos-header-menu]').forEach(function (menu) {
            var toggle = menu.querySelector('[data-pos-menu-toggle]');
            var panel = menu.querySelector('[data-pos-menu-panel]');
            if (!toggle || !panel) {
                return;
            }

            function closeMenu() {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openMenu() {
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                if (panel.hidden) {
                    openMenu();
                } else {
                    closeMenu();
                }
            });

            panel.addEventListener('click', function (ev) {
                ev.stopPropagation();
            });

            document.addEventListener('click', function () {
                closeMenu();
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    closeMenu();
                }
            });
        });
    });
})();
