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
