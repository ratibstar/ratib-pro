<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 2));

require_once __DIR__ . '/QueueDurabilityPhase451Test.php';

$runner = new QueueDurabilityPhase451Test();
$results = $runner->run();

$failed = 0;
foreach ($results as $result) {
    $label = ($result['passed'] ? 'PASS' : 'FAIL') . ': ' . $result['name'];
    if (!$result['passed']) {
        $label .= ' — ' . $result['detail'];
        $failed++;
    }
    echo $label . PHP_EOL;
}

echo PHP_EOL . (count($results) - $failed) . '/' . count($results) . ' passed' . PHP_EOL;
exit($failed > 0 ? 1 : 0);
