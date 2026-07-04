<?php
declare(strict_types=1);

namespace App\Accounting\Admin;

use App\Accounting\Support\AccountingGatewayBootstrap;

/**
 * Phase 6 UI layer bootstrap — does not modify core accounting engines.
 */
final class AccountingControlBootstrap
{
    public static function init(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $appRoot = dirname(__DIR__, 2);
        $envLoad = dirname(__DIR__, 3) . '/config/env/load.php';
        if (is_file($envLoad)) {
            require_once $envLoad;
        }

        $autoloader = $appRoot . '/Core/Autoloader.php';
        if (is_file($autoloader)) {
            require_once $autoloader;
            \App\Core\Autoloader::register($appRoot);
        }

        AccountingGatewayBootstrap::registerAutoloader();
    }
}
