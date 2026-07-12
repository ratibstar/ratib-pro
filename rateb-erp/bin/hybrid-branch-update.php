<?php
declare(strict_types=1);

/** Phase D — Update / rollback CLI. */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchUpdater;
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

$up = new BranchUpdater();
if (in_array('--rollback', $argv, true)) {
    $out = $up->rollback();
    echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(!empty($out['ok']) ? 0 : 1);
}
$target = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--to=')) {
        $target = substr($a, 5);
    }
}
if ($target === '') {
    echo json_encode(['ok' => true, 'version' => $up->currentVersion()], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
$out = $up->safeUpdate($target);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
