(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('rateb-sidebar-toggle');
        var sidebar = document.getElementById('rateb-sidebar');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('open');
            });
        }

        document.querySelectorAll('.rateb-flash .btn-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var alert = btn.closest('.rateb-flash');
                if (alert) {
                    alert.remove();
                }
            });
        });
    });
})();
