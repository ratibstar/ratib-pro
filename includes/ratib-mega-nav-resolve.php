<?php
/**
 * Resolves ratib_mega_nav_config() href_key values to absolute/relative URLs.
 *
 * @param string $hrefKey   Key from mega nav config (e.g. marketplace_domains, contact).
 * @param string $baseUrl   Site root URL without trailing slash.
 * @param string $navPrefix Optional prefix for home hash links (e.g. full home.php URL for partner pages).
 */
if (!function_exists('ratib_mega_nav_resolve_href')) {
function ratib_mega_nav_resolve_href(string $hrefKey, string $baseUrl, string $navPrefix = ''): string
{
    $baseUrl = rtrim($baseUrl, '/');
    $home = $baseUrl . '/pages/home.php';
    $clientDomains = $baseUrl . '/pages/client/domains.php?catalog=1';
    $clientServices = $baseUrl . '/pages/client/services.php';
    switch ($hrefKey) {
        case 'marketplace':
            return $clientDomains;
        case 'marketplace_domains':
            return $navPrefix !== '' ? $navPrefix . '#domains' : $home . '#domains';
        case 'infra_status':
            return $clientServices;
        case 'domain_search':
            return $navPrefix !== '' ? $navPrefix . '#domains' : $home . '#domains';
        case 'contact':
            return $navPrefix !== '' ? $navPrefix . '#contact' : $home . '#contact';
        case 'solutions':
            return $navPrefix !== '' ? $navPrefix . '#solutions' : $home . '#solutions';
        case 'programs':
            return $navPrefix !== '' ? $navPrefix . '#programs' : $home . '#programs';
        case 'features':
            return $navPrefix !== '' ? $navPrefix . '#features' : $home . '#features';
        case 'program_previews':
            return $navPrefix !== '' ? $navPrefix . '#program-previews' : $home . '#program-previews';
        case 'operational':
            return $navPrefix !== '' ? $navPrefix . '#operational' : $home . '#operational';
        case 'api':
            return $navPrefix !== '' ? $navPrefix . '#api' : $home . '#api';
        case 'agencies':
            return $navPrefix !== '' ? $navPrefix . '#agencies' : $home . '#agencies';
        case 'platform':
            return $navPrefix !== '' ? $navPrefix . '#platform' : $home . '#platform';
        case 'register':
            return $navPrefix !== '' ? $navPrefix . '#register' : $home . '#register';
        case 'customer_portal':
            return $baseUrl . '/pages/customer-portal.php';
        case 'about':
        case 'company_profile':
            return $baseUrl . '/profile/';
        default:
            return $home;
    }
}
}
