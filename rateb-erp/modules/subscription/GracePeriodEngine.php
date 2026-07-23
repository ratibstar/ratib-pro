<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Grace period lifecycle calculator.
 *
 * Example (7-day grace):
 *   subscription_end = 2026-08-01
 *   grace           = 2026-08-02 → 2026-08-08
 *   suspension eligibility = 2026-08-09
 *
 * No access blocking, no suspension enforcement, no DB writes.
 */
final class GracePeriodEngine
{
    private GracePeriodPolicy $policy;

    public function __construct(?GracePeriodPolicy $policy = null)
    {
        $this->policy = $policy ?? new GracePeriodPolicy();
    }

    public function policy(): GracePeriodPolicy
    {
        return $this->policy;
    }

    /**
     * First calendar day of grace (day after subscription_end), or null.
     */
    public function calculateGraceStart(string $subscriptionEndYmd, int $gracePeriodDays = 0): ?string
    {
        if (!$this->isValidDate($subscriptionEndYmd)) {
            return null;
        }
        $days = $this->policy->resolveGraceDays($gracePeriodDays);
        if ($days < 1) {
            return null;
        }
        $ts = strtotime($subscriptionEndYmd . ' 00:00:00');
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d', $ts + 86400);
    }

    /**
     * Last inclusive calendar day of grace, or null.
     * grace_end = subscription_end + grace_days
     */
    public function calculateGraceEnd(string $subscriptionEndYmd, int $gracePeriodDays = 0): ?string
    {
        if (!$this->isValidDate($subscriptionEndYmd)) {
            return null;
        }
        $days = $this->policy->resolveGraceDays($gracePeriodDays);
        if ($days < 1) {
            return null;
        }
        $ts = strtotime($subscriptionEndYmd . ' 00:00:00');
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d', $ts + ($days * 86400));
    }

    /**
     * Whole days remaining until grace_end (0 on last grace day; negative after).
     */
    public function daysRemaining(
        string $subscriptionEndYmd,
        string $todayYmd,
        int $gracePeriodDays = 0,
        ?string $graceEndYmd = null
    ): int {
        $end = $graceEndYmd;
        if ($end === null || !$this->isValidDate($end)) {
            $end = $this->calculateGraceEnd($subscriptionEndYmd, $gracePeriodDays);
        }
        if ($end === null || !$this->isValidDate($todayYmd)) {
            return 0;
        }
        $endTs = strtotime($end . ' 00:00:00');
        $todayTs = strtotime($todayYmd . ' 00:00:00');
        if ($endTs === false || $todayTs === false) {
            return 0;
        }
        return (int) floor(($endTs - $todayTs) / 86400);
    }

    public function isInGracePeriod(
        string $subscriptionEndYmd,
        string $todayYmd,
        int $gracePeriodDays = 0,
        ?string $graceStartYmd = null,
        ?string $graceEndYmd = null
    ): bool {
        if (!$this->isValidDate($subscriptionEndYmd) || !$this->isValidDate($todayYmd)) {
            return false;
        }
        $start = ($graceStartYmd !== null && $this->isValidDate($graceStartYmd))
            ? $graceStartYmd
            : $this->calculateGraceStart($subscriptionEndYmd, $gracePeriodDays);
        $end = ($graceEndYmd !== null && $this->isValidDate($graceEndYmd))
            ? $graceEndYmd
            : $this->calculateGraceEnd($subscriptionEndYmd, $gracePeriodDays);
        if ($start === null || $end === null) {
            return false;
        }
        return $todayYmd >= $start && $todayYmd <= $end;
    }

    public function hasGraceExpired(
        string $subscriptionEndYmd,
        string $todayYmd,
        int $gracePeriodDays = 0,
        ?string $graceEndYmd = null
    ): bool {
        if (!$this->isValidDate($subscriptionEndYmd) || !$this->isValidDate($todayYmd)) {
            return false;
        }
        $end = ($graceEndYmd !== null && $this->isValidDate($graceEndYmd))
            ? $graceEndYmd
            : $this->calculateGraceEnd($subscriptionEndYmd, $gracePeriodDays);
        if ($end === null) {
            return false;
        }
        // Still on/before subscription_end → grace has not started, hence not expired.
        if ($todayYmd <= $subscriptionEndYmd) {
            return false;
        }
        return $todayYmd > $end;
    }

    /**
     * Derived lifecycle label for today (calculation only).
     */
    public function resolveLifecycleStatus(
        string $subscriptionEndYmd,
        string $todayYmd,
        int $gracePeriodDays,
        string $storedStatus
    ): string {
        $stored = strtoupper(trim($storedStatus));
        if ($stored === SubscriptionStatus::SUSPENDED) {
            return SubscriptionStatus::SUSPENDED;
        }
        if (!$this->isValidDate($subscriptionEndYmd) || !$this->isValidDate($todayYmd)) {
            return SubscriptionStatus::isKnown($stored) ? $stored : SubscriptionStatus::ACTIVE;
        }
        if ($todayYmd <= $subscriptionEndYmd) {
            if (in_array($stored, [
                SubscriptionStatus::ACTIVE,
                SubscriptionStatus::WARNING,
                SubscriptionStatus::CRITICAL,
            ], true)) {
                return $stored;
            }
            return SubscriptionStatus::ACTIVE;
        }
        if ($this->isInGracePeriod($subscriptionEndYmd, $todayYmd, $gracePeriodDays)) {
            return GracePeriodStatus::GRACE;
        }
        if ($this->hasGraceExpired($subscriptionEndYmd, $todayYmd, $gracePeriodDays)) {
            return GracePeriodStatus::SUSPENSION_PENDING;
        }
        return GracePeriodStatus::GRACE;
    }

    private function isValidDate(string $ymd): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1;
    }
}
