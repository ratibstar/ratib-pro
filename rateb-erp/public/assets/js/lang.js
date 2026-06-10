(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-locale]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = link.getAttribute('href');
            });
        });
    });
})();
