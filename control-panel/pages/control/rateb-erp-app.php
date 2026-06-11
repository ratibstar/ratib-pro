<?php
/**
 * RATEB ERP — runs inside Control Panel (out.ratib.sa).
 * URL: /control-panel/pages/control/rateb-erp-app.php?control=1&route=admin/login
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

$erpRoot = control_rateb_erp_root_path();
$indexFile = $erpRoot . '/public/index.php';

if (!is_file($indexFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>RATEB ERP</title></head><body>';
    echo '<h1>RATEB ERP not installed</h1>';
    echo '<p>Upload the <code>rateb-erp/</code> folder to the server, then open ';
    echo '<a href="' . htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">Run database setup</a>.</p>';
    echo '</body></html>';
    exit;
}

$route = trim((string) ($_GET['route'] ?? 'admin'), '/');
if ($route === '') {
    $route = 'admin';
}

define('RATEB_CP_ENTRY', true);
define('RATEB_CP_ROUTE', $route);
define('RATEB_CP_APP_URL', control_rateb_erp_app_url(''));
define('RATEB_CP_ASSETS_URL', control_rateb_erp_assets_base_url());

$_GET['route'] = $route;
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require $indexFile;
