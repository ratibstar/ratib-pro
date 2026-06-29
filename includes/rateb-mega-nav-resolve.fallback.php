<?php
/**
 * Emergency nav URL resolver when rateb-mega-nav-resolve.php is missing on server (partial deploy).
 */
declare(strict_types=1);

if (!function_exists('rateb_mega_nav_is_profile_context')) {
    function rateb_mega_nav_is_profile_context(string $navPrefix): bool
    {
        if ($navPrefix === '') {
            return false;
        }
        $path = (string) (parse_url($navPrefix, PHP_URL_PATH) ?: $navPrefix);

        return (bool) preg_match('#/profile/?$#i', rtrim($path, '/'));
    }
}

if (!function_exists('rateb_mega_nav_use_relative_home_anchors')) {
    function rateb_mega_nav_use_relative_home_anchors(string $navPrefix): bool
    {
        return !empty($GLOBALS['rateb_public_nav_on_marketing_home'])
            && $navPrefix === ''
            && !rateb_mega_nav_is_profile_context($navPrefix);
    }
}

if (!function_exists('rateb_mega_nav_marketing_home')) {
    function rateb_mega_nav_marketing_home(string $baseUrl): string
    {
        if (function_exists('rateb_public_marketing_home_url')) {
            return rateb_public_marketing_home_url($baseUrl);
        }

        return rtrim($baseUrl, '/') . '/pages/home.php';
    }
}

if (!function_exists('rateb_mega_nav_register_href')) {
    function rateb_mega_nav_register_href(string $baseUrl, string $navPrefix = ''): string
    {
        if (function_exists('rateb_public_marketing_home_register_url')) {
            return rateb_public_marketing_home_register_url($baseUrl, 'professional', 1);
        }

        return rtrim($baseUrl, '/') . '/pages/register-agency?plan=gold&years=1';
    }
}

if (!function_exists('rateb_mega_nav_pricing_href')) {
    function rateb_mega_nav_pricing_href(string $baseUrl, string $navPrefix = ''): string
    {
        if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
            return '#programs';
        }
        if (function_exists('rateb_public_marketing_home_url')) {
            return rateb_public_marketing_home_url($baseUrl, [], '#programs');
        }
        if ($navPrefix !== '' && !rateb_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . '#programs';
        }

        return rateb_mega_nav_marketing_home($baseUrl) . '#programs';
    }
}

if (!function_exists('rateb_public_nav_tour_href')) {
    function rateb_public_nav_tour_href(string $baseUrl, string $navPrefix, string $tourHash): string
    {
        $tourHash = $tourHash !== '' && $tourHash[0] === '#' ? $tourHash : '#' . ltrim($tourHash, '#');
        if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
            return $tourHash;
        }
        if (rateb_mega_nav_is_profile_context($navPrefix)) {
            return rateb_mega_nav_marketing_home($baseUrl) . $tourHash;
        }
        if ($navPrefix !== '') {
            return rtrim($navPrefix, '/') . $tourHash;
        }

        return rateb_mega_nav_marketing_home($baseUrl) . $tourHash;
    }
}

if (!function_exists('rateb_mega_nav_resolve_href')) {
    function rateb_mega_nav_resolve_href(string $hrefKey, string $baseUrl, string $navPrefix = ''): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $home = rateb_mega_nav_marketing_home($baseUrl);

        if (in_array($hrefKey, ['programs', 'pricing'], true)) {
            return rateb_mega_nav_pricing_href($baseUrl, $navPrefix);
        }
        if ($hrefKey === 'register') {
            return rateb_mega_nav_register_href($baseUrl, $navPrefix);
        }
        if ($hrefKey === 'enterprise_demo') {
            if (function_exists('rateb_enterprise_mailto')) {
                return rateb_enterprise_mailto('RATEB — Request Enterprise Demo');
            }

            return 'mailto:solutions@rateb.sa?subject=' . rawurlencode('RATEB — Request Enterprise Demo');
        }

        if (rateb_mega_nav_is_profile_context($navPrefix)) {
            $root = rtrim($navPrefix, '/') . '/';
            $map = [
                'platform' => '#platform-overview',
                'features' => '#what-is-rateb',
                'agencies' => '#partners',
                'contact' => '#contact-cta',
                'help_center' => '#contact-cta',
                'domains' => '#corridors',
                'company_profile' => '#company-profile',
                'about' => '#company-profile',
            ];
            if (isset($map[$hrefKey])) {
                $hash = $map[$hrefKey];
                $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');

                return rtrim($root, '/') . '/' . $hash;
            }
        }

        $hashMap = [
            'platform' => '#platform',
            'features' => '#features',
            'agencies' => '#agencies',
            'contact' => '#contact',
            'domains' => '#domains',
            'company_profile' => '/profile/#company-profile',
            'about' => '/profile/#company-profile',
            'help_center' => '#contact',
            'customer_portal' => '/pages/customer-portal.php',
        ];
        if (isset($hashMap[$hrefKey])) {
            $tail = $hashMap[$hrefKey];
            if ($tail[0] === '#') {
                if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
                    return $tail;
                }

                return ($navPrefix !== '' ? rtrim($navPrefix, '/') : $home) . $tail;
            }

            return $baseUrl . $tail;
        }

        return $home;
    }
}
