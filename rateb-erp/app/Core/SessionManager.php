<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class SessionManager
{
    public static function start(): void
    {
        if (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === 'rateb_erp') {
                return;
            }
            session_write_close();
        }

        session_name('rateb_erp');
        self::ensureSavePath();
        $secure = self::requestIsSecure();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/', '', $secure, true);
        }

        session_start();

        if (empty($_SESSION['_rateb_init'])) {
            session_regenerate_id(true);
            $_SESSION['_rateb_init'] = time();
        }
    }

    private static function ensureActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== 'rateb_erp') {
            self::start();
        }
    }

    private static function requestIsSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    private static function ensureSavePath(): void
    {
        if (!defined('RATEB_ROOT')) {
            return;
        }
        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
    }

    public static function get(string $key, $default = null)
    {
        self::ensureActive();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::ensureActive();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::ensureActive();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, $value = null)
    {
        self::ensureActive();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        session_destroy();
        session_name('rateb_erp');
        session_start();
        session_regenerate_id(true);
        $_SESSION['_rateb_init'] = time();
    }
}
