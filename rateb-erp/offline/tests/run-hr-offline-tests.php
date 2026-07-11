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
        $candidates = [
            RATEB_ROOT . '/app/' . $relative,
            RATEB_ROOT . '/app/' . str_replace(
                ['Controllers/', 'Services/', 'Models/', 'Helpers/'],
                ['controllers/', 'services/', 'models/', 'helpers/'],
                $relative
            ),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    });
}

require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

require_once __DIR__ . '/HrOfflinePhase4Test.php';

$runner = new HrOfflinePhase4Test();
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
