<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Configurable grace-period length (default 7 days).
 * Not hardcoded inside GracePeriodEngine — inject or override via constructor.
 */
final class GracePeriodPolicy
{
    public const DEFAULT_GRACE_DAYS = 7;

    private int $graceDays;

    public function __construct(?int $graceDays = null)
    {
        $days = $graceDays ?? self::DEFAULT_GRACE_DAYS;
        $this->graceDays = max(0, $days);
    }

    /**
     * Resolve effective length: row value when > 0, otherwise policy default.
     */
    public function resolveGraceDays(int $rowGracePeriodDays): int
    {
        if ($rowGracePeriodDays > 0) {
            return $rowGracePeriodDays;
        }
        return $this->graceDays > 0 ? $this->graceDays : self::DEFAULT_GRACE_DAYS;
    }

    public function graceDays(): int
    {
        return $this->graceDays > 0 ? $this->graceDays : self::DEFAULT_GRACE_DAYS;
    }
}
