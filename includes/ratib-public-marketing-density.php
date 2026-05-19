<?php
/**
 * Public marketing page density — focused (default) vs full detail.
 *
 * focused: hero + essentials + pricing; deep sections hidden until expanded.
 * full:    all sections visible (?density=full).
 */
declare(strict_types=1);

if (!function_exists('ratib_public_marketing_density')) {
    function ratib_public_marketing_density(): string
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

if (!function_exists('ratib_public_marketing_is_focused')) {
    function ratib_public_marketing_is_focused(): bool
    {
        return ratib_public_marketing_density() === 'focused';
    }
}

if (!function_exists('ratib_public_marketing_density_body_class')) {
    function ratib_public_marketing_density_body_class(): string
    {
        return ratib_public_marketing_is_focused()
            ? 'ratib-marketing--focused'
            : 'ratib-marketing--full';
    }
}

if (!function_exists('ratib_public_marketing_should_render_deep')) {
    /** When false, PHP skips deep blocks (reliable vs CSS cache). */
    function ratib_public_marketing_should_render_deep(): bool
    {
        return !ratib_public_marketing_is_focused();
    }
}

if (!function_exists('ratib_public_marketing_toggle_density_url')) {
    function ratib_public_marketing_toggle_density_url(bool $wantFull): string
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

if (!function_exists('ratib_public_profile_nav_prefix')) {
    /** Profile page nav prefix — adds ?density=full when sections are server-collapsed. */
    function ratib_public_profile_nav_prefix(string $baseUrl): string
    {
        $root = rtrim($baseUrl, '/') . '/profile/';
        if (ratib_public_marketing_is_focused()) {
            return $root . '?density=full';
        }

        return $root;
    }
}

if (!function_exists('ratib_marketing_emit_focused_rescue_css')) {
    /** Inline hide rules — works even if home-marketing-focused.css is cached or missing. */
    function ratib_marketing_emit_focused_rescue_css(): void
    {
        if (!ratib_public_marketing_is_focused()) {
            return;
        }
        echo '<style id="ratib-marketing-focused-rescue">'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) [data-ratib-marketing-depth="deep"],'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) .ratib-marketing-deep-wrap,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) .ratib-hero__visual,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) .ratib-hero__video-band,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) .ratib-hero__program-strip,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) #program-previews,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) #platform.ratib-trust--deep,'
            . 'body.ratib-marketing--focused:not(.ratib-marketing--expanded) .ratib-register-wrap'
            . '{display:none!important}</style>';
    }
}
