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
$erpDiag = control_rateb_erp_diagnostic();
$erpInstalled = $erpDiag['installed'];
$dbTest = $erpInstalled ? control_rateb_erp_db_test() : ['ok' => false, 'schema' => false, 'db' => control_rateb_erp_db_name(), 'error' => ''];
$schemaReady = $dbTest['ok'] && $dbTest['schema'];

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('نظام رتب ERP', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-hospital me-2"></i>نظام رتب ERP</strong>
    — Medical Procurement &amp; Healthcare ERP. Everything opens here on <strong>rateb.sa</strong> inside your Control Panel (not rateb.sa).
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

<?php if (!$erpInstalled) { ?>
<div class="alert alert-warning mb-4">
    <strong><i class="fas fa-cloud-upload-alt me-1"></i> الملفات غير موجودة على السيرفر</strong><br>
    ارفع مجلد <code>rateb-erp/</code> إلى <code>public_html/rateb-erp/</code> بجانب <code>control-panel/</code> عبر cPanel File Manager،
    أو اعمل <strong>git push</strong> إلى فرع <code>main</code> وانتظر اكتمال النشر التلقائي.
    <div class="mt-2 small text-muted">المسار المتوقع: <code><?php echo htmlspecialchars($erpDiag['resolved'] . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?></code></div>
</div>
<?php } elseif ($erpInstalled && !$dbTest['ok']) { ?>
<div class="alert alert-danger mb-4">
    <strong><i class="fas fa-database me-1"></i> صلاحيات قاعدة البيانات</strong><br>
    المستخدم <code><?php echo htmlspecialchars(defined('DB_USER') ? (string) DB_USER : 'admin_out', ENT_QUOTES, 'UTF-8'); ?></code>
    لا يستطيع الاتصال بقاعدة <code><?php echo htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></code>.<br>
    في cPanel → MySQL® Databases → Add User To Database → ALL PRIVILEGES.<br>
    <span class="small text-muted"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<?php } elseif (!$schemaReady) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-database"></i> First time setup</h3>
    <p>Creates tables in <code><?php echo htmlspecialchars(control_rateb_erp_db_name(), ENT_QUOTES, 'UTF-8'); ?></code> — no SSH required.</p>
    <a href="<?php echo htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-play"></i> Run database setup
    </a>
</div>
<?php } ?>

<div class="alert alert-info mb-4" role="note">
    <strong><i class="fas fa-route me-1"></i> الرابط الثابت للنظام</strong>
    <p class="small mb-2">استخدم دائماً <code>/rateb-erp/public/…</code> — لا تستخدم <code>/admin</code> أو <code>/login</code> في جذر الموقع (تطبيقات أخرى).</p>
    <code><?php echo htmlspecialchars(control_rateb_erp_public_url('admin'), ENT_QUOTES, 'UTF-8'); ?></code>
</div>

<?php if (strtolower(trim((string) ($_SESSION['control_username'] ?? ''))) === 'admin' && function_exists('control_panel_page_with_control')) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-cloud-upload-alt"></i> رفع تحديثات ERP للوكالات</h3>
    <p class="small text-muted mb-2">بعد نشر كود جديد على rateb.sa، طبّق ترحيلات قاعدة البيانات على ERP الرئيسي و/أو قواعد الوكالات المشتركة.</p>
    <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/erp-agency-updates.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-play"></i> Push ERP updates
    </a>
</div>
<?php } ?>

<div class="alert alert-primary mb-4" role="region" aria-label="روابط مباشرة">
    <h2 class="h5 mb-2"><i class="fas fa-link me-2"></i>روابط مباشرة — بدون تسجيل لوحة التحكم</h2>
    <p class="small mb-2">
        <strong>إدارة الفروع</strong> من لوحة التحكم فقط. لكل فرع رابط دخول خاص — لا تستخدم <code>company/login</code> للفروع الفرعية.
    </p>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="<?php echo htmlspecialchars(control_rateb_erp_branches_hub_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary<?php echo $schemaReady ? '' : ' disabled'; ?>">
            <i class="fas fa-store"></i> الشركات والفروع
        </a>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('portals.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-info<?php echo $schemaReady ? '' : ' disabled'; ?>" target="_blank" rel="noopener">
            <i class="fas fa-list"></i> كل الروابط
        </a>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary<?php echo $schemaReady ? '' : ' disabled'; ?>" target="_blank" rel="noopener">
            <i class="fas fa-user-shield"></i> دخول الإدارة
        </a>
    </div>
</div>

<div class="control-settings-intro mb-2"><strong>Super Admin modules</strong> <span class="text-muted small">(مراقبة — قراءة فقط للمشتريات والمخزون)</span></div>
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
        <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary<?php echo $schemaReady ? '' : ' disabled'; ?>" target="_blank" rel="noopener">Admin login</a>
    </div>
    <div class="control-settings-card">
        <h3><i class="fas fa-building"></i> Company portal</h3>
        <p>Procurement &amp; inventory per company.</p>
        <a href="<?php echo htmlspecialchars(control_rateb_erp_public_url('company/login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary<?php echo $schemaReady ? '' : ' disabled'; ?>" target="_blank" rel="noopener">Company login</a>
    </div>
</div>

<?php endControlLayout(); ?>
