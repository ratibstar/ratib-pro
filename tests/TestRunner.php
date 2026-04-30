<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/bootstrap.php';

$testFiles = [
    ...glob(__DIR__ . '/Unit/*Test.php') ?: [],
    ...glob(__DIR__ . '/Workflow/*Test.php') ?: [],
    ...glob(__DIR__ . '/Integration/*Test.php') ?: [],
];
sort($testFiles);

$results = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
$failures = [];

foreach ($testFiles as $file) {
    $tests = require $file;
    if (!is_array($tests)) {
        continue;
    }
    foreach ($tests as $name => $fn) {
        try {
            $outcome = $fn();
            if ($outcome === 'skip') {
                $results['skipped']++;
                echo "[SKIP] {$name}" . PHP_EOL;
                continue;
            }
            $results['passed']++;
            echo "[PASS] {$name}" . PHP_EOL;
        } catch (TestSkippedException $e) {
            $results['skipped']++;
            echo "[SKIP] {$name}" . PHP_EOL;
        } catch (Throwable $e) {
            $results['failed']++;
            $failures[] = $name . ' => ' . $e->getMessage();
            echo "[FAIL] {$name}" . PHP_EOL;
        }
    }
}

$total = $results['passed'] + $results['failed'] + $results['skipped'];
echo PHP_EOL . "Total: {$total}, Passed: {$results['passed']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}" . PHP_EOL;
if ($failures !== []) {
    echo "Failures:" . PHP_EOL;
    foreach ($failures as $f) {
        echo ' - ' . $f . PHP_EOL;
    }
}
exit($results['failed'] > 0 ? 1 : 0);
