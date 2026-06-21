<?php
declare(strict_types=1);

/**
 * PSR-4 autoloader for Ratib\ContactCenter\App\
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

Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator::boot();
