<?php
/**
 * Purge LiteSpeed cache for this vhost, then redirect to unified marketing home (/).
 * https://rateb.sa/pages/rateb-purge-cache.php?key=rateb-deploy-sync-2026
 */
declare(strict_types=1);

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!hash_equals('rateb-deploy-sync-2026', $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Use ?key=rateb-deploy-sync-2026\n";
    exit;
}

if (!headers_sent()) {
    header('X-LiteSpeed-Purge: *');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

$host = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';
$dest = rateb_public_marketing_home_url($scheme . '://' . $host, ['rateb_purged' => '1']);

header('Location: ' . $dest, true, 302);
exit;
