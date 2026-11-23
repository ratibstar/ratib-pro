<?php
declare(strict_types=1);

/**
 * Multi-provider image generation with failover.
 * Order: Cloudflare Workers AI (if configured) → Pollinations (free) → AI Horde.
 */

/** @return list<string> */
function rateb_image_provider_order(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $raw = trim((string) ($ai['image_providers'] ?? 'cloudflare,pollinations,horde'));
    $parts = preg_split('/\s*,\s*/', strtolower($raw)) ?: [];
    $allowed = ['cloudflare', 'pollinations', 'horde'];
    $out = [];
    foreach ($parts as $p) {
        if (in_array($p, $allowed, true) && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out !== [] ? $out : ['pollinations', 'horde'];
}

/** @return array{account_id:string,token:string,model:string} */
function rateb_image_cf_config(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $model = trim((string) ($ai['cloudflare_image_model'] ?? '@cf/black-forest-labs/flux-1-schnell'));
    if (preg_match('/^@cf\/[A-Za-z0-9._\/-]+$/', $model) !== 1) {
        $model = '@cf/black-forest-labs/flux-1-schnell';
    }
    return [
        'account_id' => trim((string) ($ai['cloudflare_account_id'] ?? '')),
        'token' => trim((string) ($ai['cloudflare_api_token'] ?? '')),
        'model' => $model,
    ];
}

function rateb_image_cf_configured(): bool
{
    $c = rateb_image_cf_config();
    return $c['account_id'] !== '' && $c['token'] !== '';
}

/**
 * Sync Cloudflare Flux generate.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string}
 */
function rateb_image_cf_generate(string $prompt, int $width, int $height): array
{
    if (!rateb_image_cf_configured()) {
        return ['ok' => false, 'error' => 'not_configured'];
    }
    $c = rateb_image_cf_config();
    $width = max(256, min(1024, (int) (round($width / 8) * 8)));
    $height = max(256, min(1024, (int) (round($height / 8) * 8)));
    $payload = json_encode([
        'prompt' => $prompt,
        'width' => $width,
        'height' => $height,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'encode'];
    }
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($c['account_id'])
        . '/ai/run/' . $c['model'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $c['token'],
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $http !== 200) {
        return ['ok' => false, 'error' => 'http_' . $http];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || (($decoded['success'] ?? null) === false)) {
        return ['ok' => false, 'error' => 'api'];
    }
    $imageB64 = '';
    $result = $decoded['result'] ?? null;
    if (is_string($result)) {
        $imageB64 = $result;
    } elseif (is_array($result)) {
        $imageB64 = (string) ($result['image'] ?? $result['b64_json'] ?? '');
    }
    if ($imageB64 === '' && preg_match('/"image"\s*:\s*"([^"]+)"/', $body, $m) === 1) {
        $imageB64 = $m[1];
    }
    $bytes = base64_decode($imageB64, true);
    if ($bytes === false || strlen($bytes) < 32) {
        return ['ok' => false, 'error' => 'empty'];
    }
    $mime = rateb_image_provider_mime($bytes);
    if ($mime === null) {
        return ['ok' => false, 'error' => 'mime'];
    }
    return ['ok' => true, 'bytes' => $bytes, 'mime' => $mime, 'model' => $c['model']];
}

/**
 * Free Pollinations Flux/turbo sync generate (no API key).
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string}
 */
function rateb_image_pollinations_generate(string $prompt, int $width, int $height): array
{
    $width = max(256, min(1024, (int) (round($width / 64) * 64)));
    $height = max(256, min(1024, (int) (round($height / 64) * 64)));
    $seed = random_int(1, 999999);
    // Pollinations ignores ### negatives and drifts on long prompts — keep the positive head only.
    $positive = trim((string) preg_replace('/\s*###.*$/su', '', $prompt));
    $positive = mb_substr($positive !== '' ? $positive : $prompt, 0, 280);
    // Prefer default model first — explicit flux/turbo often hit the free 402 limit card.
    $models = ['', 'flux', 'turbo'];
    foreach ($models as $model) {
        $qs = http_build_query(array_filter([
            'width' => $width,
            'height' => $height,
            'nologo' => 'true',
            'seed' => $seed,
            'model' => $model !== '' ? $model : null,
        ], static fn($v) => $v !== null && $v !== ''));
        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($positive) . '?' . $qs;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 RATEB-AI/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/*,application/octet-stream'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || !in_array($http, [200, 402], true) || strlen($body) < 1000) {
            continue;
        }
        $mime = rateb_image_provider_mime($body);
        if ($mime === null) {
            continue;
        }
        // Reject Pollinations "Free limit reached" notice cards.
        if (rateb_image_pollinations_is_limit_card($body)) {
            continue;
        }
        if ($http !== 200) {
            continue;
        }
        return [
            'ok' => true,
            'bytes' => $body,
            'mime' => $mime,
            'model' => 'pollinations:' . ($model !== '' ? $model : 'default'),
        ];
    }
    return ['ok' => false, 'error' => 'pollinations_busy'];
}

function rateb_image_pollinations_is_limit_card(string $bytes): bool
{
    if (strlen($bytes) < 8000 || strlen($bytes) > 40000) {
        return false;
    }
    // Notice cards are small JPEGs; real photos are usually larger and more varied.
    // Heuristic: decode and look for the distinctive solid mustard background + text.
    if (!function_exists('imagecreatefromstring')) {
        return strlen($bytes) === 25348; // observed limit-card size from probe
    }
    $img = @imagecreatefromstring($bytes);
    if ($img === false) {
        return false;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 200 || $h < 100) {
        imagedestroy($img);
        return false;
    }
    $rgb = imagecolorat($img, (int) ($w * 0.1), (int) ($h * 0.1));
    imagedestroy($img);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    // Mustard/yellow notice background.
    return $r > 180 && $g > 150 && $b < 120 && $r > $b + 60;
}

function rateb_image_provider_mime(string $bytes): ?string
{
    if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }
    if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    return null;
}

/**
 * Caption sync image bytes via Horde interrogate (base64), short deadline.
 */
function rateb_image_provider_caption_bytes(string $bytes, string $mime, int $deadlineSec = 20): string
{
    if ($bytes === '' || strlen($bytes) > 900000) {
        return '';
    }
    $b64 = base64_encode($bytes);
    $payload = json_encode([
        'source_image' => $b64,
        'forms' => [['name' => 'caption']],
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return '';
    }
    $cfg = require dirname(__DIR__, 2) . '/config/ai.php';
    $key = trim((string) ($cfg['horde_api_key'] ?? ''));
    $submit = rateb_image_provider_horde('POST', 'https://aihorde.net/api/v2/interrogate/async', $payload, $key);
    $job = json_decode($submit['body'], true);
    $id = is_array($job) ? strtolower((string) ($job['id'] ?? '')) : '';
    if ($submit['http'] !== 202 || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
        return '';
    }
    $deadline = time() + max(5, $deadlineSec);
    while (time() < $deadline) {
        sleep(2);
        $status = rateb_image_provider_horde('GET', 'https://aihorde.net/api/v2/interrogate/status/' . rawurlencode($id), null, $key);
        $data = json_decode($status['body'], true);
        if (!is_array($data)) {
            continue;
        }
        if (($data['state'] ?? '') === 'done') {
            foreach ((array) ($data['forms'] ?? []) as $form) {
                if (is_array($form) && ($form['form'] ?? '') === 'caption' && ($form['state'] ?? '') === 'done') {
                    return trim((string) ($form['result']['caption'] ?? ''));
                }
            }
            return '';
        }
        if (in_array((string) ($data['state'] ?? ''), ['faulted', 'cancelled'], true)) {
            return '';
        }
    }
    return '';
}

/**
 * @return array{http:int,body:string}
 */
function rateb_image_provider_horde(string $method, string $url, ?string $payload, string $apiKey, int $timeout = 25): array
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
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $body = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'body' => $body];
}

/**
 * Try sync providers in order. Returns first successful generate result.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,provider?:string,error?:string}
 */
function rateb_image_generate_sync(string $prompt, int $width, int $height): array
{
    foreach (rateb_image_provider_order() as $provider) {
        if ($provider === 'horde') {
            continue;
        }
        if ($provider === 'cloudflare') {
            if (!rateb_image_cf_configured()) {
                continue;
            }
            $r = rateb_image_cf_generate($prompt, $width, $height);
            if (!empty($r['ok'])) {
                $r['provider'] = 'cloudflare';
                return $r;
            }
            continue;
        }
        if ($provider === 'pollinations') {
            $r = rateb_image_pollinations_generate($prompt, $width, $height);
            if (!empty($r['ok'])) {
                $r['provider'] = 'pollinations';
                return $r;
            }
        }
    }
    return ['ok' => false, 'error' => 'sync_providers_failed'];
}
