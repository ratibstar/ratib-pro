(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cm-chart-tabs]').forEach(function (root) {
            var tabs = root.querySelectorAll('[data-cm-chart-tab]');
            var panes = root.querySelectorAll('[data-cm-chart-pane]');
            if (!tabs.length || !panes.length) {
                return;
            }
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var target = tab.getAttribute('data-cm-chart-tab');
                    tabs.forEach(function (t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    var activePane = null;
                    panes.forEach(function (p) {
                        var isActive = p.getAttribute('data-cm-chart-pane') === target;
                        p.classList.toggle('is-active', isActive);
                        if (isActive) {
                            activePane = p;
                        }
                    });
                    if (activePane && typeof window.ratebChartInitPane === 'function') {
                        window.ratebChartInitPane(activePane);
                    }
                });
            });
        });
    });
})();
