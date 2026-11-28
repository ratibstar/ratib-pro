<?php
declare(strict_types=1);

/**
 * Google Gemini Nano Banana 2.1 image provider for RATEB AI.
 * Credentials: GEMINI_API_KEY only (server secrets). Never browser / Git.
 * Not part of automatic Cloudflare/Horde failover — selected explicitly.
 */

function rateb_gemini_nano_model_id(): string
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $model = trim((string) ($ai['gemini_nano_model'] ?? 'gemini-nano-banana-2.1'));
    return $model !== '' ? $model : 'gemini-nano-banana-2.1';
}

function rateb_gemini_nano_api_key(): string
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    return trim((string) ($ai['gemini_api_key'] ?? ''));
}

function rateb_gemini_nano_configured(): bool
{
    return rateb_gemini_nano_api_key() !== '';
}

/**
 * Free-tier image generation for Nano Banana 2.1 is officially unavailable.
 * Paid generation requires explicit server flag RATEB_GEMINI_NANO_PAID_APPROVED=1.
 *
 * @return array{free:bool,paid_approved:bool,can_generate:bool,reason:string,model:string}
 */
function rateb_gemini_nano_entitlement(): array
{
    $model = rateb_gemini_nano_model_id();
    // Google AI Studio pricing for gemini-nano-banana-2.1: Free Tier = Not available.
    $free = false;
    $paidApproved = trim((string) (getenv('RATEB_GEMINI_NANO_PAID_APPROVED') ?: '')) === '1';
    $reason = $free
        ? 'free_entitlement'
        : ($paidApproved
            ? 'paid_explicitly_approved'
            : 'Google Nano Banana 2.1 Free Tier is Not available; generation would incur Gemini API charges. Explicit approval required (RATEB_GEMINI_NANO_PAID_APPROVED=1).');

    return [
        'free' => $free,
        'paid_approved' => $paidApproved,
        'can_generate' => $free || $paidApproved,
        'reason' => $reason,
        'model' => $model,
    ];
}

function rateb_gemini_nano_aspect_ratio(string $ratio): string
{
    return match ($ratio) {
        '4:5' => '3:4',
        '9:16' => '9:16',
        default => '1:1',
    };
}

/**
 * Build advertising prompt from campaign brief + RATEB brand constraints.
 *
 * @param array<string,mixed> $campaign
 */
function rateb_gemini_nano_campaign_prompt(array $campaign, string $ratio): string
{
    $product = trim((string) ($campaign['product'] ?? $campaign['title'] ?? ''));
    $brief = '';
    try {
        $stmt = db()->prepare('SELECT product, description, audience, objective FROM campaign_briefs WHERE campaign_id = ? LIMIT 1');
        $stmt->execute([(int) ($campaign['id'] ?? 0)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($product === '') {
            $product = trim((string) ($row['product'] ?? ''));
        }
        $brief = trim((string) ($row['description'] ?? ''));
        $audience = trim((string) ($row['audience'] ?? ''));
        $objective = trim((string) ($row['objective'] ?? ''));
    } catch (Throwable $e) {
        $audience = '';
        $objective = '';
    }
    if ($product === '') {
        $product = 'رَتِّب إيه آي';
    }
    $parts = [
        'Premium futuristic technology advertisement for RATEB AI (Arabic brand spelling must appear accurately as: رَتِّب إيه آي).',
        'Market: Saudi Arabia. Arabic-first advertising key visual.',
        'Promote RATEB AI as an intelligent business automation platform.',
        'Visual style: premium futuristic technology ad, dark navy, turquoise, and cyan accents, cinematic lighting, sharp commercial photography look.',
        'Include clear Arabic brand text: رَتِّب إيه آي.',
        'Avoid unrelated products, food, cartoon characters, generic HR stock imagery, and watermark clutter.',
        'Aspect intent: ' . $ratio . '.',
    ];
    if ($product !== '') {
        $parts[] = 'Product/service: ' . $product;
    }
    if ($brief !== '') {
        $parts[] = 'Campaign brief: ' . mb_substr($brief, 0, 500);
    }
    if (!empty($audience)) {
        $parts[] = 'Audience: ' . $audience;
    }
    if (!empty($objective)) {
        $parts[] = 'Objective: ' . $objective;
    }

    return implode(' ', $parts);
}

/**
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,provider?:string,error?:string,http?:int,detail?:string}
 */
function rateb_gemini_nano_generate(string $prompt, string $ratio = '1:1'): array
{
    if (!rateb_gemini_nano_configured()) {
        return ['ok' => false, 'error' => 'not_configured', 'provider' => 'nanobanana21'];
    }
    $ent = rateb_gemini_nano_entitlement();
    if (!$ent['can_generate']) {
        return [
            'ok' => false,
            'error' => 'paid_approval_required',
            'provider' => 'nanobanana21',
            'model' => $ent['model'],
            'detail' => $ent['reason'],
        ];
    }
    $key = rateb_gemini_nano_api_key();
    $model = rateb_gemini_nano_model_id();
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $payload = json_encode([
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ]],
        'generationConfig' => [
            'responseModalities' => ['TEXT', 'IMAGE'],
            'imageConfig' => [
                'aspectRatio' => rateb_gemini_nano_aspect_ratio($ratio),
                'imageSize' => '1K',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'encode', 'provider' => 'nanobanana21', 'model' => $model];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl', 'provider' => 'nanobanana21', 'model' => $model];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $key,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $safeDetail = is_string($body) ? rateb_gemini_nano_sanitize($body, $key) : '';
    rateb_gemini_nano_log([
        'event' => 'generate',
        'model' => $model,
        'http' => $http,
        'ok_http' => $http >= 200 && $http < 300,
        'prompt_sha256' => hash('sha256', $prompt),
        'detail' => mb_substr($safeDetail, 0, 400),
    ]);
    if (!is_string($body) || $http < 200 || $http >= 300) {
        $err = 'http_' . $http;
        if ($http === 429) {
            $err = 'quota';
        }
        if ($http === 401 || $http === 403) {
            $err = 'unauthorized';
        }
        return [
            'ok' => false,
            'error' => $err,
            'http' => $http,
            'provider' => 'nanobanana21',
            'model' => $model,
            'detail' => mb_substr($safeDetail, 0, 300),
        ];
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'api', 'provider' => 'nanobanana21', 'model' => $model];
    }
    $b64 = '';
    $mime = 'image/png';
    foreach (($json['candidates'] ?? []) as $cand) {
        if (!is_array($cand)) {
            continue;
        }
        $parts = $cand['content']['parts'] ?? [];
        if (!is_array($parts)) {
            continue;
        }
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (!is_array($inline)) {
                continue;
            }
            $data = (string) ($inline['data'] ?? '');
            if ($data !== '') {
                $b64 = $data;
                $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
                break 2;
            }
        }
    }
    if ($b64 === '') {
        return [
            'ok' => false,
            'error' => 'no_image',
            'provider' => 'nanobanana21',
            'model' => $model,
            'detail' => 'response_missing_inline_image',
        ];
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false || $bytes === '' || strlen($bytes) < 32) {
        return ['ok' => false, 'error' => 'decode', 'provider' => 'nanobanana21', 'model' => $model];
    }
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        $mime = 'image/png';
    }

    return [
        'ok' => true,
        'bytes' => $bytes,
        'mime' => $mime,
        'provider' => 'nanobanana21',
        'model' => $model,
    ];
}

function rateb_gemini_nano_sanitize(string $text, string $key = ''): string
{
    if ($key !== '') {
        $text = str_replace($key, '***', $text);
    }
    $text = preg_replace('/AIza[0-9A-Za-z_\-]{10,}/', '***', $text) ?? $text;
    $text = preg_replace('/AQ\.[0-9A-Za-z_\-]{10,}/', '***', $text) ?? $text;

    return $text;
}

/** @param array<string,mixed> $row */
function rateb_gemini_nano_log(array $row): void
{
    $root = dirname(__DIR__, 2) . '/storage';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    $row['at'] = date('c');
    @file_put_contents(
        $root . '/gemini-nano-image.log',
        json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Dedicated campaign image endpoint branch (no RATEB credit debit).
 *
 * @param array<string,mixed> $campaign
 */
function rateb_gemini_nano_handle_campaign_image(array $campaign): void
{
    $arabic = function_exists('ui_language') && ui_language() === 'ar';
    if (!rateb_gemini_nano_configured()) {
        http_response_code(503);
        echo json_encode([
            'error' => $arabic ? 'مفتاح Gemini غير مُعد على الخادم.' : 'Gemini API key is not configured on the server.',
            'code' => 'gemini_nano_not_configured',
            'state' => 'unavailable',
            'provider' => 'nanobanana21',
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    $ent = rateb_gemini_nano_entitlement();
    if (!$ent['can_generate']) {
        http_response_code(402);
        echo json_encode([
            'error' => $arabic
                ? 'توليد نانو بانانا 2.1 يتطلب اشتراكاً مدفوعاً في Gemini. التوليد متوقف بانتظار موافقتك الصريحة. لم يُخصم من رصيد رَتِّب.'
                : 'Nano Banana 2.1 requires paid Gemini image generation. Stopped pending your explicit approval. No RATEB credits were deducted.',
            'code' => 'gemini_nano_paid_approval_required',
            'state' => 'unavailable',
            'provider' => 'nanobanana21',
            'model' => $ent['model'],
            'detail' => $ent['reason'],
        ], JSON_UNESCAPED_UNICODE);
        rateb_gemini_nano_log([
            'event' => 'blocked_paid',
            'campaign_id' => (int) ($campaign['id'] ?? 0),
            'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'model' => $ent['model'],
        ]);
        return;
    }

    $ratio = (string) ($_POST['ratio'] ?? '1:1');
    if (!in_array($ratio, ['1:1', '4:5', '9:16'], true)) {
        $ratio = '1:1';
    }
    $prompt = rateb_gemini_nano_campaign_prompt($campaign, $ratio);
    $gen = rateb_gemini_nano_generate($prompt, $ratio);
    if (empty($gen['ok'])) {
        http_response_code(502);
        echo json_encode([
            'error' => $arabic ? 'تعذر توليد الصورة عبر نانو بانانا 2.1.' : 'Nano Banana 2.1 image generation failed.',
            'code' => 'gemini_nano_' . (string) ($gen['error'] ?? 'provider'),
            'state' => 'failed',
            'provider' => 'nanobanana21',
            'model' => (string) ($gen['model'] ?? rateb_gemini_nano_model_id()),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $mime = (string) $gen['mime'];
    $bytes = (string) $gen['bytes'];
    $ext = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/webp' ? 'webp' : 'png');
    $ratioName = str_replace(':', 'x', $ratio);
    $model = (string) $gen['model'];
    $original = 'ratio-' . $ratioName . '-nanobanana21-' . preg_replace('/[^a-z0-9._-]+/i', '-', $model) . '.' . $ext;
    try {
        $mediaId = media_store_bytes($campaign, 'image', 'ai', $original, $mime, $bytes);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $arabic ? 'تعذر حفظ الصورة.' : 'Could not save the image.',
            'code' => 'gemini_nano_store',
            'state' => 'failed',
            'provider' => 'nanobanana21',
            'model' => $model,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    rateb_gemini_nano_log([
        'event' => 'saved',
        'campaign_id' => (int) ($campaign['id'] ?? 0),
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'media_id' => $mediaId,
        'model' => $model,
        'bytes' => strlen($bytes),
        'mime' => $mime,
    ]);
    echo json_encode([
        'ok' => true,
        'state' => 'ready',
        'media_id' => $mediaId,
        'provider' => 'nanobanana21',
        'model' => $model,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#images',
    ], JSON_UNESCAPED_UNICODE);
}
