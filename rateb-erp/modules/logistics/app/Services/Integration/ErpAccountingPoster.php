<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\AccountingPoster;
use Rateb\App\Services\AccountingService;

final class ErpAccountingPoster implements AccountingPoster
{
    public function __construct(private AccountingService $accounting = new AccountingService())
    {
    }

    public function ensureDefaultAccounts(int $companyId): void
    {
        $this->accounting->ensureDefaultAccounts($companyId);
    }

    public function accountIdByCode(int $companyId, string $code): ?int
    {
        return $this->accounting->accountIdByCode($companyId, $code);
    }

    public function journalExistsForSource(string $sourceType, int $sourceId): bool
    {
        return $this->accounting->journalExistsForSource($sourceType, $sourceId);
    }

    public function createPostedEntry(
        int $companyId,
        string $sourceType,
        int $sourceId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate
    ): ?int {
        return $this->accounting->createPostedEntry(
            $companyId,
            $sourceType,
            $sourceId,
            $lines,
            $description,
            $descriptionAr,
            $entryDate
        );
    }
}
