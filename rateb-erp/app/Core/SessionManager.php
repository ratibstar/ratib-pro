<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class SessionManager
{
    public static function cookiePath(): string
    {
        if (function_exists('rateb_erp_app_prefix')) {
            $p = rtrim((string) rateb_erp_app_prefix(), '/');
            if ($p !== '') {
                return $p;
            }
        }

        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if (in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
            // Site-wide path so platform catalog (/rateb-platform-catalog/) receives rateb_erp.
            return '/';
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#(/rateb-erp/public)(?:/|$)#', $uri, $m)) {
            return $m[1];
        }

        return '/';
    }

    /** @return list<string> */
    public static function cookiePathCandidates(): array
    {
        $paths = [self::cookiePath()];
        if (!in_array('/', $paths, true)) {
            $paths[] = '/';
        }

        return $paths;
    }

    private static function expireNamedCookie(string $name): void
    {
        $secure = self::requestIsSecure();
        $domain = '';
        $samesite = 'Lax';
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            $domain = (string) ($params['domain'] ?? '');
            $samesite = (string) ($params['samesite'] ?? 'Lax');
        }
        foreach (self::cookiePathCandidates() as $path) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie($name, '', [
                    'expires' => time() - 42000,
                    'path' => $path,
                    'domain' => $domain,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => $samesite,
                ]);
            } else {
                setcookie($name, '', time() - 42000, $path, $domain, $secure, true);
            }
        }
    }

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
        $cookiePath = self::cookiePath();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookiePath,
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, $cookiePath, '', $secure, true);
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
        foreach (self::sessionSavePathCandidates() as $dir) {
            $resolved = realpath($dir);
            if ($resolved !== false && is_dir($resolved) && is_writable($resolved)) {
                session_save_path($resolved);

                return;
            }
        }

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

    /**
     * @return list<string>
     */
    private static function sessionSavePathCandidates(): array
    {
        $candidates = [];

        $fromEnv = getenv('RATEB_ERP_SESSION_SAVE_PATH');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        if (defined('RATEB_ERP_SESSION_SAVE_PATH') && (string) RATEB_ERP_SESSION_SAVE_PATH !== '') {
            $candidates[] = (string) RATEB_ERP_SESSION_SAVE_PATH;
        }

        if (defined('RATEB_ROOT')) {
            $candidates[] = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/sessions';
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        if ($docRoot !== '') {
            $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
            $candidates[] = $docRoot . '/rateb-erp/storage/sessions';
            $candidates[] = dirname($docRoot) . '/rateb-erp/storage/sessions';
        }

        return array_values(array_unique($candidates));
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
        self::expireNamedCookie(session_name());
        session_destroy();
        session_name('rateb_erp');
        session_start();
        session_regenerate_id(true);
        $_SESSION['_rateb_init'] = time();
    }
}
