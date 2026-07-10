<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 3));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Rateb\\App\\Pos\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $path = RATEB_ROOT . '/modules/pos/app/' . $relative;
    if (is_file($path)) {
        require_once $path;
    }
});

// Minimal stubs for services referenced by constructors but unused in unit paths.
if (!class_exists('Rateb\\App\\Core\\TenantContext', false)) {
    eval('namespace Rateb\\App\\Core; class TenantContext {
        private static ?int $companyId = null;
        public static function setCompanyId(?int $id): void { self::$companyId = $id; }
        public static function companyId(): ?int { return self::$companyId; }
    }');
}
if (!class_exists('Rateb\\App\\Core\\Database', false)) {
    eval('namespace Rateb\\App\\Core; class Database {
        public static function liveTableHasColumn(string $table, string $column): bool { return false; }
        public static function connection() { throw new \\RuntimeException("db_unavailable_in_unit_test"); }
    }');
}

require_once __DIR__ . '/PosOfflineSyncTest.php';
require_once __DIR__ . '/PosOfflinePhase2BTest.php';

$suites = [
    new PosOfflineSyncTest(),
    new PosOfflinePhase2BTest(),
];

$failed = 0;
$total = 0;
foreach ($suites as $runner) {
    $results = $runner->run();
    foreach ($results as $result) {
        $total++;
        $label = ($result['passed'] ? 'PASS' : 'FAIL') . ': ' . $result['name'];
        if (!$result['passed']) {
            $label .= ' — ' . $result['detail'];
            $failed++;
        }
        echo $label . PHP_EOL;
    }
}

echo PHP_EOL . ($total - $failed) . '/' . $total . ' passed' . PHP_EOL;

exit($failed > 0 ? 1 : 0);
