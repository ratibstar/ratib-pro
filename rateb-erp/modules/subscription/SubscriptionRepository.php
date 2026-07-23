<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Database;

/**
 * Persistence boundary for Subscription Engine state.
 *
 * Phase 2: read-only SELECT against rateb_subscription_engine.
 * Writes (save) remain unimplemented.
 *
 * MUST NOT touch billing tables or other ERP modules.
 */
final class SubscriptionRepository implements SubscriptionEngineStore
{
    /**
     * Load engine row for a tenant company, or null if none / table unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyId(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, subscription_start, subscription_end, grace_period_days,
                        current_status, suspended_at, renewed_at,
                        next_notification_date, last_notification_date,
                        created_at, updated_at
                 FROM rateb_subscription_engine
                 WHERE company_id = :company_id
                 LIMIT 1'
            );
            $stmt->execute(['company_id' => $companyId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            // Table may not exist yet on lagging deploys — never break the request.
            error_log('RATEB SubscriptionRepository::findByCompanyId: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Persist engine state for a tenant.
     *
     * @param array<string, mixed> $state
     * @throws \LogicException Writes are out of scope until a later phase
     */
    public function save(int $companyId, array $state): void
    {
        throw new \LogicException(
            'SubscriptionRepository::save() is not implemented (Phase 2 is read-only).'
        );
    }

    /**
     * Current status string for a tenant, or null if no engine row.
     */
    public function getCurrentStatus(int $companyId): ?string
    {
        $row = $this->findByCompanyId($companyId);
        if ($row === null) {
            return null;
        }
        $status = strtoupper(trim((string) ($row['current_status'] ?? '')));
        return $status !== '' ? $status : null;
    }

    /**
     * Batch-load subscription engine rows only (no HR/users/permissions/finance).
     * Cursor pagination by primary key for safe multi-run processing.
     *
     * @return list<array<string, mixed>>
     */
    public function listEngineRowsAfterId(int $afterId, int $limit): array
    {
        $afterId = max(0, $afterId);
        $limit = max(1, min(500, $limit));

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, subscription_start, subscription_end, grace_period_days,
                        current_status, suspended_at, renewed_at,
                        next_notification_date, last_notification_date,
                        created_at, updated_at
                 FROM rateb_subscription_engine
                 WHERE id > :after_id
                 ORDER BY id ASC
                 LIMIT ' . $limit
            );
            $stmt->execute(['after_id' => $afterId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionRepository::listEngineRowsAfterId: ' . $e->getMessage());
            return [];
        }
    }
}
