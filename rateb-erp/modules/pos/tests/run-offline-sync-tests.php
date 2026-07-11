<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 3));

spl_autoload_register(static function (string $class): void {
    $map = [
        'Rateb\\App\\Pos\\' => RATEB_ROOT . '/modules/pos/app/',
        'Rateb\\App\\Services\\' => RATEB_ROOT . '/app/services/',
        'Rateb\\App\\Offline\\Models\\' => RATEB_ROOT . '/offline/server/Models/',
        'Rateb\\App\\Core\\' => RATEB_ROOT . '/app/core/',
        'Rateb\\App\\Models\\' => RATEB_ROOT . '/app/models/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $path = $base . $relative;
        if (is_file($path)) {
            require_once $path;
        }
        return;
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
if (!class_exists('Rateb\\App\\Core\\SessionManager', false)) {
    eval('namespace Rateb\\App\\Core; class SessionManager {
        private static array $data = [];
        public static function get(string $key, mixed $default = null): mixed { return self::$data[$key] ?? $default; }
        public static function set(string $key, mixed $value): void { self::$data[$key] = $value; }
        public static function forget(string $key): void { unset(self::$data[$key]); }
    }');
}
if (!class_exists('Rateb\\App\\Core\\Model', false)) {
    eval('namespace Rateb\\App\\Core; class Model {
        protected string $table = "";
        protected bool $tenantScoped = false;
        protected array $fillable = [];
    }');
}
if (!function_exists('__')) {
    function __(string $key, ...$args): string
    {
        return $key;
    }
}

require_once __DIR__ . '/PosOfflineSyncTest.php';
require_once __DIR__ . '/PosOfflinePhase2BTest.php';
require_once __DIR__ . '/PosOfflinePhase2CTest.php';

$suites = [
    new PosOfflineSyncTest(),
    new PosOfflinePhase2BTest(),
    new PosOfflinePhase2CTest(),
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
