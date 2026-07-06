<?php

declare(strict_types=1);

require_once __DIR__ . '/PosV2BenchmarkRunner.php';

$report = (new PosV2BenchmarkRunner())->run();

$reportsDir = __DIR__ . '/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0775, true);
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$latestPath = $reportsDir . '/benchmark-latest.json';
file_put_contents($latestPath, $json . PHP_EOL);

echo 'POS V2 benchmark report written to ' . $latestPath . PHP_EOL . PHP_EOL;
echo $json . PHP_EOL;

exit(0);
