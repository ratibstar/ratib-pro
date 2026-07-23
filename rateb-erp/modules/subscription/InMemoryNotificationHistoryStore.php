<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * In-memory history store for tests and local evaluation without DB.
 */
final class InMemoryNotificationHistoryStore implements NotificationHistoryStore
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];
    private int $seq = 0;

    /** @return list<array<string, mixed>> */
    public function listByCompanyId(int $companyId, int $limit = 50): array
    {
        $out = [];
        for ($i = count($this->rows) - 1; $i >= 0; $i--) {
            if ((int) $this->rows[$i]['company_id'] === $companyId) {
                $out[] = $this->rows[$i];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    public function findLastByCompanyId(int $companyId): ?array
    {
        $rows = $this->listByCompanyId($companyId, 1);
        return $rows[0] ?? null;
    }

    public function existsForTrigger(int $companyId, string $notificationType, int $triggerDay): bool
    {
        foreach ($this->rows as $row) {
            if ((int) $row['company_id'] === $companyId
                && (string) $row['notification_type'] === $notificationType
                && (int) $row['trigger_day'] === $triggerDay) {
                return true;
            }
        }
        return false;
    }

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
        $this->seq++;
        $this->rows[] = [
            'id' => $this->seq,
            'company_id' => $decision->companyId(),
            'subscription_id' => $decision->subscriptionId(),
            'notification_type' => $decision->notificationType(),
            'trigger_day' => $decision->triggerDay(),
            'scheduled_date' => $decision->scheduledDate(),
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'delivered_at' => null,
            'dismissed_at' => null,
            'status' => NotificationHistoryRepository::STATUS_GENERATED,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        return $this->seq;
    }
}
