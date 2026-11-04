<?php
declare(strict_types=1);

if (($argv[1] ?? '') === 'csrf') {
    $root = dirname(__DIR__, 3);
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    \Rateb\App\Core\Database::useConnectionOverride([
        'host' => '127.0.0.1',
        'port' => (int) (getenv('RATEB_EPC_PORT') ?: 3307),
        'user' => (string) getenv('RATEB_EPC_USER'),
        'pass' => (string) getenv('RATEB_EPC_PASS'),
        'db' => 'admin_rateb_epc_phase0',
    ]);
    if ((string) \Rateb\App\Core\Database::connection()->query('SELECT DATABASE()')->fetchColumn() !== 'admin_rateb_epc_phase0') {
        echo "WRONGDB\n";
        exit(2);
    }
    \Rateb\App\Core\SessionManager::start();
    $_SESSION['rateb_is_super_admin'] = 1;
    $_SESSION['rateb_user_id'] = 1;
    $_POST = [];
    unset($_POST['_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    [$class, $method] = explode('::', (string) ($argv[2] ?? ''));
    (new $class())->$method();
    echo "WROTE\n";
    exit(0);
}

if (($argv[1] ?? '') === 'module') {
    $root = dirname(__DIR__, 3);
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    \Rateb\App\Core\Database::useConnectionOverride([
        'host' => '127.0.0.1',
        'port' => (int) (getenv('RATEB_EPC_PORT') ?: 3307),
        'user' => (string) getenv('RATEB_EPC_USER'),
        'pass' => (string) getenv('RATEB_EPC_PASS'),
        'db' => 'admin_rateb_epc_phase0',
    ]);
    \Rateb\App\Core\SessionManager::start();
    unset($_SESSION['rateb_is_super_admin']);
    $_SESSION['rateb_user_id'] = 11;
    $_SESSION['rateb_company_id'] = 8;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/automotive/catalog';
    echo (new \Rateb\App\Core\Middleware\CompanyModuleMiddleware('automotive'))->handle() ? "ALLOW\n" : "DENY\n";
    exit(0);
}

$root = dirname(__DIR__, 3);
$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/automotive.php');
preg_match_all("/post\\(\\\$autoApp\\('[^']+'\\), \\[([^\\]]+)::class, '([^']+)'\\]/", $routes, $matches, PREG_SET_ORDER);
$targets = [];
foreach ($matches as $match) {
    $class = trim($match[1], "\\");
    if (!str_contains($class, '\\')) {
        $class = 'Rateb\\App\\Automotive\\Controllers\\' . $class;
    }
    $targets[] = $class . '::' . $match[2];
}
$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $label) use (&$passed, &$failed): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
};
$check($targets !== [], 'write routes were discovered');
if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0') {
    echo "NOT RUN: CSRF and module checks need the disposable database\n";
    echo "Blocker checks: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}
foreach ($targets as $target) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' csrf ' . escapeshellarg($target);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $check(!str_contains((string) $out, 'WROTE'), 'missing CSRF is rejected for ' . $target);
}
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' module';
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$out = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
$check(!str_contains((string) $out, 'ALLOW'), 'company without the automotive module is denied');
echo "Blocker checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
