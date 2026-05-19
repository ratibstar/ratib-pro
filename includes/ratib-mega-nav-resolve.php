<?php
/**
 * Resolves ratib_mega_nav_config() href_key values to absolute/relative URLs.
 * Profile (/profile/) and marketing home use different section anchors — keep maps in sync
 * with pages/home.php ids and includes/ratib-about-sections.php ids.
 *
 * @param string $hrefKey   Key from mega nav config (e.g. marketplace_domains, contact).
 * @param string $baseUrl   Site root URL without trailing slash.
 * @param string $navPrefix Optional prefix for home hash links (e.g. full home.php URL for partner pages).
 */
if (!function_exists('ratib_mega_nav_is_profile_context')) {
    function ratib_mega_nav_is_profile_context(string $navPrefix): bool
    {
        if ($navPrefix === '') {
            return false;
        }
        $path = (string) (parse_url($navPrefix, PHP_URL_PATH) ?: $navPrefix);

        return (bool) preg_match('#/profile/?$#i', rtrim($path, '/'));
    }
}

if (!function_exists('ratib_mega_nav_use_relative_home_anchors')) {
    /** True when rendering nav on pages/home.php (same-document section jumps). */
    function ratib_mega_nav_use_relative_home_anchors(string $navPrefix): bool
    {
        return !empty($GLOBALS['ratib_public_nav_on_marketing_home'])
            && $navPrefix === ''
            && !ratib_mega_nav_is_profile_context($navPrefix);
    }
}

if (!function_exists('ratib_mega_nav_profile_root')) {
    function ratib_mega_nav_profile_root(string $baseUrl, string $navPrefix = ''): string
    {
        if ($navPrefix !== '' && ratib_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . '/';
        }

        return rtrim($baseUrl, '/') . '/profile/';
    }
}

if (!function_exists('ratib_mega_nav_profile_hash')) {
    function ratib_mega_nav_profile_hash(string $profileRoot, string $hash): string
    {
        $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
        $root = rtrim($profileRoot, '/');
        if (strpos($root, '?') !== false) {
            return $root . $hash;
        }

        return $root . '/' . $hash;
    }
}

if (!function_exists('ratib_mega_nav_marketing_home')) {
    function ratib_mega_nav_marketing_home(string $baseUrl): string
    {
        if (function_exists('ratib_public_marketing_home_url')) {
            return ratib_public_marketing_home_url($baseUrl);
        }

        return rtrim($baseUrl, '/') . '/pages/home.php';
    }
}

if (!function_exists('ratib_mega_nav_register_href')) {
    /** Registration checkout form — marketing home with ?open=register (not #programs). */
    function ratib_mega_nav_register_href(string $baseUrl, string $navPrefix = ''): string
    {
        if (ratib_mega_nav_use_relative_home_anchors($navPrefix)) {
            return '?open=register&plan=gold&years=1#register';
        }
        if (function_exists('ratib_public_marketing_home_register_url')) {
            return ratib_public_marketing_home_register_url($baseUrl, 'gold', 1);
        }
        if ($navPrefix !== '' && !ratib_mega_nav_is_profile_context($navPrefix)) {
            $sep = (strpos($navPrefix, '?') !== false) ? '&' : '?';

            return rtrim($navPrefix, '/') . $sep . 'open=register&plan=gold&years=1#register';
        }

        $home = ratib_mega_nav_marketing_home($baseUrl);
        $sep = (strpos($home, '?') !== false) ? '&' : '?';

        return $home . $sep . 'open=register&plan=gold&years=1#register';
    }
}

if (!function_exists('ratib_mega_nav_pricing_href')) {
    /** Gold / Platinum price cards — always marketing home #programs (not profile #finance). */
    function ratib_mega_nav_pricing_href(string $baseUrl, string $navPrefix = ''): string
    {
        if (ratib_mega_nav_use_relative_home_anchors($navPrefix)) {
            return '#programs';
        }
        if (function_exists('ratib_public_marketing_home_url')) {
            return ratib_public_marketing_home_url($baseUrl, [], '#programs');
        }
        if ($navPrefix !== '' && !ratib_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . '#programs';
        }

        return ratib_mega_nav_marketing_home($baseUrl) . '#programs';
    }
}

if (!function_exists('ratib_mega_nav_home_hash')) {
    function ratib_mega_nav_home_hash(string $baseUrl, string $navPrefix, string $hash): string
    {
        $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
        if (ratib_mega_nav_use_relative_home_anchors($navPrefix)) {
            if (function_exists('ratib_public_marketing_home_anchor')) {
                return ratib_public_marketing_home_anchor($hash);
            }

            return $hash;
        }
        if ($navPrefix !== '' && !ratib_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . $hash;
        }

        return ratib_mega_nav_marketing_home($baseUrl) . $hash;
    }
}

if (!function_exists('ratib_public_nav_tour_href')) {
    /** Product tour / video band — on profile opens marketing home at the tour anchor. */
    function ratib_public_nav_tour_href(string $baseUrl, string $navPrefix, string $tourHash): string
    {
        $tourHash = $tourHash !== '' && $tourHash[0] === '#' ? $tourHash : '#' . ltrim($tourHash, '#');
        if (ratib_mega_nav_use_relative_home_anchors($navPrefix)) {
            if (function_exists('ratib_public_marketing_home_anchor')) {
                return ratib_public_marketing_home_anchor($tourHash);
            }

            return $tourHash;
        }
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

        if (in_array($hrefKey, ['programs', 'pricing'], true)) {
            return ratib_mega_nav_pricing_href($baseUrl, $navPrefix);
        }
        if ($hrefKey === 'register') {
            return ratib_mega_nav_register_href($baseUrl, $navPrefix);
        }

        switch ($hrefKey) {
            case 'customer_portal':
                return $baseUrl . '/pages/customer-portal.php';
            case 'help_center':
                // Public marketing — in-app help-center.php requires login (Ratib Pro).
                if (ratib_mega_nav_is_profile_context($navPrefix)) {
                    return ratib_mega_nav_profile_hash(
                        ratib_mega_nav_profile_root($baseUrl, $navPrefix),
                        '#contact-cta'
                    );
                }

                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#contact');
            case 'company_profile':
            case 'about':
                return $baseUrl . '/profile/#company-profile';
            case 'security_compliance':
                return $baseUrl . '/security-compliance/';
            case 'architecture':
                return $baseUrl . '/architecture/';
            case 'procurement_legal':
                return $baseUrl . '/procurement-legal/';
            case 'marketplace':
                return $baseUrl . '/pages/client/domains.php?catalog=1';
            case 'infra_status':
                return $baseUrl . '/pages/client/services.php';
        }

        if (ratib_mega_nav_is_profile_context($navPrefix)) {
            $profileRoot = ratib_mega_nav_profile_root($baseUrl, $navPrefix);
            $onProfile = [
                'platform' => '#platform-overview',
                'platform_overview' => '#platform-overview',
                'features' => '#what-is-ratib',
                'capabilities' => '#what-is-ratib',
                'product' => '#what-is-ratib',
                'agencies' => '#partners',
                'partners' => '#partners',
                'contact' => '#contact-cta',
                'solutions' => '#what-is-ratib',
                'what_is_ratib' => '#what-is-ratib',
                'operational' => '#operations',
                'operations' => '#operations',
                'api' => '#architecture',
                'program_previews' => '#platform-overview',
                'marketplace_domains' => '#corridors',
                'domain_search' => '#corridors',
                'domains' => '#corridors',
                'tracking' => '#telemetry',
                'telemetry' => '#telemetry',
                'governance' => '#governance',
                'trust' => '#trust',
                'operational_proof' => '#government-oversight',
                'government_oversight' => '#government-oversight',
                'how_it_works' => '#operations',
                'company_profile' => '#company-profile',
            ];
            if (isset($onProfile[$hrefKey])) {
                return ratib_mega_nav_profile_hash($profileRoot, $onProfile[$hrefKey]);
            }
        }

        switch ($hrefKey) {
            case 'marketplace_domains':
            case 'domain_search':
            case 'domains':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#domains');
            case 'contact':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#contact');
            case 'solutions':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#solutions');
            case 'features':
            case 'product':
            case 'capabilities':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#features');
            case 'program_previews':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#program-previews');
            case 'operational':
            case 'operations':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#operational');
            case 'api':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#api');
            case 'agencies':
            case 'partners':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#agencies');
            case 'platform':
            case 'platform_overview':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#platform');
            case 'tracking':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#tracking');
            case 'how_it_works':
                return ratib_mega_nav_home_hash($baseUrl, $navPrefix, '#how-it-works');
            default:
                return ratib_mega_nav_marketing_home($baseUrl);
        }
    }
}
