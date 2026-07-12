<?php
declare(strict_types=1);

/** Phase D — Backup / restore CLI. */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchBackupService;
use Rateb\App\Core\HybridRuntime;

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
    HybridRuntime::reset();
}

$svc = new BranchBackupService();
$args = array_slice($argv, 1);
if (($args[0] ?? '') === 'restore') {
    $path = $args[1] ?? '';
    $out = $svc->restore($path);
    echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(!empty($out['ok']) ? 0 : 1);
}
if (($args[0] ?? '') === 'list') {
    echo json_encode($svc->listBackups(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}
$label = 'manual';
foreach ($args as $a) {
    if (str_starts_with($a, '--label=')) {
        $label = substr($a, 8);
    }
}
$out = $svc->backup($label);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
