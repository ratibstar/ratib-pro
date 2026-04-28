/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/frame-guard.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/frame-guard.js`.
 */
(function () {
    if (window.self !== window.top) {
        window.top.location.href = window.location.href;
    }
})();
