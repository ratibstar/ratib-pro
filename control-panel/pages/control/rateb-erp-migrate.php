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
$dbDiagnose = ($installed && $dbTest['ok']) ? control_rateb_erp_db_diagnose() : ['erp' => [], 'control_panel' => []];

if (empty($_SESSION['rateb_erp_migrate_csrf'])) {
    $_SESSION['rateb_erp_migrate_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['rateb_erp_migrate_csrf'];

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('نظام رتب ERP — إعداد قاعدة البيانات', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
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

<?php if (!empty($dbDiagnose['erp']) || !empty($dbDiagnose['control_panel'])) {
    $erpD = $dbDiagnose['erp'] ?? [];
    $cpD = $dbDiagnose['control_panel'] ?? [];
    ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-stethoscope"></i> فحص القواعد</h3>
    <div class="table-responsive">
        <table class="table table-sm table-dark mb-0">
            <thead>
            <tr>
                <th>القاعدة</th>
                <th>الحالة</th>
                <th>جداول rateb_*</th>
                <th>صلاحيات</th>
                <th>أدوار</th>
                <th>أدوار مكررة</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code><?php echo htmlspecialchars((string) ($erpD['db'] ?? control_rateb_erp_db_name()), ENT_QUOTES, 'UTF-8'); ?></code> <span class="badge bg-primary">ERP</span></td>
                <td><?php echo !empty($erpD['ok']) ? '<span class="text-success">OK</span>' : '<span class="text-danger">خطأ</span>'; ?></td>
                <td><?php echo (int) ($erpD['rateb_tables'] ?? 0); ?></td>
                <td><?php echo (int) ($erpD['permissions'] ?? 0); ?></td>
                <td><?php echo (int) ($erpD['roles'] ?? 0); ?></td>
                <td><?php echo (int) ($erpD['duplicate_role_slugs'] ?? 0); ?></td>
            </tr>
            <tr>
                <td><code><?php echo htmlspecialchars((string) ($cpD['db'] ?? 'outratib_control_panel_db'), ENT_QUOTES, 'UTF-8'); ?></code> <span class="badge bg-secondary">CP</span></td>
                <td><?php echo !empty($cpD['ok']) ? '<span class="text-success">OK</span>' : '<span class="text-warning">—</span>'; ?></td>
                <td><?php echo (int) ($cpD['rateb_tables'] ?? 0); ?></td>
                <td><?php echo (int) ($cpD['permissions'] ?? 0); ?></td>
                <td><?php echo (int) ($cpD['roles'] ?? 0); ?></td>
                <td><?php echo (int) ($cpD['duplicate_role_slugs'] ?? 0); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php if (!empty($cpD['warning'])) { ?>
    <p class="small text-warning mb-0 mt-2"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars((string) $cpD['warning'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php } elseif ((int) ($cpD['rateb_tables'] ?? 0) === 0) { ?>
    <p class="small text-muted mb-0 mt-2">قاعدة لوحة التحكم نظيفة (لا توجد جداول ERP) — هذا صحيح.</p>
    <?php } ?>
    <p class="small text-muted mb-0 mt-2">زر التشغيل أدناه يطبّق الترحيلات على <strong>قاعدة ERP فقط</strong> (<code><?php echo htmlspecialchars(control_rateb_erp_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>).</p>
</div>
<?php } ?>

<?php if ($installed && !$dbTest['ok']) {
    $dbUser = defined('DB_USER') ? (string) DB_USER : 'outratib_out';
    $dbName = control_rateb_erp_db_name();
    ?>
<div class="alert alert-danger">
    <strong><i class="fas fa-key me-1"></i> صلاحيات MySQL مطلوبة — الكود لا يستطيع إصلاحها تلقائياً</strong>
    <p class="mb-2 mt-2">المستخدم <code><?php echo htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8'); ?></code> غير مربوط بقاعدة <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code>.</p>
    <ol class="mb-2">
        <li>cPanel → <strong>MySQL® Databases</strong></li>
        <li>تأكد أن القاعدة <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code> موجودة (35 جدول rateb_*)</li>
        <li><strong>Add User To Database</strong> → User: <code><?php echo htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8'); ?></code> + DB: <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code></li>
        <li>اختر <strong>ALL PRIVILEGES</strong> → <strong>Make Changes</strong></li>
        <li>أعد تحميل صفحة تسجيل الدخول</li>
    </ol>
    <p class="small mb-1"><strong>بديل:</strong> أنشئ مستخدماً جديداً <code>outratib_erp</code> واربطه بالقاعدة فقط، ثم أضف في <code>.env</code>:</p>
    <pre class="rateb-erp-migrate-log mb-0">RATEB_ERP_DB_USER=outratib_erp
RATEB_ERP_DB_PASS=your_password
RATEB_ERP_DB_NAME=<?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></pre>
    <?php if (($dbTest['error'] ?? '') !== '') { ?>
    <p class="small text-muted mt-2 mb-0"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php } ?>
</div>
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
        <h3><i class="fas fa-play"></i> الخطوة 1 — إصلاح قاعدة ERP</h3>
        <p>يشغّل كل الترحيلات (001–008) على <code><?php echo htmlspecialchars(control_rateb_erp_db_name(), ENT_QUOTES, 'UTF-8'); ?></code> فقط، يدمج الأدوار المكررة، ويعرض تقريراً.</p>
        <form method="post" action="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="run_migrations" value="1" class="btn btn-primary"<?php echo $installed ? '' : ' disabled'; ?>>
                <i class="fas fa-database"></i> تشغيل الإصلاح والترحيلات
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
