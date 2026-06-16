<?php
/**
 * Public marketing page density — focused (default) vs full detail.
 *
 * focused: hero + essentials + pricing; deep sections hidden until expanded.
 * full:    all sections visible (?density=full).
 */
declare(strict_types=1);

if (!function_exists('rateb_public_marketing_density')) {
    function rateb_public_marketing_density(): string
    {
        $param = isset($_GET['density']) ? strtolower(trim((string) $_GET['density'])) : '';
        if (in_array($param, ['full', 'expanded', 'all'], true)) {
            return 'full';
        }
        if (in_array($param, ['focused', 'compact', 'summary', 'short'], true)) {
            return 'focused';
        }

        return 'focused';
    }
}

if (!function_exists('rateb_public_marketing_is_focused')) {
    function rateb_public_marketing_is_focused(): bool
    {
        return rateb_public_marketing_density() === 'focused';
    }
}

if (!function_exists('rateb_public_marketing_density_body_class')) {
    function rateb_public_marketing_density_body_class(): string
    {
        return rateb_public_marketing_is_focused()
            ? 'rateb-marketing--focused'
            : 'rateb-marketing--full';
    }
}

if (!function_exists('rateb_public_marketing_should_render_deep')) {
    /** When false, PHP skips deep blocks (reliable vs CSS cache). */
    function rateb_public_marketing_should_render_deep(): bool
    {
        return !rateb_public_marketing_is_focused();
    }
}

if (!function_exists('rateb_public_marketing_toggle_density_url')) {
    function rateb_public_marketing_toggle_density_url(bool $wantFull): string
    {
        $qs = $_GET;
        if ($wantFull) {
            $qs['density'] = 'full';
        } else {
            unset($qs['density']);
        }
        $req = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($req, PHP_URL_PATH) ?: '/');
        $hash = (string) (parse_url($req, PHP_URL_FRAGMENT) ?: '');
        $url = $path;
        if ($qs !== []) {
            $url .= '?' . http_build_query($qs);
        }
        if ($hash !== '') {
            $url .= '#' . ltrim($hash, '#');
        }

        return $url;
    }
}

if (!function_exists('rateb_public_marketing_home_anchor')) {
    /**
     * In-page anchor on marketing home — adds ?density=full when sections are collapsed.
     */
    function rateb_public_marketing_home_anchor(string $hash): string
    {
        $hash = $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
        if (!rateb_public_marketing_is_focused()) {
            if (!empty($GLOBALS['rateb_public_nav_on_marketing_home'])) {
                return $hash;
            }
            if (function_exists('rateb_public_marketing_home_url')) {
                return rateb_public_marketing_home_url('', [], $hash);
            }

            return $hash;
        }
        $qs = $_GET;
        $qs['density'] = 'full';

        return '?' . http_build_query($qs) . $hash;
    }
}

if (!function_exists('rateb_public_profile_nav_prefix')) {
    /** Profile page nav prefix — adds ?density=full when sections are server-collapsed. */
    function rateb_public_profile_nav_prefix(string $baseUrl): string
    {
        $root = rtrim($baseUrl, '/') . '/profile/';
        if (rateb_public_marketing_is_focused()) {
            return $root . '?density=full';
        }

        return $root;
    }
}

if (!function_exists('rateb_marketing_emit_focused_rescue_css')) {
    /** Inline hide rules — works even if home-marketing-focused.css is cached or missing. */
    function rateb_marketing_emit_focused_rescue_css(): void
    {
        if (!rateb_public_marketing_is_focused()) {
            return;
        }
        echo '<style id="rateb-marketing-focused-rescue">'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) [data-rateb-marketing-depth="deep"],'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) .rateb-marketing-deep-wrap,'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) .rateb-hero__visual,'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) .rateb-hero__video-band,'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) .rateb-hero__program-strip,'
            . 'body.rateb-marketing--focused:not(.rateb-marketing--expanded) #program-previews,'
            . '{display:none!important}</style>';
    }
}
