<?php
/**
 * Purge LiteSpeed cache for this vhost, then redirect to profile.
 * https://out.ratib.sa/pages/ratib-purge-cache.php?key=ratib-deploy-sync-2026
 */
declare(strict_types=1);

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!hash_equals('ratib-deploy-sync-2026', $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Use ?key=ratib-deploy-sync-2026\n";
    exit;
}

if (!headers_sent()) {
    header('X-LiteSpeed-Purge: *');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

$host = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
require_once __DIR__ . '/../includes/ratib-public-base-url.php';
$dest = ratib_public_marketing_home_url($scheme . '://' . $host, ['ratib_purged' => '1']);

header('Location: ' . $dest, true, 302);
exit;
