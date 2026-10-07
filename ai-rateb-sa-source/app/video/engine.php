<?php
declare(strict_types=1);

require_once __DIR__ . '/provider.php';

function rateb_video_ratio(): string
{
    return '720:1280';
}

function rateb_video_clip_seconds(): int
{
    return 5;
}

/** @param array<int,array<string,mixed>> $images */
function rateb_video_storyboard(array $campaign, array $strategy, array $copyParts, array $images, int $planSeconds): array
{
    $clip = rateb_video_clip_seconds();
    $target = min(40, max(0, $planSeconds));
    $count = intdiv($target, $clip);
    if ($count < 1 || $images === []) {
        return [];
    }
    $count = min($count, 8, count($images) > 0 ? max($count, 1) : 0);
    $count = min($count, 8);
    $motions = [
        'subtle camera push-in, premium commercial lighting, product identity unchanged, no text',
        'slow lateral camera movement, gentle parallax, product identity unchanged, no text',
        'controlled product reveal, cinematic light movement, product identity unchanged, no text',
        'realistic environmental motion, steady premium camera, product identity unchanged, no logos changing, no text',
    ];
    $purpose = trim((string) ($strategy['creative_direction'] ?? $strategy['core_message'] ?? ''));
    if ($purpose === '' && $copyParts !== []) {
        $purpose = (string) $copyParts[0];
    }
    $purpose = mb_substr($purpose, 0, 180);
    $scenes = [];
    for ($i = 0; $i < $count; $i++) {
        $image = $images[$i % count($images)];
        $scenes[] = [
            'purpose' => $purpose,
            'image_id' => (int) $image['id'],
            'duration' => $clip,
            'motion' => $motions[$i % count($motions)],
            'prompt' => $motions[$i % count($motions)],
            'task_id' => '',
            'status' => 'queued',
            'url' => '',
        ];
    }
    return $scenes;
}

function rateb_video_image_data_uri(array $row): string
{
    $path = media_absolute_path($row);
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') {
        throw new RuntimeException('missing_image');
    }
    if (strlen($bytes) > 1500000 && function_exists('imagecreatefromstring')) {
        $image = @imagecreatefromstring($bytes);
        if ($image !== false) {
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, 720 / max(1, $width), 1280 / max(1, $height));
            $nw = max(1, (int) ($width * $scale));
            $nh = max(1, (int) ($height * $scale));
            $canvas = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $width, $height);
            ob_start();
            imagejpeg($canvas, null, 82);
            $bytes = (string) ob_get_clean();
            imagedestroy($canvas);
            imagedestroy($image);
        }
    }
    $mime = str_starts_with($bytes, "\xFF\xD8") ? 'image/jpeg' : (string) $row['mime'];
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

function rateb_video_latest_job(PDO $pdo, int $campaignId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM video_jobs WHERE campaign_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$campaignId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rateb_video_approved(PDO $pdo, int $campaignId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT j.*, m.stored_name, m.bytes, m.mime FROM video_jobs j
        INNER JOIN campaign_media m ON m.id = j.output_media_id AND m.user_id = j.user_id AND m.source = 'ai' AND m.kind = 'video'
        WHERE j.campaign_id = ? AND j.user_id = ? AND j.approval_status = 'approved' AND j.status = 'completed'
        ORDER BY j.id DESC LIMIT 1");
    $stmt->execute([$campaignId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** @return array{ok:bool,code:string,job_id:int} */
function rateb_video_start(PDO $pdo, array $campaign, array $strategyRow, array $copyRows, array $images): array
{
    $provider = rateb_video_provider();
    if (!$provider->configured()) {
        return ['ok' => false, 'code' => 'not_configured', 'job_id' => 0];
    }
    if ((string) ($strategyRow['approval_status'] ?? '') !== 'approved') {
        return ['ok' => false, 'code' => 'missing_copy', 'job_id' => 0];
    }
    $approvedCopy = false;
    $parts = [];
    foreach ($copyRows as $row) {
        if ((string) ($row['approval_status'] ?? '') === 'approved') {
            $approvedCopy = true;
            $parts[] = mb_substr(trim((string) $row['content']), 0, 180);
        }
    }
    if (!$approvedCopy) {
        return ['ok' => false, 'code' => 'missing_copy', 'job_id' => 0];
    }
    if ($images === []) {
        return ['ok' => false, 'code' => 'missing_image', 'job_id' => 0];
    }
    $active = rateb_video_latest_job($pdo, (int) $campaign['id'], (int) $campaign['user_id']);
    if ($active && in_array((string) $active['status'], ['queued', 'processing'], true)) {
        return ['ok' => true, 'code' => '', 'job_id' => (int) $active['id']];
    }
    $plan = rateb_user_plan($pdo, (int) $campaign['user_id']);
    $strategy = rateb_strategy_document((string) $strategyRow['content']) ?? [];
    $scenes = rateb_video_storyboard($campaign, $strategy, $parts, $images, (int) $plan['video_max_seconds']);
    if ($scenes === []) {
        return ['ok' => false, 'code' => 'video_too_long', 'job_id' => 0];
    }
    $seconds = count($scenes) * rateb_video_clip_seconds();
    $gate = rateb_usage_begin($pdo, (int) $campaign['user_id'], (int) $campaign['id'], 'video', $seconds);
    if (!$gate['ok']) {
        return ['ok' => false, 'code' => (string) $gate['code'], 'job_id' => 0];
    }
    $insert = $pdo->prepare('INSERT INTO video_jobs (campaign_id, user_id, status, provider, storyboard, usage_id, watermark, approval_status) VALUES (?, ?, \'queued\', ?, ?, ?, ?, \'draft\')');
    $insert->execute([
        (int) $campaign['id'],
        (int) $campaign['user_id'],
        $provider->name(),
        json_encode($scenes, JSON_UNESCAPED_UNICODE),
        (int) $gate['id'],
        !empty($gate['watermark']) ? 1 : 0,
    ]);
    return ['ok' => true, 'code' => '', 'job_id' => (int) $pdo->lastInsertId()];
}

function rateb_video_fail(PDO $pdo, array $job, string $code): void
{
    $pdo->prepare("UPDATE video_jobs SET status = 'failed', error_code = ? WHERE id = ? AND user_id = ?")->execute([
        $code,
        (int) $job['id'],
        (int) $job['user_id'],
    ]);
    rateb_usage_finish($pdo, (int) $job['usage_id'], 'failed');
}

function rateb_video_advance(PDO $pdo, array $job, array $campaign): array
{
    if ((string) $job['status'] === 'completed' || (string) $job['status'] === 'failed') {
        return $job;
    }
    $updated = strtotime((string) ($job['updated_at'] ?? 'now')) ?: time();
    if ($updated < time() - 1200 && (string) $job['status'] !== 'queued') {
        rateb_video_fail($pdo, $job, 'timeout');
        $job['status'] = 'failed';
        $job['error_code'] = 'timeout';
        return $job;
    }
    $scenes = json_decode((string) $job['storyboard'], true);
    if (!is_array($scenes) || $scenes === []) {
        rateb_video_fail($pdo, $job, 'invalid');
        $job['status'] = 'failed';
        $job['error_code'] = 'invalid';
        return $job;
    }
    $provider = rateb_video_provider();
    if (!$provider->configured()) {
        rateb_video_fail($pdo, $job, 'not_configured');
        $job['status'] = 'failed';
        $job['error_code'] = 'not_configured';
        return $job;
    }
    try {
        foreach ($scenes as $index => $scene) {
            if (!is_array($scene)) {
                throw new RuntimeException('invalid');
            }
            if ((string) ($scene['status'] ?? '') === 'ready') {
                continue;
            }
            $taskId = (string) ($scene['task_id'] ?? '');
            if ($taskId === '') {
                $image = db()->prepare('SELECT * FROM campaign_media WHERE id = ? AND campaign_id = ? AND user_id = ? AND kind = \'image\'');
                $image->execute([(int) $scene['image_id'], (int) $campaign['id'], (int) $campaign['user_id']]);
                $row = $image->fetch();
                if (!$row) {
                    throw new RuntimeException('missing_image');
                }
                $created = $provider->generateFromImage(
                    rateb_video_image_data_uri($row),
                    (string) $scene['prompt'],
                    (int) $scene['duration'],
                    rateb_video_ratio()
                );
                $scenes[$index]['task_id'] = $created['task_id'];
                $scenes[$index]['status'] = 'processing';
                $pdo->prepare("UPDATE video_jobs SET status = 'processing', storyboard = ? WHERE id = ?")->execute([
                    json_encode($scenes, JSON_UNESCAPED_UNICODE),
                    (int) $job['id'],
                ]);
                $job['status'] = 'processing';
                $job['storyboard'] = json_encode($scenes, JSON_UNESCAPED_UNICODE);
                return $job;
            }
            $remote = $provider->getTaskStatus($taskId);
            if ($remote['status'] === 'queued' || $remote['status'] === 'processing') {
                $job['status'] = 'processing';
                return $job;
            }
            if ($remote['status'] !== 'completed') {
                throw new RuntimeException($remote['error'] !== '' ? $remote['error'] : 'provider');
            }
            $clip = $provider->downloadResult($remote['url']);
            $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('storage');
            }
            $clipPath = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
            if (file_put_contents($clipPath, $clip, LOCK_EX) === false) {
                throw new RuntimeException('storage');
            }
            chmod($clipPath, 0600);
            $scenes[$index]['status'] = 'ready';
            $scenes[$index]['url'] = '';
            $pdo->prepare('UPDATE video_jobs SET storyboard = ? WHERE id = ?')->execute([
                json_encode($scenes, JSON_UNESCAPED_UNICODE),
                (int) $job['id'],
            ]);
        }
        $final = rateb_video_compose($pdo, $job, $campaign, $scenes);
        $mediaId = media_store_bytes($campaign, 'video', 'ai', 'campaign.mp4', 'video/mp4', $final);
        $pdo->prepare("UPDATE video_jobs SET status = 'completed', output_media_id = ?, error_code = '' WHERE id = ?")->execute([
            $mediaId,
            (int) $job['id'],
        ]);
        rateb_usage_finish($pdo, (int) $job['usage_id'], 'completed');
        $job['status'] = 'completed';
        $job['output_media_id'] = $mediaId;
        $job['error_code'] = '';
        return $job;
    } catch (RuntimeException $error) {
        $code = in_array($error->getMessage(), ['not_configured', 'auth', 'rejected', 'timeout', 'provider', 'invalid', 'missing_image', 'storage', 'watermark'], true)
            ? $error->getMessage()
            : 'provider';
        rateb_video_fail($pdo, $job, $code);
        $job['status'] = 'failed';
        $job['error_code'] = $code;
        return $job;
    }
}

/** @param array<int,array<string,mixed>> $scenes */
function rateb_video_compose(PDO $pdo, array $job, array $campaign, array $scenes): string
{
    $ffmpeg = is_file('/home/admin/bin/ffmpeg') ? '/home/admin/bin/ffmpeg' : 'ffmpeg';
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
    $list = $dir . '/list-' . (int) $job['id'] . '.txt';
    $lines = '';
    foreach ($scenes as $index => $scene) {
        $clip = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
        if (!is_file($clip)) {
            throw new RuntimeException('provider');
        }
        $lines .= "file " . escapeshellarg($clip) . "\n";
    }
    file_put_contents($list, $lines);
    $out = $dir . '/final-' . (int) $job['id'] . '.mp4';
    $voice = $pdo->prepare('SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = \'audio\' ORDER BY id DESC LIMIT 1');
    $voice->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
    $audio = $voice->fetch();
    $audioPath = $audio ? media_absolute_path($audio) : '';
    $filter = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,format=yuv420p';
    if ((int) $job['watermark'] === 1) {
        $filter .= ",drawtext=text='RATEB AI':fontsize=28:fontcolor=white:box=1:boxcolor=black@0.45:x=w-tw-24:y=h-th-24";
    }
    $cmd = escapeshellarg($ffmpeg) . ' -y -f concat -safe 0 -i ' . escapeshellarg($list);
    if ($audioPath !== '' && is_file($audioPath)) {
        $cmd .= ' -i ' . escapeshellarg($audioPath);
        $cmd .= ' -filter_complex ' . escapeshellarg('[0:v]' . $filter . '[v]');
        $cmd .= ' -map [v] -map 1:a -shortest';
    } else {
        $cmd .= ' -vf ' . escapeshellarg($filter) . ' -an';
    }
    $cmd .= ' -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart ' . escapeshellarg($out);
    $code = 1;
    if (!function_exists('proc_open')) {
        throw new RuntimeException('storage');
    }
    $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('storage');
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $bytes = is_file($out) ? file_get_contents($out) : false;
    if ($code !== 0 || !is_string($bytes) || !str_contains(substr($bytes, 0, 32), 'ftyp')) {
        if ((int) $job['watermark'] === 1) {
            throw new RuntimeException('watermark');
        }
        throw new RuntimeException('storage');
    }
    return $bytes;
}

function rateb_video_message(string $code, bool $arabic): string
{
    $ar = [
        'not_configured' => 'توليد الفيديو غير مُعد على الخادم.',
        'auth' => 'تعذر التحقق من مزوّد الفيديو.',
        'rejected' => 'رفض المزوّد إنشاء الفيديو.',
        'timeout' => 'انتهت مهلة إنشاء الفيديو.',
        'provider' => 'تعذر إنشاء الفيديو.',
        'invalid' => 'مدخلات الفيديو غير صالحة.',
        'missing_image' => 'يلزم صورة حملة قبل إنشاء الفيديو.',
        'missing_copy' => 'اعتمد الاستراتيجية والنص أولاً.',
        'plan_limit' => 'استنفدت حد الفيديو في خطتك.',
        'plan_inactive' => 'الخطة غير متاحة.',
        'video_too_long' => 'مدة خطتك لا تكفي لمشهد الفيديو.',
        'storage' => 'تعذر حفظ الفيديو.',
        'watermark' => 'تعذر إضافة علامة RATEB AI على الفيديو.',
    ];
    $en = [
        'not_configured' => 'Video generation is not configured.',
        'auth' => 'The video provider could not be authenticated.',
        'rejected' => 'The provider rejected the video.',
        'timeout' => 'Video generation timed out.',
        'provider' => 'The video could not be generated.',
        'invalid' => 'The video input is invalid.',
        'missing_image' => 'A campaign image is required before video generation.',
        'missing_copy' => 'Approve the strategy and copy first.',
        'plan_limit' => 'The video limit for your plan has been reached.',
        'plan_inactive' => 'The plan is not available.',
        'video_too_long' => 'Your plan duration is too short for a video scene.',
        'storage' => 'The video could not be saved.',
        'watermark' => 'The RATEB AI mark could not be applied to the video.',
    ];
    $map = $arabic ? $ar : $en;
    return $map[$code] ?? ($arabic ? 'تعذر إنشاء الفيديو.' : 'The video could not be generated.');
}
