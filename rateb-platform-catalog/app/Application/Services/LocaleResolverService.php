<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Support\Request;

final class LocaleResolverService
{
    public function resolveFromRequest(): LocaleContext
    {
        $default = defined('RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE')
            ? (string) RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE
            : 'ar';
        $fallback = defined('RATEB_PLATFORM_CATALOG_FALLBACK_LOCALE')
            ? (string) RATEB_PLATFORM_CATALOG_FALLBACK_LOCALE
            : 'en';

        $locale = $default;

        if (isset($_GET['lang']) && is_string($_GET['lang']) && $_GET['lang'] !== '') {
            $locale = strtolower($_GET['lang']);
        }

        $headerLocale = Request::header('X-Rateb-Locale');
        if ($headerLocale !== null && $headerLocale !== '') {
            $locale = strtolower($headerLocale);
        }

        $acceptLanguage = Request::header('Accept-Language');
        if ($acceptLanguage !== null && $acceptLanguage !== '' && !isset($_GET['lang']) && $headerLocale === null) {
            $locale = strtolower(trim(explode(',', $acceptLanguage)[0]));
            $locale = explode(';', $locale)[0];
        }

        $locale = substr($locale, 0, 10);

        return new LocaleContext($locale, $fallback);
    }
}
