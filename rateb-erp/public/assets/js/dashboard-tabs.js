(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rdx-chart-tabs]').forEach(function (root) {
            var tabs = root.querySelectorAll('[data-rdx-chart-tab]');
            var panes = root.querySelectorAll('[data-rdx-chart-pane]');
            if (!tabs.length || !panes.length) {
                return;
            }
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var target = tab.getAttribute('data-rdx-chart-tab');
                    tabs.forEach(function (t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    var activePane = null;
                    panes.forEach(function (p) {
                        var isActive = p.getAttribute('data-rdx-chart-pane') === target;
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
