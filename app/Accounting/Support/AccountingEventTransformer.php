<?php
declare(strict_types=1);

namespace App\Accounting\Support;

/**
 * Builds normalized accounting events from native system payloads.
 */
final class AccountingEventTransformer
{
    /**
     * @param array<string, mixed> $extraMetadata
     * @return array<string, mixed>
     */
    public static function fromRatebPostedEntry(
        int $entryId,
        ?int $companyId,
        string $sourceType,
        int|string $sourceId,
        float $amount,
        string $debitAccountCode,
        string $creditAccountCode,
        array $extraMetadata = []
    ): array {
        return [
            'source_system' => 'rateb-erp',
            'event_type' => self::mapEventType($sourceType),
            'company_id' => (int) ($companyId ?? 0),
            'branch_id' => $extraMetadata['branch_id'] ?? null,
            'amount' => round($amount, 2),
            'currency' => (string) ($extraMetadata['currency'] ?? 'SAR'),
            'debit_account' => $debitAccountCode,
            'credit_account' => $creditAccountCode,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
            'metadata' => array_merge([
                'legacy_write' => true,
                'journal_entry_id' => $entryId,
                'entry_date' => $extraMetadata['entry_date'] ?? date('Y-m-d'),
                'description' => $extraMetadata['description'] ?? null,
            ], $extraMetadata),
        ];
    }

    /**
     * @param array<string, mixed> $journalRow
     * @return array<string, mixed>
     */
    public static function fromMainSiteJournalEntry(int $entryId, array $journalRow, float $amount, string $debitAccount, string $creditAccount): array
    {
        return [
            'source_system' => 'main-site',
            'event_type' => 'journal',
            'company_id' => (int) ($journalRow['agency_id'] ?? $journalRow['company_id'] ?? 0),
            'branch_id' => isset($journalRow['branch_id']) ? (int) $journalRow['branch_id'] : null,
            'amount' => round($amount, 2),
            'currency' => (string) ($journalRow['currency'] ?? 'SAR'),
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'reference_type' => 'journal_entry',
            'reference_id' => $entryId,
            'metadata' => [
                'legacy_write' => true,
                'journal_entry_id' => $entryId,
                'entry_number' => $journalRow['entry_number'] ?? null,
                'entry_date' => $journalRow['entry_date'] ?? date('Y-m-d'),
                'description' => $journalRow['description'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromControlJournalEntry(
        int $journalEntryId,
        string $reference,
        int $countryId,
        float $amount,
        string $debitAccount,
        string $creditAccount,
        string $description = ''
    ): array {
        return [
            'source_system' => 'control-panel',
            'event_type' => 'journal',
            'company_id' => $countryId,
            'branch_id' => null,
            'amount' => round($amount, 2),
            'currency' => 'SAR',
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'reference_type' => 'control_journal_entry',
            'reference_id' => $journalEntryId,
            'metadata' => [
                'legacy_write' => true,
                'journal_entry_id' => $journalEntryId,
                'reference' => $reference,
                'country_id' => $countryId,
                'description' => $description,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromLedgerJournal(
        int $ledgerJournalId,
        int $agencyId,
        float $amount,
        string $currency,
        string $debitAccountCode,
        string $creditAccountCode,
        ?string $referenceType = null,
        int|string|null $referenceId = null
    ): array {
        return [
            'source_system' => 'ledger',
            'event_type' => 'payment',
            'company_id' => $agencyId,
            'branch_id' => null,
            'amount' => round($amount, 2),
            'currency' => strtoupper($currency),
            'debit_account' => $debitAccountCode,
            'credit_account' => $creditAccountCode,
            'reference_type' => $referenceType ?? 'ledger_journal',
            'reference_id' => $referenceId ?? $ledgerJournalId,
            'metadata' => [
                'legacy_write' => true,
                'ledger_journal_id' => $ledgerJournalId,
                'agency_id' => $agencyId,
            ],
        ];
    }

    private static function mapEventType(string $sourceType): string
    {
        return match ($sourceType) {
            'invoice', 'purchase_invoice' => 'invoice',
            'payment', 'supplier_payment', 'cash_voucher' => 'payment',
            'purchase_order', 'expense' => 'expense',
            'branch_transfer', 'stock_movement' => 'transfer',
            default => 'journal',
        };
    }
}
