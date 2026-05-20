<?php
/**
 * Infrastructure marketplace module — bootstrap and PSR-4-style autoload (no Composer required).
 */
declare(strict_types=1);

if (!function_exists('str_contains')) {
    $ratibInfraPhp74Compat = dirname(__DIR__, 2) . '/includes/ratib-php74-compat.php';
    if (is_file($ratibInfraPhp74Compat)) {
        require_once $ratibInfraPhp74Compat;
    }
}

if (!defined('RATIB_INFRASTRUCTURE_MARKETPLACE_ROOT')) {
    define('RATIB_INFRASTRUCTURE_MARKETPLACE_ROOT', __DIR__);
}

$ratibInfraEnvBootstrap = __DIR__ . '/Infrastructure/InfraEnvBootstrap.php';
if (is_file($ratibInfraEnvBootstrap)) {
    require_once $ratibInfraEnvBootstrap;
    \Ratib\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap::load();
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
