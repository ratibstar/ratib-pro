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
        if ($marker === '') {
            $marker = 'about-enterprise-20260518-profile-same-tab-v6';
        }

        return $marker;
    }
}

if (!function_exists('ratib_public_build_marker_is_valid')) {
    /** Accept current canonical + one previous deploy marker (avoids redirect/cache churn). */
    function ratib_public_build_marker_is_valid(string $req): bool
    {
        if ($req === '') {
            return false;
        }
        $canonical = ratib_public_build_marker();
        $legacy = [
            'about-enterprise-20260518-nav-resolve-v7',
            'about-enterprise-20260518-profile-same-tab-v6',
        ];

        return $req === $canonical || in_array($req, $legacy, true);
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

if (!function_exists('ratib_public_marketing_home_register_url')) {
    /**
     * Canonical marketing home + registration deep link (single public URL for Gold signup).
     *
     * @param array<string, string|int|float> $extra
     */
    function ratib_public_marketing_home_register_url(
        string $baseUrl = '',
        string $plan = 'gold',
        int $years = 1,
        array $extra = []
    ): string {
        $query = array_merge(
            [
                'open' => 'register',
                'plan' => $plan,
                'years' => $years,
            ],
            $extra
        );
        if (function_exists('ratib_site_content_revision_token')) {
            $rev = ratib_site_content_revision_token();
            if ($rev !== '') {
                $query['cms_rev'] = $rev;
            }
        }

        return ratib_public_marketing_home_url($baseUrl, $query, '#register');
    }
}
