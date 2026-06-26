<?php
/**
 * RATEB ERP — database backup download (b64/zip/gzip).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not authenticated');
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

if (!control_rateb_erp_is_installed()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('ERP not installed');
}

control_rateb_erp_ensure_root();
require_once control_rateb_erp_root_path() . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(control_rateb_erp_root_path());
require_once control_rateb_erp_root_path() . '/app/services/BackupDownloadService.php';

$service = new \Rateb\App\Services\BackupDownloadService();
$format = (string) ($_GET['format'] ?? 'b64');
$fresh = isset($_GET['fresh']) && (string) $_GET['fresh'] !== '0';
$file = trim((string) ($_GET['file'] ?? ''));

$service->sendBackup($format, $fresh, $file);
