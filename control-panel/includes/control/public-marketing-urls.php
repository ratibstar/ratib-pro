<?php
/**
 * Canonical public marketing URLs for control panel (registration, homepage, pricing).
 */
declare(strict_types=1);

if (!function_exists('control_panel_public_site_root')) {
    function control_panel_public_site_root(): string
    {
        if (defined('SITE_URL') && is_string(SITE_URL) && SITE_URL !== '') {
            return rtrim(SITE_URL, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }
        $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $basePath = preg_replace('#/control-panel(?:/pages)?/.*$#', '', $path) ?: '';
        $basePath = preg_replace('#/pages/.*$#', '', $basePath) ?: $basePath;

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}

if (!function_exists('control_panel_load_public_url_helpers')) {
    function control_panel_load_public_url_helpers(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $root = dirname(__DIR__, 3);
        $file = $root . '/includes/rateb-public-base-url.php';
        if (is_file($file)) {
            require_once $file;
        }
        $loaded = true;
    }
}

if (!function_exists('control_panel_public_cms_rev')) {
    function control_panel_public_cms_rev(?mysqli $ctrl = null): string
    {
        if (!$ctrl instanceof mysqli) {
            $ctrl = $GLOBALS['control_conn'] ?? null;
        }
        if (!$ctrl instanceof mysqli) {
            return '';
        }
        try {
            $revRes = $ctrl->query('SELECT COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS rev FROM rateb_site_content');
            if ($revRes && ($revRow = $revRes->fetch_assoc())) {
                $rev = (string) ($revRow['rev'] ?? '0');
                if ($rev !== '' && $rev !== '0') {
                    return $rev;
                }
            }
        } catch (Throwable $e) {
            /* ignore */
        }

        return '';
    }
}

if (!function_exists('control_panel_registration_country_query')) {
    /** @return array<string, string> */
    function control_panel_registration_country_query(): array
    {
        $extra = [];
        $code = strtoupper(trim((string) ($_SESSION['country_code'] ?? (defined('COUNTRY_CODE') ? COUNTRY_CODE : ''))));
        $name = trim((string) ($_SESSION['country_name'] ?? (defined('COUNTRY_NAME') ? COUNTRY_NAME : '')));
        if ($code !== '') {
            $extra['country_code'] = $code;
        } elseif ($name !== '') {
            $extra['country_name'] = $name;
        }

        return $extra;
    }
}

if (!function_exists('control_panel_registration_page_url')) {
    /**
     * Standalone register-agency page URL (Gold/Platinum pricing + checkout form).
     *
     * @param array<string, string|int|float> $extra
     */
    function control_panel_registration_page_url(
        ?mysqli $ctrl = null,
        string $plan = 'professional',
        int $years = 1,
        array $extra = []
    ): string {
        control_panel_load_public_url_helpers();
        $root = control_panel_public_site_root();
        $query = array_merge(control_panel_registration_country_query(), $extra);
        $rev = control_panel_public_cms_rev($ctrl);
        if ($rev !== '') {
            $query['cms_rev'] = $rev;
        }
        if (function_exists('rateb_public_agency_register_url')) {
            return rateb_public_agency_register_url($root, $plan, $years, $query);
        }
        $legacyPlan = function_exists('rateb_legacy_pro_plan_slug') ? rateb_legacy_pro_plan_slug($plan) : $plan;

        return rtrim($root, '/') . '/pages/register-agency?plan=' . rawurlencode($legacyPlan) . '&years=' . (int) $years
            . ($query !== [] ? '&' . http_build_query($query) : '');
    }
}

if (!function_exists('control_panel_marketing_url_append_query')) {
    /**
     * Append query params to a marketing URL that may already include ?v=…
     *
     * @param array<string, string|int|float> $params
     */
    function control_panel_marketing_url_append_query(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }
        $hash = '';
        if (($hashPos = strpos($url, '#')) !== false) {
            $hash = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }
        $sep = (strpos($url, '?') !== false) ? '&' : '?';

        return $url . $sep . http_build_query($params) . $hash;
    }
}

if (!function_exists('control_panel_pricing_page_url')) {
    /**
     * Marketing homepage pricing cards (#programs), optional plan pre-select in query.
     */
    function control_panel_pricing_page_url(
        ?mysqli $ctrl = null,
        string $plan = 'gold',
        int $years = 1
    ): string {
        $query = array_merge(
            control_panel_registration_country_query(),
            [
                'plan' => $plan,
                'years' => $years,
            ]
        );

        return control_panel_marketing_url_append_query(
            control_panel_public_marketing_home_url($ctrl, 'programs'),
            $query
        );
    }
}

if (!function_exists('control_panel_public_marketing_home_url')) {
    /**
     * View live marketing site (optional hash e.g. programs for pricing tables).
     */
    function control_panel_public_marketing_home_url(?mysqli $ctrl = null, string $hash = ''): string
    {
        control_panel_load_public_url_helpers();
        $root = control_panel_public_site_root();
        $query = [];
        $rev = control_panel_public_cms_rev($ctrl);
        if ($rev !== '') {
            $query['cms_rev'] = $rev;
        }
        if (function_exists('rateb_public_marketing_home_url')) {
            return rateb_public_marketing_home_url($root, $query, $hash);
        }
        $url = rtrim($root, '/') . '/pages/home.php';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        if ($hash !== '') {
            $url .= ($hash[0] === '#' ? $hash : '#' . ltrim($hash, '#'));
        }

        return $url;
    }
}
