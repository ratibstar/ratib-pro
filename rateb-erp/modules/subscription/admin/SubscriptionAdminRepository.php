<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

use Rateb\App\Core\Database;
use Rateb\App\Subscription\SubscriptionStatus;

/**
 * Read/query layer for the subscription ops console.
 * Uses subscription engine tables (+ companies.name only). No HR/finance/ERP modules.
 */
final class SubscriptionAdminRepository
{
    /**
     * Dashboard aggregates from rateb_subscription_engine only.
     */
    public function dashboardCounts(string $todayYmd, int $expiringSoonDays = 14): SubscriptionAdminDashboard
    {
        $expiringSoonDays = max(1, min(90, $expiringSoonDays));
        $defaults = new SubscriptionAdminDashboard(0, 0, 0, 0, 0, 0);

        try {
            $pdo = Database::connection();
            $total = (int) $pdo->query('SELECT COUNT(*) FROM rateb_subscription_engine')->fetchColumn();

            $stmt = $pdo->query(
                "SELECT current_status, COUNT(*) AS c
                 FROM rateb_subscription_engine
                 GROUP BY current_status"
            );
            $byStatus = [];
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $byStatus[strtoupper((string) ($row['current_status'] ?? ''))] = (int) ($row['c'] ?? 0);
                }
            }

            $active = (int) ($byStatus[SubscriptionStatus::ACTIVE] ?? 0);
            $warning = (int) ($byStatus[SubscriptionStatus::WARNING] ?? 0)
                + (int) ($byStatus[SubscriptionStatus::CRITICAL] ?? 0);
            $grace = (int) ($byStatus[SubscriptionStatus::GRACE] ?? 0)
                + (int) ($byStatus[SubscriptionStatus::SUSPENSION_PENDING] ?? 0);
            $suspended = (int) ($byStatus[SubscriptionStatus::SUSPENDED] ?? 0);

            $soonStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM rateb_subscription_engine
                 WHERE current_status <> :suspended
                   AND suspended_at IS NULL
                   AND subscription_end >= :today
                   AND subscription_end <= DATE_ADD(:today2, INTERVAL ' . $expiringSoonDays . ' DAY)'
            );
            $soonStmt->execute([
                'suspended' => SubscriptionStatus::SUSPENDED,
                'today' => $todayYmd,
                'today2' => $todayYmd,
            ]);
            $expiringSoon = (int) $soonStmt->fetchColumn();

            return new SubscriptionAdminDashboard(
                $total,
                $active,
                $warning,
                $grace,
                $suspended,
                $expiringSoon
            );
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminRepository::dashboardCounts: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Paginated tenant list (engine + company name only).
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listTenants(
        int $offset,
        int $limit,
        ?string $statusFilter = null,
        ?string $search = null,
        string $todayYmd = '',
        bool $expiringSoonOnly = false
    ): array {
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));
        $todayYmd = $todayYmd !== '' ? $todayYmd : gmdate('Y-m-d');

        try {
            $pdo = Database::connection();
            $where = ['1=1'];
            $params = [];

            if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
                if ($statusFilter === 'expiring_soon') {
                    $expiringSoonOnly = true;
                } elseif ($statusFilter === 'warning') {
                    $where[] = 'e.current_status IN (\'WARNING\', \'CRITICAL\')';
                } elseif ($statusFilter === 'grace') {
                    $where[] = 'e.current_status IN (\'GRACE\', \'SUSPENSION_PENDING\')';
                } else {
                    $where[] = 'e.current_status = :status';
                    $params['status'] = strtoupper($statusFilter);
                }
            }

            if ($expiringSoonOnly) {
                $days = SubscriptionAdminViewModel::EXPIRING_SOON_DAYS;
                $where[] = 'e.current_status <> \'SUSPENDED\'';
                $where[] = 'e.suspended_at IS NULL';
                $where[] = 'e.subscription_end >= :today';
                $where[] = 'e.subscription_end <= DATE_ADD(:today2, INTERVAL ' . (int) $days . ' DAY)';
                $params['today'] = $todayYmd;
                $params['today2'] = $todayYmd;
            }

            if ($search !== null && trim($search) !== '') {
                $q = trim($search);
                if (ctype_digit($q)) {
                    $where[] = 'e.company_id = :company_id_exact';
                    $params['company_id_exact'] = (int) $q;
                } else {
                    $where[] = 'c.name LIKE :name_like';
                    $params['name_like'] = '%' . $q . '%';
                }
            }

            $whereSql = implode(' AND ', $where);

            $countSql = "SELECT COUNT(*)
                         FROM rateb_subscription_engine e
                         LEFT JOIN rateb_companies c ON c.id = e.company_id
                         WHERE {$whereSql}";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT e.id, e.company_id, e.subscription_start, e.subscription_end,
                           e.grace_period_days, e.grace_started_at, e.grace_end_at,
                           e.current_status, e.suspended_at, e.renewed_at,
                           e.created_at, e.updated_at,
                           c.name AS company_name
                    FROM rateb_subscription_engine e
                    LEFT JOIN rateb_companies c ON c.id = e.company_id
                    WHERE {$whereSql}
                    ORDER BY e.subscription_end ASC, e.company_id ASC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return [
                'items' => is_array($rows) ? $rows : [],
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminRepository::listTenants: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTenant(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        try {
            $pdo = Database::connection();
            try {
                $stmt = $pdo->prepare(
                    'SELECT e.id, e.company_id, e.subscription_start, e.subscription_end,
                            e.grace_period_days, e.grace_started_at, e.grace_end_at,
                            e.current_status, e.suspended_at, e.renewed_at,
                            e.created_at, e.updated_at,
                            c.name AS company_name
                     FROM rateb_subscription_engine e
                     LEFT JOIN rateb_companies c ON c.id = e.company_id
                     WHERE e.company_id = :company_id
                     LIMIT 1'
                );
                $stmt->execute(['company_id' => $companyId]);
            } catch (\Throwable $colEx) {
                $stmt = $pdo->prepare(
                    'SELECT e.id, e.company_id, e.subscription_start, e.subscription_end,
                            e.grace_period_days,
                            e.current_status, e.suspended_at, e.renewed_at,
                            e.created_at, e.updated_at,
                            c.name AS company_name
                     FROM rateb_subscription_engine e
                     LEFT JOIN rateb_companies c ON c.id = e.company_id
                     WHERE e.company_id = :company_id
                     LIMIT 1'
                );
                $stmt->execute(['company_id' => $companyId]);
            }
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminRepository::findTenant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extend expiry only (no full reactivation) — still clears suspension/grace for safety.
     */
    public function extendExpiry(int $companyId, string $newExpiryYmd): bool
    {
        if ($companyId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newExpiryYmd)) {
            return false;
        }
        try {
            $pdo = Database::connection();
            try {
                $stmt = $pdo->prepare(
                    'UPDATE rateb_subscription_engine
                     SET subscription_end = :new_end,
                         current_status = :status,
                         suspended_at = NULL,
                         grace_started_at = NULL,
                         grace_end_at = NULL,
                         updated_at = NOW()
                     WHERE company_id = :company_id'
                );
            } catch (\Throwable $e) {
                $stmt = $pdo->prepare(
                    'UPDATE rateb_subscription_engine
                     SET subscription_end = :new_end,
                         current_status = :status,
                         suspended_at = NULL,
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
            error_log('RATEB SubscriptionAdminRepository::extendExpiry: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLifecycleAudits(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, action, old_status, new_status, actor_id, created_at
                 FROM rateb_subscription_lifecycle_audit
                 WHERE company_id = :company_id
                 ORDER BY id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['company_id' => $companyId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminRepository::listLifecycleAudits: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSuspensionAudits(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, decision, reason, created_at
                 FROM rateb_subscription_suspension_audit
                 WHERE company_id = :company_id
                 ORDER BY id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['company_id' => $companyId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminRepository::listSuspensionAudits: ' . $e->getMessage());
            return [];
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
            error_log('RATEB SubscriptionAdminRepository::insertLifecycleAudit: ' . $e->getMessage());
            return 0;
        }
    }
}
