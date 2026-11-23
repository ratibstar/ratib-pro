<?php
declare(strict_types=1);

/**
 * Replace campaign showcase image with a real photographic studio flat-lay.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/media/library.php';

$campaignId = (int) ($argv[1] ?? 80);
$userId = (int) ($argv[2] ?? 66);
$photo = $argv[3] ?? (dirname(__DIR__) . '/public/assets/image-seeds/branding-desk.jpg');
$pdo = db();

$campaign = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$campaign->execute([$campaignId, $userId]);
$row = $campaign->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "campaign_missing\n");
    exit(1);
}
if (!is_file($photo)) {
    fwrite(STDERR, "photo_missing=$photo\n");
    exit(2);
}
$bytes = (string) file_get_contents($photo);
if (strlen($bytes) < 1000) {
    fwrite(STDERR, "photo_too_small\n");
    exit(3);
}
$mime = 'image/jpeg';
if (strncmp($bytes, "\x89PNG", 4) === 0) {
    $mime = 'image/png';
}

$deleted = 0;
$old = $pdo->prepare("SELECT id, stored_name FROM campaign_media WHERE campaign_id = ? AND kind = 'image'");
$old->execute([$campaignId]);
foreach ($old->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $name = (string) ($item['stored_name'] ?? '');
    if ($name !== '') {
        $path = rtrim((string) MEDIA_ROOT, '/\\') . '/' . $campaignId . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->prepare('DELETE FROM campaign_media WHERE id = ? AND campaign_id = ?')->execute([(int) $item['id'], $campaignId]);
    $deleted++;
}

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$mediaId = media_store_bytes($row, 'image', 'upload', 'rateb-ai-professional-flatlay.' . $ext, $mime, $bytes);
$pdo->prepare("UPDATE campaign_media SET approval_status = 'approved' WHERE id = ? AND campaign_id = ?")->execute([$mediaId, $campaignId]);

// Keep seed in sync with the real photo used for the showcase.
$seedDir = dirname(__DIR__) . '/public/assets/image-seeds';
if (!is_dir($seedDir)) {
    @mkdir($seedDir, 0755, true);
}
@copy($photo, $seedDir . '/marketing-flatlay.jpg');
@copy($photo, $seedDir . '/branding-desk.jpg');

echo json_encode([
    'ok' => true,
    'campaign_id' => $campaignId,
    'deleted_images' => $deleted,
    'media_id' => $mediaId,
    'bytes' => strlen($bytes),
    'source_photo' => basename($photo),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
