<?php
/**
 * Verify that the live server actually has the expected home assets on disk (FTP path vs docroot),
 * and optionally invalidate PHP OPcache for homepage-related scripts after deploy.
 *
 * Usage (same secret as rateb-site-content-status.php):
 *   GET /api/diagnostics/rateb-home-deploy-probe.php?token=YOUR_SECRET
 * Optional OPcache refresh (run once after uploading PHP files):
 *   GET ... ?token=YOUR_SECRET&invalidate_opcache=1
 *
 * Without RATEB_SITE_CONTENT_DIAG_SECRET set, returns 404.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$secret = getenv('RATEB_SITE_CONTENT_DIAG_SECRET');
if ($secret === false || trim((string) $secret) === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'diagnostic_disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string) ($_GET['token'] ?? '');
if (!hash_equals((string) $secret, $token)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'root_resolve'], JSON_UNESCAPED_UNICODE);
    exit;
}

$paths = [
    'home_php' => $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home.php',
    'about_php' => $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'about.php',
    'chrome_top' => $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'rateb-home-public-chrome-top.php',
    'home_css' => $root . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home-public.css',
    'home_js' => $root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home-page.js',
    'build_marker' => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'rateb-build.txt',
];

/**
 * @return array<string, mixed>
 */
function ratebProbeOneFile(string $key, string $absPath): array
{
    if (!is_file($absPath)) {
        return ['path' => $absPath, 'exists' => false];
    }
    $raw = @file_get_contents($absPath, false, null, 0, 800000);
    $sample = is_string($raw) ? $raw : '';
    $base = [
        'path' => $absPath,
        'exists' => true,
        'bytes' => filesize($absPath),
        'mtime' => @filemtime($absPath) ?: null,
    ];
    if ($key === 'home_php') {
        $base['has_rateb_nav_glyph_sprite'] = str_contains($sample, 'rateb-nav-glyph-sprite');
        $base['has_rateb_nav_partner_login'] = str_contains($sample, 'rateb-nav__partner-login');
        $base['has_data_rateb_nav_visual'] = str_contains($sample, 'data-rateb-nav-visual');
        $base['has_open_about_bridge'] = str_contains($sample, "'about'");
        $base['looks_like_current_nav_build'] = str_contains($sample, 'rateb-nav-glyph-sprite')
            && str_contains($sample, 'rateb-nav__partner-login');
    } elseif ($key === 'about_php') {
        $base['has_rateb_about_hero'] = str_contains($sample, 'rateb-about-hero');
    } elseif ($key === 'chrome_top') {
        $base['has_about_nav_link'] = str_contains($sample, 'rateb-nav__link--about');
        $base['primary_links_8'] = str_contains($sample, 'primary-links=8');
    } elseif ($key === 'build_marker') {
        $base['marker'] = trim($sample);
    } elseif ($key === 'home_css') {
        $base['has_rateb_nav_glyph_rules'] = str_contains($sample, '.rateb-nav__glyph');
        $base['has_semantic_pulse'] = str_contains($sample, 'ratebSemanticPulse');
    } else {
        $base['has_video_fallback_svg'] = str_contains($sample, 'rateb-ng-video');
    }
    return $base;
}

$out = [
    'ok' => true,
    'bundle' => 'about-enterprise-20260516-v9',
    'php_version' => PHP_VERSION,
    'document_root_guess' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
    'probe_root' => $root,
    'opcache_enabled' => function_exists('opcache_get_status') ? (bool) (opcache_get_status(false)['opcache_enabled'] ?? false) : null,
    'files' => [],
];

foreach ($paths as $k => $p) {
    $out['files'][$k] = ratebProbeOneFile($k, $p);
}

$inv = isset($_GET['invalidate_opcache']) && (string) $_GET['invalidate_opcache'] === '1';
$out['invalidate_opcache_requested'] = $inv;
$out['invalidate_opcache_results'] = [];

if ($inv) {
    $scripts = [
        $paths['home_php'],
        $paths['about_php'],
        $paths['chrome_top'],
        $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'site-content.php',
        $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'site-content-home-data.php',
        $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php',
    ];
    foreach ($scripts as $script) {
        if (!is_file($script)) {
            $out['invalidate_opcache_results'][] = ['script' => $script, 'ok' => false, 'reason' => 'missing'];
            continue;
        }
        if (function_exists('opcache_invalidate')) {
            $ok = @opcache_invalidate($script, true);
            $out['invalidate_opcache_results'][] = ['script' => $script, 'ok' => (bool) $ok];
        } else {
            $out['invalidate_opcache_results'][] = ['script' => $script, 'ok' => false, 'reason' => 'no_opcache_invalidate'];
        }
    }
}

$out['hint'] = 'If files.exists is true and checks are true but the browser still shows an old header, purge LiteSpeed/Cloudflare/host HTML cache, hard-refresh, and confirm you uploaded into this probe_root (not a duplicate folder).';

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

