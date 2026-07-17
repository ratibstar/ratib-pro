<?php
declare(strict_types=1);

require __DIR__ . '/Px6SecurityHardeningTest.php';

$results = (new Px6SecurityHardeningTest())->run();
$passed = 0;
foreach ($results as $result) {
    $ok = $result['passed'];
    $passed += $ok ? 1 : 0;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $result['name'];
    if (!$ok && $result['detail'] !== '') {
        echo ' — ' . $result['detail'];
    }
    echo PHP_EOL;
}

echo PHP_EOL . $passed . '/' . count($results) . ' passed' . PHP_EOL;
exit($passed === count($results) ? 0 : 1);
