<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

/** Port over AccountingService posting helpers — no Core edits. */
interface AccountingPoster
{
    public function ensureDefaultAccounts(int $companyId): void;

    public function accountIdByCode(int $companyId, string $code): ?int;

    public function journalExistsForSource(string $sourceType, int $sourceId): bool;

    /**
     * @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines
     */
    public function createPostedEntry(
        int $companyId,
        string $sourceType,
        int $sourceId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate
    ): ?int;
}
