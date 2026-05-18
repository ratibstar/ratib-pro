<?php
/**
 * Emergency nav URL resolver when ratib-mega-nav-resolve.php is missing on server (partial deploy).
 */
declare(strict_types=1);

if (!function_exists('ratib_mega_nav_is_profile_context')) {
    function ratib_mega_nav_is_profile_context(string $navPrefix): bool
    {
        return $navPrefix !== '' && preg_match('#/profile/?$#i', rtrim($navPrefix, '/')) === 1;
    }
}

if (!function_exists('ratib_mega_nav_marketing_home')) {
    function ratib_mega_nav_marketing_home(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/pages/home.php';
    }
}

if (!function_exists('ratib_public_nav_tour_href')) {
    function ratib_public_nav_tour_href(string $baseUrl, string $navPrefix, string $tourHash): string
    {
        $tourHash = $tourHash !== '' && $tourHash[0] === '#' ? $tourHash : '#' . ltrim($tourHash, '#');
        if (ratib_mega_nav_is_profile_context($navPrefix)) {
            return ratib_mega_nav_marketing_home($baseUrl) . $tourHash;
        }
        if ($navPrefix !== '') {
            return rtrim($navPrefix, '/') . $tourHash;
        }

        return ratib_mega_nav_marketing_home($baseUrl) . $tourHash;
    }
}

if (!function_exists('ratib_mega_nav_resolve_href')) {
    function ratib_mega_nav_resolve_href(string $hrefKey, string $baseUrl, string $navPrefix = ''): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $home = ratib_mega_nav_marketing_home($baseUrl);

        if (ratib_mega_nav_is_profile_context($navPrefix)) {
            $root = rtrim($navPrefix, '/');
            $map = [
                'platform' => '#platform-overview',
                'features' => '#what-is-ratib',
                'programs' => '#finance',
                'agencies' => '#partners',
                'contact' => '#contact-cta',
                'domains' => '#corridors',
                'company_profile' => '#company-profile',
                'about' => '#company-profile',
            ];
            if (isset($map[$hrefKey])) {
                return $root . $map[$hrefKey];
            }
        }

        $hashMap = [
            'platform' => '#platform',
            'features' => '#features',
            'programs' => '#programs',
            'agencies' => '#agencies',
            'contact' => '#contact',
            'domains' => '#domains',
            'company_profile' => '/profile/#company-profile',
            'about' => '/profile/#company-profile',
            'help_center' => '/pages/help-center.php',
            'customer_portal' => '/pages/customer-portal.php',
        ];
        if (isset($hashMap[$hrefKey])) {
            $tail = $hashMap[$hrefKey];
            if ($tail[0] === '#') {
                return ($navPrefix !== '' ? rtrim($navPrefix, '/') : $home) . $tail;
            }

            return $baseUrl . $tail;
        }

        return $home;
    }
}
