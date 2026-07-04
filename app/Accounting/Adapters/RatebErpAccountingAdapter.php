<?php
declare(strict_types=1);

namespace App\Accounting\Adapters;

use App\Accounting\Contracts\AccountingAdapterInterface;
use App\Accounting\Core\AccountingResult;

/**
 * Writes to rateb_chart_of_accounts / rateb_journal_entries / rateb_journal_lines
 * via Rateb\App\Services\AccountingService (when available).
 */
final class RatebErpAccountingAdapter implements AccountingAdapterInterface
{
    public function supports(string $sourceSystem): bool
    {
        return $sourceSystem === 'rateb-erp';
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        if (!empty($event['metadata']['legacy_write'])) {
            return AccountingResult::ok([
                'mode' => 'acknowledged',
                'source_system' => 'rateb-erp',
                'journal_entry_id' => $event['metadata']['journal_entry_id'] ?? null,
                'reference_type' => $event['reference_type'],
                'reference_id' => $event['reference_id'],
            ], 'Legacy write acknowledged — no duplicate ERP post');
        }

        if (!class_exists(\Rateb\App\Services\AccountingService::class)) {
            return AccountingResult::fail('AccountingService not loaded (rateb-erp context required for gateway-first write)');
        }

        $companyId = (int) $event['company_id'];
        $amount = round((float) $event['amount'], 2);
        if ($amount <= 0) {
            return AccountingResult::fail('amount must be greater than zero for gateway-first ERP post');
        }

        /** @var \Rateb\App\Services\AccountingService $service */
        $service = new \Rateb\App\Services\AccountingService();

        $debitAccountId = $service->accountIdByCode($companyId, (string) $event['debit_account']);
        $creditAccountId = $service->accountIdByCode($companyId, (string) $event['credit_account']);

        if ($debitAccountId === null || $creditAccountId === null) {
            return AccountingResult::fail('Could not resolve debit/credit account codes to rateb_chart_of_accounts IDs', [
                'debit_account' => $event['debit_account'],
                'credit_account' => $event['credit_account'],
            ]);
        }

        $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));
        $description = (string) ($event['metadata']['description'] ?? $event['event_type'] . ' via AccountingGateway');
        $descriptionAr = (string) ($event['metadata']['description_ar'] ?? $description);
        $sourceType = (string) ($event['reference_type'] ?? $event['event_type']);
        $sourceId = is_numeric($event['reference_id']) ? (int) $event['reference_id'] : null;

        $lines = [
            ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0.0, 'memo' => $description],
            ['account_id' => $creditAccountId, 'debit' => 0.0, 'credit' => $amount, 'memo' => $description],
        ];

        $entryId = $service->createPostedEntry(
            $companyId,
            $sourceType,
            $sourceId,
            $lines,
            $description,
            $descriptionAr,
            $entryDate
        );

        if ($entryId === null || $entryId <= 0) {
            return AccountingResult::fail('AccountingService::createPostedEntry returned empty');
        }

        return AccountingResult::ok([
            'mode' => 'gateway_write',
            'source_system' => 'rateb-erp',
            'journal_entry_id' => $entryId,
            'company_id' => $companyId,
        ], 'Posted to rateb_journal_entries');
    }
}
