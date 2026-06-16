<?php
/**
 * Infrastructure marketplace module — bootstrap and PSR-4-style autoload (no Composer required).
 */
declare(strict_types=1);

if (!function_exists('str_contains')) {
    $ratebInfraPhp74Compat = dirname(__DIR__, 2) . '/includes/rateb-php74-compat.php';
    if (is_file($ratebInfraPhp74Compat)) {
        require_once $ratebInfraPhp74Compat;
    }
}

if (!defined('RATEB_INFRASTRUCTURE_MARKETPLACE_ROOT')) {
    define('RATEB_INFRASTRUCTURE_MARKETPLACE_ROOT', __DIR__);
}

$ratebInfraEnvBootstrap = __DIR__ . '/Infrastructure/InfraEnvBootstrap.php';
if (is_file($ratebInfraEnvBootstrap)) {
    require_once $ratebInfraEnvBootstrap;
    \RATEB\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap::load();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'RATEB\\InfrastructureMarketplace\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $path = RATEB_INFRASTRUCTURE_MARKETPLACE_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
