<?php
/**
 * RATEB ERP — launcher inside the Control Panel shell.
 * URL: …/pages/control/rateb-erp.php?control=1
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-nav.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$erpBase = control_rateb_erp_base_url();
$erpLinks = control_rateb_erp_nav_links();
$erpRoot = dirname(__DIR__, 3) . '/rateb-erp';
$erpPublicIndex = $erpRoot . '/public/index.php';
$erpInstalled = is_file($erpPublicIndex);
$migrationRunner = $erpRoot . '/migrations/run.php';
$migrationsReady = is_file($migrationRunner);

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('RATEB ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-hospital me-2"></i>RATEB ERP</strong>
    — Medical Procurement &amp; Healthcare ERP (Super Admin, company portal, REST API). Open modules below in a new tab.
</p>

<div class="rateb-erp-status mb-4" role="status">
    <div class="rateb-erp-status-item<?php echo $erpInstalled ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $erpInstalled ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Application files: <?php echo $erpInstalled ? 'Found' : 'Missing (upload rateb-erp/)'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $migrationsReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $migrationsReady ? 'fa-database' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Database migrations: <?php echo $migrationsReady ? 'Ready (run php rateb-erp/migrations/run.php)' : 'Not found'; ?></span>
    </div>
    <div class="rateb-erp-status-item rateb-erp-status-info">
        <i class="fas fa-link"></i>
        <span>Base URL: <code><?php echo htmlspecialchars($erpBase, ENT_QUOTES, 'UTF-8'); ?></code></span>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong>Super Admin</strong></div>
<div class="control-settings-grid mb-4">
    <?php foreach ($erpLinks as $link) { ?>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($link['description'] ?? 'Open in RATEB ERP Super Admin.', ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-arrow-up-right-from-square"></i> Open
        </a>
    </div>
    <?php } ?>
</div>

<div class="control-settings-intro mb-2"><strong>Portals &amp; API</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas fa-right-to-bracket"></i> Super Admin login</h3>
        <p>Platform administrator sign-in for companies, plans, and subscriptions.</p>
        <a href="<?php echo htmlspecialchars($erpBase . '/admin/login', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer"><i class="fas fa-user-shield"></i> Admin login</a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas fa-building-user"></i> Company portal</h3>
        <p>Procurement, inventory, suppliers, assets, and tenders per company.</p>
        <a href="<?php echo htmlspecialchars($erpBase . '/company/login', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer"><i class="fas fa-building"></i> Company login</a>
    </div>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas fa-code"></i> REST API v1</h3>
        <p>Token-based JSON API for integrations and mobile clients.</p>
        <a href="<?php echo htmlspecialchars($erpBase . '/api/v1', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light" target="_blank" rel="noopener noreferrer"><i class="fas fa-plug"></i> API index</a>
    </div>
</div>

<?php endControlLayout(); ?>
