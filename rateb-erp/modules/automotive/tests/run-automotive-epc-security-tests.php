<?php
declare(strict_types=1);

/**
 * Automotive EPC security checks that can run without production.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-security-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Csrf;
use Rateb\App\Core\Middleware\EntityPermissionMiddleware;
use Rateb\App\Core\SessionManager;

$passed = 0;
$failed = 0;

function epc_sec(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if ($ok) {
        $passed++;
        return;
    }
    $failed++;
}

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/automotive.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/AutomotiveReferenceController.php');
$service = (string) file_get_contents(dirname(__DIR__) . '/app/Services/AutomotiveReferenceService.php');
$sql = (string) file_get_contents($root . '/migrations/291_auto_ref_foundation.sql');

epc_sec(substr_count($routes, "rateb_erp_mw('automotive'") >= 1, 'reference routes keep the automotive middleware gate');
epc_sec(str_contains($routes, 'storeMarket') && str_contains($controller, 'guardManage'), 'write endpoints require manage permission');
epc_sec(str_contains($controller, 'validateCsrf'), 'write endpoints validate CSRF');
epc_sec(!str_contains($service, 'company_id') && !preg_match('/\bcompany_id\b/', $sql), 'global reference code has no tenant column');

$statements = [];
$buffer = '';
foreach (preg_split('/\R/', $sql) ?: [] as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $buffer .= $line . "\n";
    if (preg_match('/;\s*$/', $trimmed)) {
        $statements[] = trim($buffer);
        $buffer = '';
    }
}
$triggers = array_values(array_filter($statements, static fn (string $s): bool => str_starts_with($s, 'CREATE TRIGGER')));
epc_sec(count($triggers) === 5, 'migration runner keeps all five triggers intact');

SessionManager::start();
unset($_SESSION['rateb_is_super_admin'], $_SESSION['rateb_user_id'], $_SESSION['rateb_company_id']);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/automotive/reference/markets';
epc_sec(Csrf::validate('') === false, 'empty CSRF token is rejected');
epc_sec(Csrf::validate('not-the-session-token') === false, 'mismatched CSRF token is rejected');
$token = Csrf::token();
epc_sec(Csrf::validate($token) === true, 'matching CSRF token is accepted');

$_SESSION['rateb_is_super_admin'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';
epc_sec((new EntityPermissionMiddleware('automotive'))->handle() === true, 'super admin may view the automotive entity');
$_SERVER['REQUEST_METHOD'] = 'POST';
epc_sec((new EntityPermissionMiddleware('automotive'))->handle() === true, 'super admin may mutate the automotive entity');

$deny = dirname(__DIR__) . '/tests/epc-deny-child.php';
file_put_contents($deny, <<<'PHP'
<?php
declare(strict_types=1);
$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);
require_once dirname(__DIR__) . '/AutomotiveModule.php';
\Rateb\App\Automotive\AutomotiveModule::init();
use Rateb\App\Core\Middleware\EntityPermissionMiddleware;
use Rateb\App\Core\SessionManager;
SessionManager::start();
unset($_SESSION['rateb_is_super_admin'], $_SESSION['rateb_user_id'], $_SESSION['rateb_company_id']);
$_SERVER['REQUEST_METHOD'] = getenv('EPC_METHOD') ?: 'GET';
$_SERVER['REQUEST_URI'] = '/admin/automotive/reference';
echo (new EntityPermissionMiddleware('automotive'))->handle() ? "ALLOW\n" : "DENY\n";
PHP);
foreach (['GET' => 'unauthenticated view is denied', 'POST' => 'unauthenticated write is denied'] as $method => $label) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($deny);
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $spec, $pipes, null, ['EPC_METHOD' => $method]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    epc_sec(!str_contains((string) $out, 'ALLOW'), $label);
}
unlink($deny);

echo "Security checks: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
