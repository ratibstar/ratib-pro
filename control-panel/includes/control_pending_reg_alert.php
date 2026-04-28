<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control_pending_reg_alert.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control_pending_reg_alert.php`.
 */
/**
 * Control Panel: Pending registration popup alert on all pages.
 * Shows a modal with OK; repeats every 10 minutes, up to 3 times, when there are pending requests.
 * Include this before </body> on every control panel page. Requires config.php (asset, pageUrl).
 */
$controlAlertBasePath = rtrim(preg_replace('#/pages/[^?]*.*$#', '', $_SERVER['REQUEST_URI'] ?? ''), '/');
$controlAlertApiBase = isset($apiBase) ? $apiBase : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $controlAlertBasePath . '/api/control');
$controlAlertCss = function_exists('asset') ? asset('css/control-pending-reg-alert.css') : '/css/control-pending-reg-alert.css';
$controlAlertJs = function_exists('asset') ? asset('js/control-pending-reg-alert.js') : '/js/control-pending-reg-alert.js';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($controlAlertCss); ?>?v=1">
<div id="pendingRegAlertOverlay">
    <div id="pendingRegAlertBox">
        <p class="mb-3 pending-reg-alert-title">
            <i class="fas fa-bell pending-reg-alert-bell"></i>
            <strong>⚠️ ATTENTION REQUIRED</strong>
        </p>
        <p id="pendingRegAlertMessage">You have <span id="pendingRegAlertNum">0</span> pending registration request(s). Please review them immediately.</p>
        <div class="pending-reg-alert-actions">
            <a href="<?php echo htmlspecialchars(function_exists('pageUrl') ? pageUrl('control/registration-requests.php') : '/pages/control/registration-requests.php'); ?>" class="btn btn-danger btn-sm me-2 pending-reg-alert-btn-requests">Go to Requests</a>
            <button type="button" id="pendingRegAlertOk" class="btn btn-warning btn-sm pending-reg-alert-btn-ok">OK</button>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars($controlAlertJs); ?>?v=1"></script>
