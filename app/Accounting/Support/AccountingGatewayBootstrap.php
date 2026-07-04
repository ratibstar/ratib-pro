<?php
declare(strict_types=1);

namespace App\Accounting\Support;

use App\Accounting\Adapters\ControlPanelAccountingAdapter;
use App\Accounting\Adapters\LedgerAccountingAdapter;
use App\Accounting\Adapters\MainSiteAccountingAdapter;
use App\Accounting\Adapters\RatebErpAccountingAdapter;
use App\Accounting\Core\AccountingGateway;

final class AccountingGatewayBootstrap
{
    private static ?AccountingGateway $gateway = null;

    public static function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $appRoot = dirname(__DIR__, 2);
        $autoloader = $appRoot . '/Core/Autoloader.php';
        if (is_file($autoloader)) {
            require_once $autoloader;
            \App\Core\Autoloader::register($appRoot);
        }
    }

    public static function gateway(): AccountingGateway
    {
        if (self::$gateway instanceof AccountingGateway) {
            return self::$gateway;
        }

        self::registerAutoloader();

        self::$gateway = new AccountingGateway([
            new RatebErpAccountingAdapter(),
            new MainSiteAccountingAdapter(),
            new ControlPanelAccountingAdapter(),
            new LedgerAccountingAdapter(),
        ]);

        return self::$gateway;
    }

    public static function isEnabled(): bool
    {
        if (defined('ACCOUNTING_GATEWAY_ENABLED')) {
            return (bool) ACCOUNTING_GATEWAY_ENABLED;
        }

        $env = getenv('ACCOUNTING_GATEWAY_ENABLED');
        if ($env !== false && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        $acctPath = dirname(__DIR__, 3) . '/config/accounting.php';
        if (is_file($acctPath)) {
            $cfg = require $acctPath;
            if (array_key_exists('gateway_enabled', $cfg)) {
                return (bool) $cfg['gateway_enabled'];
            }
        }

        $configPath = dirname(__DIR__, 3) . '/config/accounting-gateway.php';
        if (is_file($configPath)) {
            $config = require $configPath;

            return !empty($config['enabled']);
        }

        return false;
    }
}
