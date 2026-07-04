<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Services/LedgerService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Services/LedgerService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Models\LedgerJournal;
use App\Modules\Ledger\Repositories\LedgerRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LedgerService
{
    public function __construct(
        private readonly LedgerRepository $repository
    ) {
    }

    /**
     * Record a double-entry transaction. Creates exactly 1 debit and 1 credit entry.
     *
     * @param int    $agencyId      Agency ID
     * @param int    $debitAccountId  Ledger account for debit
     * @param int    $creditAccountId Ledger account for credit
     * @param float  $amount        Amount (must be positive)
     * @param string $currencyCode  ISO 4217 currency code
     * @param string|null $description Optional description
     * @return LedgerJournal The created journal with entries
     *
     * @throws InvalidArgumentException On validation failure
     */
    public function recordEntry(
        int $agencyId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $currencyCode,
        ?string $description = null
    ): LedgerJournal {
        $this->validateRecordEntry($agencyId, $debitAccountId, $creditAccountId, $amount, $currencyCode, null, null);
        $this->enforceLedgerMutable($agencyId, date('Y-m-d'));

        $debitAccount = $this->repository->findAccountById($debitAccountId);
        $creditAccount = $this->repository->findAccountById($creditAccountId);

        if ($debitAccount === null || $creditAccount === null) {
            throw new InvalidArgumentException('One or both ledger accounts do not exist.');
        }

        if ($debitAccount->agency_id !== $agencyId || $creditAccount->agency_id !== $agencyId) {
            throw new InvalidArgumentException('Ledger accounts must belong to the same agency.');
        }

        if ($debitAccountId === $creditAccountId) {
            throw new InvalidArgumentException('Debit and credit accounts must be different.');
        }

        return DB::transaction(function () use (
            $agencyId,
            $debitAccountId,
            $creditAccountId,
            $amount,
            $currencyCode,
            $description
        ): LedgerJournal {
            $journal = $this->repository->createJournal([
                'agency_id' => $agencyId,
                'description' => $description,
                'reference_type' => null,
                'reference_id' => null,
                'currency_code' => $currencyCode,
                'posted_at' => now(),
            ]);

            $this->repository->createEntry([
                'ledger_journal_id' => $journal->id,
                'ledger_account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'currency_code' => $currencyCode,
                'description' => $description,
            ]);

            $this->repository->createEntry([
                'ledger_journal_id' => $journal->id,
                'ledger_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'currency_code' => $currencyCode,
                'description' => $description,
            ]);

            $this->assertJournalBalances($journal);

            $loaded = $journal->load('entries');
            $this->emitAccountingGatewayEvent($loaded, $agencyId, $debitAccountId, $creditAccountId, $amount, $currencyCode, $description);

            return $loaded;
        });
    }

    /**
     * Record with reference for idempotency. Prevents duplicate entries for same source.
     */
    public function recordEntryWithReference(
        int $agencyId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $currencyCode,
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): LedgerJournal {
        $this->validateRecordEntry($agencyId, $debitAccountId, $creditAccountId, $amount, $currencyCode, $referenceType, $referenceId);
        $this->enforceLedgerMutable($agencyId, date('Y-m-d'));

        $debitAccount = $this->repository->findAccountById($debitAccountId);
        $creditAccount = $this->repository->findAccountById($creditAccountId);

        if ($debitAccount === null || $creditAccount === null) {
            throw new InvalidArgumentException('One or both ledger accounts do not exist.');
        }

        if ($debitAccount->agency_id !== $agencyId || $creditAccount->agency_id !== $agencyId) {
            throw new InvalidArgumentException('Ledger accounts must belong to the same agency.');
        }

        if ($debitAccountId === $creditAccountId) {
            throw new InvalidArgumentException('Debit and credit accounts must be different.');
        }

        return DB::transaction(function () use (
            $agencyId,
            $debitAccountId,
            $creditAccountId,
            $amount,
            $currencyCode,
            $description,
            $referenceType,
            $referenceId
        ): LedgerJournal {
            if ($this->repository->journalExistsForReference($referenceType, $referenceId)) {
                throw new InvalidArgumentException('A ledger entry already exists for this reference. Duplicate entries are not allowed.');
            }

            $journal = $this->repository->createJournal([
                'agency_id' => $agencyId,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'currency_code' => $currencyCode,
                'posted_at' => now(),
            ]);

            $this->repository->createEntry([
                'ledger_journal_id' => $journal->id,
                'ledger_account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'currency_code' => $currencyCode,
                'description' => $description,
            ]);

            $this->repository->createEntry([
                'ledger_journal_id' => $journal->id,
                'ledger_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'currency_code' => $currencyCode,
                'description' => $description,
            ]);

            $this->assertJournalBalances($journal);

            $loaded = $journal->load('entries');
            $this->emitAccountingGatewayEvent(
                $loaded,
                $agencyId,
                $debitAccountId,
                $creditAccountId,
                $amount,
                $currencyCode,
                $description,
                $referenceType,
                $referenceId
            );

            return $loaded;
        });
    }

    public function findAccountByCode(int $agencyId, string $code): ?LedgerAccount
    {
        return $this->repository->findAccountByCode($agencyId, $code);
    }

    public function journalExistsForReference(string $referenceType, int $referenceId): bool
    {
        return $this->repository->journalExistsForReference($referenceType, $referenceId);
    }

    public function getAccountBalance(int $ledgerAccountId, ?string $asOfDate = null): float
    {
        return $this->repository->getAccountBalance($ledgerAccountId, $asOfDate);
    }

    private function validateRecordEntry(
        int $agencyId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $currencyCode,
        ?string $referenceType,
        ?int $referenceId
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException('Currency code must be 3 characters (ISO 4217).');
        }

        $supported = array_keys(config('currencies.supported', []));
        if (! empty($supported) && ! in_array($currencyCode, $supported, true)) {
            throw new InvalidArgumentException("Currency code '{$currencyCode}' is not supported.");
        }

        if (($referenceType === null) !== ($referenceId === null)) {
            throw new InvalidArgumentException('Reference type and reference ID must both be set or both be null.');
        }
    }

    private function assertJournalBalances(LedgerJournal $journal): void
    {
        $entries = $this->repository->getJournalEntries($journal);
        $totalDebit = $entries->sum(fn (LedgerEntry $e) => (float) $e->debit);
        $totalCredit = $entries->sum(fn (LedgerEntry $e) => (float) $e->credit);

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new \RuntimeException(
                sprintf('Journal %d does not balance: debit=%.2f, credit=%.2f', $journal->id, $totalDebit, $totalCredit)
            );
        }
    }

    private function enforceLedgerMutable(int $agencyId, string $entryDate): void
    {
        $integrity = dirname(__DIR__, 3) . '/Accounting/Support/post_accounting_integrity.php';
        if (!is_file($integrity)) {
            return;
        }
        require_once $integrity;
        if (!function_exists('accounting_enforce_ledger_mutable')) {
            return;
        }

        accounting_enforce_ledger_mutable($agencyId, $entryDate, null, 'create');
    }

    private function emitAccountingGatewayEvent(
        LedgerJournal $journal,
        int $agencyId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $currencyCode,
        ?string $description = null,
        ?string $referenceType = null,
        int|string|null $referenceId = null
    ): void {
        $bootstrap = dirname(__DIR__, 3) . '/Accounting/Support/post_accounting_event.php';
        if (!is_file($bootstrap)) {
            return;
        }
        require_once $bootstrap;
        if (!function_exists('postAccountingEvent')) {
            return;
        }

        postAccountingEvent([
            'source_system' => 'ledger',
            'event_type' => 'payment',
            'company_id' => $agencyId,
            'branch_id' => null,
            'amount' => round($amount, 2),
            'currency' => strtoupper($currencyCode),
            'debit_account' => 'id:' . $debitAccountId,
            'credit_account' => 'id:' . $creditAccountId,
            'reference_type' => $referenceType ?? 'ledger_journal',
            'reference_id' => $referenceId ?? (int) $journal->id,
            'metadata' => [
                'legacy_write' => true,
                'ledger_journal_id' => (int) $journal->id,
                'agency_id' => $agencyId,
                'debit_account_id' => $debitAccountId,
                'credit_account_id' => $creditAccountId,
                'description' => $description,
            ],
        ]);
    }
}
