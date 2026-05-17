<?php
declare(strict_types=1);

/**
 * Emergency sync — redirects to repo copy (GitHub raw is blocked on this host).
 * Use: /pages/ratib-sync-from-github.php?run=1&key=ratib-deploy-sync-2026
 */
header('Cache-Control: no-store');
$key = (string) ($_GET['key'] ?? '');
$run = (string) ($_GET['run'] ?? '');
if ($run !== '1' || !hash_equals('ratib-deploy-sync-2026', $key)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(403);
    echo "Use: ?run=1&key=ratib-deploy-sync-2026\n";
    echo "Or open: /pages/ratib-copy-from-repo.php?run=1&key=ratib-deploy-sync-2026\n";
    exit;
}
header('Location: /pages/ratib-copy-from-repo.php?run=1&key=' . rawurlencode($key), true, 302);
exit;
