<?php
declare(strict_types=1);

/**
 * Phase 5 integrity helpers — opt-in from legacy write paths without modifying gateway/adapters.
 */

use App\Accounting\Integrity\AccountingEventPipelineDecorator;
use App\Accounting\Integrity\AccountingIntegrityHook;
use App\Accounting\Integrity\AccountingLedgerLockManager;
use App\Accounting\Integrity\AccountingLedgerLockedException;
use App\Accounting\Integrity\AccountingLockVerdict;
use App\Accounting\Support\AccountingConfig;
use App\Accounting\Support\AccountingGatewayBootstrap;

if (!function_exists('accounting_assert_ledger_mutable')) {
    /**
     * Advisory lock check — returns verdict array; never throws.
     *
     * @return array<string, mixed>
     */
    function accounting_assert_ledger_mutable(
        int $companyId,
        string $entryDate,
        ?int $branchId = null,
        string $operation = 'create'
    ): array {
        if (!AccountingConfig::integrityEnabled()) {
            return ['status' => 'allowed', 'message' => 'Integrity layer disabled', 'allowed' => true];
        }

        try {
            AccountingGatewayBootstrap::registerAutoloader();
            $verdict = (new AccountingLedgerLockManager())->assertMutable($companyId, $entryDate, $branchId, $operation);

            return $verdict->toArray();
        } catch (\Throwable $e) {
            error_log('accounting_assert_ledger_mutable: ' . $e->getMessage());

            return ['status' => 'allowed', 'message' => 'Lock check failed — allowed by fallback', 'allowed' => true];
        }
    }
}

if (!function_exists('accounting_can_mutate_ledger')) {
    function accounting_can_mutate_ledger(
        int $companyId,
        string $entryDate,
        ?int $branchId = null,
        string $operation = 'create'
    ): bool {
        $verdict = accounting_assert_ledger_mutable($companyId, $entryDate, $branchId, $operation);

        return !empty($verdict['allowed']);
    }
}

if (!function_exists('accounting_enforce_ledger_mutable')) {
    /**
     * Hard-stop ledger writes when ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED is on.
     *
     * @throws AccountingLedgerLockedException
     */
    function accounting_enforce_ledger_mutable(
        int $companyId,
        string $entryDate,
        ?int $branchId = null,
        string $operation = 'create'
    ): void {
        if (!AccountingConfig::ledgerLockEnforcementEnabled() && !AccountingConfig::integrityEnabled()) {
            return;
        }

        try {
            AccountingGatewayBootstrap::registerAutoloader();
            $verdict = (new AccountingLedgerLockManager())->assertMutable($companyId, $entryDate, $branchId, $operation);
        } catch (\Throwable $e) {
            error_log('accounting_enforce_ledger_mutable: ' . $e->getMessage());

            return;
        }

        if (
            AccountingConfig::ledgerLockEnforcementEnabled()
            && $verdict->status === AccountingLockVerdict::HARD_REJECT
        ) {
            throw new AccountingLedgerLockedException(
                $verdict->message,
                $verdict->status,
                $verdict->periodFrom,
                $verdict->periodTo
            );
        }
    }
}

if (!function_exists('runAccountingIntegrityFollowUp')) {
    /**
     * Manual trigger when not using AccountingEventPipelineDecorator.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $resultData
     */
    function runAccountingIntegrityFollowUp(array $event, string $eventUuid, array $resultData = []): void
    {
        if (!AccountingConfig::integrityEnabled()) {
            return;
        }

        try {
            AccountingGatewayBootstrap::registerAutoloader();
            (new AccountingIntegrityHook())->afterProjectionCompleted($event, $eventUuid, $resultData);
        } catch (\Throwable $e) {
            error_log('runAccountingIntegrityFollowUp: ' . $e->getMessage());
        }
    }
}

if (!function_exists('accounting_integrity_bootstrap')) {
    function accounting_integrity_bootstrap(): void
    {
        AccountingGatewayBootstrap::registerAutoloader();
    }
}

if (!function_exists('accounting_pipeline_with_integrity')) {
    /**
     * Factory for decorated pipeline when integrity enforcement follow-up is enabled.
     */
    function accounting_pipeline_with_integrity(): AccountingEventPipelineDecorator
    {
        accounting_integrity_bootstrap();

        return new AccountingEventPipelineDecorator(
            new \App\Accounting\Pipeline\AccountingEventPipeline(AccountingGatewayBootstrap::gateway())
        );
    }
}
