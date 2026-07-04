<?php
declare(strict_types=1);

/**
 * Optional global hook for the unified accounting write pipeline.
 * Safe to call from legacy PHP, rateb-erp, control-panel, and Laravel modules.
 */

use App\Accounting\Core\AccountingResult;
use App\Accounting\Support\AccountingGatewayBootstrap;

if (!function_exists('postAccountingEvent')) {
    /**
     * @param array<string, mixed> $event Normalized accounting event
     */
    function postAccountingEvent(array $event): ?AccountingResult
    {
        if (!AccountingGatewayBootstrap::isEnabled()) {
            return null;
        }

        try {
            return AccountingGatewayBootstrap::gateway()->post($event);
        } catch (\Throwable $e) {
            error_log('postAccountingEvent failed: ' . $e->getMessage());

            return AccountingResult::fail($e->getMessage());
        }
    }
}

if (!function_exists('accounting_gateway_bootstrap')) {
    function accounting_gateway_bootstrap(): void
    {
        AccountingGatewayBootstrap::registerAutoloader();
    }
}
