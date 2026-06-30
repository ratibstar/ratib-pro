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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string) $_SESSION['sync_test_domain_csrf'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    if ((string) ($_POST['confirm'] ?? '') === 'SYNC') {
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
<?php else: ?>
<div class="card gov-card mb-3">
    <div class="card-body">
        <p class="text-warning small mb-3"><?php echo htmlspecialchars(cp_t('sync_test.warning'), ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) $_SESSION['sync_test_domain_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
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
