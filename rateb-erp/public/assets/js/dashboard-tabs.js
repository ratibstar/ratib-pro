(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dash-chart-tabs]').forEach(function (root) {
            var tabs = root.querySelectorAll('[data-dash-chart-tab]');
            var panes = root.querySelectorAll('[data-dash-chart-pane]');
            if (!tabs.length || !panes.length) {
                return;
            }
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var target = tab.getAttribute('data-dash-chart-tab');
                    tabs.forEach(function (t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    panes.forEach(function (p) {
                        p.classList.toggle('is-active', p.getAttribute('data-dash-chart-pane') === target);
                    });
                });
            });
        });
    });
})();
