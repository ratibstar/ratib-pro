<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 3));

spl_autoload_register(static function (string $class): void {
    $map = [
        'Rateb\\App\\Pos\\' => RATEB_ROOT . '/modules/pos/app/',
        'Rateb\\App\\Core\\' => RATEB_ROOT . '/app/Core/',
        'Rateb\\App\\Services\\' => RATEB_ROOT . '/app/Services/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $candidates = [$base . $relative];
        if (str_contains($base, '/Core/')) {
            $candidates[] = str_replace('/Core/', '/core/', $base) . $relative;
        }
        if (str_contains($base, '/Services/')) {
            $candidates[] = str_replace('/Services/', '/services/', $base) . $relative;
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
        return;
    }
});

if (!function_exists('__')) {
    function __(string $key): string
    {
        return $key;
    }
}

if (!class_exists('Rateb\\App\\Core\\TenantContext', false)) {
    eval('namespace Rateb\\App\\Core; class TenantContext {
        private static ?int $companyId = null;
        public static function setCompanyId(?int $id): void { self::$companyId = $id; }
        public static function companyId(): ?int { return self::$companyId; }
        public static function isSuperAdmin(): bool { return false; }
        public static function apiUserId(): ?int { return null; }
    }');
}
if (!class_exists('Rateb\\App\\Core\\Database', false)) {
    eval('namespace Rateb\\App\\Core; class Database {
        public static function liveTableHasColumn(string $table, string $column): bool {
            return $column === "commit_token" || $column === "server_sync_id";
        }
        public static function tableExists(string $table): bool { return false; }
        public static function connection() { return new class {
            public function prepare($sql) { throw new \\RuntimeException("db_unavailable_in_unit_test"); }
            public function query($sql) { throw new \\RuntimeException("db_unavailable_in_unit_test"); }
        }; }
    }');
}
if (!class_exists('Rateb\\App\\Services\\AuditService', false)) {
    eval('namespace Rateb\\App\\Services; class AuditService {
        public function log(...$args): void {}
    }');
}

require_once RATEB_ROOT . '/modules/pos/app/Models/PosModels.php';
require_once __DIR__ . '/PosSyncCommitTest.php';

$test = new PosSyncCommitTest();
$results = $test->run();
$failed = 0;
foreach ($results as $row) {
    $mark = $row['passed'] ? 'PASS' : 'FAIL';
    echo $mark . ' ' . $row['name'] . ' — ' . $row['detail'] . PHP_EOL;
    if (!$row['passed']) {
        $failed++;
    }
}
exit($failed > 0 ? 1 : 0);
