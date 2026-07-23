<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Future delivery channels — catalog only.
 * Phase 3 never sends via any channel.
 */
final class NotificationChannel
{
    public const EMAIL = 'email';
    public const PUSH = 'push';
    public const IN_APP = 'in_app';
    public const WHATSAPP = 'whatsapp';
    public const SMS = 'sms';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::EMAIL,
            self::PUSH,
            self::IN_APP,
            self::WHATSAPP,
            self::SMS,
        ];
    }
}
