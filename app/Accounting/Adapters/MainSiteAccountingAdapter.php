<?php
declare(strict_types=1);

namespace App\Accounting\Adapters;

use App\Accounting\Contracts\AccountingAdapterInterface;
use App\Accounting\Core\AccountingResult;

/**
 * Writes to financial_accounts / journal_entries / journal_entry_lines / general_ledger
 * via api/accounting/core/general-ledger-helper.php (when available).
 */
final class MainSiteAccountingAdapter implements AccountingAdapterInterface
{
    public function supports(string $sourceSystem): bool
    {
        return $sourceSystem === 'main-site';
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        if (!empty($event['metadata']['legacy_write'])) {
            return AccountingResult::ok([
                'mode' => 'acknowledged',
                'source_system' => 'main-site',
                'journal_entry_id' => $event['metadata']['journal_entry_id'] ?? null,
                'reference_type' => $event['reference_type'],
                'reference_id' => $event['reference_id'],
            ], 'Legacy write acknowledged — no duplicate main-site post');
        }

        $conn = $event['metadata']['mysqli'] ?? null;
        if (!$conn instanceof \mysqli) {
            return AccountingResult::fail('metadata.mysqli connection required for gateway-first main-site write');
        }

        if (!function_exists('postJournalEntryToLedger')) {
            $helper = $this->resolveGeneralLedgerHelperPath();
            if ($helper === null || !is_file($helper)) {
                return AccountingResult::fail('general-ledger-helper.php not found');
            }
            require_once $helper;
        }

        $journalEntryId = isset($event['metadata']['journal_entry_id'])
            ? (int) $event['metadata']['journal_entry_id']
            : 0;

        if ($journalEntryId <= 0) {
            return AccountingResult::fail('metadata.journal_entry_id required for main-site GL post (create journal via legacy API first)');
        }

        try {
            $glResult = postJournalEntryToLedger($conn, $journalEntryId);
        } catch (\Throwable $e) {
            return AccountingResult::fail('postJournalEntryToLedger failed: ' . $e->getMessage());
        }

        return AccountingResult::ok([
            'mode' => 'gateway_write',
            'source_system' => 'main-site',
            'journal_entry_id' => $journalEntryId,
            'general_ledger' => $glResult,
        ], 'Posted to general_ledger');
    }

    private function resolveGeneralLedgerHelperPath(): ?string
    {
        $candidates = [
            dirname(__DIR__, 3) . '/api/accounting/core/general-ledger-helper.php',
        ];
        if (defined('RATEB_ROOT')) {
            $candidates[] = dirname((string) RATEB_ROOT) . '/api/accounting/core/general-ledger-helper.php';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
