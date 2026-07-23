<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Database;

/**
 * Persistence for renewal history, lifecycle audit, and engine reactivation writes.
 * Does not touch billing / payments / invoices tables.
 */
class RenewalRepository implements RenewalStore
{
    /**
     * Apply renewal state onto rateb_subscription_engine and clear suspension/grace.
     *
     * @return bool true when a row was updated
     */
    public function reactivateEngineRow(
        int $companyId,
        string $newExpiryYmd,
        string $todayYmd
    ): bool {
        if ($companyId < 1 || !$this->isValidDate($newExpiryYmd)) {
            return false;
        }

        try {
            $pdo = Database::connection();
            $sql = 'UPDATE rateb_subscription_engine
                    SET subscription_end = :new_end,
                        current_status = :status,
                        suspended_at = NULL,
                        renewed_at = NOW(),
                        grace_started_at = NULL,
                        grace_end_at = NULL,
                        updated_at = NOW()
                    WHERE company_id = :company_id';
            try {
                $stmt = $pdo->prepare($sql);
            } catch (\Throwable $e) {
                // Pre-grace-column schema fallback.
                $stmt = $pdo->prepare(
                    'UPDATE rateb_subscription_engine
                     SET subscription_end = :new_end,
                         current_status = :status,
                         suspended_at = NULL,
                         renewed_at = NOW(),
                         updated_at = NOW()
                     WHERE company_id = :company_id'
                );
            }
            $stmt->execute([
                'new_end' => $newExpiryYmd,
                'status' => SubscriptionStatus::ACTIVE,
                'company_id' => $companyId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('RATEB RenewalRepository::reactivateEngineRow: ' . $e->getMessage());
            return false;
        }
    }

    public function insertHistory(
        int $companyId,
        ?string $previousExpiry,
        string $newExpiry,
        string $period,
        int $actorId,
        ?string $reference
    ): int {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_subscription_renewal_history
                    (company_id, previous_expiry_date, new_expiry_date, period, actor_id, reference, created_at)
                 VALUES
                    (:company_id, :prev, :new_exp, :period, :actor_id, :reference, NOW())'
            );
            $stmt->execute([
                'company_id' => $companyId,
                'prev' => $previousExpiry,
                'new_exp' => $newExpiry,
                'period' => substr($period, 0, 64),
                'actor_id' => $actorId > 0 ? $actorId : null,
                'reference' => $reference !== null ? substr($reference, 0, 190) : null,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('RATEB RenewalRepository::insertHistory: ' . $e->getMessage());
            return 0;
        }
    }

    public function insertLifecycleAudit(
        int $companyId,
        string $action,
        string $oldStatus,
        string $newStatus,
        int $actorId
    ): int {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_subscription_lifecycle_audit
                    (company_id, action, old_status, new_status, actor_id, created_at)
                 VALUES
                    (:company_id, :action, :old_status, :new_status, :actor_id, NOW())'
            );
            $stmt->execute([
                'company_id' => $companyId,
                'action' => substr($action, 0, 64),
                'old_status' => substr($oldStatus, 0, 64),
                'new_status' => substr($newStatus, 0, 64),
                'actor_id' => $actorId > 0 ? $actorId : null,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('RATEB RenewalRepository::insertLifecycleAudit: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listHistoryByCompanyId(int $companyId, int $limit = 20): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, previous_expiry_date, new_expiry_date, period, actor_id, reference, created_at
                 FROM rateb_subscription_renewal_history
                 WHERE company_id = :company_id
                 ORDER BY id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['company_id' => $companyId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB RenewalRepository::listHistoryByCompanyId: ' . $e->getMessage());
            return [];
        }
    }

    private function isValidDate(string $ymd): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1;
    }
}
