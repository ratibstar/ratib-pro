<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Subscription notification type vocabulary.
 * Delivery is out of scope — types label eligibility decisions only.
 */
final class NotificationType
{
    public const REMINDER = 'REMINDER';
    public const FINAL_WARNING = 'FINAL_WARNING';
    public const GRACE = 'GRACE';
    public const SUSPENSION = 'SUSPENSION';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::REMINDER,
            self::FINAL_WARNING,
            self::GRACE,
            self::SUSPENSION,
        ];
    }

    public static function isKnown(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
