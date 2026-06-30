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
        if ($token === '') {
            return false;
        }
        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        if (is_string($stored) && $stored !== '' && hash_equals($stored, $token)) {
            return true;
        }
        $cookie = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        return $cookie !== '' && hash_equals($cookie, $token);
    }

    public static function clearCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = self::requestIsSecure();
        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', $secure, true);
        }
    }

    private static function mirrorCookie(string $token): void
    {
        if (headers_sent() || $token === '') {
            return;
        }
        $secure = self::requestIsSecure();
        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::COOKIE_NAME, $token, [
                'expires' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::COOKIE_NAME, $token, 0, '/', '', $secure, true);
        }
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
