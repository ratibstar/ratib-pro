<?php
declare(strict_types=1);

/** Phase D — Health monitor CLI (--once | --max-cycles=N). */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchHealthMonitor;
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

$mon = new BranchHealthMonitor();
if (in_array('--once', $argv, true)) {
    $snap = $mon->snapshot();
    echo json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(($snap['score'] ?? 0) >= 60 ? 0 : 1);
}
$max = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--max-cycles=')) {
        $max = (int) substr($a, 13);
    }
}
exit($mon->run(['max_cycles' => $max > 0 ? $max : 1, 'interval_sec' => 1]));
