<?php

declare(strict_types=1);

/**
 * Master runner for all POS V2 test suites.
 *
 * Usage: php modules/pos/tests/run-all-pos-v2-tests.php
 */

$runners = [
    'cart' => 'run-cart-tests.php',
    'catalog' => 'run-catalog-tests.php',
    'checkout' => 'run-checkout-tests.php',
    'customer' => 'run-customer-tests.php',
    'discount' => 'run-discount-tests.php',
    'payment' => 'run-payment-tests.php',
    'pricing-consistency' => 'run-pricing-consistency-tests.php',
    'blocking-fixes' => 'run-blocking-fixes-tests.php',
    'security' => 'run-security-tests.php',
    'integration' => 'run-integration-tests.php',
    'e2e' => 'run-e2e-tests.php',
    'benchmarks' => 'run-benchmarks.php',
];

$php = PHP_BINARY;
$baseDir = __DIR__;
$failedSuites = [];
$passedSuites = [];

foreach ($runners as $name => $script) {
    $path = $baseDir . '/' . $script;
    if (!is_file($path)) {
        echo "SKIP: {$name} — missing {$script}" . PHP_EOL;
        $failedSuites[] = $name . ' (missing runner)';
        continue;
    }

    echo str_repeat('=', 60) . PHP_EOL;
    echo "SUITE: {$name}" . PHP_EOL;
    echo str_repeat('=', 60) . PHP_EOL;

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path);
    passthru($cmd, $exitCode);
    echo PHP_EOL;

    if ($exitCode !== 0) {
        $failedSuites[] = $name;
    } else {
        $passedSuites[] = $name;
    }
}

echo str_repeat('=', 60) . PHP_EOL;
echo 'POS V2 MASTER RUNNER SUMMARY' . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
echo 'Passed: ' . count($passedSuites) . PHP_EOL;
echo 'Failed: ' . count($failedSuites) . PHP_EOL;

if ($passedSuites !== []) {
    echo '  OK  — ' . implode(', ', $passedSuites) . PHP_EOL;
}
if ($failedSuites !== []) {
    echo ' FAIL — ' . implode(', ', $failedSuites) . PHP_EOL;
}

exit($failedSuites === [] ? 0 : 1);
