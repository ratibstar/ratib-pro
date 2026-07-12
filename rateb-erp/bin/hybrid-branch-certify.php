<?php
declare(strict_types=1);

/** Phase D — Branch certification CLI. */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchCertification;
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

$out = (new BranchCertification())->certify();
foreach ($out['items'] as $it) {
    echo (($it['ok'] ? 'PASS' : 'FAIL') . ' | ' . $it['id'] . ' | ' . $it['detail']) . PHP_EOL;
}
echo PHP_EOL . 'Passed: ' . $out['passed'] . '  Failed: ' . $out['failed'] . PHP_EOL;
echo 'VERDICT: ' . ($out['ok'] ? 'CERTIFIED' : 'NOT_CERTIFIED') . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
