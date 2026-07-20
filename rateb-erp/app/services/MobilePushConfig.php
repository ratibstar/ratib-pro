<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Server-side mobile push configuration (placeholders — no secrets in git).
 */
final class MobilePushConfig
{
    /** @var array<string,string>|null */
    private static ?array $fileCache = null;

    public static function outboxEnabled(): bool
    {
        $v = strtolower(trim(self::get('RATEB_MOBILE_PUSH_OUTBOX_ENABLED', '0')));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Client apps that receive push outbox rows for each notification.
     *
     * @return list<string>
     */
    public static function targetClientApps(): array
    {
        $raw = self::get('RATEB_MOBILE_PUSH_CLIENT_APPS', 'ess,manager');
        $parts = preg_split('/\s*,\s*/', strtolower(trim($raw))) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if ($p !== '' && in_array($p, MobileDeviceRegistryService::CLIENT_APPS, true)) {
                $out[] = $p;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : ['ess'];
    }

    public static function fcmProjectId(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_FCM_PROJECT_ID', ''));
    }

    public static function fcmCredentialsPath(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_FCM_CREDENTIALS_PATH', ''));
    }

    public static function apnsKeyId(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_APNS_KEY_ID', ''));
    }

    public static function apnsTeamId(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_APNS_TEAM_ID', ''));
    }

    public static function apnsBundleId(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_APNS_BUNDLE_ID', ''));
    }

    public static function apnsKeyPath(): string
    {
        return trim(self::get('RATEB_MOBILE_PUSH_APNS_KEY_PATH', ''));
    }

    public static function fcmConfigured(): bool
    {
        return self::fcmProjectId() !== '' && self::fcmCredentialsPath() !== '';
    }

    public static function apnsConfigured(): bool
    {
        return self::apnsKeyId() !== ''
            && self::apnsTeamId() !== ''
            && self::apnsBundleId() !== ''
            && self::apnsKeyPath() !== '';
    }

    /** Redact token-like values for logs / last_error. */
    public static function redactToken(string $token): string
    {
        $t = trim($token);
        if ($t === '') {
            return '';
        }
        if (strlen($t) <= 8) {
            return '***';
        }

        return substr($t, 0, 4) . '…' . substr($t, -2) . '(len=' . strlen($t) . ')';
    }

    private static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return (string) $env;
        }
        if (isset($_ENV[$key]) && (string) $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        $file = self::fileConfig();
        if (isset($file[$key]) && (string) $file[$key] !== '') {
            return (string) $file[$key];
        }

        return $default;
    }

    /** @return array<string,string> */
    private static function fileConfig(): array
    {
        if (self::$fileCache !== null) {
            return self::$fileCache;
        }
        self::$fileCache = [];
        $paths = [];
        if (defined('RATEB_ROOT')) {
            $paths[] = RATEB_ROOT . '/config/mobile-push.secrets.php';
            $paths[] = dirname(RATEB_ROOT) . '/config/mobile-push.secrets.php';
        }
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = require $path;
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_string($k) && (is_string($v) || is_numeric($v))) {
                        self::$fileCache[$k] = (string) $v;
                    }
                }
            }
            break;
        }

        return self::$fileCache;
    }

    /** @internal tests */
    public static function resetFileCache(): void
    {
        self::$fileCache = null;
    }
}
