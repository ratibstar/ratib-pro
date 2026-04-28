<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/super-admin-tenants.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/super-admin-tenants.php`.
 */
/**
 * Control Panel: Super Admin - All Countries Login
 * Quick access to each country's login page + user count
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    die('Control panel database unavailable.');
}

require_once __DIR__ . '/../../includes/control-permissions.php';

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
$apiBase = $baseUrl . '/api/control';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Super Admin - All Countries Login', ['css/control/super-admin-tenants.css'], []);
?>

<div class="control-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>All Countries Login</h2>
        <a href="<?php echo pageUrl('control/dashboard.php'); ?>?control=1" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
    </div>

    <div class="countries-login-section">
        <div class="section-header">
            <h3><i class="fas fa-globe me-2"></i>Quick access to each country's login page</h3>
        </div>
        <div class="countries-login-grid" id="countriesLoginGrid">
            <div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<div id="page-config" class="hidden" data-agencies-url="<?php echo htmlspecialchars(pageUrl('control/agencies.php') . '?control=1'); ?>"></div>
<?php endControlLayout(['js/control/super-admin-tenants.js']); ?>
