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

if (!function_exists('db')) {
    function db(): PDO
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('No database');
        }
        return $pdo;
    }
}

$renderLock = fopen(dirname(__DIR__) . '/storage/video-render.lock', 'c');
if ($renderLock === false || !flock($renderLock, LOCK_EX | LOCK_NB)) {
    echo "render_videos=locked\n";
    exit(0);
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
