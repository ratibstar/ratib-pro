<?php
/**
 * RATIB Contact Center — database migrations from Control Panel.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$installed = control_contact_center_is_installed();
$diag = control_contact_center_diagnostic();
$log = [];
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['rcc_migrate_csrf'] ?? ''), $token)) {
        $error = 'Invalid request. Refresh and try again.';
    } elseif (!$installed) {
        $error = 'ratib-contact-center/ folder is missing on the server.';
    } else {
        try {
            $log = control_contact_center_run_migrations();
            $success = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('RCC migration failed: ' . $e->getMessage());
        }
    }
}

$dbTest = $installed ? control_contact_center_db_test() : ['ok' => false, 'schema' => false, 'db' => control_contact_center_db_name(), 'user' => control_contact_center_db_user(), 'error' => '', 'tables' => 0];
$schemaVerify = $installed ? control_contact_center_verify_schema() : ['ok' => false, 'missing' => []];
$schemaReady = $dbTest['ok'] && $dbTest['schema'] && ($schemaVerify['ok'] ?? false);

if (empty($_SESSION['rcc_migrate_csrf'])) {
    $_SESSION['rcc_migrate_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['rcc_migrate_csrf'];

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Contact Center — Database Setup', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-database me-2"></i>Contact Center database setup</strong>
    — Creates all <code>rcc_*</code> tables in
    <code><?php echo htmlspecialchars(control_contact_center_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>.
    MySQL user: <code><?php echo htmlspecialchars(control_contact_center_db_user(), ENT_QUOTES, 'UTF-8'); ?></code>
    (cPanel → Add User To Database → ALL PRIVILEGES).
</p>

<div class="rateb-erp-status mb-4">
    <div class="rateb-erp-status-item<?php echo $installed ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $installed ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Module: <?php echo $installed ? 'Found' : 'Missing'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $dbTest['ok'] ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $dbTest['ok'] ? 'fa-link' : 'fa-unlink'; ?>"></i>
        <span>Connection: <?php echo $dbTest['ok'] ? 'OK' : 'Failed'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $schemaReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $schemaReady ? 'fa-table' : 'fa-database'; ?>"></i>
        <span>Tables: <?php echo $schemaReady ? (int) $dbTest['tables'] . ' rcc_*' : 'Not created yet'; ?></span>
    </div>
</div>

<?php if ($error !== '') { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php if ($success) { ?>
<div class="alert alert-success">Migrations finished successfully.</div>
<?php } ?>
<?php if (!empty($log)) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-terminal"></i> Log</h3>
    <pre class="rateb-erp-migrate-log"><?php echo htmlspecialchars(implode("\n", $log), ENT_QUOTES, 'UTF-8'); ?></pre>
</div>
<?php } ?>

<?php if (!$installed) { ?>
<div class="alert alert-warning mb-4">
    <strong>Module files missing</strong> — upload <code>ratib-contact-center/</code> to
    <code><?php echo htmlspecialchars(dirname($diag['resolved'] ?? control_contact_center_root_path()), ENT_QUOTES, 'UTF-8'); ?>/</code>
    or push to <code>main</code> for auto-deploy.
</div>
<?php } elseif (!$dbTest['ok']) { ?>
<div class="alert alert-danger mb-4">
    <strong>Database connection failed.</strong> Fix MySQL user/database, then add to <code>.env</code>:<br>
    <pre class="rateb-erp-migrate-log mb-0 mt-2">RATIB_CC_DB_PASS=your_mysql_password</pre>
    <?php if (($dbTest['error'] ?? '') !== '') { ?>
    <span class="small text-muted d-block mt-2"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php } ?>
</div>
<?php } ?>

<div class="control-settings-grid mb-4">
    <div class="control-settings-card">
        <h3><i class="fas fa-play"></i> Run migrations</h3>
        <p>Applies <code>001_core_schema.sql</code> through <code>020_migration_reconcile.sql</code> (skips already applied).</p>
        <form method="post" action="<?php echo htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="run_migrations" value="1" class="btn btn-primary"<?php echo $installed ? '' : ' disabled'; ?>>
                <i class="fas fa-database"></i> Run database setup
            </button>
        </form>
        <?php if (!$installed) { ?>
        <p class="small text-warning mb-0 mt-2">Disabled until <code>ratib-contact-center/bootstrap.php</code> exists on server.</p>
        <?php } elseif (!$dbTest['ok']) { ?>
        <p class="small text-warning mb-0 mt-2">Fix DB connection first, then run migrations.</p>
        <?php } ?>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-headset"></i> Open Agent Desktop</h3>
        <a href="<?php echo htmlspecialchars(control_contact_center_app_url('agent-desktop'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary<?php echo ($installed && $schemaReady) ? '' : ' disabled'; ?>"<?php echo ($installed && $schemaReady) ? '' : ' aria-disabled="true" tabindex="-1"'; ?>>
            Agent Desktop
        </a>
        <a href="<?php echo htmlspecialchars(control_contact_center_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light ms-2">Hub</a>
    </div>
</div>

<?php endControlLayout(); ?>
