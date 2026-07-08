<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Support\Request;

final class AdminLocale
{
    public static function resolve(): string
    {
        $supported = ['ar', 'en'];

        if (isset($_GET['lang']) && is_string($_GET['lang'])) {
            $lang = strtolower(trim($_GET['lang']));
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        $header = Request::header('X-Rateb-Locale');
        if (is_string($header) && $header !== '') {
            $lang = strtolower(trim($header));
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return defined('RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE')
            ? (string) RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE
            : 'ar';
    }

    public static function dir(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    public static function withLang(string $path, string $locale): string
    {
        $sep = str_contains($path, '?') ? '&' : '?';

        return $path . $sep . 'lang=' . rawurlencode($locale);
    }
}
