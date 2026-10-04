<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/media/library.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$id = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$pdo = db();

$owned = $pdo->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$id, $userId]);
if (!$owned->fetch()) {
    http_response_code(404);
    exit('Campaign not found.');
}

$media = $pdo->prepare('SELECT campaign_id, stored_name FROM campaign_media WHERE campaign_id = ? AND user_id = ?');
$media->execute([$id, $userId]);
$paths = [];
$unsafeFile = false;
foreach ($media->fetchAll() as $row) {
    $name = trim((string) ($row['stored_name'] ?? ''));
    if ($name === '') {
        continue;
    }
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm|mp3|wav|ogg|m4a)$/', $name) !== 1) {
        $unsafeFile = true;
        continue;
    }
    $path = campaign_delete_media_path($row, $id);
    if ($path !== null) {
        $paths[] = $path;
    }
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM campaign_media WHERE campaign_id = ? AND user_id = ?')->execute([$id, $userId]);
    $pdo->prepare('DELETE FROM campaign_outputs WHERE campaign_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM campaign_items WHERE campaign_id = ? AND user_id = ?')->execute([$id, $userId]);
    $pdo->prepare('DELETE FROM campaign_variations WHERE campaign_id = ? AND user_id = ?')->execute([$id, $userId]);
    $deleted = $pdo->prepare('DELETE FROM campaigns WHERE id = ? AND user_id = ?');
    $deleted->execute([$id, $userId]);
    if ($deleted->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Campaign not found.');
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: /campaign.php?id=' . $id . '&delete_error=1');
    exit;
}

$fileError = $unsafeFile || !campaign_delete_media_files($id, $paths);
header('Location: /dashboard.php?' . ($fileError ? 'delete_error=1' : 'deleted=1'));
exit;

function campaign_delete_media_path(array $row, int $campaignId): ?string
{
    if ((int) ($row['campaign_id'] ?? 0) !== $campaignId) {
        return null;
    }
    $name = (string) ($row['stored_name'] ?? '');
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm|mp3|wav|ogg|m4a)$/', $name) !== 1) {
        return null;
    }
    $root = realpath(MEDIA_ROOT);
    $full = realpath(MEDIA_ROOT . '/' . $campaignId . '/' . $name);
    if ($root === false || $full === false || !is_file($full)) {
        return null;
    }
    $prefix = $root . DIRECTORY_SEPARATOR . $campaignId . DIRECTORY_SEPARATOR;
    if (!str_starts_with($full, $prefix)) {
        return null;
    }
    return $full;
}

function campaign_delete_media_files(int $campaignId, array $paths): bool
{
    $ok = true;
    foreach ($paths as $path) {
        if (is_file($path) && !unlink($path)) {
            $ok = false;
        }
    }
    $root = realpath(MEDIA_ROOT);
    if ($root === false) {
        return $ok;
    }
    $dir = $root . DIRECTORY_SEPARATOR . $campaignId;
    if (!is_dir($dir)) {
        return $ok;
    }
    $realDir = realpath($dir);
    if ($realDir === false || $realDir !== $dir) {
        return false;
    }
    $entries = scandir($realDir);
    if ($entries === false) {
        return false;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $realDir . DIRECTORY_SEPARATOR . $entry;
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm|mp3|wav|ogg|m4a)$/', $entry) !== 1 || !is_file($full)) {
            $ok = false;
            continue;
        }
        if (!unlink($full)) {
            $ok = false;
        }
    }
    $left = scandir($realDir);
    if ($left === false) {
        return false;
    }
    $remaining = array_diff($left, ['.', '..']);
    if ($remaining === []) {
        if (!rmdir($realDir)) {
            $ok = false;
        }
    } else {
        $ok = false;
    }
    return $ok;
}
