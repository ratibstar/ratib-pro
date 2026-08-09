<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const COOKIE_NAME = 'rateb_csrf';

    public static function token(): string
    {
        SessionManager::start();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        $token = (string) $_SESSION[self::SESSION_KEY];
        self::mirrorCookie($token);
        return $token;
    }

    public static function validate(string $token): bool
    {
        SessionManager::start();
        if ($token === '' || strlen($token) < 16) {
            return false;
        }
        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        if (is_string($stored) && $stored !== '' && hash_equals($stored, $token)) {
            return true;
        }

        // Browsers may send duplicate rateb_csrf cookies (path=/ + path=/rateb-erp/public).
        // PHP keeps only one value in $_COOKIE (often the first) — check every raw value.
        foreach (self::cookieValues(self::COOKIE_NAME) as $cookie) {
            if ($cookie !== '' && hash_equals($cookie, $token)) {
                $_SESSION[self::SESSION_KEY] = $token;
                self::mirrorCookie($token);

                return true;
            }
        }

        return false;
    }

    /**
     * All values for a cookie name from the raw Cookie header (handles duplicates).
     *
     * @return list<string>
     */
    private static function cookieValues(string $name): array
    {
        $values = [];
        $raw = (string) ($_SERVER['HTTP_COOKIE'] ?? '');
        if ($raw !== '') {
            foreach (explode(';', $raw) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $eq = strpos($part, '=');
                if ($eq === false) {
                    continue;
                }
                $key = trim(substr($part, 0, $eq));
                if ($key !== $name) {
                    continue;
                }
                $values[] = rawurldecode(trim(substr($part, $eq + 1)));
            }
        }
        if (isset($_COOKIE[$name])) {
            $values[] = (string) $_COOKIE[$name];
        }

        return array_values(array_unique(array_filter($values, static fn ($v) => $v !== '')));
    }

    public static function clearCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = self::requestIsSecure();
        foreach (SessionManager::cookiePathCandidates() as $path) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie(self::COOKIE_NAME, '', [
                    'expires' => time() - 3600,
                    'path' => $path,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie(self::COOKIE_NAME, '', time() - 3600, $path, '', $secure, true);
            }
        }
    }

    private static function mirrorCookie(string $token): void
    {
        if (headers_sent() || $token === '') {
            return;
        }
        $secure = self::requestIsSecure();
        $canonical = SessionManager::cookiePath();
        // Only set the canonical CSRF cookie. Do NOT expire path=/ here —
        // mid-request cookie deletes race with duplicate rateb_erp cookies and
        // break the next POST (Import / login). Alternates cleared on login/destroy only.
        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::COOKIE_NAME, $token, [
                'expires' => 0,
                'path' => $canonical,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::COOKIE_NAME, $token, 0, $canonical, '', $secure, true);
        }
    }

    /** Force a new CSRF token into the current session (after a failed check). */
    public static function regenerate(): string
    {
        SessionManager::start();
        unset($_SESSION[self::SESSION_KEY]);

        return self::token();
    }

    private static function requestIsSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . View::escape(self::token()) . '">';
    }
}
