<?php
/**
 * RATIB Contact Center — launcher inside Control Panel.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-nav.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$diag = control_contact_center_diagnostic();
$installed = $diag['installed'];
$dbTest = $installed ? control_contact_center_db_test() : ['ok' => false, 'schema' => false, 'db' => control_contact_center_db_name(), 'user' => control_contact_center_db_user(), 'error' => '', 'tables' => 0];
$schemaReady = $dbTest['ok'] && $dbTest['schema'];
$links = control_contact_center_nav_links();

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('RATIB Contact Center', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-headset me-2"></i>RATIB Contact Center</strong>
    — IVR, AI routing, WebRTC softphone, and unified omnichannel agent inbox.
    Database: <code><?php echo htmlspecialchars(control_contact_center_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>
    · User: <code><?php echo htmlspecialchars(control_contact_center_db_user(), ENT_QUOTES, 'UTF-8'); ?></code>
</p>

<div class="rateb-erp-status mb-4" role="status">
    <div class="rateb-erp-status-item<?php echo $installed ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $installed ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Module files: <?php echo $installed ? 'Found' : 'Missing — upload ratib-contact-center/'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $dbTest['ok'] ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $dbTest['ok'] ? 'fa-link' : 'fa-unlink'; ?>"></i>
        <span>DB connection: <?php echo $dbTest['ok'] ? 'OK' : 'Failed'; ?> — <code><?php echo htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></code></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $schemaReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $schemaReady ? 'fa-table' : 'fa-database'; ?>"></i>
        <span>Schema: <?php echo $schemaReady ? (int) $dbTest['tables'] . ' rcc_* tables' : 'Run database setup first'; ?></span>
    </div>
</div>

<?php if (!$installed) { ?>
<div class="alert alert-warning mb-4">
    <strong>Files not found on server</strong><br>
    Expected: <code><?php echo htmlspecialchars($diag['resolved'] . '/bootstrap.php', ENT_QUOTES, 'UTF-8'); ?></code>
</div>
<?php } elseif (!$dbTest['ok']) { ?>
<div class="alert alert-danger mb-4">
    <strong><i class="fas fa-database me-1"></i> MySQL permissions required</strong><br>
    In cPanel → MySQL® Databases:<br>
    1. Create database <code><?php echo htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></code><br>
    2. Create user <code><?php echo htmlspecialchars((string) $dbTest['user'], ENT_QUOTES, 'UTF-8'); ?></code> (same name as DB is OK)<br>
    3. <strong>Add User To Database</strong> → ALL PRIVILEGES<br>
    <?php if (($dbTest['error'] ?? '') !== '') { ?>
    <span class="small text-muted"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php } ?>
</div>
<?php } elseif (!$schemaReady) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-database"></i> First-time setup</h3>
    <p>Runs migrations 001–008 on <code><?php echo htmlspecialchars(control_contact_center_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>.</p>
    <a href="<?php echo htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-play"></i> Run database setup
    </a>
</div>
<?php } ?>

<div class="control-settings-grid mb-4">
    <?php foreach ($links as $link) { ?>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary<?php echo $schemaReady ? '' : ' disabled'; ?>">
            <i class="fas fa-arrow-right"></i> Open
        </a>
    </div>
    <?php } ?>
    <div class="control-settings-card">
        <h3><i class="fas fa-book"></i> Phase documentation</h3>
        <p>Architecture and module guides (IVR, Realtime, Softphone, AI Routing, Inbox).</p>
        <ul class="small mb-0">
            <li>PHASE-1 Architecture</li>
            <li>PHASE-2 IVR Runtime</li>
            <li>PHASE-3 Realtime Core</li>
            <li>PHASE-4 Softphone</li>
            <li>PHASE-5 AI Routing</li>
            <li>PHASE-6 Omnichannel Inbox</li>
        </ul>
    </div>
</div>

<div class="control-settings-card mb-4">
    <h3><i class="fas fa-server"></i> Runtime services</h3>
    <p class="small">For live WebSocket updates, run on the server:</p>
    <pre class="rateb-erp-migrate-log mb-0">php ratib-contact-center/bin/rcc-realtime-hub.php</pre>
    <p class="small text-muted mb-0 mt-2">WebSocket: <?php echo htmlspecialchars(control_contact_center_ws_url(), ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<?php endControlLayout(); ?>
