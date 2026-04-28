/**
 * EN: Implements system administration/observability module behavior in `admin/assets/js/admin-refresh.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/assets/js/admin-refresh.js`.
 */
(function () {
    var buttons = document.querySelectorAll('[data-refresh-page]');
    if (!buttons.length) {
        return;
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.location.reload();
        });
    });
})();
