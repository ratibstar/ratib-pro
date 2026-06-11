<?php
/**
 * RATEB ERP — run database migrations from Control Panel (no SSH).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$erpRoot = control_rateb_erp_root_path();
$installed = control_rateb_erp_is_installed();
$log = [];
$error = '';
$success = false;
$schemaReady = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
  $token = (string) ($_POST['_csrf'] ?? '');
  if (!hash_equals((string) ($_SESSION['rateb_erp_migrate_csrf'] ?? ''), $token)) {
      $error = 'Invalid request. Refresh the page and try again.';
  } elseif (!$installed) {
      $error = 'rateb-erp folder is missing on the server. Upload it first.';
  } else {
      try {
          $log = control_rateb_erp_run_migrations();
          $success = true;
      } catch (Throwable $e) {
          $error = $e->getMessage();
          error_log('RATEB ERP migration failed: ' . $e->getMessage());
      }
  }
}

$dbTest = $installed ? control_rateb_erp_db_test() : ['ok' => false, 'schema' => false, 'db' => control_rateb_erp_db_name(), 'error' => ''];
$schemaReady = $dbTest['ok'] && $dbTest['schema'];

if (empty($_SESSION['rateb_erp_migrate_csrf'])) {
    $_SESSION['rateb_erp_migrate_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['rateb_erp_migrate_csrf'];

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('RATEB ERP — Database Setup', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-database me-2"></i>Database setup</strong>
    — Creates all <code>rateb_*</code> tables in the dedicated ERP database
    <code><?php echo htmlspecialchars(control_rateb_erp_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>
    (not the control panel DB). In cPanel → MySQL®, add user <code><?php echo htmlspecialchars(defined('DB_USER') ? (string) DB_USER : 'outratib_out', ENT_QUOTES, 'UTF-8'); ?></code>
    to database <code><?php echo htmlspecialchars(control_rateb_erp_db_name(), ENT_QUOTES, 'UTF-8'); ?></code> with ALL PRIVILEGES.
</p>

<div class="rateb-erp-status mb-4">
    <div class="rateb-erp-status-item<?php echo $installed ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $installed ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>ERP files: <?php echo $installed ? 'Found' : 'Missing — upload rateb-erp/ folder'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $dbTest['ok'] ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $dbTest['ok'] ? 'fa-link' : 'fa-unlink'; ?>"></i>
        <span>DB connection: <?php echo $dbTest['ok'] ? 'OK (' . htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8') . ')' : 'Failed — grant user on ' . htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $schemaReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $schemaReady ? 'fa-table' : 'fa-database'; ?>"></i>
        <span>Tables: <?php echo $schemaReady ? 'rateb_* tables exist' : 'Not created yet — run migrations below'; ?></span>
    </div>
</div>

<?php if ($installed && !$dbTest['ok'] && ($dbTest['error'] ?? '') !== '') { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

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

<div class="control-settings-grid mb-4">
    <div class="control-settings-card">
        <h3><i class="fas fa-play"></i> Step 1 — Run migrations</h3>
        <p>Creates all <code>rateb_*</code> tables and default super admin (<code>admin@rateb.sa</code> / <code>password</code>).</p>
        <form method="post" action="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="run_migrations" value="1" class="btn btn-primary"<?php echo $installed ? '' : ' disabled'; ?>>
                <i class="fas fa-database"></i> Run migrations now
            </button>
        </form>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-right-to-bracket"></i> Step 2 — Open ERP</h3>
        <p>After migrations, open Super Admin login (still inside Control Panel).</p>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_app_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary<?php echo $schemaReady ? '' : ' disabled'; ?>">
            <i class="fas fa-hospital"></i> Super Admin login
        </a>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light ms-2">
            <i class="fas fa-th-large"></i> ERP hub
        </a>
    </div>
</div>

<?php endControlLayout(); ?>
