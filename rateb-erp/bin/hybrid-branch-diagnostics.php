<?php
declare(strict_types=1);

/** Phase D — Appliance diagnostics CLI. */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchDiagnostics;

// Load serve.env if present
$serve = $root . '/storage/branch/serve.env';
if (is_readable($serve)) {
    foreach (file($serve, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($k) . '=' . trim($v));
        $_ENV[trim($k)] = trim($v);
    }
    \Rateb\App\Core\HybridRuntime::reset();
}

$out = (new BranchDiagnostics())->run();
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
