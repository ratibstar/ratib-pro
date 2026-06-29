<?php
/**
 * Site root URL for public marketing pages (never includes /profile or /pages/...).
 */
declare(strict_types=1);

if (!function_exists('rateb_public_site_base_url')) {
    function rateb_public_site_base_url(): string
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

if (!function_exists('rateb_public_build_marker')) {
    function rateb_public_build_marker(): string
    {
        static $marker = null;
        if ($marker !== null) {
            return $marker;
        }
        $path = dirname(__DIR__) . '/public/rateb-build.txt';
        $marker = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($marker === '') {
            $marker = 'about-enterprise-20260518-profile-same-tab-v6';
        }

        return $marker;
    }
}

if (!function_exists('rateb_public_build_marker_is_valid')) {
    /** Accept current canonical + one previous deploy marker (avoids redirect/cache churn). */
    function rateb_public_build_marker_is_valid(string $req): bool
    {
        if ($req === '') {
            return false;
        }
        $canonical = rateb_public_build_marker();
        $legacy = [
            'about-enterprise-20260518-nav-resolve-v7',
            'about-enterprise-20260518-profile-same-tab-v6',
            'rateb-cms-rebrand-sanitize-20260521',
            'rateb-public-brand-force-defaults-20260522',
            'rateb-lscache-bust-20260522b',
            'rateb-official-brand-v20260522',
            'rateb-official-brand-v20260522c',
            'rateb-enterprise-audit-pass-20260522',
        ];

        if ($req === $canonical || in_array($req, $legacy, true)) {
            return true;
        }

        return str_starts_with($req, 'rateb-');
    }
}

if (!function_exists('rateb_public_marketing_home_url')) {
    /**
     * Marketing home with ?v= build marker (bypasses LiteSpeed stale full-page cache).
     *
     * @param array<string, string|int|float> $query
     */
    function rateb_public_marketing_home_url(string $baseUrl = '', array $query = [], string $hash = ''): string
    {
        if ($baseUrl === '') {
            $baseUrl = rateb_public_site_base_url();
        }
        $url = rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/');
        $build = rateb_public_build_marker();
        if ($build !== '' && !isset($query['v'])) {
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

if (!function_exists('rateb_public_nav_marketing_home_prefix')) {
    /** Base marketing home URL with ?v= (no hash) — use as navPrefix on satellite pages. */
    function rateb_public_nav_marketing_home_prefix(string $baseUrl = ''): string
    {
        if (function_exists('rateb_public_marketing_home_url')) {
            return rateb_public_marketing_home_url($baseUrl);
        }
        if ($baseUrl === '') {
            $baseUrl = rateb_public_site_base_url();
        }

        return rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/');
    }
}

if (!function_exists('rateb_normalize_marketing_plan_slug')) {
    /** Map legacy Rateb Pro slugs (pro/gold/platinum) to ERP plan slugs. */
    function rateb_normalize_marketing_plan_slug(string $plan): string
    {
        $slug = strtolower(trim($plan));
        if ($slug === '') {
            return '';
        }
        $legacy = [
            'pro' => 'starter',
            'gold' => 'professional',
            'platinum' => 'enterprise',
        ];
        if (isset($legacy[$slug])) {
            $slug = $legacy[$slug];
        }

        return in_array($slug, ['starter', 'professional', 'enterprise'], true) ? $slug : '';
    }
}

if (!function_exists('rateb_public_marketing_home_register_url')) {
    /**
     * Canonical marketing home + registration deep link (single public URL for Gold signup).
     *
     * @param array<string, string|int|float> $extra
     */
    function rateb_public_marketing_home_register_url(
        string $baseUrl = '',
        string $plan = 'professional',
        int $years = 1,
        array $extra = []
    ): string {
        unset($years);
        if ($baseUrl === '') {
            $baseUrl = rateb_public_site_base_url();
        }
        $query = $extra;
        unset($query['open'], $query['years'], $query['cms_rev']);
        if (function_exists('rateb_normalize_marketing_plan_slug')) {
            $plan = rateb_normalize_marketing_plan_slug($plan);
        } else {
            $plan = strtolower(trim($plan));
        }
        if ($plan !== '') {
            $query['plan'] = $plan;
        }

        $url = rtrim($baseUrl, '/') . '/site/register';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

// Guard: stale rateb-home-public-nav-bootstrap.php assigned $GLOBALS before these were set.
if (!isset($ratebEnterpriseCalmCssQuery)) {
    $ratebEnterpriseCalmCssQuery = '';
}
if (!isset($ratebMegaNavJsQuery)) {
    $ratebMegaNavJsQuery = '';
}
if (!isset($ratebHomePublicCssQuery)) {
    $ratebHomePublicCssQuery = '';
}
if (!isset($ratebMegaNavCssQuery)) {
    $ratebMegaNavCssQuery = '';
}
if (!isset($ratebPublicNavBrandCssQuery)) {
    $ratebPublicNavBrandCssQuery = '';
}
