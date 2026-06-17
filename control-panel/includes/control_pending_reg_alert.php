<?php
/**
 * Control Panel: Pending registration popup alert on all pages.
 */
$controlAlertBasePath = rtrim(preg_replace('#/pages/[^?]*.*$#', '', $_SERVER['REQUEST_URI'] ?? ''), '/');
$controlAlertApiBase = isset($apiBase) ? $apiBase : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $controlAlertBasePath . '/api/control');
$controlAlertCss = function_exists('asset') ? asset('css/control-pending-reg-alert.css') : '/css/control-pending-reg-alert.css';
$controlAlertJs = function_exists('asset') ? asset('js/control-pending-reg-alert.js') : '/js/control-pending-reg-alert.js';
$alertTitle = function_exists('cp_t') ? cp_t('alert.pending_title') : 'ATTENTION REQUIRED';
$alertMessage = function_exists('cp_t') ? cp_t('alert.pending_message', ['count' => '{count}']) : 'You have {count} pending registration request(s). Please review them immediately.';
$alertGo = function_exists('cp_t') ? cp_t('alert.go_to_requests') : 'Go to Requests';
$alertOk = function_exists('cp_t') ? cp_t('common.ok') : 'OK';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($controlAlertCss); ?>?v=1">
<div id="pendingRegAlertOverlay">
    <div id="pendingRegAlertBox">
        <p class="mb-3 pending-reg-alert-title">
            <i class="fas fa-bell pending-reg-alert-bell"></i>
            <strong><?php echo htmlspecialchars($alertTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
        </p>
        <p id="pendingRegAlertMessage"><?php
            $msgParts = explode('{count}', $alertMessage, 2);
            echo htmlspecialchars($msgParts[0], ENT_QUOTES, 'UTF-8');
        ?><span id="pendingRegAlertNum">0</span><?php
            echo htmlspecialchars($msgParts[1] ?? '', ENT_QUOTES, 'UTF-8');
        ?></p>
        <div class="pending-reg-alert-actions">
            <a href="<?php echo htmlspecialchars(function_exists('pageUrl') ? pageUrl('control/registration-requests.php') : '/pages/control/registration-requests.php'); ?>" class="btn btn-danger btn-sm me-2 pending-reg-alert-btn-requests"><?php echo htmlspecialchars($alertGo, ENT_QUOTES, 'UTF-8'); ?></a>
            <button type="button" id="pendingRegAlertOk" class="btn btn-warning btn-sm pending-reg-alert-btn-ok"><?php echo htmlspecialchars($alertOk, ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars($controlAlertJs); ?>?v=1"></script>
