<?php
/**
 * One-shot: persist RATEB rebrand over stale ratib_site_content rows + purge snapshot cache.
 *
 * https://rateb.sa/pages/ratib-cms-rebrand-apply.php?key=ratib-deploy-sync-2026
 * https://rateb.sa/pages/ratib-cms-rebrand-apply.php?key=ratib-deploy-sync-2026&dry=1
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$expectedKey = 'ratib-deploy-sync-2026';
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($key !== $expectedKey) {
    http_response_code(403);
    echo "Forbidden. Pass ?key={$expectedKey}\n";
    exit;
}

$dry = isset($_GET['dry']) && (string) $_GET['dry'] !== '0';

require_once __DIR__ . '/../includes/ratib-php74-compat.php';
require_once __DIR__ . '/../includes/ratib-public-cms.php';
require_once __DIR__ . '/../includes/site-content.php';
require_once __DIR__ . '/../includes/site-content-home-data.php';
require_once __DIR__ . '/../includes/ratib-site-content-rebrand-sanitize.php';

$defaults = ratib_site_content_defaults_home();
$before = ratib_site_content_home_flat(false);
$after = ratib_site_content_rebrand_sanitize_flat($before, $defaults);

$changed = [];
foreach ($after as $k => $v) {
    if (!array_key_exists($k, $before)) {
        continue;
    }
    if ((string) $before[$k] !== (string) $v) {
        $changed[$k] = ['was' => (string) $before[$k], 'now' => (string) $v];
    }
}

echo 'dry_run=' . ($dry ? 'yes' : 'no') . "\n";
echo 'changed_keys=' . count($changed) . "\n\n";

foreach ($changed as $k => $pair) {
    echo $k . "\n  was: " . $pair['was'] . "\n  now: " . $pair['now'] . "\n\n";
}

if ($dry) {
    echo "No DB writes (dry run). Remove &dry=1 to persist.\n";
    exit;
}

$stats = ratib_site_content_rebrand_persist_stale_keys($defaults);
echo 'persist_updated=' . (int) $stats['updated'] . "\n";
echo 'persist_skipped=' . (int) $stats['skipped'] . "\n";
echo 'persist_errors=' . (int) $stats['errors'] . "\n";
echo "\nDone. Purge LiteSpeed cache, then reload home + /profile/\n";
