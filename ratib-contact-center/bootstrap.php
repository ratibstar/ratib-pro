<?php
declare(strict_types=1);

if (!defined('RCC_ROOT')) {
    define('RCC_ROOT', __DIR__);
}

require_once __DIR__ . '/config/env.php';

/**
 * PSR-4 autoloader for Rateb\ContactCenter\App\
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ratib\\ContactCenter\\App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
    Rateb\ContactCenter\App\Application\Services\RealtimeOrchestrator::boot();
}
