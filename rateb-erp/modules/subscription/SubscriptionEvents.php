<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Future event catalog for the Subscription Engine.
 *
 * Phase 1: constants only — no emitters, listeners, queues, or notifications.
 * Later phases may emit these names through a dedicated event bus without
 * coupling the engine to ERP UI or other business modules.
 *
 * Isolation: this class must never reference HR, POS, Inventory, Accounting,
 * Payroll, CRM, Procurement, Employees, Attendance, or any UI class.
 */
final class SubscriptionEvents
{
    public const STATUS_CHANGED = 'subscription.status_changed';
    public const ENTERED_WARNING = 'subscription.entered_warning';
    public const ENTERED_CRITICAL = 'subscription.entered_critical';
    public const ENTERED_GRACE = 'subscription.entered_grace';
    public const SUSPENDED = 'subscription.suspended';
    public const RENEWED = 'subscription.renewed';
    public const ACCESS_DENIED = 'subscription.access_denied';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::STATUS_CHANGED,
            self::ENTERED_WARNING,
            self::ENTERED_CRITICAL,
            self::ENTERED_GRACE,
            self::SUSPENDED,
            self::RENEWED,
            self::ACCESS_DENIED,
        ];
    }
}
