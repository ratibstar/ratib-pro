<?php
declare(strict_types=1);
/**
 * SUPER_ADMIN: copy rateb.sa files → test.rateb.sa (Control Panel — no agency URL gate).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$isSuperAdmin = strtolower(trim((string) ($_SESSION['control_username'] ?? ''))) === 'admin';
if (!$isSuperAdmin) {
    http_response_code(403);
    die('SUPER_ADMIN required.');
}

require_once __DIR__ . '/../../../includes/rateb-test-domain-sync.php';
require_once __DIR__ . '/../../../includes/rateb-agency-super-admin-restore.php';
require_once __DIR__ . '/../../includes/control/layout-wrapper.php';

if (empty($_SESSION['sync_test_domain_csrf']) || !is_string($_SESSION['sync_test_domain_csrf'])) {
    try {
        $_SESSION['sync_test_domain_csrf'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['sync_test_domain_csrf'] = sha1((string) microtime(true));
    }
}

$paths = rateb_test_domain_sync_resolve();
$ran = false;
$result = null;
$restoreRan = false;
$restoreResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string) $_SESSION['sync_test_domain_csrf'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $action = (string) ($_POST['action'] ?? 'sync');
    if ($action === 'restore_admin' && (string) ($_POST['confirm'] ?? '') === 'RESTORE-ADMIN') {
        $restoreRan = true;
        try {
            $restoreResult = rateb_agency_restore_super_admin_for_host('test.rateb.sa', true);
            $restoreResult['ok'] = empty($restoreResult['errors'] ?? []);
        } catch (Throwable $e) {
            $restoreResult = ['ok' => false, 'error' => $e->getMessage()];
        }
    } elseif ($action === 'sync' && (string) ($_POST['confirm'] ?? '') === 'SYNC') {
        $ran = true;
        $result = rateb_test_domain_sync_run();
    }
}

$testErpAdmin = 'https://test.rateb.sa/rateb-erp/public/admin';
$testProbe = 'https://test.rateb.sa/pages/rateb-test-domain-probe';

startControlLayout(cp_t('sync_test.title'), ['css/control/system.css'], []);
?>
<div class="card gov-card mb-3">
    <div class="card-body">
        <h2 class="h5 mb-3"><i class="fas fa-clone me-2"></i><?php echo htmlspecialchars(cp_t('sync_test.title'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="text-muted mb-3"><?php echo htmlspecialchars(cp_t('sync_test.intro'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="small mb-3">
            <?php echo htmlspecialchars(cp_t('sync_test.erp_page_hint'), ENT_QUOTES, 'UTF-8'); ?>
            <a href="https://rateb.sa/rateb-erp/public/admin/agency-updates" target="_blank" rel="noopener">rateb.sa/…/admin/agency-updates</a>
        </p>
        <dl class="row mb-0 small">
            <dt class="col-sm-2"><?php echo htmlspecialchars(cp_t('sync_test.source'), ENT_QUOTES, 'UTF-8'); ?></dt>
            <dd class="col-sm-10"><code><?php echo htmlspecialchars($paths['source'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
            <dt class="col-sm-2"><?php echo htmlspecialchars(cp_t('sync_test.target'), ENT_QUOTES, 'UTF-8'); ?></dt>
            <dd class="col-sm-10"><code><?php echo htmlspecialchars($paths['target'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
        </dl>
    </div>
</div>

<?php if ($ran && is_array($result)): ?>
<div class="card gov-card mb-3 border-<?php echo !empty($result['ok']) ? 'success' : 'danger'; ?>">
    <div class="card-body">
        <p class="mb-2 fw-semibold <?php echo !empty($result['ok']) ? 'text-success' : 'text-danger'; ?>">
            <?php echo htmlspecialchars(!empty($result['ok']) ? cp_t('sync_test.success') : cp_t('sync_test.fail'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <pre class="bg-dark text-light p-3 rounded small mb-0" style="max-height:24rem;overflow:auto"><?php
            echo htmlspecialchars(implode("\n", (array) ($result['log'] ?? [])), ENT_QUOTES, 'UTF-8');
        ?></pre>
        <?php if (!empty($result['ok'])): ?>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($testErpAdmin, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(cp_t('sync_test.open_test_erp'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($testProbe, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(cp_t('sync_test.open_probe'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($restoreRan && is_array($restoreResult)): ?>
<div class="card gov-card mb-3 border-<?php echo !empty($restoreResult['ok']) ? 'success' : 'danger'; ?>">
    <div class="card-body">
        <h3 class="h6 mb-2"><i class="fas fa-user-shield me-2"></i>استعادة مدير النظام (test.rateb.sa)</h3>
        <?php if (!empty($restoreResult['ok'])): ?>
        <p class="text-success mb-2">تمت الاستعادة — سجّل الدخول بـ <code>admin@rateb.sa</code> / <code>password</code></p>
        <?php else: ?>
        <p class="text-danger mb-2"><?php echo htmlspecialchars((string) ($restoreResult['error'] ?? 'فشلت الاستعادة'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <pre class="bg-dark text-light p-3 rounded small mb-0" style="max-height:16rem;overflow:auto"><?php
            echo htmlspecialchars(json_encode($restoreResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
        ?></pre>
        <?php if (!empty($restoreResult['ok'])): ?>
        <div class="mt-3">
            <a class="btn btn-primary btn-sm" href="https://test.rateb.sa/rateb-erp/public/login" target="_blank" rel="noopener">فتح تسجيل الدخول</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$restoreRan): ?>
<div class="card gov-card mb-3 border-warning">
    <div class="card-body">
        <h3 class="h6 mb-2"><i class="fas fa-user-shield me-2"></i>استعادة مدير ERP على test.rateb.sa</h3>
        <p class="small text-muted mb-3">يُعيد إنشاء <code>admin@rateb.sa</code> بكلمة المرور <code>password</code> في قاعدة بيانات الوكالة (بدون حذف بيانات الشركة).</p>
        <form method="post" class="mb-0" onsubmit="return confirm('استعادة admin@rateb.sa بكلمة password على test.rateb.sa؟');">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['sync_test_domain_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="restore_admin">
            <input type="hidden" name="confirm" value="RESTORE-ADMIN">
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-user-plus me-1"></i>استعادة admin@rateb.sa
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$ran): ?>
<div class="card gov-card mb-3">
    <div class="card-body">
        <p class="text-warning small mb-3"><?php echo htmlspecialchars(cp_t('sync_test.warning'), ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['sync_test_domain_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="sync">
            <input type="hidden" name="confirm" value="SYNC">
            <button type="submit" class="btn btn-primary" onclick="return confirm(<?php echo json_encode(cp_t('sync_test.confirm'), JSON_UNESCAPED_UNICODE); ?>);">
                <i class="fas fa-clone me-1"></i><?php echo htmlspecialchars(cp_t('sync_test.button'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php
endControlLayout();
