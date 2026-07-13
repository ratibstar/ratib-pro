<?php
declare(strict_types=1);

/**
 * Minimal mbstring fallbacks for Branch Appliance PHP builds that omit the extension.
 * Prefer enabling extension=mbstring via hybrid-branch-serve.php / launchers.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        if ($string === '') {
            return 0;
        }
        if (function_exists('preg_match_all')) {
            $n = preg_match_all('/./us', $string);
            if ($n !== false) {
                return $n;
            }
        }
        return strlen($string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (function_exists('preg_split')) {
            $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($chars)) {
                $slice = $length === null
                    ? array_slice($chars, $start)
                    : array_slice($chars, $start, $length);
                return implode('', $slice);
            }
        }
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        return strtoupper($string);
    }
}
