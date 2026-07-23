<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Decides WHEN a subscription reminder is eligible to exist.
 *
 * Phase 3: eligibility only — no send, UI, cron, email, SMS, push, or banners.
 * Does not modify rateb_subscription_engine.
 *
 * Architecture:
 *   SubscriptionContext → NotificationEngine → NotificationPolicy
 *                       → NotificationHistoryRepository → history table
 */
final class NotificationEngine
{
    private NotificationPolicy $policy;
    private NotificationHistoryStore $history;

    public function __construct(
        ?NotificationPolicy $policy = null,
        ?NotificationHistoryStore $history = null
    ) {
        $this->policy = $policy ?? new NotificationPolicy();
        $this->history = $history ?? new NotificationHistoryRepository();
    }

    /**
     * Evaluate eligibility for today against context + policy + history.
     */
    public function evaluate(SubscriptionContext $context, ?string $todayYmd = null): NotificationDecision
    {
        $companyId = $context->companyId();
        if ($companyId < 1) {
            return NotificationDecision::decline($companyId, 'invalid_company');
        }

        if (!$context->hasRecord()) {
            return NotificationDecision::decline($companyId, 'no_subscription_record');
        }

        if ($context->isSuspended()
            || $context->status() === SubscriptionStatus::SUSPENDED) {
            return $this->evaluateSuspension($context, $todayYmd);
        }

        $daysRemaining = $context->daysRemaining();
        if (!$this->policy->isTriggerDay($daysRemaining)) {
            return NotificationDecision::decline(
                $companyId,
                'not_a_policy_trigger_day',
                $this->resolveSubscriptionId($context)
            );
        }

        $type = $this->policy->typeForTriggerDay($daysRemaining);
        if ($this->history->existsForTrigger($companyId, $type, $daysRemaining)) {
            return NotificationDecision::decline(
                $companyId,
                'duplicate_trigger_already_recorded',
                $this->resolveSubscriptionId($context)
            );
        }

        $today = $todayYmd ?? gmdate('Y-m-d');

        return NotificationDecision::eligible(
            $companyId,
            $this->resolveSubscriptionId($context),
            $type,
            $daysRemaining,
            $today,
            'policy_trigger_day_matched',
            $this->policy->channels()
        );
    }

    public function shouldGenerate(SubscriptionContext $context, ?string $todayYmd = null): bool
    {
        return $this->evaluate($context, $todayYmd)->shouldGenerate();
    }

    /**
     * Calendar date (Y-m-d) of the next policy trigger, or null if none.
     * Computed in memory — does not write subscription.next_notification_date.
     */
    public function nextNotificationDate(SubscriptionContext $context, ?string $todayYmd = null): ?string
    {
        if (!$context->hasRecord() || $context->expirationDate() === null) {
            return null;
        }

        $nextTrigger = $this->policy->nextTriggerDayAfter($context->daysRemaining());
        if ($nextTrigger === null) {
            return null;
        }

        $end = $context->expirationDate();
        $endTs = strtotime($end . ' 00:00:00');
        if ($endTs === false) {
            return null;
        }

        // trigger_day N means scheduled on (subscription_end - N days).
        $scheduledTs = $endTs - ($nextTrigger * 86400);
        return gmdate('Y-m-d', $scheduledTs);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastNotification(int $companyId): ?array
    {
        return $this->history->findLastByCompanyId($companyId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $companyId, int $limit = 50): array
    {
        return $this->history->listByCompanyId($companyId, $limit);
    }

    /**
     * Optional persistence hook for a future dispatcher — not invoked by bootstrap.
     */
    public function recordGenerated(NotificationDecision $decision): int
    {
        return $this->history->recordGenerated($decision);
    }

    public function policy(): NotificationPolicy
    {
        return $this->policy;
    }

    private function evaluateSuspension(
        SubscriptionContext $context,
        ?string $todayYmd
    ): NotificationDecision {
        $companyId = $context->companyId();
        $type = NotificationType::SUSPENSION;
        $triggerDay = $context->daysRemaining();

        if ($this->history->existsForTrigger($companyId, $type, $triggerDay)) {
            return NotificationDecision::decline(
                $companyId,
                'duplicate_suspension_already_recorded',
                $this->resolveSubscriptionId($context)
            );
        }

        // Only emit SUSPENSION eligibility once per distinct trigger_day value while suspended.
        // Prefer a stable trigger key of 0 when suspended regardless of days, to avoid spam.
        $stableTrigger = 0;
        if ($this->history->existsForTrigger($companyId, $type, $stableTrigger)) {
            return NotificationDecision::decline(
                $companyId,
                'duplicate_suspension_already_recorded',
                $this->resolveSubscriptionId($context)
            );
        }

        $today = $todayYmd ?? gmdate('Y-m-d');

        return NotificationDecision::eligible(
            $companyId,
            $this->resolveSubscriptionId($context),
            $type,
            $stableTrigger,
            $today,
            'subscription_suspended',
            $this->policy->channels()
        );
    }

    private function resolveSubscriptionId(SubscriptionContext $context): ?int
    {
        return $context->recordId();
    }
}
