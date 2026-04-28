<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Repositories/LedgerRepository.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Repositories/LedgerRepository.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Repositories;

use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Models\LedgerJournal;
use Illuminate\Support\Collection;

final class LedgerRepository
{
    public function findAccountById(int $id): ?LedgerAccount
    {
        return LedgerAccount::find($id);
    }

    public function findAccountByCode(int $agencyId, string $code): ?LedgerAccount
    {
        return LedgerAccount::where('agency_id', $agencyId)
            ->where('code', $code)
            ->first();
    }

    public function getAccountsByAgency(int $agencyId, ?string $type = null): Collection
    {
        $query = LedgerAccount::where('agency_id', $agencyId)
            ->orderBy('code');

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    public function journalExistsForReference(?string $referenceType, ?int $referenceId): bool
    {
        if ($referenceType === null || $referenceId === null) {
            return false;
        }

        return LedgerJournal::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    public function createJournal(array $data): LedgerJournal
    {
        return LedgerJournal::create($data);
    }

    public function createEntry(array $data): LedgerEntry
    {
        return LedgerEntry::create($data);
    }

    public function getJournalEntries(LedgerJournal $journal): Collection
    {
        return $journal->entries()->orderBy('id')->get();
    }

    public function getEntriesByAccount(int $ledgerAccountId, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = LedgerEntry::where('ledger_account_id', $ledgerAccountId)
            ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
            ->select('ledger_entries.*')
            ->orderBy('ledger_journals.posted_at')
            ->orderBy('ledger_entries.id');

        if ($fromDate !== null) {
            $query->where('ledger_journals.posted_at', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->where('ledger_journals.posted_at', '<=', $toDate);
        }

        return $query->get();
    }

    public function getAccountBalance(int $ledgerAccountId, ?string $asOfDate = null): float
    {
        $query = LedgerEntry::where('ledger_account_id', $ledgerAccountId)
            ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id');

        if ($asOfDate !== null) {
            $query->where('ledger_journals.posted_at', '<=', $asOfDate);
        }

        $totals = $query->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as total_debit, COALESCE(SUM(ledger_entries.credit), 0) as total_credit')
            ->first();

        if ($totals === null) {
            return 0.0;
        }

        return (float) $totals->total_debit - (float) $totals->total_credit;
    }
}
