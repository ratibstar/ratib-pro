<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Canonical subscription lifecycle statuses.
 *
 * Single vocabulary for the Subscription Engine. No transitions here —
 * transition rules belong to a future phase (Policy + Engine).
 *
 * Isolation: this class must never reference HR, POS, Inventory, Accounting,
 * Payroll, CRM, Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionStatus
{
    public const ACTIVE = 'ACTIVE';
    public const WARNING = 'WARNING';
    public const CRITICAL = 'CRITICAL';
    public const GRACE = 'GRACE';
    public const SUSPENDED = 'SUSPENDED';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::WARNING,
            self::CRITICAL,
            self::GRACE,
            self::SUSPENDED,
        ];
    }

    public static function isKnown(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
