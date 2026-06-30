<?php
declare(strict_types=1);
/**
 * One-time copy: rateb.sa public_html → test.rateb.sa public_html
 * Open (while logged into Control Panel):
 *   https://rateb.sa/pages/rateb-bootstrap-test-domain.php?control=1
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_GET['control']) || (string) $_GET['control'] !== '1') {
    http_response_code(403);
    exit('Add ?control=1 to URL (open from Control Panel session).');
}

// Platform ops only — do not load includes/config.php (avoids agency Site URL gate on rateb.sa).
define('RATEB_PLATFORM_OPS_PAGE', true);
require_once __DIR__ . '/../control-panel/includes/config.php';

$isSuper = strtolower(trim((string) ($_SESSION['control_username'] ?? ''))) === 'admin';
if (empty($_SESSION['control_logged_in']) || !$isSuper) {
    http_response_code(403);
    $login = (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/control-panel/pages/login.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>ربط test.rateb.sa</title></head><body style="font-family:system-ui;padding:2rem;max-width:40rem;margin:auto">';
    echo '<h1>يلزم تسجيل الدخول</h1>';
    echo '<p>سجّل دخول <strong>Super Admin</strong> في لوحة التحكم أولاً، ثم أعد فتح هذه الصفحة.</p>';
    echo '<p><a href="' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '">تسجيل الدخول — لوحة التحكم</a></p>';
    echo '<p><code>https://rateb.sa/pages/rateb-bootstrap-test-domain.php?control=1</code></p>';
    echo '</body></html>';
    exit;
}

$source = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
$target = getenv('RATEB_TEST_DOMAIN_DOCROOT') ?: '';
if ($target === '' && $source !== '') {
    $target = str_replace(
        [DIRECTORY_SEPARATOR . 'rateb.sa' . DIRECTORY_SEPARATOR, '/rateb.sa/'],
        [DIRECTORY_SEPARATOR . 'test.rateb.sa' . DIRECTORY_SEPARATOR, '/test.rateb.sa/'],
        $source
    );
}
$target = rtrim((string) $target, '/\\');

$paths = ['rateb-erp', 'config', 'core', 'app', 'includes', 'css', 'js', 'pages', 'api', 'control-panel', 'admin', 'public'];
$rootFiles = ['index.php', '.htaccess', 'composer.json', 'control.php'];
$criticalAfterCopy = [
    'core/TenantExecutionContext.php',
    'core/bootstrap.php',
    'app/Core/ErrorTracker.php',
    'config/env/test_rateb_sa.php',
];

function rateb_bootstrap_copy_tree(string $src, string $dst, array &$log): bool
{
    if (!is_dir($src)) {
        $log[] = 'SKIP missing: ' . $src;
        return true;
    }
    if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
        $log[] = 'FAIL mkdir: ' . $dst;
        return false;
    }
    $items = @scandir($src);
    if (!is_array($items)) {
        $log[] = 'FAIL scandir: ' . $src;
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($from)) {
            if (!rateb_bootstrap_copy_tree($from, $to, $log)) {
                return false;
            }
            continue;
        }
        if (!@copy($from, $to)) {
            $log[] = 'FAIL copy: ' . $from;
            return false;
        }
        @chmod($to, 0644);
    }
    return true;
}

$ran = false;
$log = [];
$ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && (string) $_POST['confirm'] === 'SYNC') {
    $ran = true;
    if ($source === '' || !is_dir($source)) {
        $ok = false;
        $log[] = 'Source document root not found.';
    } elseif ($target === '' || $target === $source) {
        $ok = false;
        $log[] = 'Target path could not be resolved. Set RATEB_TEST_DOMAIN_DOCROOT in .env';
    } else {
        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            $ok = false;
            $log[] = 'Cannot create target: ' . $target;
        } else {
            $log[] = 'Source: ' . $source;
            $log[] = 'Target: ' . $target;
            foreach ($paths as $rel) {
                $src = $source . DIRECTORY_SEPARATOR . $rel;
                $dst = $target . DIRECTORY_SEPARATOR . $rel;
                if (!is_dir($src)) {
                    $log[] = 'SKIP ' . $rel;
                    continue;
                }
                $log[] = 'COPY ' . $rel . ' …';
                if (!rateb_bootstrap_copy_tree($src, $dst, $log)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                foreach ($rootFiles as $file) {
                    $src = $source . DIRECTORY_SEPARATOR . $file;
                    if (!is_file($src)) {
                        continue;
                    }
                    if (!@copy($src, $target . DIRECTORY_SEPARATOR . $file)) {
                        $log[] = 'FAIL root file: ' . $file;
                        $ok = false;
                    } else {
                        $log[] = 'OK ' . $file;
                    }
                }
            }
            if ($ok && is_file($source . DIRECTORY_SEPARATOR . '.env') && !is_file($target . DIRECTORY_SEPARATOR . '.env')) {
                if (@copy($source . DIRECTORY_SEPARATOR . '.env', $target . DIRECTORY_SEPARATOR . '.env')) {
                    $log[] = 'OK .env (copied once; edit test DB settings if needed)';
                }
            }
            if ($ok) {
                foreach ($criticalAfterCopy as $rel) {
                    $p = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    if (is_file($p)) {
                        $log[] = 'VERIFY OK ' . $rel;
                    } else {
                        $log[] = 'VERIFY MISSING ' . $rel;
                        $ok = false;
                    }
                }
            }
        }
    }
}

$testLogin = 'https://test.rateb.sa/pages/login.php';
$testAgency = 'https://test.rateb.sa/?control=1&agency_id=33';
$testProbe = 'https://test.rateb.sa/pages/rateb-test-domain-probe';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>ربط test.rateb.sa</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 1.5rem; max-width: 52rem; margin: 0 auto; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.25rem; margin: 1rem 0; }
        code { background: #0f3460; padding: 0.15rem 0.4rem; border-radius: 4px; }
        pre { background: #020617; padding: 1rem; overflow: auto; border-radius: 8px; font-size: 0.85rem; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: 0.6rem 1rem; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .ok { color: #4ade80; } .bad { color: #f87171; }
        a { color: #93c5fd; }
    </style>
</head>
<body>
    <h1>نسخ ملفات rateb.sa → test.rateb.sa</h1>
    <div class="card">
        <p>بديل عن File Manager اليدوي: ينسخ مجلدات التطبيق من الدومين الرئيسي إلى <code>test.rateb.sa</code>.</p>
        <p><strong>المصدر:</strong> <code><?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <p><strong>الهدف:</strong> <code><?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?></code></p>
    </div>
<?php if ($ran): ?>
    <div class="card">
        <p class="<?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo $ok ? 'اكتمل النسخ بنجاح.' : 'فشل النسخ — راجع السجل.'; ?></p>
        <pre><?php echo htmlspecialchars(implode("\n", $log), ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php if ($ok): ?>
        <p>جرّب الآن:</p>
        <ul>
            <li><a href="<?php echo htmlspecialchars($testLogin, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($testLogin, ENT_QUOTES, 'UTF-8'); ?></a></li>
            <li><a href="<?php echo htmlspecialchars($testAgency, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($testAgency, ENT_QUOTES, 'UTF-8'); ?></a></li>
            <li><a href="<?php echo htmlspecialchars($testProbe, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($testProbe, ENT_QUOTES, 'UTF-8'); ?></a> (تشخيص)</li>
        </ul>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form method="post" class="card">
        <p>سجّل دخول <a href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/control-panel/pages/login.php', ENT_QUOTES, 'UTF-8'); ?>">لوحة التحكم (admin)</a> أولاً، ثم افتح هذه الصفحة.</p>
        <p>اضغط مرة واحدة فقط. لا تشغّلها إلا إذا <code>test.rateb.sa</code> ما زال فارغاً (Under Construction).</p>
        <input type="hidden" name="confirm" value="SYNC">
        <button type="submit" class="btn">نسخ الملفات الآن</button>
    </form>
<?php endif; ?>
</body>
</html>
