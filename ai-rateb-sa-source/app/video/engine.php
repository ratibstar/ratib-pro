<?php
declare(strict_types=1);

function rateb_video_test_job_id(?int $set = null): int
{
    static $id = 0;
    if ($set !== null) {
        $id = $set > 0 ? $set : 0;
    }
    return $id;
}

require_once __DIR__ . '/provider.php';
require_once __DIR__ . '/faces.php';
require_once __DIR__ . '/../plans.php';

function rateb_video_ratio(): string
{
    return '720:1280';
}

function rateb_video_frame(string $ratio): array
{
    return match ($ratio) {
        '16:9' => [1280, 720, '1280:720'],
        '1:1' => [720, 720, '720:720'],
        default => [720, 1280, '720:1280'],
    };
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
function rateb_video_storyboard(array $campaign, array $strategy, array $copyParts, array $images, int $planSeconds, array $brand = [], string $prefer = 'ar', string $direction = '', string $ratio = '9:16'): array
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
    if (!in_array($ratio, ['1:1', '9:16', '16:9'], true)) {
        $ratio = '9:16';
    }
    $scenes = [];
    foreach ($clips as $i => $clip) {
        $image = $images[$i % count($images)];
        $motion = $motions[$i % count($motions)];
        $reference = (string) ($image['source'] ?? '');
        $vision = $reference === 'upload' && is_array($image['vision'] ?? null) ? $image['vision'] : [];
        $directionLine = trim(preg_replace('/\s+/u', ' ', mb_substr($direction, 0, 240)) ?? '');
        $visual = rateb_visual_prompt(
            array_merge($directionLine === '' ? [] : ['Customer direction: ' . $directionLine], [$motion], rateb_video_scene_lines($vision), rateb_visual_lines($campaign, $strategy, $copyLine, $brand, $prefer)),
            'No written text and no logo.',
            500
        );
        $scenes[] = [
            'scene' => $i + 1,
            'visual' => $visual,
            'voiceover' => $voiceLines[$i] ?? $purpose,
            'purpose' => $purpose,
            'scene_request' => rateb_video_scene_request(),
            'reference' => $vision,
            'shows_saudi_employees' => false,
            'reference_source' => $reference,
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

function rateb_video_scene_request(): string
{
    return 'Planning request: Saudi employees explain the service. Generated frames are not claimed to show them.';
}

/** @param array<string,mixed> $vision @return array<int,string> */
function rateb_video_scene_lines(array $vision): array
{
    $summary = trim((string) ($vision['summary'] ?? ''));
    if ($summary === '') {
        return [];
    }
    $join = static function (mixed $value): string {
        if (!is_array($value)) {
            return '';
        }
        $parts = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $parts[] = $item;
            }
        }
        return implode(', ', $parts);
    };
    $bits = ['Reference: ' . $summary];
    foreach ([
        'Subjects' => $join($vision['subjects'] ?? []),
        'Colors' => $join($vision['colors'] ?? []),
        'Visible text' => $join($vision['visible_text'] ?? []),
        'Products' => $join($vision['products'] ?? []),
        'Setting' => trim((string) ($vision['setting'] ?? '')),
        'Brand cues' => $join($vision['brand_cues'] ?? []),
    ] as $label => $value) {
        if ($value !== '') {
            $bits[] = $label . ': ' . $value;
        }
    }
    return [rateb_clip_prompt_line(implode('. ', $bits), 220)];
}

/**
 * Copy stored upload analysis onto matching upload rows.
 * The media source is left unchanged.
 *
 * @param array<int,array<string,mixed>> $images
 * @return array<int,array<string,mixed>>
 */
function rateb_video_apply_vision(array $images, ?string $sourceMedia): array
{
    $decoded = json_decode((string) $sourceMedia, true);
    $byName = [];
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item) || (string) ($item['analysis'] ?? '') !== 'ready') {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '' && trim((string) ($item['summary'] ?? '')) !== '') {
                $byName[$name] = $item;
            }
        }
    }
    foreach ($images as $index => $image) {
        if (!is_array($image) || (string) ($image['source'] ?? '') !== 'upload') {
            continue;
        }
        $match = $byName[trim((string) ($image['original_name'] ?? ''))] ?? null;
        if ($match === null) {
            continue;
        }
        $vision = ['summary' => trim((string) $match['summary'])];
        foreach (['subjects', 'colors', 'visible_text', 'products', 'brand_cues'] as $key) {
            $vision[$key] = is_array($match[$key] ?? null) ? $match[$key] : [];
        }
        $vision['setting'] = trim((string) ($match['setting'] ?? ''));
        $images[$index]['vision'] = $vision;
    }
    return $images;
}

function rateb_video_image_data_uri(array $row): string
{
    $path = media_absolute_path($row);
    $bytes = file_get_contents($path);
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
function rateb_video_start(PDO $pdo, array $campaign, array $strategyRow, array $copyRows, array $images, int $seconds = 5, string $direction = '', string $ratio = '9:16'): array
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
    $active = rateb_video_latest_job($pdo, (int) $campaign['id'], (int) $campaign['user_id']);
    if ($active && in_array((string) $active['status'], ['queued', 'processing'], true)) {
        return ['ok' => true, 'code' => '', 'job_id' => (int) $active['id']];
    }
    $plan = rateb_user_plan($pdo, (int) $campaign['user_id']);
    $durationCode = rateb_video_duration_code($plan, $seconds);
    if ($durationCode !== '') {
        return ['ok' => false, 'code' => $durationCode, 'job_id' => 0];
    }
    $strategy = rateb_strategy_document((string) $strategyRow['content']) ?? [];
    $prefer = (string) (rateb_output_language($pdo, $campaign)['speech'] ?? 'ar') === 'en' ? 'en' : 'ar';
    $parts = rateb_video_copy_parts($copyRows, $prefer);
    $brief = $pdo->prepare('SELECT source_media FROM campaign_briefs WHERE campaign_id = ? AND user_id = ?');
    $brief->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
    $images = rateb_video_apply_vision($images, (string) ($brief->fetchColumn() ?: ''));
    $scenes = rateb_video_storyboard($campaign, $strategy, $parts, $images, $seconds, rateb_video_brand($pdo, (int) $campaign['user_id']), $prefer, $direction, $ratio);
    if ($scenes === []) {
        return ['ok' => false, 'code' => 'invalid', 'job_id' => 0];
    }
    if (rateb_feature_locked((int) $campaign['user_id'], 'video_storyboard')) {
        return ['ok' => false, 'code' => 'package', 'job_id' => 0];
    }
    $balance = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
    $balance->execute([(int) $campaign['user_id']]);
    if ((int) $balance->fetchColumn() < rateb_get_feature_cost('video_storyboard')) {
        return ['ok' => false, 'code' => 'credits', 'job_id' => 0];
    }
    $chargeId = 0;
    $seconds = 0;
    foreach ($scenes as $scene) {
        $seconds += (int) $scene['duration'];
    }
    $gate = rateb_usage_begin($pdo, (int) $campaign['user_id'], (int) $campaign['id'], 'video', $seconds);
    if (!$gate['ok']) {
        rateb_video_refund_prejob($pdo, (int) $campaign['user_id'], (int) $campaign['id'], $chargeId);
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

function rateb_video_refund_prejob(PDO $pdo, int $userId, int $campaignId, int $chargeId): bool
{
    if ($userId < 1 || $campaignId < 1 || $chargeId < 1) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $charge = $pdo->prepare("SELECT credits_deducted FROM usage_events WHERE id = ? AND user_id = ? AND campaign_id = ? AND operation = 'video_storyboard' AND status = 'completed' AND credits_deducted > 0");
        $charge->execute([$chargeId, $userId, $campaignId]);
        $amount = (int) ($charge->fetchColumn() ?: 0);
        if ($amount < 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, ?, ?, 1, \'completed\', ?, 0, ?)')->execute([
            $userId,
            $campaignId,
            'video_storyboard_refund',
            'video_storyboard_refund',
            'video_storyboard_charge:' . $chargeId . ':refund',
        ]);
        $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $error->getMessage();
        if (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate') || str_contains($message, 'constraint')) {
            return false;
        }
        throw $error;
    }
}

function rateb_video_save_storyboard(PDO $pdo, array $job, array $scenes): bool
{
    $token = (string) ($job['lock_token'] ?? '');
    if ($token === '') {
        return false;
    }
    $update = $pdo->prepare("UPDATE video_jobs SET status = 'processing', storyboard = ? WHERE id = ? AND user_id = ? AND lock_token = ? AND output_media_id IS NULL AND status NOT IN ('completed', 'failed')");
    $update->execute([
        json_encode($scenes, JSON_UNESCAPED_UNICODE),
        (int) $job['id'],
        (int) $job['user_id'],
        $token,
    ]);
    return $update->rowCount() === 1;
}

function rateb_video_output_name(int $jobId): string
{
    return 'hold-video-job-' . $jobId . '.mp4';
}

function rateb_video_find_output(PDO $pdo, int $campaignId, int $userId, int $jobId): int
{
    $stmt = $pdo->prepare("SELECT id FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = 'video' AND source = 'ai' AND original_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$campaignId, $userId, rateb_video_output_name($jobId)]);
    $found = (int) ($stmt->fetchColumn() ?: 0);
    if ($found > 0) {
        return $found;
    }
    $stmt->execute([$campaignId, $userId, 'video-job-' . $jobId . '.mp4']);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function rateb_video_drop_output(PDO $pdo, int $mediaId): void
{
    $stmt = $pdo->prepare('SELECT campaign_id, stored_name FROM campaign_media WHERE id = ?');
    $stmt->execute([$mediaId]);
    $row = $stmt->fetch();
    $delete = $pdo->prepare('DELETE FROM campaign_media WHERE id = ? AND NOT EXISTS (SELECT 1 FROM video_jobs WHERE output_media_id = ?)');
    $delete->execute([$mediaId, $mediaId]);
    if ($delete->rowCount() !== 1 || !is_array($row)) {
        return;
    }
    $name = (string) ($row['stored_name'] ?? '');
    if (preg_match('/^[a-f0-9]{32}\.mp4$/', $name) === 1) {
        $path = MEDIA_ROOT . '/' . (int) $row['campaign_id'] . '/' . $name;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function rateb_video_owns(array $job, int $now): bool
{
    if (in_array((string) ($job['status'] ?? ''), ['completed', 'failed'], true)) {
        return false;
    }
    if ((int) ($job['output_media_id'] ?? 0) > 0 || (string) ($job['approval_status'] ?? '') === 'approved') {
        return false;
    }
    $token = (string) ($job['lock_token'] ?? '');
    $until = strtotime((string) ($job['locked_until'] ?? '')) ?: 0;
    return $token === '' || $until <= $now;
}

function rateb_video_claim(PDO $pdo, int $jobId, string $token, int $now): bool
{
    $until = date('Y-m-d H:i:s', $now + 900);
    $nowText = date('Y-m-d H:i:s', $now);
    $update = $pdo->prepare("UPDATE video_jobs SET status = 'processing', lock_token = ?, locked_until = ?, attempts = attempts + 1 WHERE id = ? AND output_media_id IS NULL AND attempts < ? AND status NOT IN ('completed', 'failed') AND approval_status <> 'approved' AND (lock_token IS NULL OR lock_token = ? OR locked_until < ?)");
    $update->execute([$token, $until, $jobId, rateb_video_attempt_limit(), $token, $nowText]);
    return $update->rowCount() === 1;
}

function rateb_video_fail(PDO $pdo, array $job, string $code): void
{
    $token = (string) ($job['lock_token'] ?? '');
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE video_jobs SET status = 'failed', error_code = ? WHERE id = ? AND user_id = ? AND output_media_id IS NULL AND status NOT IN ('failed', 'completed') AND approval_status <> 'approved' AND (lock_token IS NULL OR lock_token = ?)");
        $update->execute([
            $code,
            (int) $job['id'],
            (int) $job['user_id'],
            $token,
        ]);
        if ($update->rowCount() !== 1) {
            $pdo->commit();
            return;
        }
        rateb_usage_finish($pdo, (int) ($job['usage_id'] ?? 0), 'failed');
        $campaignId = (int) ($job['campaign_id'] ?? 0);
        $charges = $pdo->prepare("SELECT id, credits_deducted FROM usage_events WHERE user_id = ? AND campaign_id = ? AND operation = 'video_storyboard' AND status = 'completed' AND credits_deducted > 0 ORDER BY id");
        $charges->execute([(int) $job['user_id'], $campaignId]);
        $amount = 0;
        $chargeId = 0;
        foreach ($charges->fetchAll() as $charge) {
            $taken = $pdo->prepare('SELECT id FROM usage_events WHERE idempotency_key = ?');
            $taken->execute(['video_storyboard_charge:' . (int) $charge['id'] . ':refund']);
            if ($taken->fetchColumn() === false) {
                $chargeId = (int) $charge['id'];
                $amount = (int) $charge['credits_deducted'];
                break;
            }
        }
        if ($chargeId < 1 || $amount < 1) {
            $pdo->commit();
            return;
        }
        try {
            $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, ?, ?, 1, \'completed\', ?, 0, ?)')->execute([
                (int) $job['user_id'],
                $campaignId,
                'video_storyboard_refund',
                'video_storyboard_refund',
                'video_storyboard_charge:' . $chargeId . ':refund',
            ]);
            $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, (int) $job['user_id']]);
        } catch (Throwable $duplicate) {
            $message = $duplicate->getMessage();
            if (!str_contains($message, 'UNIQUE') && !str_contains($message, 'Duplicate') && !str_contains($message, 'constraint')) {
                throw $duplicate;
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rateb_video_complete(PDO $pdo, array $job, int $mediaId): bool
{
    $token = (string) ($job['lock_token'] ?? '');
    $update = $pdo->prepare("UPDATE video_jobs SET status = 'completed', output_media_id = ?, error_code = '' WHERE id = ? AND user_id = ? AND status = 'processing' AND output_media_id IS NULL AND approval_status <> 'approved' AND ((lock_token IS NULL AND ? = '') OR lock_token = ?)");
    $update->execute([
        $mediaId,
        (int) $job['id'],
        (int) $job['user_id'],
        $token,
        $token,
    ]);
    if ($update->rowCount() === 1) {
        rateb_usage_finish($pdo, (int) ($job['usage_id'] ?? 0), 'completed');
        return true;
    }
    rateb_video_drop_output($pdo, $mediaId);
    return false;
}

function rateb_video_advance(PDO $pdo, array $job, array $campaign): array
{
    if ((string) $job['status'] === 'completed' || (string) $job['status'] === 'failed' || (int) ($job['output_media_id'] ?? 0) > 0 || (string) ($job['approval_status'] ?? '') === 'approved') {
        return $job;
    }
    $now = time();
    $updated = strtotime((string) ($job['updated_at'] ?? 'now')) ?: $now;
    if ($updated < $now - 1200 && (string) $job['status'] !== 'queued' && rateb_video_owns($job, $now)) {
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
    $token = bin2hex(random_bytes(16));
    if (!rateb_video_claim($pdo, (int) $job['id'], $token, $now)) {
        return $job;
    }
    $job['lock_token'] = $token;
    $job['status'] = 'processing';
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
                $image = $pdo->prepare('SELECT * FROM campaign_media WHERE id = ? AND campaign_id = ? AND user_id = ? AND kind = \'image\'');
                $image->execute([(int) $scene['image_id'], (int) $campaign['id'], (int) $campaign['user_id']]);
                $row = $image->fetch();
                if (!$row) {
                    throw new RuntimeException('missing_image');
                }
                try {
                    $frame = rateb_video_frame((string) ($scene['ratio'] ?? '9:16'));
                    $created = $provider->generateFromImage(
                        rateb_video_image_data_uri($row),
                        (string) $scene['prompt'],
                        (int) $scene['duration'],
                        $frame[2]
                    );
                } catch (RuntimeException $providerError) {
                    if (!in_array($providerError->getMessage(), ['rejected_credit', 'rejected', 'rejected_model'], true)) {
                        throw $providerError;
                    }
                    $pictures = $pdo->prepare('SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = \'image\' ORDER BY id');
                    $pictures->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
                    $pictureRows = $pictures->fetchAll();
                    rateb_video_local_clip($campaign, $job, $index, $row, (int) $scene['duration'], $pictureRows === [] ? [$row] : $pictureRows, (string) ($scene['ratio'] ?? '9:16'));
                    $scenes[$index]['status'] = 'ready';
                    $scenes[$index]['local'] = true;
                    if (!rateb_video_save_storyboard($pdo, $job, $scenes)) {
                        return $job;
                    }
                    continue;
                }
                $scenes[$index]['task_id'] = $created['task_id'];
                $scenes[$index]['status'] = 'processing';
                if (!rateb_video_save_storyboard($pdo, $job, $scenes)) {
                    return $job;
                }
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
            rateb_video_protect_file($clipPath);
            $scenes[$index]['status'] = 'ready';
            $scenes[$index]['url'] = '';
            if (!rateb_video_save_storyboard($pdo, $job, $scenes)) {
                return $job;
            }
        }
        $final = rateb_video_compose($pdo, $job, $campaign, $scenes);
        $mediaId = rateb_video_find_output($pdo, (int) $campaign['id'], (int) $campaign['user_id'], (int) $job['id']);
        if ($mediaId < 1) {
            $mediaId = media_store_bytes($campaign, 'video', 'ai', rateb_video_output_name((int) $job['id']), 'video/mp4', $final);
        }
        if (!rateb_video_complete($pdo, $job, $mediaId)) {
            return $job;
        }
        $job['status'] = 'completed';
        $job['output_media_id'] = $mediaId;
        $job['error_code'] = '';
        return $job;
    } catch (RuntimeException $error) {
        $code = in_array($error->getMessage(), ['not_configured', 'auth', 'rejected', 'rejected_image', 'rejected_model', 'rejected_credit', 'timeout', 'provider', 'invalid', 'missing_image', 'storage', 'watermark', 'face_protection'], true)
            ? $error->getMessage()
            : 'provider';
        rateb_video_fail($pdo, $job, $code);
        $job['status'] = 'failed';
        $job['error_code'] = $code;
        return $job;
    }
}

function rateb_video_local_clip(array $campaign, array $job, int $index, array $imageRow, int $seconds, array $imageRows = [], string $ratio = '9:16'): void
{
    $rows = [];
    foreach ($imageRows === [] ? [$imageRow] : $imageRows as $candidate) {
        if (is_array($candidate)) {
            $rows[] = $candidate;
        }
    }
    if ($rows === []) {
        throw new RuntimeException('rejected_image');
    }
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('storage');
    }
    $clip = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
    $parts = [];
    $totalFrames = max(1, $seconds) * 25;
    $share = max(8, intdiv($totalFrames, count($rows)));
    foreach ($rows as $partIndex => $row) {
        $frames = $partIndex === count($rows) - 1 ? max(8, $totalFrames - ($share * (count($rows) - 1))) : $share;
        $part = $dir . '/' . (int) $job['id'] . '-' . $index . '-p' . $partIndex . '.mp4';
        rateb_video_local_still($campaign, $job, $partIndex, $row, $frames, $part, $ratio);
        $parts[] = $part;
    }
    if (count($parts) === 1) {
        if (!rename($parts[0], $clip) && !is_file($clip)) {
            throw new RuntimeException('storage');
        }
    } else {
        $list = $dir . '/slides-' . (int) $job['id'] . '-' . $index . '.txt';
        $lines = '';
        foreach ($parts as $part) {
            $lines .= 'file ' . escapeshellarg($part) . "\n";
        }
        file_put_contents($list, $lines);
        $ffmpeg = rateb_video_ffmpeg();
        $cmd = escapeshellarg($ffmpeg) . ' -y -f concat -safe 0 -i ' . escapeshellarg($list) . ' -c copy ' . escapeshellarg($clip);
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('storage');
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($list);
        foreach ($parts as $part) {
            @unlink($part);
        }
        if ($code !== 0 || !is_file($clip) || filesize($clip) < 1000) {
            throw new RuntimeException('storage');
        }
    }
    chmod($clip, 0600);
    rateb_video_face_mark($clip, 0, 'local');
}

function rateb_video_local_still(array $campaign, array $job, int $index, array $imageRow, int $frames, string $clip, string $ratio = '9:16'): void
{
    $bytes = file_get_contents(media_absolute_path($imageRow));
    $jpeg = is_string($bytes) ? rateb_video_prompt_jpeg($bytes) : null;
    if (!is_string($jpeg)) {
        throw new RuntimeException('rejected_image');
    }
    $dir = dirname($clip);
    $still = $dir . '/still-' . (int) $job['id'] . '-' . $index . '.jpg';
    if (file_put_contents($still, $jpeg, LOCK_EX) === false) {
        throw new RuntimeException('storage');
    }
    $frame = rateb_video_frame($ratio);
    $wide = $frame[0] * 2;
    $tall = $frame[1] * 2;
    $zoom = $index % 2 === 0 ? "min(zoom+0.0009,1.15)" : "if(eq(on,0),1.15,max(zoom-0.0009,1))";
    $filter = 'scale=' . $wide . ':' . $tall . ',zoompan=z=' . "'" . $zoom . "'" . ':d=' . $frames
        . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=" . $frame[0] . 'x' . $frame[1] . ':fps=25,format=yuv420p';
    $ffmpeg = rateb_video_ffmpeg();
    $cmd = escapeshellarg($ffmpeg) . ' -y -i ' . escapeshellarg($still) . ' -vf ' . escapeshellarg($filter)
        . ' -frames:v ' . $frames . ' -c:v libx264 -preset veryfast -crf 23 -an ' . escapeshellarg($clip);
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
    @unlink($still);
    if ($code !== 0 || !is_file($clip) || filesize($clip) < 1000) {
        throw new RuntimeException('storage');
    }
    chmod($clip, 0600);
    rateb_video_face_mark($clip, 0, 'local');
}

/** @param array<int,array<string,mixed>> $scenes */
function rateb_video_compose(PDO $pdo, array $job, array $campaign, array $scenes): string
{
    $ffmpeg = rateb_video_ffmpeg();
    $dir = MEDIA_ROOT . '/' . (int) $campaign['id'] . '/clips';
    $list = $dir . '/list-' . (int) $job['id'] . '.txt';
    $lines = '';
    $clips = [];
    foreach ($scenes as $index => $scene) {
        $clip = $dir . '/' . (int) $job['id'] . '-' . $index . '.mp4';
        if (!is_file($clip)) {
            throw new RuntimeException('provider');
        }
        $clips[] = $clip;
        $lines .= "file " . escapeshellarg($clip) . "\n";
    }
    rateb_video_face_marks_ready($clips);
    file_put_contents($list, $lines);
    $out = $dir . '/final-' . (int) $job['id'] . '.mp4';
    $voice = $pdo->prepare('SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = \'audio\' AND original_name NOT LIKE \'hold-%\' ORDER BY id DESC LIMIT 1');
    $voice->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
    $audio = $voice->fetch();
    $audioPath = $audio ? media_absolute_path($audio) : '';
    $frame = rateb_video_frame((string) (($scenes[0]['ratio'] ?? '9:16')));
    $filter = 'scale=' . $frame[0] . ':' . $frame[1] . ':force_original_aspect_ratio=decrease,pad=' . $frame[0] . ':' . $frame[1] . ':(ow-iw)/2:(oh-ih)/2,format=yuv420p';
    $wmPng = '';
    if ((int) $job['watermark'] === 1) {
        if (rateb_video_ffmpeg_has_filter('drawtext')) {
            $filter .= rateb_video_watermark_filter();
        } else {
            $wmPng = $dir . '/wm-' . (int) $job['id'] . '.png';
            rateb_video_watermark_png($wmPng);
        }
    }
    $cmd = escapeshellarg($ffmpeg) . ' -y -f concat -safe 0 -i ' . escapeshellarg($list);
    if ($wmPng !== '') {
        $cmd .= ' -i ' . escapeshellarg($wmPng);
    }
    $audioIndex = $wmPng !== '' ? 2 : 1;
    if ($audioPath !== '' && is_file($audioPath)) {
        $cmd .= ' -i ' . escapeshellarg($audioPath);
        $videoFilter = $wmPng !== '' ? '[0:v]' . $filter . '[base];[base][1:v]overlay=W-w-24:H-h-24[v]' : '[0:v]' . $filter . '[v]';
        $cmd .= ' -filter_complex ' . escapeshellarg($videoFilter);
        $cmd .= ' -map [v] -map ' . $audioIndex . ':a -shortest';
    } elseif ($wmPng !== '') {
        $cmd .= ' -filter_complex ' . escapeshellarg('[0:v]' . $filter . '[base];[base][1:v]overlay=W-w-24:H-h-24[v]');
        $cmd .= ' -map [v] -an';
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
    if ($code !== 0 || !is_file($out)) {
        throw new RuntimeException((int) $job['watermark'] === 1 ? 'watermark' : 'storage');
    }
    $localOnly = $scenes !== [];
    foreach ($scenes as $scene) {
        if (empty($scene['local'])) {
            $localOnly = false;
            break;
        }
    }
    if ($localOnly) {
        rateb_video_face_mark($out, 0, 'local');
    } else {
        rateb_video_protect_file($out);
    }
    $bytes = file_get_contents($out);
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
        'face_protection' => 'تعذر تأكيد تمويه الوجوه على كل المشاهد، وتوقف إنشاء الفيديو.',
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
        'face_protection' => 'Face protection could not be confirmed on every scene, so the video was stopped.',
    ];
    $map = $arabic ? $ar : $en;
    return $map[$code] ?? ($arabic ? 'تعذر إنشاء الفيديو.' : 'The video could not be generated.');
}

function rateb_video_ffmpeg_has_filter(string $name): bool
{
    if (!function_exists('proc_open') || !preg_match('/^[a-z0-9_]+$/', $name)) {
        return false;
    }
    $process = @proc_open([rateb_video_ffmpeg(), '-hide_banner', '-filters'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return false;
    }
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return is_string($out) && preg_match('/\b' . $name . '\b/', $out) === 1;
}

function rateb_video_watermark_filter(): string
{
    if (!rateb_video_ffmpeg_has_filter('drawtext')) {
        throw new RuntimeException('watermark');
    }
    return ",drawtext=text='RATEB AI':fontsize=28:fontcolor=white:box=1:boxcolor=black@0.45:x=w-tw-24:y=h-th-24";
}

function rateb_video_watermark_png(string $path): void
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
        throw new RuntimeException('watermark');
    }
    $image = imagecreatetruecolor(180, 48);
    if ($image === false) {
        throw new RuntimeException('watermark');
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $background = imagecolorallocatealpha($image, 24, 16, 48, 60);
    $foreground = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 179, 47, $background);
    imagestring($image, 5, 28, 16, 'RATEB AI', $foreground);
    $saved = imagepng($image, $path);
    imagedestroy($image);
    if ($saved !== true) {
        throw new RuntimeException('watermark');
    }
    chmod($path, 0600);
}

function rateb_video_worker_next(PDO $pdo, int $now): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM video_jobs WHERE output_media_id IS NULL AND approval_status <> 'approved' AND status NOT IN ('completed', 'failed') AND (status = 'queued' OR lock_token IS NULL OR locked_until IS NULL OR locked_until < ?) ORDER BY id LIMIT 1");
    $stmt->execute([date('Y-m-d H:i:s', $now)]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function rateb_video_attempt_limit(): int
{
    return 3;
}

function rateb_video_worker_pdo(): PDO
{
    $config = require dirname(__DIR__, 2) . '/config/database.php';
    if ((string) ($config['password'] ?? '') === '') {
        throw new RuntimeException('database_unavailable');
    }
    return new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=' . $config['charset'],
        (string) $config['username'],
        (string) $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function rateb_video_worker_reconcile(PDO $pdo, int $now): array
{
    $done = ['settled' => 0, 'orphans' => 0];
    $open = $pdo->query("SELECT j.user_id, j.status, j.usage_id FROM video_jobs j INNER JOIN usage_events u ON u.id = j.usage_id AND u.user_id = j.user_id WHERE j.status IN ('completed', 'failed') AND u.status = 'reserved' AND u.operation = 'video' ORDER BY j.id LIMIT 50");
    foreach ($open->fetchAll() as $job) {
        $status = (string) $job['status'] === 'completed' ? 'completed' : 'failed';
        if ($status === 'failed') {
            rateb_reservation_settle($pdo, (int) $job['usage_id'], (int) $job['user_id'], 'failed', 'video_storyboard');
        }
        rateb_usage_finish($pdo, (int) $job['usage_id'], $status);
        $done['settled']++;
    }
    $stale = $pdo->prepare("SELECT u.id, u.user_id FROM usage_events u WHERE u.operation = 'video' AND u.status = 'reserved' AND u.credits_deducted > 0 AND u.created_at < ? AND NOT EXISTS (SELECT 1 FROM video_jobs j WHERE j.usage_id = u.id) ORDER BY u.id LIMIT 50");
    $stale->execute([date('Y-m-d H:i:s', $now - 1200)]);
    foreach ($stale->fetchAll() as $row) {
        if (rateb_reservation_settle($pdo, (int) $row['id'], (int) $row['user_id'], 'failed', 'video_storyboard')) {
            $done['orphans']++;
        }
    }
    return $done;
}

function rateb_video_worker_one(PDO $pdo, int $jobId, int $now): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM video_jobs WHERE id = ? AND output_media_id IS NULL AND approval_status <> 'approved' AND status NOT IN ('completed', 'failed') AND (status = 'queued' OR lock_token IS NULL OR locked_until IS NULL OR locked_until < ?)");
    $stmt->execute([$jobId, date('Y-m-d H:i:s', $now)]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function rateb_video_worker_run(PDO $pdo, int $now): array
{
    $onlyJobId = rateb_video_test_job_id();
    $reconciled = $onlyJobId > 0 ? ['settled' => 0, 'orphans' => 0] : rateb_video_worker_reconcile($pdo, $now);
    $job = $onlyJobId > 0 ? rateb_video_worker_one($pdo, $onlyJobId, $now) : rateb_video_worker_next($pdo, $now);
    if ($job === null) {
        return ['ran' => false, 'status' => 'empty', 'settled' => $reconciled['settled'], 'orphans' => $reconciled['orphans']];
    }
    if ((int) ($job['attempts'] ?? 0) >= rateb_video_attempt_limit()) {
        rateb_video_fail($pdo, $job, 'attempts');
        return ['ran' => true, 'status' => 'failed', 'id' => (int) $job['id'], 'settled' => $reconciled['settled'], 'orphans' => $reconciled['orphans']];
    }
    $campaign = ['id' => (int) $job['campaign_id'], 'user_id' => (int) $job['user_id']];
    $done = rateb_video_advance($pdo, $job, $campaign);
    return ['ran' => true, 'status' => (string) ($done['status'] ?? ''), 'id' => (int) $job['id'], 'settled' => $reconciled['settled'], 'orphans' => $reconciled['orphans']];
}
