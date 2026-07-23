<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Grace-period lifecycle vocabulary (calculation only — no enforcement).
 *
 * Flow: ACTIVE → WARNING → CRITICAL → GRACE → SUSPENSION_PENDING
 */
final class GracePeriodStatus
{
    public const ACTIVE = 'ACTIVE';
    public const WARNING = 'WARNING';
    public const CRITICAL = 'CRITICAL';
    public const GRACE = 'GRACE';
    public const SUSPENSION_PENDING = 'SUSPENSION_PENDING';

    /** @return list<string> */
    public static function flow(): array
    {
        return [
            self::ACTIVE,
            self::WARNING,
            self::CRITICAL,
            self::GRACE,
            self::SUSPENSION_PENDING,
        ];
    }

    public static function isGrace(string $status): bool
    {
        return strtoupper($status) === self::GRACE;
    }

    public static function isSuspensionPending(string $status): bool
    {
        return strtoupper($status) === self::SUSPENSION_PENDING;
    }
}
