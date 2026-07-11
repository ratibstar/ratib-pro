<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\FiscalPeriod;
use Rateb\App\Models\JournalEntry;

/**
 * Tenant + branch isolation for Accounting offline replay (Phase 16B).
 * Additive — does not alter Accounting domain services.
 */
final class AccountingOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, journal?: array<string, mixed>}
     */
    public function assertJournal(int $entryId, array $scope): array
    {
        if ($entryId < 1) {
            return ['ok' => false, 'error' => 'invalid_journal_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $sql = 'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid';
        if (OfflineSchema::hasColumn('rateb_journal_entries', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = (new JournalEntry())->queryOne($sql, ['id' => $entryId, 'cid' => $companyId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'journal_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'journal' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, account?: array<string, mixed>}
     */
    public function assertAccount(int $accountId, array $scope): array
    {
        if ($accountId < 1) {
            return ['ok' => false, 'error' => 'invalid_account_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $sql = 'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id = :cid';
        if (OfflineSchema::hasColumn('rateb_chart_of_accounts', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = (new ChartOfAccount())->queryOne($sql, ['id' => $accountId, 'cid' => $companyId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'account_not_found'];
        }

        return ['ok' => true, 'account' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, period?: array<string, mixed>}
     */
    public function assertPeriod(int $periodId, array $scope): array
    {
        if ($periodId < 1) {
            return ['ok' => false, 'error' => 'invalid_period_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new FiscalPeriod())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'period_not_found'];
        }

        return ['ok' => true, 'period' => $row];
    }

    public function journalExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_journal_entries', 'description')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $sql = 'SELECT id FROM rateb_journal_entries
                WHERE company_id = :cid AND description LIKE :marker';
        if (OfflineSchema::hasColumn('rateb_journal_entries', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $row = (new JournalEntry())->queryOne($sql, ['cid' => $companyId, 'marker' => $marker]);

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    public function isPeriodClosedForDate(int $companyId, string $entryDate): bool
    {
        if ($companyId < 1 || $entryDate === '') {
            return false;
        }
        try {
            $db = Database::connection();
            $sql = 'SELECT id FROM rateb_fiscal_periods
                    WHERE company_id = :cid
                      AND start_date <= :d AND end_date >= :d2
                      AND status = \'closed\'';
            if (OfflineSchema::hasColumn('rateb_fiscal_periods', 'locked')) {
                $sql = 'SELECT id FROM rateb_fiscal_periods
                        WHERE company_id = :cid
                          AND start_date <= :d AND end_date >= :d2
                          AND (status = \'closed\' OR locked = 1)';
            }
            $sql .= ' LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute(['cid' => $companyId, 'd' => $entryDate, 'd2' => $entryDate]);

            return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
