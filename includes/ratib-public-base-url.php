<?php
/**
 * Site root URL for public marketing pages (never includes /profile or /pages/...).
 */
declare(strict_types=1);

if (!function_exists('ratib_public_site_base_url')) {
    function ratib_public_site_base_url(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $pathBase = '';
        if (function_exists('getBaseUrl')) {
            $pathBase = rtrim((string) getBaseUrl(), '/');
        } elseif (defined('BASE_URL')) {
            $pathBase = rtrim((string) BASE_URL, '/');
        }

        return $scheme . '://' . $host . $pathBase;
    }
}

if (!function_exists('ratib_public_build_marker')) {
    function ratib_public_build_marker(): string
    {
        static $marker = null;
        if ($marker !== null) {
            return $marker;
        }
        $path = dirname(__DIR__) . '/public/ratib-build.txt';
        $marker = is_file($path) ? trim((string) file_get_contents($path)) : '';

        return $marker;
    }
}

if (!function_exists('ratib_public_marketing_home_url')) {
    /**
     * Marketing home with ?v= build marker (bypasses LiteSpeed stale full-page cache).
     *
     * @param array<string, string|int|float> $query
     */
    function ratib_public_marketing_home_url(string $baseUrl = '', array $query = [], string $hash = ''): string
    {
        if ($baseUrl === '') {
            $baseUrl = ratib_public_site_base_url();
        }
        $url = rtrim($baseUrl, '/') . '/pages/home.php';
        $build = ratib_public_build_marker();
        if ($build !== '') {
            $query['v'] = $build;
        }
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        if ($hash !== '') {
            $url .= ($hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#'));
        }

        return $url;
    }
}
