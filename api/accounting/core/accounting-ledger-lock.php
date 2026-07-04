<?php
declare(strict_types=1);

/**
 * Bootstrap ledger lock enforcement for main-site accounting APIs.
 */

if (!function_exists('accounting_main_site_enforce_ledger_mutable')) {
    function accounting_main_site_enforce_ledger_mutable(
        int $companyId,
        string $entryDate,
        ?int $branchId = null,
        string $operation = 'create'
    ): void {
        $integrity = dirname(__DIR__, 3) . '/app/Accounting/Support/post_accounting_integrity.php';
        if (!is_file($integrity)) {
            return;
        }
        require_once $integrity;
        if (!function_exists('accounting_enforce_ledger_mutable')) {
            return;
        }

        accounting_enforce_ledger_mutable($companyId, $entryDate, $branchId, $operation);
    }
}

if (!function_exists('accounting_main_site_ledger_lock_response')) {
    /**
     * @return array{success:false,message:string,code:int}
     */
    function accounting_main_site_ledger_lock_response(\Throwable $e): array
    {
        return [
            'success' => false,
            'message' => 'Ledger period is locked: ' . $e->getMessage(),
            'code' => 423,
        ];
    }
}
