<?php
/**
 * RATEB ERP — runs inside Control Panel (rateb.sa).
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
    $diag = control_rateb_erp_diagnostic();
    require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
    startControlLayout('نظام رتب ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
    ?>
    <div class="alert alert-warning">
        <h2 class="h5"><i class="fas fa-exclamation-triangle"></i> ملفات نظام رتب ERP غير موجودة على السيرفر</h2>
        <p>Upload <code>rateb-erp/</code> to <code>public_html/rateb-erp/</code> (same level as <code>control-panel/</code>), or push to GitHub <code>main</code> and wait for deploy.</p>
        <p class="mb-1">Checked path:</p>
        <code><?php echo htmlspecialchars($diag['resolved'] . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?></code>
        <div class="mt-3">
            <a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Back to ERP hub</a>
        </div>
    </div>
    <?php
    endControlLayout();
    exit;
}

$routeRaw = $_GET['route'] ?? 'admin';
if (is_array($routeRaw)) {
    $routeRaw = end($routeRaw);
}
$route = trim((string) $routeRaw, '/');
if ($route === '') {
    $route = 'admin';
}

define('RATEB_CP_ENTRY', true);
define('RATEB_CP_ROUTE', $route);
define('RATEB_CP_APP_URL', control_rateb_erp_app_base_url());
define('RATEB_CP_ASSETS_URL', control_rateb_erp_assets_base_url());

$_GET['route'] = $route;
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require $indexFile;
