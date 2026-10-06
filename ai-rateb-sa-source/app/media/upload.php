<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/library.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
$postedCampaign = (int) ($_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);
if (($_POST['csrf_token'] ?? $_POST['csrf'] ?? '') === '' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    media_redirect($postedCampaign, 'size');
}
check_csrf();
session_write_close();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$kind = (string) ($_POST['kind'] ?? '');
$campaign = media_owned_campaign($campaignId);
if (!isset(media_kind_mimes()[$kind])) {
    media_redirect($campaignId, 'type');
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
    media_redirect($campaignId, 'upload');
}
if ((int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    media_redirect($campaignId, 'size');
}
if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
    media_redirect($campaignId, 'upload');
}

$max = media_max_bytes($kind);
$size = (int) $file['size'];
if ($size < 1 || $size > $max) {
    media_redirect($campaignId, 'size');
}

$bytes = file_get_contents((string) $file['tmp_name']);
if ($bytes === false || strlen($bytes) !== $size) {
    media_redirect($campaignId, 'upload');
}
if (media_reject_active_content($bytes)) {
    media_redirect($campaignId, 'type');
}
$mime = media_detect_mime($bytes, $kind);
if ($kind === 'video') {
    require_once __DIR__ . '/../idea.php';
    set_time_limit(180);
    $existing = db()->prepare('SELECT COUNT(*) FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = ?');
    $existing->execute([$campaignId, (int) $campaign['user_id'], 'video']);
    if ((int) $existing->fetchColumn() >= 1) {
        media_redirect($campaignId, 'video_one');
    }
    if ($mime === null && !rateb_looks_like_video($bytes)) {
        media_redirect($campaignId, 'type');
    }
    $seconds = $mime === null ? null : rateb_video_seconds($bytes, $mime);
    if ($mime === null || $seconds === null || $seconds > 40) {
        $trimmed = rateb_trim_video_file((string) $file['tmp_name']);
        if ($trimmed === null) {
            media_redirect($campaignId, 'video_trim');
        }
        $bytes = $trimmed;
        $mime = 'video/mp4';
    }
} elseif ($mime === null) {
    media_redirect($campaignId, 'type');
}

try {
    media_store_bytes($campaign, $kind, 'upload', (string) ($file['name'] ?? ''), $mime, $bytes);
} catch (Throwable $error) {
    media_redirect($campaignId, 'upload');
}
media_redirect($campaignId);

function rateb_looks_like_video(string $bytes): bool
{
    if (strlen($bytes) < 12) {
        return false;
    }
    if (substr($bytes, 4, 4) === 'ftyp' || str_starts_with($bytes, "\x1A\x45\xDF\xA3")) {
        return true;
    }
    $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes));
    return str_starts_with($mime, 'video/');
}

function rateb_trim_video_file(string $source): ?string
{
    $ffmpeg = '';
    foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/home/admin/bin/ffmpeg'] as $path) {
        if (is_executable($path)) {
            $ffmpeg = $path;
            break;
        }
    }
    if ($ffmpeg === '') {
        return null;
    }
    $base = tempnam(sys_get_temp_dir(), 'rv');
    if ($base === false) {
        return null;
    }
    $target = $base . '.mp4';
    @unlink($base);
    $attempts = [
        [$ffmpeg, '-y', '-i', $source, '-t', '40', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-c:a', 'aac', '-movflags', '+faststart', $target],
        [$ffmpeg, '-y', '-i', $source, '-t', '40', '-c', 'copy', '-movflags', '+faststart', $target],
    ];
    foreach ($attempts as $command) {
        @unlink($target);
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            continue;
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === 0 && is_file($target) && filesize($target) > 32) {
            $trimmed = file_get_contents($target);
            @unlink($target);
            return $trimmed === false ? null : $trimmed;
        }
    }
    @unlink($target);
    return null;
}
