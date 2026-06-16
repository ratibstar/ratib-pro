<?php
declare(strict_types=1);

require_once __DIR__ . '/rateb-nav-asset-preflight.php';

/**
 * Shared prep for the public home chrome (top bar + sticky SVG nav): CMS flat keys, nav targets, asset bust tokens.
 * Requires $baseUrl (same computation as pages/home.php) before include when used outside home.php.
 *
 * SERVER CHECK (bootstrap updated): Around line 194 you should see "// Keep home working..." NOT require_once deploy-ensure.
 *
 * @see pages/home.php
 * @see includes/rateb-home-public-chrome-top.php
 */
if (!function_exists('rateb_site_content_home_flat')) {
    require_once __DIR__ . '/site-content.php';
}

if (!function_exists('rateb_public_site_base_url')) {
    require_once __DIR__ . '/rateb-public-base-url.php';
}
if (!isset($baseUrl) || !is_string($baseUrl) || $baseUrl === '') {
    $baseUrl = rateb_public_site_base_url();
} else {
    $basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?? '');
    if (preg_match('#/(profile|about)/?$#', $basePath)) {
        $baseUrl = rateb_public_site_base_url();
    }
}

// Bump via env RATEB_HOME_UI_REV on the server after upload, or edit the fallback string when deploying marquee/lightbox/CSS.
// Ensures ?v= on CSS/JS changes even when the host preserves old file mtimes (FTP / sync tools).
$ratebHomeUiRevRaw = getenv('RATEB_HOME_UI_REV');
$ratebHomeUiRev = ($ratebHomeUiRevRaw !== false && trim((string) $ratebHomeUiRevRaw) !== '')
    ? preg_replace('/[^a-zA-Z0-9._-]/', '', trim((string) $ratebHomeUiRevRaw))
    : '20260524-nav-ia-v5';
$GLOBALS['ratebHomeUiRev'] = $ratebHomeUiRev;
/** Proof token: ties asset URLs to pages/home.php deploy */
$ratebHomePhpPath = __DIR__ . '/../pages/home.php';
clearstatcache(true, $ratebHomePhpPath);
$ratebHomePhpMtime = (string) (int) (@filemtime($ratebHomePhpPath) ?: 0);
/** Append to CSS/JS ?v= when CDN/host preserves mtimes (set in env or config: RATEB_HOME_ASSET_EXTRA_BUST=manual-20260210). */
$ratebHomeAssetExtraBustRaw = getenv('RATEB_HOME_ASSET_EXTRA_BUST');
$ratebHomeAssetExtraQ = ($ratebHomeAssetExtraBustRaw !== false && trim((string) $ratebHomeAssetExtraBustRaw) !== '')
    ? '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', trim((string) $ratebHomeAssetExtraBustRaw))
    : '';
$ratebHome = rateb_site_content_home_flat(false);
$ratebRebrandSanitize = __DIR__ . '/rateb-site-content-rebrand-sanitize.php';
if (is_file($ratebRebrandSanitize)) {
    require_once $ratebRebrandSanitize;
}
if (function_exists('rateb_site_content_rebrand_sanitize_flat') && function_exists('rateb_site_content_defaults_home')) {
    $ratebHome = rateb_site_content_rebrand_sanitize_flat($ratebHome, rateb_site_content_defaults_home());
}
if (function_exists('rateb_site_content_home_ensure_header_nav_labels')) {
    rateb_site_content_home_ensure_header_nav_labels($ratebHome);
}
$ratebDbFingerprint = function_exists('rateb_site_content_db_fingerprint')
    ? rateb_site_content_db_fingerprint()
    : '';
// Top bar: one DB round-trip for all keys so phone/WA/nodes stay in sync (no mixed JSON vs row timing).
$ratebTopbarKeys = [
    'home.topbar.phone_display',
    'home.topbar.wa_label',
    'home.topbar.tls_label',
    'home.topbar.ops_line',
    'home.topbar.nodes_count',
    'home.topbar.nodes_suffix',
    'home.topbar.client_login',
];
if (function_exists('rateb_site_content_fetch_key_values') && function_exists('rateb_site_content_defaults_home')) {
    $ratebDef = rateb_site_content_defaults_home();
    $liveTopbar = rateb_site_content_fetch_key_values($ratebTopbarKeys);
    foreach ($ratebTopbarKeys as $ratebTbKey) {
        if (!array_key_exists($ratebTbKey, $ratebDef)) {
            continue;
        }
        $ratebFallback = $ratebHome[$ratebTbKey] ?? $ratebDef[$ratebTbKey];
        $ratebHome[$ratebTbKey] = array_key_exists($ratebTbKey, $liveTopbar)
            ? $liveTopbar[$ratebTbKey]
            : $ratebFallback;
    }
} elseif (function_exists('rateb_site_content_get') && function_exists('rateb_site_content_defaults_home')) {
    $ratebDef = rateb_site_content_defaults_home();
    foreach ($ratebTopbarKeys as $ratebTbKey) {
        if (!array_key_exists($ratebTbKey, $ratebDef)) {
            continue;
        }
        $ratebFallback = $ratebHome[$ratebTbKey] ?? $ratebDef[$ratebTbKey];
        $ratebHome[$ratebTbKey] = rateb_site_content_get($ratebTbKey, $ratebFallback);
    }
}
// Visible phone text = exactly what the CMS saved (WYSIWYG). tel:/wa.me use normalized digits below.
$ratebPhoneRaw = (string) ($ratebHome['home.topbar.phone_display'] ?? '');
$ratebPhoneDigits = function_exists('rateb_site_content_phone_digits_for_links')
    ? rateb_site_content_phone_digits_for_links($ratebPhoneRaw)
    : (preg_replace('/\D+/', '', $ratebPhoneRaw) ?: '966599863868');
$ratebTopbarOpsLine = trim((string) ($ratebHome['home.topbar.ops_line'] ?? ''));
$ratebTopbarNodesDigits = preg_replace('/\D/', '', (string) ($ratebHome['home.topbar.nodes_count'] ?? ''));
$ratebTopbarNodesNum = $ratebTopbarNodesDigits !== '' ? (int) $ratebTopbarNodesDigits : 0;
if ($ratebTopbarOpsLine === '' && $ratebTopbarNodesDigits !== '') {
    if ($ratebTopbarNodesNum > 999999 || strlen($ratebTopbarNodesDigits) > 6) {
        $ratebTopbarNodesNum = 0;
        $ratebTopbarNodesDigits = '';
    }
} elseif ($ratebTopbarOpsLine !== '') {
    $ratebTopbarNodesNum = 0;
    $ratebTopbarNodesDigits = '';
}
$ratebPricingStarterLines = rateb_site_content_home_nl_lines($ratebHome['home.pricing.starter.features'] ?? '');
$ratebPricingGoldLines = rateb_site_content_home_nl_lines($ratebHome['home.pricing.gold.features'] ?? '');
$ratebPricingPlatinumLines = rateb_site_content_home_nl_lines($ratebHome['home.pricing.platinum.features'] ?? '');
// Public site: show a program card only when the CMS slot has a real image/video URL or token (no caption-only placeholders).
$ratebProgSlotsOut = [];
if (function_exists('rateb_site_content_home_program_slots_from_flat')) {
    foreach (rateb_site_content_home_program_slots_from_flat($ratebHome) as $slot) {
        $stored = trim((string) ($slot['src'] ?? ''));
        if ($stored === '') {
            continue;
        }
        $imgSrc = rateb_site_content_asset_url($baseUrl, $stored, '', '');
        if (trim($imgSrc) === '') {
            continue;
        }
        $ratebProgSlotsOut[] = [
            'src' => $imgSrc,
            'alt' => (string) ($slot['alt'] ?? ''),
            'caption' => (string) ($slot['caption'] ?? ''),
        ];
    }
}

$ratebVideoSources = [];
$videoExists = false;
$videoSrcRel = '';
$videoUrl = '';
if (function_exists('rateb_site_content_home_video_src_strings_from_flat') && function_exists('rateb_site_content_home_resolve_video_display_url')) {
    foreach (rateb_site_content_home_video_src_strings_from_flat($ratebHome) as $vs) {
        $vs = trim((string) $vs);
        if ($vs === '') {
            continue;
        }
        if (str_starts_with($vs, 'scmedia:') && function_exists('rateb_site_content_media_filename_from_token') && function_exists('rateb_site_content_media_resolve_fs')) {
            $vf = rateb_site_content_media_filename_from_token($vs);
            if ($vf === '' || rateb_site_content_media_resolve_fs($vf) === null) {
                continue;
            }
        }
        $u = rateb_site_content_home_resolve_video_display_url($vs, $baseUrl);
        if ($u !== '') {
            $ratebVideoSources[] = [
                'url' => $u,
                'is_image' => function_exists('rateb_site_content_media_stored_is_image')
                    && rateb_site_content_media_stored_is_image($vs),
            ];
        }
    }
}
if ($ratebVideoSources !== []) {
    $videoExists = true;
    $videoSrcRel = (string) ($ratebVideoSources[0]['url'] ?? '');
    $videoUrl = $videoSrcRel;
}

$ratebVideoSlotsRawCheck = trim((string) ($ratebHome['home.video.slots_json'] ?? ''));
$ratebVideoClearedInCms = false;
if ($ratebVideoSlotsRawCheck !== '') {
    $ratebVideoSlotsDecoded = json_decode($ratebVideoSlotsRawCheck, true);
    $ratebVideoClearedInCms = is_array($ratebVideoSlotsDecoded) && count($ratebVideoSlotsDecoded) === 0;
}
// Hide the whole video band (heading + strip) when there is nothing to show and CMS did not ask for the empty-state hint.
$ratebShowHomeVideoBand = !empty($ratebVideoSources) || (!$videoExists && !$ratebVideoClearedInCms);
// Header link "video/pic" scroll target: walkthrough video when present, else screenshot strip, else platform section.
$ratebNavProductTourHref = '#platform';
if ($ratebShowHomeVideoBand) {
    $ratebNavProductTourHref = '#video';
} elseif (!empty($ratebProgSlotsOut)) {
    $ratebNavProductTourHref = '#program-previews';
}
$ratebNavProductTourLabel = trim((string) ($ratebHome['home.nav.tour'] ?? ''));
if ($ratebNavProductTourLabel === '') {
    $ratebNavProductTourLabel = trim((string) ($ratebHome['home.nav.product_tour'] ?? ''));
}
if ($ratebNavProductTourLabel === '') {
    $ratebNavProductTourLabel = 'Tour';
}

// Initialize before any $GLOBALS writes (prevents "Undefined variable" if order changes).
$ratebHomePublicCssQuery = '';
$ratebMegaNavCssQuery = '';
$ratebPublicNavBrandCssQuery = '';
$ratebEnterpriseCalmCssQuery = '';
$ratebMegaNavJsQuery = '';

$ratebHomeCssPath = __DIR__ . '/../css/pages/home-public.css';
clearstatcache(true, $ratebHomeCssPath);
$ratebHomeCssTs = (int) (@filemtime($ratebHomeCssPath) ?: time());
$ratebHomePublicCssQuery = $ratebHomeCssTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ;

$ratebMegaNavCssPath = __DIR__ . '/../css/pages/rateb-mega-nav.css';
clearstatcache(true, $ratebMegaNavCssPath);
$ratebMegaNavCssTs = (int) (@filemtime($ratebMegaNavCssPath) ?: time());
$ratebMegaNavCssQuery = $ratebMegaNavCssTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ;

$ratebPublicNavBrandCssPath = __DIR__ . '/../css/pages/rateb-public-nav-brand.css';
clearstatcache(true, $ratebPublicNavBrandCssPath);
$ratebPublicNavBrandCssTs = (int) (@filemtime($ratebPublicNavBrandCssPath) ?: time());
$ratebPublicNavBrandCssQuery = $ratebPublicNavBrandCssTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ;

$ratebEnterpriseCalmCssPath = __DIR__ . '/../css/pages/home-enterprise-calm.css';
clearstatcache(true, $ratebEnterpriseCalmCssPath);
$ratebEnterpriseCalmCssTs = (int) (@filemtime($ratebEnterpriseCalmCssPath) ?: time());
$ratebEnterpriseCalmCssQuery = $ratebEnterpriseCalmCssTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ;

$ratebMegaNavJsPath = __DIR__ . '/../js/pages/rateb-mega-nav.js';
clearstatcache(true, $ratebMegaNavJsPath);
$ratebMegaNavJsTs = (int) (@filemtime($ratebMegaNavJsPath) ?: time());
$ratebMegaNavJsQuery = $ratebMegaNavJsTs . '-' . $ratebHomeUiRev . '-' . $ratebHomePhpMtime . $ratebHomeAssetExtraQ;

/*
 * Bust browser/CDN caches when nav chrome changes even if RATEB_HOME_UI_REV is unchanged (common on FTP deploy).
 * Short hash from mtimes of nav markup, sync inline script, mega config, home-page.js.
 */
$ratebChromeNavPathsForBundle = [
    __DIR__ . '/rateb-home-public-nav-sync.php',
    __DIR__ . '/rateb-mega-nav-config.php',
    __DIR__ . '/rateb-home-public-chrome-top.php',
    __DIR__ . '/rateb-brand-full-title.php',
    __DIR__ . '/../css/pages/home-public.css',
    __DIR__ . '/../css/pages/rateb-public-nav-brand.css',
    __DIR__ . '/../css/pages/home-enterprise-calm.css',
    __DIR__ . '/../js/pages/home-page.js',
    __DIR__ . '/../js/pages/rateb-public-nav-ia-fix.js',
];
$ratebChromeBundleKey = '';
foreach ($ratebChromeNavPathsForBundle as $ratebChromeNavPath) {
    if (!is_readable($ratebChromeNavPath)) {
        continue;
    }
    clearstatcache(true, $ratebChromeNavPath);
    $ratebChromeBundleKey .= $ratebChromeNavPath . '=' . (string) (int) (@filemtime($ratebChromeNavPath) ?: 0) . '|';
}
$ratebChromeBundleHash = $ratebChromeBundleKey !== ''
    ? substr(hash('sha256', $ratebChromeBundleKey), 0, 12)
    : '0';
$ratebHomePublicCssQuery .= '-c' . $ratebChromeBundleHash;
$ratebBrandNavCssPath = __DIR__ . '/rateb-brand-full-title.php';
clearstatcache(true, $ratebBrandNavCssPath);
$ratebHomePublicCssQuery .= '-bn' . (int) (@filemtime($ratebBrandNavCssPath) ?: time());
$ratebMegaNavCssQuery .= '-c' . $ratebChromeBundleHash;
$ratebPublicNavBrandCssQuery .= '-c' . $ratebChromeBundleHash;
$ratebEnterpriseCalmCssQuery .= '-c' . $ratebChromeBundleHash;
$ratebMegaNavJsQuery .= '-c' . $ratebChromeBundleHash;

$GLOBALS['ratebHomePublicCssQuery'] = $ratebHomePublicCssQuery;
$GLOBALS['ratebMegaNavCssQuery'] = $ratebMegaNavCssQuery;
$GLOBALS['ratebPublicNavBrandCssQuery'] = $ratebPublicNavBrandCssQuery;
$GLOBALS['ratebEnterpriseCalmCssQuery'] = $ratebEnterpriseCalmCssQuery;
$GLOBALS['ratebMegaNavJsQuery'] = $ratebMegaNavJsQuery;

// Keep home working when deploy copies home.php before new includes (partial FTP/git sync).
if (!function_exists('rateb_public_materialize_include')) {
    function rateb_public_materialize_include(string $basename, array $alsoMaterialize = []): void
    {
        $dir = __DIR__;
        $target = $dir . '/' . $basename;
        if (!is_file($target)) {
            $dist = $dir . '/' . preg_replace('/\.php$/', '.dist.php', $basename);
            if (is_file($dist)) {
                @copy($dist, $target);
            }
        }
        foreach ($alsoMaterialize as $extra) {
            $extra = trim($extra);
            if ($extra === '') {
                continue;
            }
            $extraTarget = $dir . '/' . $extra;
            if (!is_file($extraTarget)) {
                $extraDist = $dir . '/' . preg_replace('/\.php$/', '.dist.php', $extra);
                if (is_file($extraDist)) {
                    @copy($extraDist, $extraTarget);
                }
            }
        }
        if (is_file($target)) {
            return;
        }
        rateb_public_write_include_stub($basename);
    }

    function rateb_public_write_include_stub(string $basename): void
    {
        $target = __DIR__ . '/' . $basename;
        if (is_file($target)) {
            return;
        }
        if ($basename === 'rateb-enterprise-trust-home.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('rateb_enterprise_mailto')) {
    function rateb_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@rateb.sa?subject=' . rawurlencode($subject);
    }
}
if (!function_exists('rateb_enterprise_trust_render_home')) {
    function rateb_enterprise_trust_render_home(array $ratebHome, string $baseUrl): void {}
    function rateb_enterprise_trust_render_hero_strip(array $ratebHome): void {}
}
PHP;
        } elseif ($basename === 'rateb-operational-proof-render.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('rateb_operational_proof_render')) {
    function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void {}
}
PHP;
        } else {
            return;
        }
        @file_put_contents($target, $stub);
        @chmod($target, 0644);
    }

    rateb_public_materialize_include('rateb-enterprise-trust-home.php');
    rateb_public_materialize_include('rateb-operational-proof-data.php');
    rateb_public_materialize_include('rateb-operational-proof-render.php');
    rateb_public_materialize_include('rateb-architecture-data.php');
    rateb_public_materialize_include('rateb-architecture-sections.php');
}

if (!function_exists('rateb_public_load_operational_proof_render')) {
    function rateb_public_load_operational_proof_render(): void
    {
        $render = __DIR__ . '/rateb-operational-proof-render.php';
        if (is_file($render)) {
            require_once $render;
            return;
        }
        if (!function_exists('rateb_operational_proof_render')) {
            function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
            {
            }
        }
    }
}
rateb_public_load_operational_proof_render();

require_once __DIR__ . '/rateb-home-public-nav-sync.php';
require_once __DIR__ . '/rateb-home-public-nav-styles.php';
require_once __DIR__ . '/rateb-public-marketing-density.php';
require_once __DIR__ . '/rateb-marketing-expand-bar.php';
