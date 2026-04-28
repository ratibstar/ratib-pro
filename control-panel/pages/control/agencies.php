<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/agencies.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/agencies.php`.
 */
/**
 * Control Panel: Manage Agencies
 * Renders agencies content directly (no iframe)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../../core/TenantExecutionContext.php';
require_once __DIR__ . '/../../../core/query/QueryGateway.php';
require_once __DIR__ . '/../../../admin/core/EventBus.php';

function agenciesDebugLog(string $message): void
{
    $logFile = __DIR__ . '/../../../logs/agencies-page-debug.log';
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    $phpErrorLog = __DIR__ . '/../../../logs/php-errors.log';
    @error_log('AGENCIES_PAGE_DEBUG ' . $message . PHP_EOL, 3, $phpErrorLog);
    $tmpLog = rtrim((string) sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'agencies-page-debug.log';
    @file_put_contents($tmpLog, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

set_exception_handler(static function (Throwable $e): void {
    $msg = 'control agencies page uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    error_log($msg);
    agenciesDebugLog($msg);
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<div class="alert alert-danger m-3">'
        . 'Agencies page internal error: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . ' ('
        . htmlspecialchars(basename($e->getFile()) . ':' . (string) $e->getLine(), ENT_QUOTES, 'UTF-8')
        . ')'
        . '</div>';
    exit;
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if (!$last) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array((int) ($last['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    $msg = 'control agencies page fatal: ' . ($last['message'] ?? 'unknown') . ' @ ' . ($last['file'] ?? '-') . ':' . ($last['line'] ?? '-');
    error_log($msg);
    agenciesDebugLog($msg);
});

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_AGENCIES, 'view_control_agencies');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$agencyId = (int) ($_GET['agency_id'] ?? 0);
$tenantId = 0;
if ($agencyId > 0) {
    try {
        QueryGateway::setConnection($ctrl);
        $st = QueryGateway::execute("SELECT tenant_id FROM control_agencies WHERE id = ? LIMIT 1", [$agencyId]);
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
        }
    } catch (Throwable $e) {
        $tenantId = 0;
    }
}
try {
    if ($tenantId > 0) {
        TenantExecutionContext::setTenant($tenantId);
    } else {
        TenantExecutionContext::setSystemContext();
    }
} catch (Throwable $e) {
    // Request context may already be locked by bootstrap; avoid hard-fail on page load.
    TenantExecutionContext::markSystemContext(true);
}
emitEvent('AGENCY_PANEL_LOADED', 'info', 'Agencies panel viewed', [
    'tenant_id' => $tenantId > 0 ? $tenantId : null,
    'agency_id' => $agencyId > 0 ? $agencyId : null,
    'action' => 'agencies_page_load',
    'request_id' => getRequestId(),
    'source' => 'control_panel_agencies_page',
    'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    'query' => null,
    'mode' => 'SAFE',
    'duration_ms' => null,
]);

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Manage Agencies', ['css/control/admins.css', 'css/control/agencies.css'], []);

require_once __DIR__ . '/../../includes/control/agencies-content.php';

endControlLayout(['js/control/agencies-standalone.js']);
