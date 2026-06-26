<?php
declare(strict_types=1);

/**
 * RATEB ERP Phase 6 — automated enterprise validation (CLI).
 *
 * Usage: php bin/enterprise-test/run.php [--json]
 */
define('RATEB_ROOT', dirname(__DIR__, 2));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/EnterpriseTestRunner.php';

$json = in_array('--json', $argv ?? [], true);
$runner = new EnterpriseTestRunner();
$report = $runner->runAll();

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "RATIB ERP Enterprise Test Suite\n";
    echo str_repeat('=', 40) . "\n";
    foreach ($report['suites'] as $suite => $data) {
        echo strtoupper($suite) . ": {$data['passed']}/{$data['total']} passed\n";
        foreach ($data['tests'] as $t) {
            $mark = ($t['passed'] ?? false) ? 'PASS' : 'FAIL';
            echo "  [{$mark}] {$t['name']}";
            if (!($t['passed'] ?? false) && !empty($t['reason'])) {
                echo ' — ' . $t['reason'];
            }
            echo "\n";
        }
    }
    echo str_repeat('-', 40) . "\n";
    echo "TOTAL: {$report['passed']}/{$report['total']} passed\n";
}

exit(($report['failed'] ?? 0) > 0 ? 1 : 0);
