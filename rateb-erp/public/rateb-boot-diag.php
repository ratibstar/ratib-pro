<?php
declare(strict_types=1);
/**
 * Temporary boot diagnostic — remove after cloud 500 is resolved.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$out = [];
$out[] = 'sapi=' . PHP_SAPI;
$out[] = 'php=' . PHP_VERSION;
$out[] = 'host=' . ($_SERVER['HTTP_HOST'] ?? '');
$out[] = 'RATEB_RUNTIME_env=' . (getenv('RATEB_RUNTIME') ?: '(empty)');

try {
    define('RATEB_ENV_NO_SESSION', true);
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::initMinimal($root);
    $out[] = 'initMinimal=ok';
    $out[] = 'RATEB_RUNTIME_after=' . (getenv('RATEB_RUNTIME') ?: '(empty)');
    if (class_exists(\Rateb\App\Core\HybridRuntime::class)) {
        \Rateb\App\Core\HybridRuntime::reset();
        $out[] = 'mode=' . \Rateb\App\Core\HybridRuntime::mode();
        $out[] = 'driver=' . \Rateb\App\Core\HybridRuntime::driver();
    }
    if (function_exists('rateb_bootstrap_css')) {
        $out[] = 'bootstrap_css=' . rateb_bootstrap_css();
    }
    try {
        $pdo = \Rateb\App\Core\Database::connection();
        $out[] = 'db=ok driver=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $dbEx) {
        $out[] = 'db=FAIL ' . $dbEx->getMessage();
    }
} catch (Throwable $e) {
    $out[] = 'FAIL ' . $e->getMessage();
    $out[] = 'at ' . $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $out) . "\n";
