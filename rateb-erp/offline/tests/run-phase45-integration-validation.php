<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 2));

$bootstrap = RATEB_ROOT . '/app/Core/Bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
    try {
        Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Bootstrap soft-fail: ' . $e->getMessage() . PHP_EOL);
    }
}

if (!class_exists(\Rateb\App\Core\Model::class, false)) {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Rateb\\App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        foreach ([
            RATEB_ROOT . '/app/' . $relative,
            RATEB_ROOT . '/app/' . str_replace(
                ['Controllers/', 'Services/', 'Models/', 'Helpers/'],
                ['controllers/', 'services/', 'models/', 'helpers/'],
                $relative
            ),
        ] as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    });
}

require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();
require_once __DIR__ . '/Phase45IntegrationValidationTest.php';

$runner = new Phase45IntegrationValidationTest();
$out = $runner->run();
$results = $out['results'];
$findings = $out['findings'];

$failed = 0;
$criticalFail = 0;
$highFail = 0;

foreach ($results as $result) {
    $label = ($result['passed'] ? 'PASS' : 'FAIL') . ' [' . $result['severity'] . ']: ' . $result['name'];
    if (!$result['passed']) {
        $label .= ' — ' . $result['detail'];
        $failed++;
        if ($result['severity'] === 'Critical') {
            $criticalFail++;
        }
        if ($result['severity'] === 'High') {
            $highFail++;
        }
    }
    echo $label . PHP_EOL;
}

echo PHP_EOL . 'Findings:' . PHP_EOL;
foreach ($findings as $f) {
    echo '- [' . $f['severity'] . '] ' . $f['id'] . ' ' . $f['title'] . PHP_EOL;
}

$critFindings = count(array_filter($findings, static fn ($f) => $f['severity'] === 'Critical'));
$highFindings = count(array_filter($findings, static fn ($f) => $f['severity'] === 'High'));

echo PHP_EOL . (count($results) - $failed) . '/' . count($results) . ' checks passed' . PHP_EOL;
echo 'Critical findings: ' . $critFindings . ' | High findings: ' . $highFindings . PHP_EOL;

if ($critFindings > 0 || $highFindings > 0 || $criticalFail > 0 || $highFail > 0) {
    echo 'GATE: STOP — do not begin Procurement Offline' . PHP_EOL;
    exit(2);
}

echo 'GATE: CLEAR for Procurement planning (staging soak still required)' . PHP_EOL;
exit($failed > 0 ? 1 : 0);
