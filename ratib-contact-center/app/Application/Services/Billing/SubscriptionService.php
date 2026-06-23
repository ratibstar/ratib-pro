<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class SubscriptionService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function listPlans(): array
    {
        $stmt = Database::connection()->query(
            "SELECT * FROM rcc_plans WHERE status = 'active' ORDER BY sort_order ASC"
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

  /** @return array<string, mixed>|null */
    public function activeSubscription(int $tenantId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.*, p.code AS plan_code, p.name AS plan_name, p.max_agents, p.max_queues
             FROM rcc_subscriptions s
             INNER JOIN rcc_plans p ON p.id = s.plan_id
             WHERE s.tenant_id = :tid AND s.status IN ('trialing','active','past_due')
             ORDER BY s.id DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function subscribe(int $tenantId, int $planId, ?int $userId, int $trialDays = 14): array
    {
        $pdo = Database::connection();
        $plan = $pdo->prepare('SELECT * FROM rcc_plans WHERE id = :id AND status = \'active\' LIMIT 1');
        $plan->execute(['id' => $planId]);
        $planRow = $plan->fetch();
        if (!$planRow) {
            throw new \InvalidArgumentException('Plan not found');
        }
        $start = new \DateTimeImmutable('now');
        $end = $start->modify('+1 month');
        $pdo->prepare(
            "UPDATE rcc_subscriptions SET status = 'cancelled', cancelled_at = NOW()
             WHERE tenant_id = :tid AND status IN ('trialing','active','past_due')"
        )->execute(['tid' => $tenantId]);
        $pdo->prepare(
            'INSERT INTO rcc_subscriptions (tenant_id, plan_id, status, current_period_start, current_period_end)
             VALUES (:tid, :pid, :st, :start, :end)'
        )->execute([
            'tid' => $tenantId,
            'pid' => $planId,
            'st' => $trialDays > 0 ? 'trialing' : 'active',
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
        $subId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE rcc_tenants SET plan_id = :pid, trial_ends_at = :trial WHERE id = :tid')
            ->execute([
                'pid' => $planId,
                'trial' => $trialDays > 0 ? $start->modify('+' . $trialDays . ' days')->format('Y-m-d H:i:s') : null,
                'tid' => $tenantId,
            ]);
        $this->audit->log($tenantId, 'billing.subscription.created', $userId, 'subscription', $subId, ['plan_id' => $planId]);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_SUBSCRIPTION_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['subscription_id' => $subId, 'plan_id' => $planId],
        ]);
        return $this->activeSubscription($tenantId) ?? [];
    }

    public function cancel(int $tenantId, ?int $userId, bool $atPeriodEnd = true): array
    {
        $sub = $this->activeSubscription($tenantId);
        if (!$sub) {
            throw new \RuntimeException('No active subscription');
        }
        if ($atPeriodEnd) {
            Database::connection()->prepare(
                'UPDATE rcc_subscriptions SET cancel_at_period_end = 1 WHERE id = :id AND tenant_id = :tid'
            )->execute(['id' => $sub['id'], 'tid' => $tenantId]);
        } else {
            Database::connection()->prepare(
                "UPDATE rcc_subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id AND tenant_id = :tid"
            )->execute(['id' => $sub['id'], 'tid' => $tenantId]);
        }
        $this->audit->log($tenantId, 'billing.subscription.cancelled', $userId, 'subscription', (int) $sub['id']);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_SUBSCRIPTION_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['subscription_id' => $sub['id'], 'cancelled' => true],
        ]);
        return $this->activeSubscription($tenantId) ?? ['status' => 'cancelled'];
    }
}
