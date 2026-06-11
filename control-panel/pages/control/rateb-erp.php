<?php
/**
 * RATEB ERP — launcher inside the Control Panel shell.
 * URL: …/control-panel/pages/control/rateb-erp.php?control=1
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

$erpLinks = control_rateb_erp_nav_links();
$erpInstalled = control_rateb_erp_is_installed();
$schemaReady = false;

if ($erpInstalled) {
    try {
        $erpRoot = control_rateb_erp_root_path();
        define('RATEB_ROOT', str_replace('\\', '/', realpath($erpRoot) ?: $erpRoot));
        require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
        Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
        require_once RATEB_ROOT . '/app/services/MigrationService.php';
        $schemaReady = (new \Rateb\App\Services\MigrationService())->isSchemaReady();
    } catch (Throwable $e) {
        $schemaReady = false;
    }
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('RATEB ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-hospital me-2"></i>RATEB ERP</strong>
    — Medical Procurement &amp; Healthcare ERP. Everything opens here on <strong>out.ratib.sa</strong> inside your Control Panel (not rateb.sa).
</p>

<div class="rateb-erp-status mb-4" role="status">
    <div class="rateb-erp-status-item<?php echo $erpInstalled ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $erpInstalled ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Application files: <?php echo $erpInstalled ? 'Found' : 'Missing — upload rateb-erp/ to server'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $schemaReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $schemaReady ? 'fa-table' : 'fa-database'; ?>"></i>
        <span>Database: <?php echo $schemaReady ? 'Ready' : 'Run setup first'; ?></span>
    </div>
</div>

<?php if (!$schemaReady) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-database"></i> First time setup</h3>
    <p>Click once to create tables — no SSH, no <code>php rateb-erp/migrations/run.php</code> in terminal.</p>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-play"></i> Run database setup
    </a>
</div>
<?php } ?>

<div class="control-settings-intro mb-2"><strong>Super Admin modules</strong></div>
<div class="control-settings-grid mb-4">
    <?php foreach ($erpLinks as $link) { ?>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($link['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary<?php echo $schemaReady ? '' : ' disabled'; ?>">
            <i class="fas fa-arrow-right"></i> Open
        </a>
    </div>
    <?php } ?>
</div>

<div class="control-settings-intro mb-2"><strong>Portals</strong></div>
<div class="control-settings-grid mb-4">
    <div class="control-settings-card">
        <h3><i class="fas fa-user-shield"></i> Super Admin login</h3>
        <p>Default: <code>admin@rateb.sa</code> / <code>password</code> (change after first login).</p>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_app_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary<?php echo $schemaReady ? '' : ' disabled'; ?>">Admin login</a>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-building"></i> Company portal</h3>
        <p>Procurement &amp; inventory per company.</p>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_app_url('company/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary<?php echo $schemaReady ? '' : ' disabled'; ?>">Company login</a>
    </div>
</div>

<?php endControlLayout(); ?>
