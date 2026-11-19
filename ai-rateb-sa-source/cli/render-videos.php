<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/app/media/library.php';
require dirname(__DIR__) . '/app/video/engine.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$rows = $pdo->query("SELECT * FROM video_jobs WHERE status IN ('queued', 'processing') ORDER BY id ASC LIMIT 5");
$count = 0;
foreach ($rows as $job) {
    $campaign = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
    $campaign->execute([(int) $job['campaign_id'], (int) $job['user_id']]);
    $row = $campaign->fetch();
    if (!$row) {
        continue;
    }
    $done = rateb_video_advance($pdo, $job, $row);
    $count++;
    echo 'job=' . (int) $job['id'] . ' status=' . (string) ($done['status'] ?? '') . ' error=' . (string) ($done['error_code'] ?? '') . PHP_EOL;
}
echo "render_videos={$count}\n";
