<?php
/**
 * Extensionless page URLs — hide .php from browser-visible links.
 */
declare(strict_types=1);

if (!function_exists('ratib_clean_page_segment')) {
    /**
     * @param string $page e.g. dashboard.php, cases/cases-table.php, control/agencies.php
     */
    function ratib_clean_page_segment(string $page): string
    {
        $page = ltrim(str_replace('\\', '/', $page), '/');
        if ($page === '') {
            return '';
        }
        if (preg_match('/\.php$/i', $page)) {
            return (string) preg_replace('/\.php$/i', '', $page);
        }

        return $page;
    }
}

if (!function_exists('ratib_marketing_home_path')) {
    /** Canonical marketing home path (no .php). */
    function ratib_marketing_home_path(): string
    {
        return '/home';
    }
}

if (!function_exists('ratib_public_page_path')) {
    /**
     * Path under site root for a pages/*.php file (extensionless).
     *
     * @param string $page e.g. customer-portal.php, client/domains.php
     */
    function ratib_public_page_path(string $page): string
    {
        return '/pages/' . ratib_clean_page_segment($page);
    }
}

if (!function_exists('ratib_public_page_url')) {
    function ratib_public_page_url(string $baseUrl, string $page): string
    {
        return rtrim($baseUrl, '/') . ratib_public_page_path($page);
    }
}
