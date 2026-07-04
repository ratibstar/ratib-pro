<?php
declare(strict_types=1);

namespace App\Accounting\Support;

final class AccountingConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = dirname(__DIR__, 3) . '/config/accounting.php';
        self::$config = is_file($path) ? (require $path) : [];

        return self::$config;
    }

    public static function eventStoreEnabled(): bool
    {
        if (defined('ACCOUNTING_EVENT_STORE_ENABLED')) {
            return (bool) ACCOUNTING_EVENT_STORE_ENABLED;
        }

        return !empty(self::all()['event_store_enabled']);
    }

    public static function replayEnabled(): bool
    {
        if (defined('ACCOUNTING_REPLAY_ENABLED')) {
            return (bool) ACCOUNTING_REPLAY_ENABLED;
        }

        return !empty(self::all()['replay_enabled']);
    }

    public static function auditEnabled(): bool
    {
        if (defined('ACCOUNTING_AUDIT_ENABLED')) {
            return (bool) ACCOUNTING_AUDIT_ENABLED;
        }

        $config = self::all();

        return array_key_exists('audit_enabled', $config) ? (bool) $config['audit_enabled'] : true;
    }
}
