<?php
declare(strict_types=1);

/**
 * Offline/live CLI: generate one AI image for a campaign using the same
 * marketing prompt path + Horde Flux, then store it if relevance passes.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/plans.php';
require_once dirname(__DIR__) . '/app/media/library.php';

$campaignId = (int) ($argv[1] ?? 80);
$userId = (int) ($argv[2] ?? 66);
$pdo = db();

$campaignStmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$campaignStmt->execute([$campaignId, $userId]);
$campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) {
    fwrite(STDERR, "campaign_missing\n");
    exit(1);
}

$briefStmt = $pdo->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
$briefStmt->execute([$campaignId, $userId]);
$brief = $briefStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$outputs = $pdo->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC');
$outputs->execute([$campaignId]);
$outputRows = $outputs->fetchAll(PDO::FETCH_ASSOC);
$facts = rateb_image_brief($campaign, $brief, $outputRows);
$plan = rateb_image_plan_recover_marketing($facts);
if ($plan === null) {
    $grounded = rateb_image_plan_ground_marketing_service([
        'sufficient' => true,
        'category' => 'digital_service',
        'subject' => 'marketing studio desk',
        'elements' => ['printed advertising posters'],
        'setting' => 'studio desk',
        'mood' => 'professional',
        'avoid' => [],
        'evidence' => mb_substr((string) ($facts['description'] ?? $facts['product'] ?? ''), 0, 120),
    ], $facts);
    $plan = $grounded;
}
$cfg = require dirname(__DIR__) . '/config/ai.php';
$hordeKey = trim((string) ($cfg['horde_api_key'] ?? ''));
$modelsHttp = rateb_cli_horde('GET', 'https://aihorde.net/api/v2/status/models?type=image', null, $hordeKey);
$modelList = json_decode($modelsHttp['body'], true);
$counts = [];
if (is_array($modelList)) {
    foreach ($modelList as $row) {
        if (is_array($row) && is_string($row['name'] ?? null)) {
            $counts[$row['name']] = (int) ($row['count'] ?? 0);
        }
    }
}
$preferred = array_values(array_filter(
    rateb_image_model_preferences('digital_service'),
    static fn(string $name): bool => stripos($name, 'flux') !== false
));
if ($preferred === []) {
    $preferred = rateb_image_model_preferences('digital_service');
}
$models = rateb_image_select_models($preferred, $counts, 3);
if ($models === []) {
    // Prefer waiting for Flux over accepting SD gibberish poster clones.
    $models = rateb_image_select_models(rateb_image_model_preferences('digital_service'), $counts, 3);
}
if ($models === []) {
    fwrite(STDERR, "no_workers\n");
    exit(2);
}
echo 'models=' . implode(',', $models) . PHP_EOL;

$mediaId = 0;
$modelName = '';
$caption = '';
$relevance = [];
$attempts = (int) ($argv[3] ?? 5);
for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    $prompt = rateb_image_prompt_compose($plan, $facts, '1:1', $attempt - 1);
    echo "attempt={$attempt} prompt={$prompt}\n";
    $params = rateb_image_generation_params(512, 512, $models);
    $payloadData = [
        'prompt' => $prompt,
        'params' => $params,
        'nsfw' => false,
        'censor_nsfw' => true,
        'r2' => true,
        'trusted_workers' => false,
        'models' => $models,
    ];
    // Marketing: Flux txt2img only — marketing seed img2img drifts to gift-card/cactus clones.
    echo "source=txt2img\n";
    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

    $submit = rateb_cli_horde('POST', 'https://aihorde.net/api/v2/generate/async', $payload, $hordeKey);
    $job = json_decode($submit['body'], true);
    $jobId = is_array($job) ? strtolower((string) ($job['id'] ?? '')) : '';
    if ($submit['http'] !== 202 || preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
        echo 'submit_failed http=' . $submit['http'] . PHP_EOL;
        continue;
    }
    echo "job_id={$jobId}\n";

    $deadline = time() + 60;
    $imageUrl = '';
    $modelName = '';
    $idleZeroTicks = 0;
    while (time() < $deadline) {
        sleep(2);
        $check = rateb_cli_horde('GET', 'https://aihorde.net/api/v2/generate/check/' . rawurlencode($jobId), null, $hordeKey);
        $state = json_decode($check['body'], true);
        $wait = is_array($state) ? (int) ($state['wait_time'] ?? 0) : 0;
        $queue = is_array($state) ? (int) ($state['queue_position'] ?? 0) : 0;
        $processing = !empty($state['processing']);
        echo 'check wait=' . $wait . ' queue=' . $queue . ' done=' . (!empty($state['done']) ? '1' : '0') . PHP_EOL;
        // Hard cap: abort if Horde says >1 minute or deep queue.
        if ($wait >= 60 || $queue >= 20) {
            rateb_cli_horde('DELETE', 'https://aihorde.net/api/v2/generate/status/' . rawurlencode($jobId), null, $hordeKey);
            echo "queue_too_long\n";
            break;
        }
        if (!empty($state['faulted'])) {
            echo "faulted\n";
            break;
        }
        // Expired/cancelled jobs often report wait=0 queue=0 forever without done/faulted.
        if ($wait === 0 && $queue === 0 && !$processing && empty($state['done'])) {
            $idleZeroTicks++;
            if ($idleZeroTicks >= 8) {
                echo "job_abandoned\n";
                break;
            }
        } else {
            $idleZeroTicks = 0;
        }
        if (!empty($state['done'])) {
            $status = rateb_cli_horde('GET', 'https://aihorde.net/api/v2/generate/status/' . rawurlencode($jobId), null, $hordeKey);
            $result = json_decode($status['body'], true);
            if (is_array($result) && is_array($result['generations'][0] ?? null)) {
                $imageUrl = (string) ($result['generations'][0]['img'] ?? '');
                $modelName = (string) ($result['generations'][0]['model'] ?? '');
            }
            break;
        }
    }
    if ($imageUrl === '' || !rateb_cli_image_url_allowed($imageUrl)) {
        echo "no_image_url\n";
        continue;
    }

    $captionSubmit = rateb_cli_horde('POST', 'https://aihorde.net/api/v2/interrogate/async', json_encode([
        'source_image' => $imageUrl,
        'forms' => [['name' => 'caption']],
    ], JSON_UNESCAPED_UNICODE), $hordeKey);
    $captionJob = json_decode($captionSubmit['body'], true);
    $validationId = is_array($captionJob) ? strtolower((string) ($captionJob['id'] ?? '')) : '';
    $caption = '';
    if ($captionSubmit['http'] === 202 && preg_match('/^[a-f0-9-]{36}$/', $validationId) === 1) {
        $capDeadline = time() + 90;
        while (time() < $capDeadline) {
            sleep(3);
            $capStatus = rateb_cli_horde('GET', 'https://aihorde.net/api/v2/interrogate/status/' . rawurlencode($validationId), null, $hordeKey);
            $capData = json_decode($capStatus['body'], true);
            if (($capData['state'] ?? '') === 'done') {
                foreach ((array) ($capData['forms'] ?? []) as $form) {
                    if (is_array($form) && ($form['form'] ?? '') === 'caption' && ($form['state'] ?? '') === 'done') {
                        $caption = trim((string) ($form['result']['caption'] ?? ''));
                        break 2;
                    }
                }
                break;
            }
            if (in_array((string) ($capData['state'] ?? ''), ['faulted', 'cancelled'], true)) {
                break;
            }
        }
    }
    echo 'caption=' . $caption . PHP_EOL;
    $relevance = ['status' => 'unavailable'];
    if ($caption !== '') {
        $hard = rateb_image_caption_hard_reject($facts, $caption);
        if ($hard !== null) {
            $relevance = ['status' => 'unrelated', 'reason' => $hard];
        } elseif (rateb_image_caption_hard_accept($facts, $caption)) {
            $relevance = ['status' => 'relevant', 'reason' => 'hard_accept'];
        } elseif (preg_match('/\b(poster|flyers?|brochure|pamphlet|advert|print ads?|social media|templates?|mockups?)\b/i', $caption) === 1
            && preg_match('/\b(blank white screen|empty screen|calligraphy|notepad)\b/i', $caption) !== 1) {
            $relevance = ['status' => 'relevant', 'reason' => 'caption_keywords'];
        } else {
            $relevance = ['status' => 'unrelated', 'reason' => 'no_marketing_outputs'];
        }
    }
    echo 'relevance=' . json_encode($relevance, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if (($relevance['status'] ?? '') !== 'relevant') {
        continue;
    }

    $download = rateb_cli_horde('GET', $imageUrl, null, $hordeKey, 40);
    if ($download['http'] !== 200 || strlen($download['body']) < 32) {
        echo "download_failed\n";
        continue;
    }
    $bytes = $download['body'];
    $mime = 'image/webp';
    if (strncmp($bytes, "\x89PNG", 4) === 0) {
        $mime = 'image/png';
    } elseif (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
        $mime = 'image/jpeg';
    }
    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpg' : 'webp');
    $mediaId = media_store_bytes($campaign, 'image', 'ai', 'rateb-ai-showcase.' . $ext, $mime, $bytes);
    // Leave draft — visual QA / campaign UI must approve, never auto-approve.
    echo json_encode([
        'ok' => true,
        'attempt' => $attempt,
        'media_id' => $mediaId,
        'model' => $modelName,
        'caption' => $caption,
        'relevance' => $relevance,
        'approval_status' => 'draft',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "all_attempts_rejected_or_queue_busy\n");
exit(7);

function rateb_cli_image_url_allowed(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host === '' || $host === 'localhost') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    return str_ends_with($host, 'aihorde.net')
        || str_ends_with($host, 'r2.dev')
        || str_ends_with($host, 'cloudflarestorage.com')
        || str_ends_with($host, 'amazonaws.com');
}

/**
 * @return array{http:int,body:string,effective:string}
 */
function rateb_cli_horde(string $method, string $url, ?string $payload, string $apiKey, int $timeout = 25): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'apikey: ' . $apiKey;
    }
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $body = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['http' => $http, 'body' => $body, 'effective' => $effective];
}
