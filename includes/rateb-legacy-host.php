<?php
declare(strict_types=1);

/**
 * Legacy cPanel hostnames — ignore stored agency site_url and use rateb.sa + country slug instead.
 */
if (!function_exists('rateb_legacy_ratib_hosts')) {
    /** @return list<string> */
    function rateb_legacy_ratib_hosts(): array
    {
        return [
            'ratib.sa',
            'www.ratib.sa',
            'out.ratib.sa',
        ];
    }
}

if (!function_exists('rateb_url_host_is_legacy_ratib')) {
    function rateb_url_host_is_legacy_ratib(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, rateb_legacy_ratib_hosts(), true);
    }
}

if (!function_exists('rateb_rewrite_legacy_ratib_url')) {
    /** Replace legacy host with RATEB_PRO_URL / SITE_URL origin when possible. */
    function rateb_rewrite_legacy_ratib_url(string $url): string
    {
        if (!rateb_url_host_is_legacy_ratib($url)) {
            return $url;
        }
        $base = '';
        if (defined('RATEB_PRO_URL') && (string) RATEB_PRO_URL !== '') {
            $base = rtrim((string) RATEB_PRO_URL, '/');
        } elseif (defined('SITE_URL') && (string) SITE_URL !== '') {
            $base = rtrim((string) SITE_URL, '/');
        }
        if ($base === '') {
            $base = 'https://rateb.sa';
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        $query = parse_url($url, PHP_URL_QUERY);
        $out = $base . ($path !== '' ? $path : '/');
        if (is_string($query) && $query !== '') {
            $out .= '?' . $query;
        }

        return $out;
    }
}

if (!function_exists('rateb_country_login_url')) {
    /** Canonical country login on single-URL mode (rateb.sa/bangladesh/login). */
    function rateb_country_login_url(string $countrySlug): string
    {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9_-]/', '', $countrySlug)));
        $base = '';
        if (defined('RATEB_PRO_URL') && (string) RATEB_PRO_URL !== '') {
            $base = rtrim((string) RATEB_PRO_URL, '/');
        } elseif (defined('SITE_URL') && (string) SITE_URL !== '') {
            $base = rtrim((string) SITE_URL, '/');
        }
        if ($base === '') {
            $base = 'https://rateb.sa';
        }
        if ($slug === '') {
            return $base . '/pages/login';
        }

        return $base . '/' . rawurlencode($slug) . '/login';
    }
}
