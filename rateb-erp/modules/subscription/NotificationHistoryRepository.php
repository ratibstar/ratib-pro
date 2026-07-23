<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Database;

/**
 * Persistence for subscription notification eligibility / generation history.
 *
 * Phase 3: read + optional record of a generated decision.
 * Never sends email/push/SMS/in-app. Does not modify rateb_subscription_engine.
 */
final class NotificationHistoryRepository implements NotificationHistoryStore
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return list<array<string, mixed>>
     */
    public function listByCompanyId(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id, company_id, subscription_id, notification_type, trigger_day,
                        scheduled_date, generated_at, delivered_at, dismissed_at, status, created_at
                 FROM rateb_subscription_notification_history
                 WHERE company_id = :company_id
                 ORDER BY id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['company_id' => $companyId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB NotificationHistoryRepository::listByCompanyId: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLastByCompanyId(int $companyId): ?array
    {
        $rows = $this->listByCompanyId($companyId, 1);
        return $rows[0] ?? null;
    }

    public function existsForTrigger(int $companyId, string $notificationType, int $triggerDay): bool
    {
        if ($companyId < 1 || $notificationType === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id FROM rateb_subscription_notification_history
                 WHERE company_id = :company_id
                   AND notification_type = :notification_type
                   AND trigger_day = :trigger_day
                 LIMIT 1'
            );
            $stmt->execute([
                'company_id' => $companyId,
                'notification_type' => $notificationType,
                'trigger_day' => $triggerDay,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return is_array($row);
        } catch (\Throwable $e) {
            error_log('RATEB NotificationHistoryRepository::existsForTrigger: ' . $e->getMessage());
            // Fail closed for duplicates: treat as existing so we do not invent duplicates on DB errors.
            return true;
        }
    }

    /**
     * Persist a generated eligibility decision (future dispatcher / tests).
     * Phase 3 Engine does not call this automatically.
     *
     * @return int inserted id, or 0 on failure / duplicate
     */
    public function recordGenerated(NotificationDecision $decision): int
    {
        if (!$decision->shouldGenerate()
            || $decision->notificationType() === null
            || $decision->triggerDay() === null
            || $decision->scheduledDate() === null) {
            return 0;
        }

        if ($this->existsForTrigger(
            $decision->companyId(),
            $decision->notificationType(),
            $decision->triggerDay()
        )) {
            return 0;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_subscription_notification_history
                    (company_id, subscription_id, notification_type, trigger_day,
                     scheduled_date, generated_at, delivered_at, dismissed_at, status, created_at)
                 VALUES
                    (:company_id, :subscription_id, :notification_type, :trigger_day,
                     :scheduled_date, NOW(), NULL, NULL, :status, NOW())'
            );
            $stmt->execute([
                'company_id' => $decision->companyId(),
                'subscription_id' => $decision->subscriptionId(),
                'notification_type' => $decision->notificationType(),
                'trigger_day' => $decision->triggerDay(),
                'scheduled_date' => $decision->scheduledDate(),
                'status' => self::STATUS_GENERATED,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('RATEB NotificationHistoryRepository::recordGenerated: ' . $e->getMessage());
            return 0;
        }
    }
}
