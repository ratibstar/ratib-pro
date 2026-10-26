<?php
declare(strict_types=1);

require_once __DIR__ . '/provider.php';
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/../plans.php';

function rateb_video_ratio(): string
{
    return '720:1280';
}

function rateb_video_clip_seconds(): int
{
    return 5;
}

/** @return array<int,int> */
function rateb_video_clips(int $seconds): array
{
    return match ($seconds) {
        5 => [5],
        10 => [10],
        15 => [10, 5],
        20 => [10, 10],
        30 => [10, 10, 10],
        default => [],
    };
}

function rateb_video_copy_text(string $content, string $prefer = 'ar', int $limit = 180): string
{
    $content = trim($content);
    if ($content !== '' && ($content[0] === '{' || $content[0] === '[')) {
        json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
    }
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $picked = (string) ($decoded['instagram'] ?? $decoded['snapchat'] ?? $decoded['x'] ?? '');
        if ($picked !== '') {
            $content = $picked;
        }
    }
    $plain = trim(preg_replace('/\s+/u', ' ', rateb_prompt_plain($content, $prefer)) ?? '');
    if ($plain === '' || $plain[0] === '{' || $plain[0] === '[') {
        return '';
    }
    return mb_substr($plain, 0, max(1, $limit));
}

function rateb_video_voiceover_limit(): int
{
    return 600;
}

function rateb_video_split_lines(string $text, int $count): array
{
    $text = trim($text);
    if ($count < 1) {
        return [];
    }
    $bits = preg_split('/(?<=[.!?؟])\s+/u', $text) ?: [];
    $bits = array_values(array_filter(array_map('trim', $bits), static fn (string $bit): bool => $bit !== ''));
    if ($bits === []) {
        $bits = [$text !== '' ? $text : ''];
    }
    $lines = [];
    for ($i = 0; $i < $count; $i++) {
        $lines[] = $bits[$i] ?? $bits[count($bits) - 1];
    }
    return $lines;
}

/** @param array<int,array<string,mixed>> $rows */
function rateb_video_copy_parts(array $rows, string $prefer = 'ar'): array
{
    $order = array_flip(['short_ad', 'headline', 'call_to_action', 'ad_copy', 'social_posts']);
    $rows = array_values(array_filter($rows, 'is_array'));
    usort($rows, static function (array $a, array $b) use ($order): int {
        $rank = static fn (array $row): int => $order[(string) ($row['output_type'] ?? '')] ?? count($order);
        return $rank($a) <=> $rank($b);
    });
    $parts = [];
    foreach ($rows as $row) {
        if ((string) ($row['approval_status'] ?? '') !== 'approved') {
            continue;
        }
        $line = rateb_video_copy_text((string) ($row['content'] ?? ''), $prefer, rateb_video_voiceover_limit());
        if ($line !== '') {
            $parts[] = $line;
        }
    }
    return $parts;
}

/** @param array<int,array<string,mixed>> $images @param array<string,mixed> $brand */
function rateb_video_storyboard(array $campaign, array $strategy, array $copyParts, array $images, int $planSeconds, array $brand = [], string $prefer = 'ar'): array
{
    $clips = rateb_video_clips($planSeconds);
    if ($clips === [] || $images === []) {
        return [];
    }
    $motions = [
        'subtle camera push-in, premium commercial lighting, product identity unchanged, no text',
        'slow lateral camera movement, gentle parallax, product identity unchanged, no text',
        'controlled product reveal, cinematic light movement, product identity unchanged, no text',
        'realistic environmental motion, steady premium camera, product identity unchanged, no logos changing, no text',
    ];
    $prefer = $prefer === 'en' ? 'en' : 'ar';
    $spokenCopy = rateb_video_copy_text((string) ($copyParts[0] ?? ''), $prefer, rateb_video_voiceover_limit());
    $copyLine = mb_substr($spokenCopy, 0, 180);
    $purpose = rateb_prompt_plain($strategy['creative_direction'] ?? '', $prefer);
    if ($purpose === '') {
        $purpose = rateb_prompt_plain($strategy['core_message'] ?? '', $prefer);
    }
    if ($purpose === '') {
        $purpose = $copyLine;
    }
    $purpose = mb_substr($purpose, 0, 180);
    $voiceLines = rateb_video_split_lines($spokenCopy !== '' ? $spokenCopy : $purpose, count($clips));
    $ratio = '1:1';
    foreach ($images as $imageRow) {
        if (str_contains((string) ($imageRow['original_name'] ?? ''), '9x16')) {
            $ratio = '9:16';
            break;
        }
    }
    $scenes = [];
    foreach ($clips as $i => $clip) {
        $image = $images[$i % count($images)];
        $motion = $motions[$i % count($motions)];
        $visual = rateb_visual_prompt(
            array_merge([$motion], rateb_visual_lines($campaign, $strategy, $copyLine, $brand, $prefer)),
            'No written text and no logo.',
            400
        );
        $scenes[] = [
            'scene' => $i + 1,
            'visual' => $visual,
            'voiceover' => $voiceLines[$i] ?? $purpose,
            'purpose' => $purpose,
            'image_id' => (int) $image['id'],
            'duration' => $clip,
            'ratio' => $ratio,
            'motion' => $motion,
            'prompt' => $visual,
            'task_id' => '',
            'status' => 'queued',
            'url' => '',
        ];
    }
    return $scenes;
}

function rateb_video_image_data_uri(array $row): string
{
    $path = media_resolve_path($row);
    $bytes = $path === null ? false : file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') {
        throw new RuntimeException('missing_image');
    }
    $jpeg = rateb_video_prompt_jpeg($bytes);
    if (is_string($jpeg)) {
        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }
    $mime = match (true) {
        str_starts_with($bytes, "\xFF\xD8") => 'image/jpeg',
        str_starts_with($bytes, "\x89PNG") => 'image/png',
        str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
        default => '',
    };
    $uri = $mime === '' ? '' : 'data:' . $mime . ';base64,' . base64_encode($bytes);
    if ($uri === '' || strlen($uri) > 5000000) {
        throw new RuntimeException('rejected_image');
    }
    return $uri;
}

function rateb_video_prompt_jpeg(string $bytes): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return null;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    if ($width < 1 || $height < 1) {
        imagedestroy($image);
        return null;
    }
    $canvasW = 720;
    $canvasH = 1280;
    $scale = min($canvasW / $width, $canvasH / $height);
    $nw = max(1, (int) round($width * $scale));
    $nh = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($canvasW, $canvasH);
    $fill = imagecolorallocate($canvas, 12, 12, 16);
    imagefill($canvas, 0, 0, $fill);
    imagecopyresampled(
        $canvas,
        $image,
        (int) (($canvasW - $nw) / 2),
        (int) (($canvasH - $nh) / 2),
        0,
        0,
        $nw,
        $nh,
        $width,
        $height
    );
    imagedestroy($image);
    $quality = 85;
    $jpeg = '';
    do {
        ob_start();
        imagejpeg($canvas, null, $quality);
        $jpeg = (string) ob_get_clean();
        $quality -= 10;
    } while (strlen($jpeg) > 3000000 && $quality >= 45);
    imagedestroy($canvas);
    if ($jpeg === '' || !str_starts_with($jpeg, "\xFF\xD8") || strlen($jpeg) > 3300000) {
        return null;
    }
    return $jpeg;
}

/** @return array<string,mixed> */
function rateb_video_brand(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT brand_name, tone, primary_color, secondary_color FROM brand_kits WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: [];
}

/**
 * @param array<int,array<string,mixed>> $images
 * @return array<int,array<string,mixed>>
 */
function rateb_video_images_product_first(array $images): array
{
    usort($images, static fn (array $a, array $b): int => ((string) ($a['source'] ?? '') === 'ai') <=> ((string) ($b['source'] ?? '') === 'ai'));
    return $images;
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
function rateb_video_start(PDO $pdo, array $campaign, array $strategyRow, array $copyRows, array $images, int $seconds = 5): array
{
    $provider = rateb_video_provider();
    if (!$provider->configured()) {
        return ['ok' => false, 'code' => 'not_configured', 'job_id' => 0];
    }
    if ((string) ($strategyRow['approval_status'] ?? '') !== 'approved') {
        return ['ok' => false, 'code' => 'missing_copy', 'job_id' => 0];
    }
    $approvedCopy = false;
    foreach ($copyRows as $row) {
        if ((string) ($row['approval_status'] ?? '') === 'approved') {
            $approvedCopy = true;
            break;
        }
    }
    if (!$approvedCopy) {
        return ['ok' => false, 'code' => 'missing_copy', 'job_id' => 0];
    }
    if ($images === []) {
        return ['ok' => false, 'code' => 'missing_image', 'job_id' => 0];
    }
    $userId = (int) $campaign['user_id'];
    $campaignId = (int) $campaign['id'];
    $lockKey = 'video-start:' . $campaignId;
    if (!rateb_video_lock($pdo, $lockKey, 10)) {
        return ['ok' => false, 'code' => 'busy', 'job_id' => 0];
    }
    try {
        $active = rateb_video_latest_job($pdo, $campaignId, $userId);
        if ($active && rateb_video_active((string) $active['status'])) {
            return ['ok' => true, 'code' => '', 'job_id' => (int) $active['id']];
        }
        $plan = rateb_user_plan($pdo, $userId);
        $durationCode = rateb_video_duration_code($plan, $seconds);
        if ($durationCode !== '') {
            return ['ok' => false, 'code' => $durationCode, 'job_id' => 0];
        }
        $strategy = rateb_strategy_document((string) $strategyRow['content']) ?? [];
        $prefer = (string) (rateb_output_language($pdo, $campaign)['speech'] ?? 'ar') === 'en' ? 'en' : 'ar';
        $parts = rateb_video_copy_parts($copyRows, $prefer);
        $scenes = rateb_video_storyboard($campaign, $strategy, $parts, $images, $seconds, rateb_video_brand($pdo, $userId), $prefer);
        if ($scenes === [] || rateb_video_valid_scenes($scenes) === null) {
            return ['ok' => false, 'code' => 'invalid', 'job_id' => 0];
        }
        $seconds = 0;
        foreach ($scenes as $scene) {
            $seconds += (int) $scene['duration'];
        }
        $gate = rateb_usage_begin($pdo, $userId, $campaignId, 'video', $seconds);
        if (!$gate['ok']) {
            return ['ok' => false, 'code' => (string) $gate['code'], 'job_id' => 0];
        }
        $usageId = (int) $gate['id'];
        $charge = rateb_reservation_charge($pdo, $usageId, $userId, 'video_storyboard', $campaignId);
        if ($charge !== 'ok') {
            rateb_reservation_settle($pdo, $usageId, $userId, 'failed', 'video_storyboard');
            $code = $charge === 'credits' ? (rateb_feature_locked($userId, 'video_storyboard') ? 'package' : 'credits') : 'provider';
            return ['ok' => false, 'code' => $code, 'job_id' => 0];
        }
        try {
            $insert = $pdo->prepare('INSERT INTO video_jobs (campaign_id, user_id, status, provider, storyboard, usage_id, watermark, approval_status) VALUES (?, ?, \'queued\', ?, ?, ?, ?, \'draft\')');
            $insert->execute([
                $campaignId,
                $userId,
                $provider->name(),
                json_encode($scenes, JSON_UNESCAPED_UNICODE),
                $usageId,
                !empty($gate['watermark']) ? 1 : 0,
            ]);
        } catch (Throwable $error) {
            rateb_reservation_settle($pdo, $usageId, $userId, 'failed', 'video_storyboard');
            throw $error;
        }
        return ['ok' => true, 'code' => '', 'job_id' => (int) $pdo->lastInsertId()];
    } finally {
        rateb_video_unlock($pdo, $lockKey);
    }
}

function rateb_video_active(string $status): bool
{
    return $status === 'queued' || $status === 'processing';
}

/** Seconds without progress after which a queued or processing job is failed and refunded. */
function rateb_video_stale_seconds(): int
{
    return 1200;
}

function rateb_video_valid_task_id(string $taskId): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $taskId) === 1;
}

/**
 * @return array<int,array<string,mixed>>|null the storyboard when every scene is well formed
 */
function rateb_video_valid_scenes(mixed $scenes): ?array
{
    if (!is_array($scenes) || $scenes === [] || count($scenes) > 3 || !array_is_list($scenes)) {
        return null;
    }
    foreach ($scenes as $scene) {
        if (!is_array($scene)) {
            return null;
        }
        $imageId = $scene['image_id'] ?? null;
        $prompt = $scene['prompt'] ?? null;
        $taskId = $scene['task_id'] ?? '';
        $status = $scene['status'] ?? 'queued';
        if (!is_int($imageId) || $imageId < 1
            || !in_array($scene['duration'] ?? null, [5, 10], true)
            || !is_string($prompt) || trim($prompt) === '' || mb_strlen($prompt) > 1000
            || !is_string($taskId) || ($taskId !== '' && !rateb_video_valid_task_id($taskId))
            || !in_array($status, ['queued', 'processing', 'ready'], true)) {
            return null;
        }
    }
    return $scenes;
}

/**
 * Close the job's usage reservation. A reservation that records its own charge settles
 * through rateb_reservation_settle. Jobs created before that (credits_deducted = 0) are
 * refunded only when their separate charge row is proven; otherwise they close unrefunded.
 */
function rateb_video_settle(PDO $pdo, array $job, string $status): bool
{
    $usageId = (int) ($job['usage_id'] ?? 0);
    $userId = (int) ($job['user_id'] ?? 0);
    if ($status === 'failed') {
        $legacy = rateb_video_settle_legacy_failure($pdo, $usageId, $userId);
        if ($legacy !== null) {
            return $legacy;
        }
    }
    return rateb_reservation_settle($pdo, $usageId, $userId, $status, 'video_storyboard');
}

/** Seconds before its reservation within which a pre-reservation video charge must fall. */
function rateb_video_legacy_charge_window(): int
{
    return 300;
}

/**
 * Fail a reservation from before charges were recorded on it. The old start flow wrote a
 * video_storyboard charge row and then, straight after, the video reservation, so the
 * charge counts as proven only when it is the latest such charge for the same user and
 * campaign before the reservation, falls inside the window, and has no other video
 * reservation or refund between them. That pairs each charge with at most one reservation,
 * and the reservation leaves 'reserved' once, so the refund cannot repeat. Without proof
 * the reservation is closed with no refund.
 *
 * @return bool|null null when the reservation is not an open pre-reservation one
 */
function rateb_video_settle_legacy_failure(PDO $pdo, int $usageId, int $userId): ?bool
{
    if ($usageId < 1 || $userId < 1) {
        return null;
    }
    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare("SELECT campaign_id, status, credits_deducted, created_at FROM usage_events WHERE id = ? AND user_id = ? AND operation = 'video' FOR UPDATE");
        $find->execute([$usageId, $userId]);
        $reservation = $find->fetch();
        if (!$reservation || (string) $reservation['status'] !== 'reserved' || (int) $reservation['credits_deducted'] !== 0) {
            $pdo->commit();
            return null;
        }
        $campaignId = (int) ($reservation['campaign_id'] ?? 0);
        $amount = 0;
        if ($campaignId > 0) {
            $charge = $pdo->prepare("SELECT id, credits_deducted FROM usage_events
                WHERE user_id = ? AND campaign_id = ? AND operation = 'video_storyboard' AND event_type = 'video_storyboard'
                  AND status = 'completed' AND credits_deducted > 0 AND id < ?
                  AND created_at >= DATE_SUB(?, INTERVAL ? SECOND)
                ORDER BY id DESC LIMIT 1");
            $charge->execute([$userId, $campaignId, $usageId, (string) $reservation['created_at'], rateb_video_legacy_charge_window()]);
            $row = $charge->fetch();
            if ($row) {
                $between = $pdo->prepare("SELECT COUNT(*) FROM usage_events WHERE user_id = ? AND campaign_id = ? AND id > ? AND id < ? AND operation IN ('video', 'video_storyboard_refund')");
                $between->execute([$userId, $campaignId, (int) $row['id'], $usageId]);
                if ((int) $between->fetchColumn() === 0) {
                    $amount = (int) $row['credits_deducted'];
                }
            }
        }
        $close = $pdo->prepare("UPDATE usage_events SET status = 'failed', credits_deducted = ? WHERE id = ? AND user_id = ? AND status = 'reserved' AND credits_deducted = 0");
        $close->execute([$amount, $usageId, $userId]);
        if ($close->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        if ($amount > 0) {
            $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
            $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, 0)")
                ->execute([$userId, $campaignId, 'video_storyboard_refund', 'video_storyboard_refund']);
        }
        $pdo->commit();
        return $amount > 0;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/** @return bool true when this call moved the job to failed */
function rateb_video_fail(PDO $pdo, array $job, string $code): bool
{
    $update = $pdo->prepare("UPDATE video_jobs SET status = 'failed', error_code = ? WHERE id = ? AND user_id = ? AND status IN ('queued', 'processing')");
    $update->execute([
        $code,
        (int) $job['id'],
        (int) $job['user_id'],
    ]);
    if ($update->rowCount() !== 1) {
        return false;
    }
    rateb_video_settle($pdo, $job, 'failed');
    return true;
}

/** @return array<string,mixed> the job, failed with 'timeout' when it stopped making progress */
function rateb_video_reap_locked(PDO $pdo, array $job): array
{
    if (!rateb_video_active((string) $job['status'])) {
        return $job;
    }
    $stale = $pdo->prepare("SELECT * FROM video_jobs WHERE id = ? AND user_id = ? AND status IN ('queued', 'processing') AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stale->execute([(int) $job['id'], (int) $job['user_id'], rateb_video_stale_seconds()]);
    $row = $stale->fetch();
    if ($row) {
        rateb_video_fail($pdo, $row, 'timeout');
    }
    $fresh = $pdo->prepare('SELECT * FROM video_jobs WHERE id = ? AND user_id = ?');
    $fresh->execute([(int) $job['id'], (int) $job['user_id']]);
    return $fresh->fetch() ?: $job;
}

/** Read-only status path: fail a stalled job unless a worker currently holds it. */
function rateb_video_reap(PDO $pdo, array $job): array
{
    if (!rateb_video_active((string) $job['status'])) {
        return $job;
    }
    $key = 'video-job:' . (int) $job['id'];
    if (!rateb_video_lock($pdo, $key, 0)) {
        return $job;
    }
    try {
        return rateb_video_reap_locked($pdo, $job);
    } finally {
        rateb_video_unlock($pdo, $key);
    }
}

/**
 * Store the final video and mark the job completed in one transaction. A rerun after a
 * crash reuses the job's media row instead of adding another one.
 */
function rateb_video_complete(PDO $pdo, array $job, array $campaign, string $bytes): ?int
{
    $jobId = (int) $job['id'];
    $name = media_safe_download_name('campaign-video-' . $jobId . '.mp4', 'mp4');
    $created = '';
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT status FROM video_jobs WHERE id = ? AND user_id = ? FOR UPDATE');
        $lock->execute([$jobId, (int) $job['user_id']]);
        if (!rateb_video_active((string) $lock->fetchColumn())) {
            $pdo->rollBack();
            return null;
        }
        $existing = $pdo->prepare("SELECT id FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = 'video' AND source = 'ai' AND original_name = ? ORDER BY id LIMIT 1");
        $existing->execute([(int) $campaign['id'], (int) $campaign['user_id'], $name]);
        $mediaId = (int) ($existing->fetchColumn() ?: 0);
        if ($mediaId < 1) {
            $mediaId = media_store_bytes($campaign, 'video', 'ai', $name, 'video/mp4', $bytes);
            $stored = $pdo->prepare('SELECT stored_name FROM campaign_media WHERE id = ?');
            $stored->execute([$mediaId]);
            $created = (string) $stored->fetchColumn();
        }
        $pdo->prepare("UPDATE video_jobs SET status = 'completed', output_media_id = ?, error_code = '' WHERE id = ? AND status IN ('queued', 'processing')")
            ->execute([$mediaId, $jobId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($created !== '') {
            $path = media_resolve_path(['stored_name' => $created, 'campaign_id' => (int) $campaign['id']]);
            if ($path !== null) {
                @unlink($path);
            }
        }
        throw $error;
    }
    rateb_video_settle($pdo, $job, 'completed');
    return $mediaId;
}

/** Called only by the CLI worker while it holds the job lock. */
function rateb_video_advance(PDO $pdo, array $job, array $campaign, ?RatebVideoProvider $provider = null): array
{
    if (!rateb_video_active((string) $job['status'])) {
        return $job;
    }
    $markFailed = static function (string $code) use ($pdo, $job): array {
        rateb_video_fail($pdo, $job, $code);
        $job['status'] = 'failed';
        $job['error_code'] = $code;
        return $job;
    };
    if ((int) $campaign['id'] !== (int) $job['campaign_id'] || (int) $campaign['user_id'] !== (int) $job['user_id']) {
        return $markFailed('invalid');
    }
    $scenes = rateb_video_valid_scenes(json_decode((string) $job['storyboard'], true));
    if ($scenes === null) {
        return $markFailed('invalid');
    }
    $provider ??= rateb_video_provider();
    if (!$provider->configured()) {
        return $markFailed('not_configured');
    }
    $saveScenes = static function (array $scenes) use ($pdo, $job): void {
        $pdo->prepare("UPDATE video_jobs SET status = 'processing', storyboard = ? WHERE id = ? AND status IN ('queued', 'processing')")->execute([
            json_encode($scenes, JSON_UNESCAPED_UNICODE),
            (int) $job['id'],
        ]);
    };
    try {
        foreach ($scenes as $index => $scene) {
            if ((string) ($scene['status'] ?? '') === 'ready') {
                continue;
            }
            $taskId = (string) ($scene['task_id'] ?? '');
            if ($taskId === '') {
                $image = $pdo->prepare('SELECT * FROM campaign_media WHERE id = ? AND campaign_id = ? AND user_id = ? AND kind = \'image\'');
                $image->execute([(int) $scene['image_id'], (int) $campaign['id'], (int) $campaign['user_id']]);
                $row = $image->fetch();
                if (!$row || media_resolve_path($row) === null) {
                    throw new RuntimeException('missing_image');
                }
                try {
                    $created = $provider->generateFromImage(
                        rateb_video_image_data_uri($row),
                        (string) $scene['prompt'],
                        (int) $scene['duration'],
                        rateb_video_ratio()
                    );
                } catch (RuntimeException $providerError) {
                    if ($providerError->getMessage() !== 'rejected_credit') {
                        throw $providerError;
                    }
                    rateb_video_local_clip($campaign, $job, $index, $row, (int) $scene['duration']);
                    $scenes[$index]['status'] = 'ready';
                    $scenes[$index]['local'] = true;
                    $saveScenes($scenes);
                    continue;
                }
                $createdId = (string) ($created['task_id'] ?? '');
                if (!rateb_video_valid_task_id($createdId)) {
                    throw new RuntimeException('provider');
                }
                $scenes[$index]['task_id'] = $createdId;
                $scenes[$index]['status'] = 'processing';
                $saveScenes($scenes);
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
            $saveScenes($scenes);
        }
        $final = rateb_video_compose($pdo, $job, $campaign, $scenes);
        $mediaId = rateb_video_complete($pdo, $job, $campaign, $final);
        if ($mediaId === null) {
            return $job;
        }
        $job['status'] = 'completed';
        $job['output_media_id'] = $mediaId;
        $job['error_code'] = '';
        return $job;
    } catch (RuntimeException $error) {
        if ($error instanceof PDOException) {
            throw $error;
        }
        $message = $error->getMessage();
        $code = in_array($message, ['not_configured', 'auth', 'rejected', 'rejected_image', 'rejected_model', 'rejected_credit', 'timeout', 'provider', 'invalid', 'missing_image', 'storage', 'watermark'], true)
            ? $message
            : (in_array($message, ['Storage unavailable.', 'Unsupported media.'], true) ? 'storage' : 'provider');
        return $markFailed($code);
    }
}

function rateb_video_local_clip(array $campaign, array $job, int $index, array $imageRow, int $seconds): void
{
    $source = media_resolve_path($imageRow);
    $bytes = $source === null ? false : file_get_contents($source);
    $jpeg = is_string($bytes) ? rateb_video_prompt_jpeg($bytes) : null;
    if (!is_string($jpeg)) {
        throw new RuntimeException('rejected_image');
    }
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('storage');
    }
    $still = $dir . '/still-' . (int) $job['id'] . '-' . $index . '.jpg';
    $clip = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
    if (file_put_contents($still, $jpeg, LOCK_EX) === false) {
        throw new RuntimeException('storage');
    }
    $ffmpeg = rateb_video_ffmpeg_path();
    if ($ffmpeg === '') {
        @unlink($still);
        throw new RuntimeException('storage');
    }
    $frames = max(1, $seconds) * 25;
    $ok = rateb_video_run_ffmpeg(rateb_video_clip_command($ffmpeg, $still, $index, $frames, $clip), 240);
    @unlink($still);
    clearstatcache(true, $clip);
    if (!$ok || !is_file($clip) || filesize($clip) < 1000) {
        @unlink($clip);
        throw new RuntimeException('storage');
    }
    chmod($clip, 0600);
}

/** @param array<int,array<string,mixed>> $scenes */
function rateb_video_compose(PDO $pdo, array $job, array $campaign, array $scenes): string
{
    $ffmpeg = rateb_video_ffmpeg_path();
    if ($ffmpeg === '') {
        throw new RuntimeException('storage');
    }
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
    $list = $dir . '/list-' . (int) $job['id'] . '.txt';
    $clips = [];
    foreach ($scenes as $index => $scene) {
        $clip = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
        if (!is_file($clip)) {
            throw new RuntimeException('provider');
        }
        $clips[] = $clip;
    }
    $out = $dir . '/final-' . (int) $job['id'] . '.mp4';
    $mark = (int) $job['watermark'] === 1 ? $dir . '/watermark-' . (int) $job['id'] . '.png' : '';
    try {
        file_put_contents($list, rateb_video_concat_list($clips));
        $voice = $pdo->prepare('SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = \'audio\' ORDER BY id DESC LIMIT 1');
        $voice->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
        $audio = $voice->fetch();
        $audioPath = $audio ? (media_resolve_path($audio) ?? '') : '';
        $audioPath = $audioPath !== '' && is_file($audioPath) ? $audioPath : '';
        if ($mark !== '' && !rateb_video_watermark_png($mark)) {
            throw new RuntimeException('watermark');
        }
        $ok = rateb_video_run_ffmpeg(rateb_video_compose_command($ffmpeg, $list, $audioPath, $mark, $out), 600);
        clearstatcache(true, $out);
        $bytes = is_file($out) ? file_get_contents($out) : false;
    } finally {
        @unlink($list);
        @unlink($out);
        if ($mark !== '') {
            @unlink($mark);
        }
    }
    if (!$ok || !is_string($bytes) || !str_contains(substr($bytes, 0, 32), 'ftyp')) {
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
        'rejected_image' => 'المزوّد رفض الصورة. استخدم صورة JPEG أو PNG أوضح.',
        'rejected_model' => 'موديل الفيديو غير متاح عند المزوّد.',
        'rejected_credit' => 'رصيد مزوّد الفيديو غير كافٍ.',
        'timeout' => 'انتهت مهلة إنشاء الفيديو.',
        'provider' => 'تعذر إنشاء الفيديو.',
        'invalid' => 'مدخلات الفيديو غير صالحة.',
        'missing_image' => 'يلزم صورة حملة قبل إنشاء الفيديو.',
        'missing_copy' => 'اعتمد الاستراتيجية والنص أولاً.',
        'plan_limit' => 'استنفدت حد الفيديو في خطتك.',
        'plan_inactive' => 'الخطة غير متاحة.',
        'video_too_long' => 'مدة خطتك لا تكفي لمشهد الفيديو.',
        'video_paid' => '١٥ و٢٠ و٣٠ ثانية متاحة في الباقات المدفوعة.',
        'video_free_used' => 'استخدمت الفيديوين المجانيين لهذا الشهر.',
        'credits' => 'رصيد النقاط غير كافٍ، اضغط هنا لترقية الباقة',
        'package' => 'هذه الميزة غير متاحة في الباقة المجانية، اضغط هنا لترقية الباقة',
        'storage' => 'تعذر حفظ الفيديو.',
        'watermark' => 'تعذر إضافة علامة RATEB AI على الفيديو.',
        'busy' => 'يجري تجهيز طلب فيديو آخر لهذه الحملة. حاول بعد لحظات.',
    ];
    $en = [
        'not_configured' => 'Video generation is not configured.',
        'auth' => 'The video provider could not be authenticated.',
        'rejected' => 'The provider rejected the video.',
        'rejected_image' => 'The provider rejected the image. Use a clearer JPEG or PNG.',
        'rejected_model' => 'The video model is not available from the provider.',
        'rejected_credit' => 'The video provider has no remaining credit.',
        'timeout' => 'Video generation timed out.',
        'provider' => 'The video could not be generated.',
        'invalid' => 'The video input is invalid.',
        'missing_image' => 'A campaign image is required before video generation.',
        'missing_copy' => 'Approve the strategy and copy first.',
        'plan_limit' => 'The video limit for your plan has been reached.',
        'plan_inactive' => 'The plan is not available.',
        'video_too_long' => 'Your plan duration is too short for a video scene.',
        'video_paid' => '15, 20, and 30 seconds are available on paid plans.',
        'video_free_used' => 'The two free videos for this month have been used.',
        'credits' => 'Not enough credits. Click here to upgrade.',
        'package' => 'This feature is not in the free package. Click here to upgrade.',
        'storage' => 'The video could not be saved.',
        'watermark' => 'The RATEB AI mark could not be applied to the video.',
        'busy' => 'Another video request for this campaign is being prepared. Try again in a moment.',
    ];
    $map = $arabic ? $ar : $en;
    return $map[$code] ?? ($arabic ? 'تعذر إنشاء الفيديو.' : 'The video could not be generated.');
}
