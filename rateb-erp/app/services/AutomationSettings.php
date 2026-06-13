<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

final class AutomationSettings
{
    public static function getInt(string $key, int $default): int
    {
        $val = (new SystemSetting())->get($key);
        if ($val === null || $val === '') {
            return $default;
        }
        return max(0, (int) $val);
    }

    public static function getString(string $key, string $default = ''): string
    {
        $val = (new SystemSetting())->get($key);
        return $val !== null && $val !== '' ? (string) $val : $default;
    }

    public static function lockoutMaxAttempts(): int
    {
        return max(3, self::getInt('lockout_max_attempts', 5));
    }

    public static function lockoutDurationMinutes(): int
    {
        return max(5, self::getInt('lockout_duration_minutes', 30));
    }

    public static function rememberMeDays(): int
    {
        return max(1, self::getInt('remember_me_days', 30));
    }

    public static function smtpEncryption(): string
    {
        $enc = strtolower(self::getString('smtp_encryption', 'tls'));
        return in_array($enc, ['tls', 'ssl', 'none'], true) ? $enc : 'tls';
    }

    public static function backupRetentionDays(): int
    {
        return max(7, self::getInt('backup_retention_days', 30));
    }
}
