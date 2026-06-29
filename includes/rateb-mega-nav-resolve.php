<?php
/**
 * Resolves rateb_mega_nav_config() href_key values to absolute/relative URLs.
 * Profile (/profile/) and marketing home use different section anchors — keep maps in sync
 * with pages/home.php ids and includes/rateb-about-sections.php ids.
 *
 * @param string $hrefKey   Key from mega nav config (e.g. marketplace_domains, contact).
 * @param string $baseUrl   Site root URL without trailing slash.
 * @param string $navPrefix Optional prefix for home hash links (e.g. full home.php URL for partner pages).
 */
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
    /** True when rendering nav on pages/home.php (same-document section jumps). */
    function rateb_mega_nav_use_relative_home_anchors(string $navPrefix): bool
    {
        return !empty($GLOBALS['rateb_public_nav_on_marketing_home'])
            && $navPrefix === ''
            && !rateb_mega_nav_is_profile_context($navPrefix);
    }
}

if (!function_exists('rateb_mega_nav_profile_root')) {
    function rateb_mega_nav_profile_root(string $baseUrl, string $navPrefix = ''): string
    {
        if ($navPrefix !== '' && rateb_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . '/';
        }

        return rtrim($baseUrl, '/') . '/profile/';
    }
}

if (!function_exists('rateb_mega_nav_profile_hash')) {
    function rateb_mega_nav_profile_hash(string $profileRoot, string $hash): string
    {
        $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
        $root = rtrim($profileRoot, '/');
        if (strpos($root, '?') !== false) {
            return $root . $hash;
        }

        return $root . '/' . $hash;
    }
}

if (!function_exists('rateb_mega_nav_marketing_home')) {
    function rateb_mega_nav_marketing_home(string $baseUrl): string
    {
        if (function_exists('rateb_public_marketing_home_url')) {
            return rateb_public_marketing_home_url($baseUrl);
        }

        return rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/home');
    }
}

if (!function_exists('rateb_mega_nav_register_href')) {
    /** Registration checkout form — marketing home with ?open=register (not #programs). */
    function rateb_mega_nav_register_href(string $baseUrl, string $navPrefix = ''): string
    {
        if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
            return '?open=register&plan=professional#register';
        }
        if (function_exists('rateb_public_marketing_home_register_url')) {
            return rateb_public_marketing_home_register_url($baseUrl, 'professional', 1);
        }
        if ($navPrefix !== '' && !rateb_mega_nav_is_profile_context($navPrefix)) {
            $sep = (strpos($navPrefix, '?') !== false) ? '&' : '?';

            return rtrim($navPrefix, '/') . $sep . 'open=register&plan=professional#register';
        }

        $home = rateb_mega_nav_marketing_home($baseUrl);
        $sep = (strpos($home, '?') !== false) ? '&' : '?';

        return $home . $sep . 'open=register&plan=professional#register';
    }
}

if (!function_exists('rateb_mega_nav_pricing_href')) {
    /** Gold / Platinum price cards — always marketing home #programs (not profile #finance). */
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

if (!function_exists('rateb_mega_nav_home_hash')) {
    function rateb_mega_nav_home_hash(string $baseUrl, string $navPrefix, string $hash): string
    {
        $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
        if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
            if (function_exists('rateb_public_marketing_home_anchor')) {
                return rateb_public_marketing_home_anchor($hash);
            }

            return $hash;
        }
        if ($navPrefix !== '' && !rateb_mega_nav_is_profile_context($navPrefix)) {
            return rtrim($navPrefix, '/') . $hash;
        }

        return rateb_mega_nav_marketing_home($baseUrl) . $hash;
    }
}

if (!function_exists('rateb_public_nav_tour_href')) {
    /** Product tour / video band — on profile opens marketing home at the tour anchor. */
    function rateb_public_nav_tour_href(string $baseUrl, string $navPrefix, string $tourHash): string
    {
        $tourHash = $tourHash !== '' && $tourHash[0] === '#' ? $tourHash : '#' . ltrim($tourHash, '#');
        if (rateb_mega_nav_use_relative_home_anchors($navPrefix)) {
            if (function_exists('rateb_public_marketing_home_anchor')) {
                return rateb_public_marketing_home_anchor($tourHash);
            }

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

        if (in_array($hrefKey, ['programs', 'pricing'], true)) {
            return rateb_mega_nav_pricing_href($baseUrl, $navPrefix);
        }
        if ($hrefKey === 'register') {
            return rateb_mega_nav_register_href($baseUrl, $navPrefix);
        }

        switch ($hrefKey) {
            case 'customer_portal':
                return function_exists('rateb_public_page_url')
                    ? rateb_public_page_url($baseUrl, 'customer-portal.php')
                    : $baseUrl . '/pages/customer-portal';
            case 'help_center':
                // Public marketing — in-app help-center.php requires login (RATEB Pro).
                if (rateb_mega_nav_is_profile_context($navPrefix)) {
                    return rateb_mega_nav_profile_hash(
                        rateb_mega_nav_profile_root($baseUrl, $navPrefix),
                        '#contact-cta'
                    );
                }

                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#contact');
            case 'company_profile':
            case 'about':
                return $baseUrl . '/profile/#company-profile';
            case 'security_compliance':
                return $baseUrl . '/security-compliance/';
            case 'architecture':
                return $baseUrl . '/architecture/';
            case 'procurement_legal':
                return $baseUrl . '/procurement-legal/';
            case 'enterprise_trust':
                return $baseUrl . '/enterprise-trust/';
            case 'government_workforce':
            case 'government_operations':
                return $baseUrl . '/government-workforce-operations/';
            case 'enterprise_pack':
            case 'enterprise_documents':
                return $baseUrl . '/enterprise-pack/';
            case 'marketplace':
                return (function_exists('rateb_public_page_url')
                    ? rateb_public_page_url($baseUrl, 'client/domains.php')
                    : $baseUrl . '/pages/client/domains') . '?catalog=1';
            case 'infra_status':
                return function_exists('rateb_public_page_url')
                    ? rateb_public_page_url($baseUrl, 'client/services.php')
                    : $baseUrl . '/pages/client/services';
        }

        if (rateb_mega_nav_is_profile_context($navPrefix)) {
            $profileRoot = rateb_mega_nav_profile_root($baseUrl, $navPrefix);
            $onProfile = [
                'platform' => '#platform-overview',
                'platform_overview' => '#platform-overview',
                'features' => '#what-is-rateb',
                'capabilities' => '#what-is-rateb',
                'product' => '#what-is-rateb',
                'agencies' => '#partners',
                'partners' => '#partners',
                'contact' => '#contact-cta',
                'solutions' => '#what-is-rateb',
                'what_is_rateb' => '#what-is-rateb',
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
                return rateb_mega_nav_profile_hash($profileRoot, $onProfile[$hrefKey]);
            }
        }

        if ($hrefKey === 'enterprise_demo') {
            if (function_exists('rateb_enterprise_mailto')) {
                return rateb_enterprise_mailto('RATEB — Request Enterprise Demo');
            }

            return 'mailto:solutions@rateb.sa?subject=' . rawurlencode('RATEB — Request Enterprise Demo');
        }

        switch ($hrefKey) {
            case 'marketplace_domains':
            case 'domain_search':
            case 'domains':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#domains');
            case 'contact':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#contact');
            case 'solutions':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#solutions');
            case 'features':
            case 'product':
            case 'capabilities':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#features');
            case 'program_previews':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#program-previews');
            case 'operational':
            case 'operations':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#operational');
            case 'api':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#api');
            case 'agencies':
            case 'partners':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#agencies');
            case 'platform':
            case 'platform_overview':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#platform');
            case 'tracking':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#tracking');
            case 'how_it_works':
                return rateb_mega_nav_home_hash($baseUrl, $navPrefix, '#how-it-works');
            default:
                return rateb_mega_nav_marketing_home($baseUrl);
        }
    }
}
