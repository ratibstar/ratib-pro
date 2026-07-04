<?php
declare(strict_types=1);

/**
 * Optional global hook for the unified accounting write pipeline.
 * Safe to call from legacy PHP, rateb-erp, control-panel, and Laravel modules.
 */

use App\Accounting\Core\AccountingResult;
use App\Accounting\Integrity\AccountingEventPipelineDecorator;
use App\Accounting\Pipeline\AccountingEventPipeline;
use App\Accounting\Support\AccountingConfig;
use App\Accounting\Support\AccountingGatewayBootstrap;

if (!function_exists('postAccountingEvent')) {
    /**
     * @param array<string, mixed> $event Normalized accounting event
     */
    function postAccountingEvent(array $event): ?AccountingResult
    {
        if (!AccountingGatewayBootstrap::isEnabled() && !AccountingConfig::eventStoreEnabled()) {
            return null;
        }

        try {
            AccountingGatewayBootstrap::registerAutoloader();

            if (AccountingEventPipeline::isEnabled()) {
                $integrityBootstrap = dirname(__DIR__) . '/Support/post_accounting_integrity.php';
                if (is_file($integrityBootstrap)) {
                    require_once $integrityBootstrap;
                }

                if (AccountingEventPipelineDecorator::shouldUse()) {
                    $pipeline = new AccountingEventPipelineDecorator(
                        new AccountingEventPipeline(AccountingGatewayBootstrap::gateway())
                    );
                } else {
                    $pipeline = new AccountingEventPipeline(AccountingGatewayBootstrap::gateway());
                }

                return $pipeline->post($event);
            }

            if (!AccountingGatewayBootstrap::isEnabled()) {
                return null;
            }

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
