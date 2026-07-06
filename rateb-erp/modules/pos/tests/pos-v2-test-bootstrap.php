<?php

declare(strict_types=1);

/**
 * Full RATEB app bootstrap for POS V2 integration / E2E / benchmark runners.
 */

if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', dirname(__DIR__, 3));
}

// CI: pin ERP DB credentials before Bootstrap loads config/env/default.php (empty DB_PASS).
foreach (['RATEB_ERP_DB_HOST', 'RATEB_ERP_DB_USER', 'RATEB_ERP_DB_PASS', 'RATEB_ERP_DB_NAME'] as $const) {
    if (!defined($const)) {
        $fromEnv = getenv($const);
        if ($fromEnv !== false && $fromEnv !== '') {
            define($const, (string) $fromEnv);
        }
    }
}

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';

Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

if (is_file(RATEB_ROOT . '/modules/pos/PosModule.php')) {
    require_once RATEB_ROOT . '/modules/pos/PosModule.php';
    \Rateb\App\Pos\PosModule::init();
}

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
