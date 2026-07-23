<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Suspension eligibility rules (shadow mode).
 *
 * Eligible only when subscription expired AND grace period expired.
 * Does not enforce access.
 */
final class SuspensionPolicy
{
    private GracePeriodEngine $grace;

    public function __construct(?GracePeriodEngine $grace = null)
    {
        $this->grace = $grace ?? new GracePeriodEngine();
    }

    public function graceEngine(): GracePeriodEngine
    {
        return $this->grace;
    }

    /**
     * First calendar day suspension becomes eligible (day after grace_end).
     */
    public function suspensionEligibleDate(
        string $subscriptionEndYmd,
        int $gracePeriodDays = 0,
        ?string $graceEndYmd = null
    ): ?string {
        $graceEnd = $graceEndYmd;
        if ($graceEnd === null || !$this->isValidDate($graceEnd)) {
            $graceEnd = $this->grace->calculateGraceEnd($subscriptionEndYmd, $gracePeriodDays);
        }
        if ($graceEnd === null || !$this->isValidDate($graceEnd)) {
            return null;
        }
        $ts = strtotime($graceEnd . ' 00:00:00');
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d', $ts + 86400);
    }

    public function isEligible(
        string $subscriptionEndYmd,
        string $todayYmd,
        int $gracePeriodDays = 0,
        ?string $graceEndYmd = null
    ): bool {
        if (!$this->isValidDate($subscriptionEndYmd) || !$this->isValidDate($todayYmd)) {
            return false;
        }
        if ($todayYmd <= $subscriptionEndYmd) {
            return false;
        }
        return $this->grace->hasGraceExpired(
            $subscriptionEndYmd,
            $todayYmd,
            $gracePeriodDays,
            $graceEndYmd
        );
    }

    private function isValidDate(string $ymd): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1;
    }
}
