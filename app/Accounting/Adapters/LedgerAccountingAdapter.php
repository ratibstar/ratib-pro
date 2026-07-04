<?php
declare(strict_types=1);

namespace App\Accounting\Adapters;

use App\Accounting\Contracts\AccountingAdapterInterface;
use App\Accounting\Core\AccountingResult;
use App\Accounting\Support\AccountingReplayGuard;
use App\Modules\Ledger\Services\LedgerService;

/**
 * Writes to ledger_accounts / ledger_journals / ledger_entries via LedgerService.
 */
final class LedgerAccountingAdapter implements AccountingAdapterInterface
{
    public function supports(string $sourceSystem): bool
    {
        return $sourceSystem === 'ledger';
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        if (!empty($event['metadata']['legacy_write'])) {
            return AccountingResult::ok([
                'mode' => 'acknowledged',
                'source_system' => 'ledger',
                'ledger_journal_id' => $event['metadata']['ledger_journal_id'] ?? null,
                'reference_type' => $event['reference_type'],
                'reference_id' => $event['reference_id'],
            ], 'Legacy write acknowledged — no duplicate ledger post');
        }

        if (!class_exists(LedgerService::class)) {
            return AccountingResult::fail('LedgerService not available (Laravel bootstrap required for gateway-first ledger write)');
        }

        $agencyId = (int) ($event['metadata']['agency_id'] ?? $event['company_id']);
        $debitAccountId = (int) ($event['metadata']['debit_account_id'] ?? 0);
        $creditAccountId = (int) ($event['metadata']['credit_account_id'] ?? 0);
        $amount = round((float) $event['amount'], 2);
        $currency = strtoupper((string) $event['currency']);
        $description = (string) ($event['metadata']['description'] ?? null);

        if ($debitAccountId <= 0 || $creditAccountId <= 0) {
            return AccountingResult::fail('metadata.debit_account_id and credit_account_id required for gateway-first ledger write');
        }

        /** @var LedgerService $ledger */
        $ledger = app(LedgerService::class);

        $referenceType = (string) $event['reference_type'];
        $referenceId = is_numeric($event['reference_id']) ? (int) $event['reference_id'] : 0;

        $replayGate = AccountingReplayGuard::gateBeforeJournalWrite(
            $event,
            'ledger',
            static function (array $ev) use ($ledger, $referenceType, $referenceId): ?int {
                if ($referenceType === '' || $referenceId <= 0) {
                    return null;
                }
                if (!$ledger->journalExistsForReference($referenceType, $referenceId)) {
                    return null;
                }

                return 1;
            }
        );
        if ($replayGate !== null) {
            return $replayGate;
        }

        if ($referenceType !== '' && $referenceId > 0) {
            $journal = $ledger->recordEntryWithReference(
                $agencyId,
                $debitAccountId,
                $creditAccountId,
                $amount,
                $currency,
                $referenceType,
                $referenceId,
                $description
            );
        } else {
            $journal = $ledger->recordEntry(
                $agencyId,
                $debitAccountId,
                $creditAccountId,
                $amount,
                $currency,
                $description
            );
        }

        return AccountingResult::ok([
            'mode' => 'gateway_write',
            'source_system' => 'ledger',
            'ledger_journal_id' => $journal->id,
            'agency_id' => $agencyId,
        ], 'Posted to ledger_journals');
    }
}
