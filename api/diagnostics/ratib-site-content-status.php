<?php
/**
 * Quick check: can this PHP runtime read ratib_site_content the same way pages/home.php does?
 *
 * Security: set env RATIB_SITE_CONTENT_DIAG_SECRET to a long random string, then call:
 *   GET /api/diagnostics/ratib-site-content-status.php?token=YOUR_SECRET
 *
 * Without the secret (or if unset), returns 404.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$secret = getenv('RATIB_SITE_CONTENT_DIAG_SECRET');
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

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/site-content.php';

$conn = function_exists('ratib_site_content_db') ? ratib_site_content_db() : null;
$dbOk = $conn instanceof mysqli;
$tableReadable = $dbOk && function_exists('ratib_site_content_db_can_read_table')
    ? ratib_site_content_db_can_read_table($conn)
    : false;
$phoneKey = 'home.topbar.phone_display';
$phoneVal = ($dbOk && function_exists('ratib_site_content_fetch_value_by_key'))
    ? ratib_site_content_fetch_value_by_key($conn, $phoneKey)
    : null;

$cachePath = function_exists('ratib_site_content_public_cache_path_for_read')
    ? ratib_site_content_public_cache_path_for_read()
    : null;
$cacheBasename = ($cachePath !== null && $cachePath !== '') ? basename($cachePath) : null;
$cacheMtime = ($cachePath !== null && is_file($cachePath)) ? @filemtime($cachePath) : null;

$batch = [];
if ($dbOk && function_exists('ratib_site_content_fetch_key_values')) {
    $batch = ratib_site_content_fetch_key_values([$phoneKey, 'home.topbar.wa_label']);
}

$resolvedPlatform = null;
$resolvedPhonePreview = null;
if (function_exists('ratib_site_content_home_flat')) {
    $flat = ratib_site_content_home_flat();
    $resolvedPlatform = isset($flat['home.nav.platform']) ? (string) $flat['home.nav.platform'] : null;
    $resolvedPhonePreview = isset($flat['home.topbar.phone_display']) ? (string) $flat['home.topbar.phone_display'] : null;
}

$payload = [
    'ok' => true,
    'ratib_site_content_db' => $dbOk,
    'ratib_site_content_table_readable' => $tableReadable,
    'env_RATIB_SITE_CONTENT_PUBLIC_SOURCE' => getenv('RATIB_SITE_CONTENT_PUBLIC_SOURCE') !== false
        ? trim((string) getenv('RATIB_SITE_CONTENT_PUBLIC_SOURCE'))
        : '',
    'env_RATIB_SITE_CONTENT_SKIP_DISK_JSON_CACHE' => getenv('RATIB_SITE_CONTENT_SKIP_DISK_JSON_CACHE') !== false
        ? trim((string) getenv('RATIB_SITE_CONTENT_SKIP_DISK_JSON_CACHE'))
        : '',
    'resolved_home_nav_platform' => $resolvedPlatform,
    'resolved_home_topbar_phone_display' => $resolvedPhonePreview,
    'env_has_CONTROL_DB_USER' => getenv('CONTROL_DB_USER') !== false && trim((string) getenv('CONTROL_DB_USER')) !== '',
    'phone_key_present_in_batch' => array_key_exists($phoneKey, $batch),
    'wa_key_present_in_batch' => array_key_exists('home.topbar.wa_label', $batch),
    'phone_value_length' => $phoneVal !== null ? strlen($phoneVal) : null,
    'newest_json_cache_file' => $cacheBasename,
    'newest_json_cache_mtime_unix' => $cacheMtime,
    'define_single_url' => defined('SINGLE_URL_MODE') && SINGLE_URL_MODE,
    'define_control_panel_db' => defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : null,
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
