<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Invalid catalog root\n");
    exit(1);
}

// Prevent stale opcode cache from causing "missing method" errors
// after a deploy uploads updated migration PHP files.
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($root);

$command = $argv[1] ?? 'up';
$service = new \Rateb\PlatformCatalog\Application\Services\MigrationService();

try {
    $lines = $command === 'rollback'
        ? $service->rollbackLast()
        : $service->runAll();

    foreach ($lines as $line) {
        echo $line . PHP_EOL;
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
