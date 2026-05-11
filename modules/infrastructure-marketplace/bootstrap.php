<?php
/**
 * Infrastructure marketplace module — bootstrap and PSR-4-style autoload (no Composer required).
 */
declare(strict_types=1);

if (!defined('RATIB_INFRASTRUCTURE_MARKETPLACE_ROOT')) {
    define('RATIB_INFRASTRUCTURE_MARKETPLACE_ROOT', __DIR__);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Ratib\\InfrastructureMarketplace\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $path = RATIB_INFRASTRUCTURE_MARKETPLACE_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
