<?php
/**
 * Shared prep for the public home chrome (top bar + sticky SVG nav): CMS flat keys, nav targets, asset bust tokens.
 * Requires $baseUrl (same computation as pages/home.php) before include when used outside home.php.
 *
 * @see pages/home.php
 * @see includes/ratib-home-public-chrome-top.php
 */
if (!function_exists('ratib_site_content_home_flat')) {
    require_once __DIR__ . '/site-content.php';
}

if (!isset($baseUrl) || !is_string($baseUrl) || $baseUrl === '') {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;
}

// Bump via env RATIB_HOME_UI_REV on the server after upload, or edit the fallback string when deploying marquee/lightbox/CSS.
// Ensures ?v= on CSS/JS changes even when the host preserves old file mtimes (FTP / sync tools).
$ratibHomeUiRevRaw = getenv('RATIB_HOME_UI_REV');
$ratibHomeUiRev = ($ratibHomeUiRevRaw !== false && trim((string) $ratibHomeUiRevRaw) !== '')
    ? preg_replace('/[^a-zA-Z0-9._-]/', '', trim((string) $ratibHomeUiRevRaw))
    : '20260516-brand-profile';
/** Proof token: ties asset URLs to pages/home.php deploy */
$ratibHomePhpPath = __DIR__ . '/../pages/home.php';
clearstatcache(true, $ratibHomePhpPath);
$ratibHomePhpMtime = (string) (int) (@filemtime($ratibHomePhpPath) ?: 0);
/** Append to CSS/JS ?v= when CDN/host preserves mtimes (set in env or config: RATIB_HOME_ASSET_EXTRA_BUST=manual-20260210). */
$ratibHomeAssetExtraBustRaw = getenv('RATIB_HOME_ASSET_EXTRA_BUST');
$ratibHomeAssetExtraQ = ($ratibHomeAssetExtraBustRaw !== false && trim((string) $ratibHomeAssetExtraBustRaw) !== '')
    ? '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', trim((string) $ratibHomeAssetExtraBustRaw))
    : '';
$ratibHome = ratib_site_content_home_flat(false);
if (function_exists('ratib_site_content_home_ensure_header_nav_labels')) {
    ratib_site_content_home_ensure_header_nav_labels($ratibHome);
}
$ratibDbFingerprint = function_exists('ratib_site_content_db_fingerprint')
    ? ratib_site_content_db_fingerprint()
    : '';
// Top bar: one DB round-trip for all keys so phone/WA/nodes stay in sync (no mixed JSON vs row timing).
$ratibTopbarKeys = [
    'home.topbar.phone_display',
    'home.topbar.wa_label',
    'home.topbar.tls_label',
    'home.topbar.nodes_count',
    'home.topbar.nodes_suffix',
    'home.topbar.client_login',
];
if (function_exists('ratib_site_content_fetch_key_values') && function_exists('ratib_site_content_defaults_home')) {
    $ratibDef = ratib_site_content_defaults_home();
    $liveTopbar = ratib_site_content_fetch_key_values($ratibTopbarKeys);
    foreach ($ratibTopbarKeys as $ratibTbKey) {
        if (!array_key_exists($ratibTbKey, $ratibDef)) {
            continue;
        }
        $ratibFallback = $ratibHome[$ratibTbKey] ?? $ratibDef[$ratibTbKey];
        $ratibHome[$ratibTbKey] = array_key_exists($ratibTbKey, $liveTopbar)
            ? $liveTopbar[$ratibTbKey]
            : $ratibFallback;
    }
} elseif (function_exists('ratib_site_content_get') && function_exists('ratib_site_content_defaults_home')) {
    $ratibDef = ratib_site_content_defaults_home();
    foreach ($ratibTopbarKeys as $ratibTbKey) {
        if (!array_key_exists($ratibTbKey, $ratibDef)) {
            continue;
        }
        $ratibFallback = $ratibHome[$ratibTbKey] ?? $ratibDef[$ratibTbKey];
        $ratibHome[$ratibTbKey] = ratib_site_content_get($ratibTbKey, $ratibFallback);
    }
}
// Visible phone text = exactly what the CMS saved (WYSIWYG). tel:/wa.me use normalized digits below.
$ratibPhoneRaw = (string) ($ratibHome['home.topbar.phone_display'] ?? '');
$ratibPhoneDigits = function_exists('ratib_site_content_phone_digits_for_links')
    ? ratib_site_content_phone_digits_for_links($ratibPhoneRaw)
    : (preg_replace('/\D+/', '', $ratibPhoneRaw) ?: '966599863868');
$ratibTopbarNodesDigits = preg_replace('/\D/', '', (string) ($ratibHome['home.topbar.nodes_count'] ?? '247'));
$ratibTopbarNodesNum = $ratibTopbarNodesDigits !== '' ? (int) $ratibTopbarNodesDigits : 247;
// Avoid billion-scale “counts” (usually a pasted phone fragment) confusing the top bar next to the phone.
if ($ratibTopbarNodesNum > 999999 || strlen($ratibTopbarNodesDigits) > 6) {
    $ratibTopbarNodesNum = 247;
    $ratibTopbarNodesDigits = '247';
}
$ratibPricingStarterLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.starter.features'] ?? '');
$ratibPricingGoldLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.gold.features'] ?? '');
$ratibPricingPlatinumLines = ratib_site_content_home_nl_lines($ratibHome['home.pricing.platinum.features'] ?? '');
// Public site: show a program card only when the CMS slot has a real image/video URL or token (no caption-only placeholders).
$ratibProgSlotsOut = [];
if (function_exists('ratib_site_content_home_program_slots_from_flat')) {
    foreach (ratib_site_content_home_program_slots_from_flat($ratibHome) as $slot) {
        $stored = trim((string) ($slot['src'] ?? ''));
        if ($stored === '') {
            continue;
        }
        $imgSrc = ratib_site_content_asset_url($baseUrl, $stored, '', '');
        if (trim($imgSrc) === '') {
            continue;
        }
        $ratibProgSlotsOut[] = [
            'src' => $imgSrc,
            'alt' => (string) ($slot['alt'] ?? ''),
            'caption' => (string) ($slot['caption'] ?? ''),
        ];
    }
}

$ratibVideoSources = [];
$videoExists = false;
$videoSrcRel = '';
$videoUrl = '';
if (function_exists('ratib_site_content_home_video_src_strings_from_flat') && function_exists('ratib_site_content_home_resolve_video_display_url')) {
    foreach (ratib_site_content_home_video_src_strings_from_flat($ratibHome) as $vs) {
        $u = ratib_site_content_home_resolve_video_display_url((string) $vs, $baseUrl);
        if ($u !== '') {
            $ratibVideoSources[] = $u;
        }
    }
}
if ($ratibVideoSources !== []) {
    $videoExists = true;
    $videoSrcRel = (string) $ratibVideoSources[0];
    $videoUrl = $videoSrcRel;
}

$ratibVideoSlotsRawCheck = trim((string) ($ratibHome['home.video.slots_json'] ?? ''));
$ratibVideoClearedInCms = false;
if ($ratibVideoSlotsRawCheck !== '') {
    $ratibVideoSlotsDecoded = json_decode($ratibVideoSlotsRawCheck, true);
    $ratibVideoClearedInCms = is_array($ratibVideoSlotsDecoded) && count($ratibVideoSlotsDecoded) === 0;
}
// Hide the whole video band (heading + strip) when there is nothing to show and CMS did not ask for the empty-state hint.
$ratibShowHomeVideoBand = !empty($ratibVideoSources) || (!$videoExists && !$ratibVideoClearedInCms);
// Header link "video/pic" scroll target: walkthrough video when present, else screenshot strip, else platform section.
$ratibNavProductTourHref = '#platform';
if ($ratibShowHomeVideoBand) {
    $ratibNavProductTourHref = '#video';
} elseif (!empty($ratibProgSlotsOut)) {
    $ratibNavProductTourHref = '#program-previews';
}
$ratibNavProductTourLabel = trim((string) ($ratibHome['home.nav.tour'] ?? ''));
if ($ratibNavProductTourLabel === '') {
    $ratibNavProductTourLabel = trim((string) ($ratibHome['home.nav.product_tour'] ?? ''));
}
if ($ratibNavProductTourLabel === '') {
    $ratibNavProductTourLabel = 'Tour';
}

$ratibHomeCssPath = __DIR__ . '/../css/pages/home-public.css';
clearstatcache(true, $ratibHomeCssPath);
$ratibHomeCssTs = (int) (@filemtime($ratibHomeCssPath) ?: time());
$ratibHomePublicCssQuery = $ratibHomeCssTs . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ;

$ratibMegaNavCssPath = __DIR__ . '/../css/pages/ratib-mega-nav.css';
clearstatcache(true, $ratibMegaNavCssPath);
$ratibMegaNavCssTs = (int) (@filemtime($ratibMegaNavCssPath) ?: time());
$ratibMegaNavCssQuery = $ratibMegaNavCssTs . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ;

$ratibMegaNavJsPath = __DIR__ . '/../js/pages/ratib-mega-nav.js';
clearstatcache(true, $ratibMegaNavJsPath);
$ratibMegaNavJsTs = (int) (@filemtime($ratibMegaNavJsPath) ?: time());
$ratibMegaNavJsQuery = $ratibMegaNavJsTs . '-' . $ratibHomeUiRev . '-' . $ratibHomePhpMtime . $ratibHomeAssetExtraQ;

/*
 * Bust browser/CDN caches when nav chrome changes even if RATIB_HOME_UI_REV is unchanged (common on FTP deploy).
 * Short hash from mtimes of nav markup, sync inline script, mega config, home-page.js.
 */
$ratibChromeNavPathsForBundle = [
    __DIR__ . '/ratib-home-public-nav-sync.php',
    __DIR__ . '/ratib-mega-nav-config.php',
    __DIR__ . '/ratib-home-public-chrome-top.php',
    __DIR__ . '/../js/pages/home-page.js',
];
$ratibChromeBundleKey = '';
foreach ($ratibChromeNavPathsForBundle as $ratibChromeNavPath) {
    if (!is_readable($ratibChromeNavPath)) {
        continue;
    }
    clearstatcache(true, $ratibChromeNavPath);
    $ratibChromeBundleKey .= $ratibChromeNavPath . '=' . (string) (int) (@filemtime($ratibChromeNavPath) ?: 0) . '|';
}
$ratibChromeBundleHash = $ratibChromeBundleKey !== ''
    ? substr(hash('sha256', $ratibChromeBundleKey), 0, 12)
    : '0';
$ratibHomePublicCssQuery .= '-c' . $ratibChromeBundleHash;
$ratibMegaNavCssQuery .= '-c' . $ratibChromeBundleHash;
$ratibMegaNavJsQuery .= '-c' . $ratibChromeBundleHash;

require_once __DIR__ . '/ratib-home-public-nav-sync.php';
