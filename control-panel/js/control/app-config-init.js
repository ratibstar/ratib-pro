/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/app-config-init.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/app-config-init.js`.
 */
(function () {
    var el = document.getElementById("app-config");
    if (!el) return;
    window.APP_CONFIG = window.APP_CONFIG || {};
    window.APP_CONFIG.baseUrl = el.getAttribute("data-base-url") || "";
    window.APP_CONFIG.apiBase = el.getAttribute("data-api-base") || "";
    window.APP_CONFIG.controlApiPath = el.getAttribute("data-control-api-path") || (window.APP_CONFIG.baseUrl ? (window.APP_CONFIG.baseUrl + "/api/control") : "");
    window.APP_CONFIG.controlHrApiBase = el.getAttribute("data-control-hr-api-base") || "";
    window.BASE_PATH = window.APP_CONFIG.baseUrl;
})();
