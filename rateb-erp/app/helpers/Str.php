<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class Str
{
    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Accepts Gmail, Outlook, Yahoo, Hotmail, and corporate domains. */
    public static function isValidEmail(string $email): bool
    {
        $email = trim($email);
        if ($email === '' || strlen($email) > 254) {
            return false;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return (bool) preg_match('/^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]{2,}$/i', $email);
    }

    public static function emailDomain(string $email): string
    {
        $pos = strrpos($email, '@');
        if ($pos === false) {
            return '';
        }
        return strtolower(substr($email, $pos + 1));
    }
}
