<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Suspension eligibility calculator — SHADOW MODE only.
 *
 * Calculates whether a tenant SHOULD be suspended. Does not:
 * - call SubscriptionGuard
 * - block requests / redirect
 * - change auth or permissions
 * - write suspension onto rateb_subscription_engine
 */
final class SuspensionEngine
{
    private SuspensionPolicy $policy;
    private ?SuspensionAuditRepository $audit;
    private bool $auditEligibleOnly;
    private ?SuspensionDecision $lastDecision = null;

    public function __construct(
        ?SuspensionPolicy $policy = null,
        ?SuspensionAuditRepository $audit = null,
        bool $auditEligibleOnly = true
    ) {
        $this->policy = $policy ?? new SuspensionPolicy();
        $this->audit = $audit;
        $this->auditEligibleOnly = $auditEligibleOnly;
    }

    public function evaluate(SubscriptionContext $context, ?string $todayYmd = null): SuspensionDecision
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $companyId = $context->companyId();
        $status = $context->hasRecord() ? $context->status() : 'NONE';

        if ($companyId < 1 || !$context->hasRecord()) {
            $decision = SuspensionDecision::notEligible($companyId, 'missing_subscription_data', $status);
            return $this->finish($decision);
        }

        if ($context->isSuspended() || $status === SubscriptionStatus::SUSPENDED) {
            $decision = SuspensionDecision::notEligible(
                $companyId,
                'already_suspended',
                $status,
                $this->suspensionDate($context)
            );
            return $this->finish($decision);
        }

        $expiration = $context->expirationDate();
        if ($expiration === null || $expiration === '') {
            $decision = SuspensionDecision::notEligible($companyId, 'missing_subscription_data', $status);
            return $this->finish($decision);
        }

        $graceDays = $context->gracePeriodDays();
        $graceEnd = $context->graceEndDate();
        $effective = $this->policy->suspensionEligibleDate($expiration, $graceDays, $graceEnd);

        if (!$context->isExpired() && $today <= $expiration) {
            $decision = SuspensionDecision::notEligible(
                $companyId,
                'subscription_active',
                $status,
                $effective
            );
            return $this->finish($decision);
        }

        if ($context->isInGrace()
            || !$this->policy->isEligible($expiration, $today, $graceDays, $graceEnd)) {
            $decision = SuspensionDecision::notEligible(
                $companyId,
                'grace_period_active',
                $status,
                $effective
            );
            return $this->finish($decision);
        }

        $decision = SuspensionDecision::makeEligible(
            $companyId,
            'grace_period_expired',
            $effective ?? $today,
            $status !== '' ? $status : SubscriptionStatus::SUSPENSION_PENDING
        );

        return $this->finish($decision);
    }

    public function shouldSuspend(SubscriptionContext $context, ?string $todayYmd = null): bool
    {
        return $this->evaluate($context, $todayYmd)->isEligible();
    }

    public function reason(?SubscriptionContext $context = null, ?string $todayYmd = null): string
    {
        if ($context !== null) {
            return $this->evaluate($context, $todayYmd)->reason();
        }
        return $this->lastDecision?->reason() ?? 'no_evaluation';
    }

    /**
     * First date suspension becomes eligible (day after grace_end), or null.
     */
    public function suspensionDate(SubscriptionContext $context): ?string
    {
        $expiration = $context->expirationDate();
        if ($expiration === null || $expiration === '') {
            return null;
        }
        return $this->policy->suspensionEligibleDate(
            $expiration,
            $context->gracePeriodDays(),
            $context->graceEndDate()
        );
    }

    public function lastDecision(): ?SuspensionDecision
    {
        return $this->lastDecision;
    }

    public function policy(): SuspensionPolicy
    {
        return $this->policy;
    }

    private function finish(SuspensionDecision $decision): SuspensionDecision
    {
        $this->lastDecision = $decision;
        $this->maybeAudit($decision);
        return $decision;
    }

    private function maybeAudit(SuspensionDecision $decision): void
    {
        if ($this->audit === null) {
            return;
        }
        if ($this->auditEligibleOnly && !$decision->isEligible()) {
            return;
        }
        $this->audit->record($decision);
    }
}
