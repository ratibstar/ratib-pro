<?php
declare(strict_types=1);

/**
 * Multi-provider image generation with controlled failover.
 * Default order: Cloudflare Workers AI → Pollinations → AI Horde (async, caller-owned).
 * OpenAI Images is provider 4 (after Horde), gated and disabled by default.
 * Stability AI + Runware are isolated FREE-plan comparison tests only — never in the
 * automatic failover chain (CLI oneshots + explicit latches only).
 * Sync helpers try each sync provider at most once per request.
 */

/** @return list<string> */
function rateb_image_provider_order(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $raw = trim((string) ($ai['image_providers'] ?? 'cloudflare,pollinations,horde'));
    $parts = preg_split('/\s*,\s*/', strtolower($raw)) ?: [];
    $allowed = ['cloudflare', 'pollinations', 'horde', 'openai'];
    $out = [];
    foreach ($parts as $p) {
        if (in_array($p, $allowed, true) && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out !== [] ? $out : ['pollinations', 'horde'];
}

/**
 * Sync providers only (Horde + OpenAI are handled after sync by the image endpoint).
 *
 * @return list<string>
 */
function rateb_image_sync_provider_order(): array
{
    return array_values(array_filter(
        rateb_image_provider_order(),
        static fn(string $p): bool => !in_array($p, ['horde', 'openai'], true)
    ));
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
 * Classify a provider failure for failover decisions.
 * - retryable: try next provider (quota, overload, 5xx, timeout, busy)
 * - config: skip provider (missing/invalid credentials) — do not re-hit
 * - fatal: stop chain (bad request/prompt that will fail everywhere)
 *
 * @return 'retryable'|'config'|'fatal'
 */
function rateb_image_provider_classify_failure(string $error, string $detail = ''): string
{
    $blob = strtolower(trim($error . ' ' . $detail));
    if ($blob === '') {
        return 'retryable';
    }
    if (preg_match('/\b(not_configured|http_401|http_403|unauthorized|forbidden|invalid.?token|invalid.?api.?key)\b/u', $blob) === 1) {
        return 'config';
    }
    if (preg_match('/\b(invalid_prompt|prompt_too_long|unsupported.?request|bad.?request|http_422)\b/u', $blob) === 1) {
        return 'fatal';
    }
    // Quota / overload / transient — move to next provider.
    if (preg_match('/\b(http_429|http_402|http_408|http_5\d\d|4006|quota|neuron|billing|exhausted|timeout|pollinations_busy|sync_providers_failed|overloaded|capacity|rate.?limit)\b/u', $blob) === 1) {
        return 'retryable';
    }
    if (preg_match('/\b(http_400)\b/u', $blob) === 1 && preg_match('/\b(neuron|quota|4006|limit|exhaust)/u', $blob) === 1) {
        return 'retryable';
    }
    // Empty/mime/api without quota signal — treat as retryable so failover continues.
    if (preg_match('/\b(empty|mime|api|encode|http_\d+)\b/u', $blob) === 1) {
        return 'retryable';
    }
    return 'retryable';
}

/**
 * Redact secrets from provider error snippets before logging.
 */
function rateb_image_provider_sanitize_detail(string $detail): string
{
    $detail = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $detail) ?? $detail;
    $detail = preg_replace('/sk-[A-Za-z0-9_\-]+/i', 'sk-[redacted]', $detail) ?? $detail;
    $detail = preg_replace('/apikey["\']?\s*[:=]\s*["\']?[^"\'\s]+/i', 'apikey=[redacted]', $detail) ?? $detail;
    $detail = preg_replace('/[A-Za-z0-9_\-]{32,}/', '[redacted]', $detail) ?? $detail;
    return mb_substr(trim((string) preg_replace('/\s+/', ' ', $detail)), 0, 240);
}

/** @return array{api_key:string,model:string,test:bool,test_user_id:int} */
function rateb_image_openai_config(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $model = trim((string) ($ai['openai_image_model'] ?? 'gpt-image-2.6-flare'));
    if ($model === '' || preg_match('/^[A-Za-z0-9._\-]+$/', $model) !== 1) {
        $model = 'gpt-image-2.6-flare';
    }
    return [
        'api_key' => trim((string) ($ai['openai_api_key'] ?? '')),
        'model' => $model,
        'test' => !empty($ai['openai_image_test']),
        'test_user_id' => max(0, (int) ($ai['openai_image_test_user_id'] ?? 0)),
    ];
}

function rateb_image_openai_configured(): bool
{
    return rateb_image_openai_config()['api_key'] !== '';
}

/**
 * Temporary FREE-plan experimental gate. Never true for paid plans or other users.
 */
function rateb_image_openai_eligible(int $userId, string $packageType = ''): bool
{
    if ($userId < 1 || !rateb_image_openai_configured()) {
        return false;
    }
    $cfg = rateb_image_openai_config();
    if (!$cfg['test'] || $cfg['test_user_id'] < 1 || $userId !== $cfg['test_user_id']) {
        return false;
    }
    $pkg = strtolower(trim($packageType));
    if ($pkg === '' && function_exists('db') && function_exists('rateb_account_credits')) {
        $pkg = strtolower((string) (rateb_account_credits(db(), $userId)['package_type'] ?? ''));
    }
    return $pkg === 'free';
}

/** Absolute path for the one-real-request latch (experimental OpenAI test only). */
function rateb_image_openai_test_latch_path(): string
{
    $root = dirname(__DIR__, 2) . '/storage';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    return $root . '/openai-image-test-once.latch';
}

/**
 * Atomically consume the single allowed OpenAI Images API call for this experiment.
 * Returns false when already used or not creatable — callers must not hit the API.
 */
function rateb_image_openai_consume_test_slot(): bool
{
    $path = rateb_image_openai_test_latch_path();
    if (is_file($path)) {
        return false;
    }
    $fh = @fopen($path, 'x');
    if ($fh === false) {
        return false;
    }
    fwrite($fh, json_encode([
        'at' => date('c'),
        'pid' => getmypid(),
        'note' => 'one OpenAI Images request reserved',
    ], JSON_UNESCAPED_UNICODE) . "\n");
    fclose($fh);
    return true;
}

function rateb_image_openai_size(int $width, int $height): string
{
    $ratio = $height > 0 ? ($width / $height) : 1.0;
    if ($ratio >= 1.2) {
        return '1536x1024';
    }
    if ($ratio <= 0.85) {
        return '1024x1536';
    }
    return '1024x1024';
}

/**
 * Official OpenAI Images API — one HTTP attempt. Call only after eligibility + latch checks.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string,detail?:string,http?:int,usage?:array<string,mixed>,duration_ms?:int}
 */
function rateb_image_openai_generate(string $prompt, int $width, int $height): array
{
    $started = microtime(true);
    if (!rateb_image_openai_configured()) {
        return ['ok' => false, 'error' => 'not_configured', 'duration_ms' => 0];
    }
    $cfg = rateb_image_openai_config();
    $payload = json_encode([
        'model' => $cfg['model'],
        'prompt' => mb_substr($prompt, 0, 32000),
        'size' => rateb_image_openai_size($width, $height),
        'quality' => 'medium',
        'n' => 1,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'encode', 'duration_ms' => 0];
    }
    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $durationMs = (int) max(0, round((microtime(true) - $started) * 1000));
    if ($errno === 28) {
        return ['ok' => false, 'error' => 'timeout', 'http' => $http, 'duration_ms' => $durationMs];
    }
    if ($errno !== 0) {
        return ['ok' => false, 'error' => 'http_0', 'detail' => 'curl_' . $errno, 'http' => $http, 'duration_ms' => $durationMs];
    }
    if (!is_string($body) || $http < 200 || $http >= 300) {
        $snippet = is_string($body) ? rateb_image_provider_sanitize_detail($body) : '';
        return ['ok' => false, 'error' => 'http_' . $http, 'detail' => $snippet, 'http' => $http, 'duration_ms' => $durationMs];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'api', 'detail' => 'invalid_json', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $b64 = '';
    if (is_array($decoded['data'][0] ?? null)) {
        $b64 = (string) ($decoded['data'][0]['b64_json'] ?? '');
    }
    if ($b64 === '') {
        return ['ok' => false, 'error' => 'empty', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false || strlen($bytes) < 32) {
        return ['ok' => false, 'error' => 'empty', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $mime = rateb_image_provider_mime($bytes);
    if ($mime === null) {
        // OpenAI often returns PNG; if magic missed, default png when decode succeeded.
        $mime = 'image/png';
    }
    $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
    // Never persist raw prompt/key material — keep numeric usage only.
    $usageSafe = [];
    foreach (['total_tokens', 'input_tokens', 'output_tokens', 'input_tokens_details', 'output_tokens_details'] as $k) {
        if (array_key_exists($k, $usage)) {
            $usageSafe[$k] = $usage[$k];
        }
    }
    return [
        'ok' => true,
        'bytes' => $bytes,
        'mime' => $mime,
        'model' => $cfg['model'],
        'http' => $http,
        'usage' => $usageSafe,
        'duration_ms' => $durationMs,
    ];
}

/**
 * Provider 4 entry: eligible FREE test user only, one real API call max (latch).
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,provider?:string,error?:string,detail?:string,http?:int,usage?:array<string,mixed>,duration_ms?:int,skipped?:string}
 */
function rateb_image_openai_try(string $prompt, int $width, int $height, int $userId, string $packageType = ''): array
{
    if (!rateb_image_openai_eligible($userId, $packageType)) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'ineligible'];
    }
    if (!rateb_image_openai_consume_test_slot()) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'test_slot_exhausted'];
    }
    $r = rateb_image_openai_generate($prompt, $width, $height);
    $r['provider'] = 'openai';
    return $r;
}

// ---------------------------------------------------------------------------
// Stability AI — isolated comparison provider (NOT in automatic failover)
// ---------------------------------------------------------------------------

/** @return array{api_key:string,model:string,test:bool,test_user_id:int} */
function rateb_image_stability_config(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $model = strtolower(trim((string) ($ai['stability_image_model'] ?? 'ultra')));
    $allowed = ['ultra', 'sd3.5-flash', 'sd3.5-large', 'sd3.5-large-turbo', 'sd3.5-medium'];
    if (!in_array($model, $allowed, true)) {
        $model = 'ultra';
    }
    return [
        'api_key' => trim((string) ($ai['stability_api_key'] ?? '')),
        'model' => $model,
        'test' => !empty($ai['stability_image_test']),
        'test_user_id' => max(0, (int) ($ai['stability_image_test_user_id'] ?? 0)),
    ];
}

function rateb_image_stability_configured(): bool
{
    return rateb_image_stability_config()['api_key'] !== '';
}

function rateb_image_stability_eligible(int $userId, string $packageType = ''): bool
{
    if ($userId < 1 || !rateb_image_stability_configured()) {
        return false;
    }
    $cfg = rateb_image_stability_config();
    if (!$cfg['test'] || $cfg['test_user_id'] < 1 || $userId !== $cfg['test_user_id']) {
        return false;
    }
    $pkg = strtolower(trim($packageType));
    if ($pkg === '' && function_exists('db') && function_exists('rateb_account_credits')) {
        $pkg = strtolower((string) (rateb_account_credits(db(), $userId)['package_type'] ?? ''));
    }
    return $pkg === 'free';
}

function rateb_image_stability_test_latch_path(): string
{
    $root = dirname(__DIR__, 2) . '/storage';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    return $root . '/stability-image-test-once.latch';
}

function rateb_image_stability_consume_test_slot(): bool
{
    $path = rateb_image_stability_test_latch_path();
    if (is_file($path)) {
        return false;
    }
    $fh = @fopen($path, 'x');
    if ($fh === false) {
        return false;
    }
    fwrite($fh, json_encode([
        'at' => date('c'),
        'pid' => getmypid(),
        'note' => 'one Stability AI image request reserved',
    ], JSON_UNESCAPED_UNICODE) . "\n");
    fclose($fh);
    return true;
}

/**
 * Read Stability credit balance (does not generate images; does not consume the oneshot latch).
 *
 * @return array{ok:bool,credits?:float,http?:int,error?:string,detail?:string}
 */
function rateb_image_stability_balance(): array
{
    if (!rateb_image_stability_configured()) {
        return ['ok' => false, 'error' => 'not_configured'];
    }
    $key = rateb_image_stability_config()['api_key'];
    $ch = curl_init('https://api.stability.ai/v1/user/balance');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $http !== 200) {
        return [
            'ok' => false,
            'error' => 'http_' . $http,
            'http' => $http,
            'detail' => is_string($body) ? rateb_image_provider_sanitize_detail($body) : '',
        ];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !isset($decoded['credits'])) {
        return ['ok' => false, 'error' => 'api', 'http' => $http, 'detail' => 'missing_credits_field'];
    }
    return ['ok' => true, 'credits' => (float) $decoded['credits'], 'http' => $http];
}

function rateb_image_stability_aspect_ratio(int $width, int $height): string
{
    $ratio = $height > 0 ? ($width / $height) : 1.0;
    if ($ratio >= 1.3) {
        return '16:9';
    }
    if ($ratio <= 0.8) {
        return '9:16';
    }
    if ($ratio >= 1.1) {
        return '3:2';
    }
    if ($ratio <= 0.9) {
        return '2:3';
    }
    return '1:1';
}

/**
 * Official Stability v2beta generate — one HTTP attempt.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string,detail?:string,http?:int,cost?:array<string,mixed>,duration_ms?:int}
 */
function rateb_image_stability_generate(string $prompt, int $width, int $height): array
{
    $started = microtime(true);
    if (!rateb_image_stability_configured()) {
        return ['ok' => false, 'error' => 'not_configured', 'duration_ms' => 0];
    }
    $cfg = rateb_image_stability_config();
    $model = $cfg['model'];
    if ($model === 'ultra') {
        $url = 'https://api.stability.ai/v2beta/stable-image/generate/ultra';
        $fields = [
            'prompt' => mb_substr($prompt, 0, 10000),
            'output_format' => 'png',
            'aspect_ratio' => rateb_image_stability_aspect_ratio($width, $height),
        ];
        $costCredits = 8.0;
    } else {
        $url = 'https://api.stability.ai/v2beta/stable-image/generate/sd3';
        $fields = [
            'prompt' => mb_substr($prompt, 0, 10000),
            'output_format' => 'png',
            'aspect_ratio' => rateb_image_stability_aspect_ratio($width, $height),
            'model' => $model,
            'mode' => 'text-to-image',
        ];
        $costCredits = match ($model) {
            'sd3.5-flash' => 2.5,
            'sd3.5-large-turbo' => 4.0,
            'sd3.5-medium' => 3.5,
            default => 6.5,
        };
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: image/*',
            'Authorization: Bearer ' . $cfg['api_key'],
        ],
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HEADER => true,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $durationMs = (int) max(0, round((microtime(true) - $started) * 1000));
    if ($errno === 28) {
        return ['ok' => false, 'error' => 'timeout', 'http' => $http, 'duration_ms' => $durationMs];
    }
    if ($errno !== 0 || !is_string($raw)) {
        return ['ok' => false, 'error' => 'http_0', 'detail' => 'curl_' . $errno, 'http' => $http, 'duration_ms' => $durationMs];
    }
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    if ($http < 200 || $http >= 300 || strlen($body) < 32) {
        return [
            'ok' => false,
            'error' => 'http_' . $http,
            'detail' => rateb_image_provider_sanitize_detail($body !== '' ? $body : $headers),
            'http' => $http,
            'duration_ms' => $durationMs,
        ];
    }
    $finish = '';
    if (preg_match('/^finish-reason:\s*(\S+)/mi', $headers, $m) === 1) {
        $finish = strtoupper(trim($m[1]));
    }
    if ($finish === 'CONTENT_FILTERED') {
        return ['ok' => false, 'error' => 'content_filtered', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $mime = rateb_image_provider_mime($body) ?? 'image/png';
    return [
        'ok' => true,
        'bytes' => $body,
        'mime' => $mime,
        'model' => $model === 'ultra' ? 'stable-image-ultra' : $model,
        'http' => $http,
        'cost' => ['stability_credits' => $costCredits, 'currency' => 'stability_credits'],
        'duration_ms' => $durationMs,
    ];
}

/**
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,provider?:string,error?:string,detail?:string,http?:int,cost?:array<string,mixed>,duration_ms?:int,skipped?:string,balance?:float}
 */
function rateb_image_stability_try(string $prompt, int $width, int $height, int $userId, string $packageType = '', bool $requireBalance = true): array
{
    if (!rateb_image_stability_eligible($userId, $packageType)) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'ineligible'];
    }
    $bal = null;
    if ($requireBalance) {
        $bal = rateb_image_stability_balance();
        if (empty($bal['ok'])) {
            return ['ok' => false, 'error' => (string) ($bal['error'] ?? 'balance_unavailable'), 'skipped' => 'balance_unverified', 'http' => $bal['http'] ?? null];
        }
        $credits = (float) ($bal['credits'] ?? 0);
        if ($credits <= 0) {
            return ['ok' => false, 'error' => 'balance_zero', 'skipped' => 'balance_zero', 'balance' => $credits];
        }
    }
    if (!rateb_image_stability_consume_test_slot()) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'test_slot_exhausted'];
    }
    $r = rateb_image_stability_generate($prompt, $width, $height);
    $r['provider'] = 'stability';
    if (is_array($bal) && isset($bal['credits'])) {
        $r['balance'] = (float) $bal['credits'];
    }
    return $r;
}

// ---------------------------------------------------------------------------
// Runware — isolated comparison provider (NOT in automatic failover)
// ---------------------------------------------------------------------------

/** @return array{api_key:string,model:string,test:bool,test_user_id:int} */
function rateb_image_runware_config(): array
{
    $ai = require dirname(__DIR__, 2) . '/config/ai.php';
    $model = trim((string) ($ai['runware_image_model'] ?? 'ideogram:4.6@0'));
    // AIR ids look like creator:family@version — keep conservative charset.
    if ($model === '' || preg_match('/^[A-Za-z0-9:._\-@\/]+$/', $model) !== 1) {
        $model = 'ideogram:4.6@0';
    }
    return [
        'api_key' => trim((string) ($ai['runware_api_key'] ?? '')),
        'model' => $model,
        'test' => !empty($ai['runware_image_test']),
        'test_user_id' => max(0, (int) ($ai['runware_image_test_user_id'] ?? 0)),
    ];
}

function rateb_image_runware_configured(): bool
{
    return rateb_image_runware_config()['api_key'] !== '';
}

function rateb_image_runware_eligible(int $userId, string $packageType = ''): bool
{
    if ($userId < 1 || !rateb_image_runware_configured()) {
        return false;
    }
    $cfg = rateb_image_runware_config();
    if (!$cfg['test'] || $cfg['test_user_id'] < 1 || $userId !== $cfg['test_user_id']) {
        return false;
    }
    $pkg = strtolower(trim($packageType));
    if ($pkg === '' && function_exists('db') && function_exists('rateb_account_credits')) {
        $pkg = strtolower((string) (rateb_account_credits(db(), $userId)['package_type'] ?? ''));
    }
    return $pkg === 'free';
}

function rateb_image_runware_test_latch_path(): string
{
    $root = dirname(__DIR__, 2) . '/storage';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    return $root . '/runware-image-test-once.latch';
}

function rateb_image_runware_consume_test_slot(): bool
{
    $path = rateb_image_runware_test_latch_path();
    if (is_file($path)) {
        return false;
    }
    $fh = @fopen($path, 'x');
    if ($fh === false) {
        return false;
    }
    fwrite($fh, json_encode([
        'at' => date('c'),
        'pid' => getmypid(),
        'note' => 'one Runware image request reserved',
    ], JSON_UNESCAPED_UNICODE) . "\n");
    fclose($fh);
    return true;
}

function rateb_image_runware_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Runware accountManagement getDetails — balance only (no generation; no latch).
 *
 * @return array{ok:bool,balance?:float,http?:int,error?:string,detail?:string}
 */
function rateb_image_runware_balance(): array
{
    if (!rateb_image_runware_configured()) {
        return ['ok' => false, 'error' => 'not_configured'];
    }
    $payload = json_encode([[
        'taskType' => 'accountManagement',
        'taskUUID' => rateb_image_runware_uuid(),
        'operation' => 'getDetails',
    ]], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.runware.ai/v1');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . rateb_image_runware_config()['api_key'],
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $http < 200 || $http >= 300) {
        return [
            'ok' => false,
            'error' => 'http_' . $http,
            'http' => $http,
            'detail' => is_string($body) ? rateb_image_provider_sanitize_detail($body) : '',
        ];
    }
    $decoded = json_decode($body, true);
    $row = is_array($decoded['data'][0] ?? null) ? $decoded['data'][0] : null;
    if (!is_array($row) || !isset($row['balance'])) {
        return ['ok' => false, 'error' => 'api', 'http' => $http, 'detail' => 'missing_balance_field'];
    }
    return ['ok' => true, 'balance' => (float) $row['balance'], 'http' => $http];
}

function rateb_image_runware_size(int $width, int $height): array
{
    // Ideogram/Runware prefer multiples of 32–64; keep near campaign ratio.
    $w = max(512, min(1536, (int) (round($width / 64) * 64)));
    $h = max(512, min(1536, (int) (round($height / 64) * 64)));
    if ($w < 1) {
        $w = 1024;
    }
    if ($h < 1) {
        $h = 1024;
    }
    return [$w, $h];
}

/**
 * Official Runware imageInference — one HTTP attempt (base64Data; includeCost=true).
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string,detail?:string,http?:int,cost?:array<string,mixed>,duration_ms?:int}
 */
function rateb_image_runware_generate(string $prompt, int $width, int $height): array
{
    $started = microtime(true);
    if (!rateb_image_runware_configured()) {
        return ['ok' => false, 'error' => 'not_configured', 'duration_ms' => 0];
    }
    $cfg = rateb_image_runware_config();
    [$w, $h] = rateb_image_runware_size($width, $height);
    $taskUUID = rateb_image_runware_uuid();
    $payload = json_encode([[
        'taskType' => 'imageInference',
        'taskUUID' => $taskUUID,
        'model' => $cfg['model'],
        'positivePrompt' => mb_substr($prompt, 0, 3000),
        'width' => $w,
        'height' => $h,
        'numberResults' => 1,
        'outputType' => 'base64Data',
        'includeCost' => true,
    ]], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'encode', 'duration_ms' => 0];
    }
    $ch = curl_init('https://api.runware.ai/v1');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 150,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $durationMs = (int) max(0, round((microtime(true) - $started) * 1000));
    if ($errno === 28) {
        return ['ok' => false, 'error' => 'timeout', 'http' => $http, 'duration_ms' => $durationMs];
    }
    if ($errno !== 0 || !is_string($body)) {
        return ['ok' => false, 'error' => 'http_0', 'detail' => 'curl_' . $errno, 'http' => $http, 'duration_ms' => $durationMs];
    }
    if ($http < 200 || $http >= 300) {
        return [
            'ok' => false,
            'error' => 'http_' . $http,
            'detail' => rateb_image_provider_sanitize_detail($body),
            'http' => $http,
            'duration_ms' => $durationMs,
        ];
    }
    $decoded = json_decode($body, true);
    $row = is_array($decoded['data'][0] ?? null) ? $decoded['data'][0] : null;
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'api', 'detail' => 'empty_data', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $b64 = (string) ($row['imageBase64Data'] ?? '');
    if ($b64 === '' && is_string($row['imageDataURI'] ?? null)) {
        $uri = (string) $row['imageDataURI'];
        if (preg_match('/^data:image\/[a-zA-Z0-9+.-]+;base64,(.+)$/', $uri, $m) === 1) {
            $b64 = $m[1];
        }
    }
    if ($b64 === '') {
        return ['ok' => false, 'error' => 'empty', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false || strlen($bytes) < 32) {
        return ['ok' => false, 'error' => 'empty', 'http' => $http, 'duration_ms' => $durationMs];
    }
    $mime = rateb_image_provider_mime($bytes) ?? 'image/png';
    $costUsd = isset($row['cost']) ? (float) $row['cost'] : null;
    return [
        'ok' => true,
        'bytes' => $bytes,
        'mime' => $mime,
        'model' => $cfg['model'],
        'http' => $http,
        'cost' => $costUsd !== null ? ['usd' => $costUsd, 'currency' => 'USD'] : [],
        'duration_ms' => $durationMs,
    ];
}

/**
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,provider?:string,error?:string,detail?:string,http?:int,cost?:array<string,mixed>,duration_ms?:int,skipped?:string,balance?:float}
 */
function rateb_image_runware_try(string $prompt, int $width, int $height, int $userId, string $packageType = '', bool $requireBalance = true): array
{
    if (!rateb_image_runware_eligible($userId, $packageType)) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'ineligible'];
    }
    $bal = null;
    if ($requireBalance) {
        $bal = rateb_image_runware_balance();
        if (empty($bal['ok'])) {
            return ['ok' => false, 'error' => (string) ($bal['error'] ?? 'balance_unavailable'), 'skipped' => 'balance_unverified', 'http' => $bal['http'] ?? null];
        }
        $credits = (float) ($bal['balance'] ?? 0);
        if ($credits <= 0) {
            return ['ok' => false, 'error' => 'balance_zero', 'skipped' => 'balance_zero', 'balance' => $credits];
        }
    }
    if (!rateb_image_runware_consume_test_slot()) {
        return ['ok' => false, 'error' => 'not_configured', 'skipped' => 'test_slot_exhausted'];
    }
    $r = rateb_image_runware_generate($prompt, $width, $height);
    $r['provider'] = 'runware';
    if (is_array($bal) && isset($bal['balance'])) {
        $r['balance'] = (float) $bal['balance'];
    }
    return $r;
}

/**
 * Sync Cloudflare Flux generate — one HTTP attempt.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string,detail?:string,http?:int}
 */
function rateb_image_cf_generate(string $prompt, int $width, int $height): array
{
    if (!rateb_image_cf_configured()) {
        return ['ok' => false, 'error' => 'not_configured'];
    }
    $c = rateb_image_cf_config();
    // Workers AI Flux Schnell schema: prompt + steps only (width/height rejected).
    $payload = json_encode([
        'prompt' => mb_substr($prompt, 0, 2048),
        'steps' => 4,
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
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno === 28 /* CURLE_OPERATION_TIMEDOUT */) {
        return ['ok' => false, 'error' => 'timeout', 'http' => $http];
    }
    if ($errno !== 0) {
        return ['ok' => false, 'error' => 'http_0', 'detail' => 'curl_' . $errno, 'http' => $http];
    }
    if (!is_string($body) || $http !== 200) {
        $snippet = is_string($body) ? mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 240) : '';
        return ['ok' => false, 'error' => 'http_' . $http, 'detail' => $snippet, 'http' => $http];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || (($decoded['success'] ?? null) === false)) {
        $snippet = is_string($body) ? mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 240) : '';
        return ['ok' => false, 'error' => 'api', 'detail' => $snippet, 'http' => $http];
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
        return ['ok' => false, 'error' => 'empty', 'http' => $http];
    }
    $mime = rateb_image_provider_mime($bytes);
    if ($mime === null) {
        return ['ok' => false, 'error' => 'mime', 'http' => $http];
    }
    return ['ok' => true, 'bytes' => $bytes, 'mime' => $mime, 'model' => $c['model'], 'http' => 200];
}

/**
 * Free Pollinations generate — one HTTP attempt for this provider.
 *
 * @return array{ok:bool,bytes?:string,mime?:string,model?:string,error?:string,detail?:string,http?:int}
 */
function rateb_image_pollinations_generate(string $prompt, int $width, int $height): array
{
    $width = max(256, min(1024, (int) (round($width / 64) * 64)));
    $height = max(256, min(1024, (int) (round($height / 64) * 64)));
    $seed = random_int(1, 999999);
    // Pollinations ignores ### negatives and drifts on long prompts — keep the positive head only.
    $positive = trim((string) preg_replace('/\s*###.*$/su', '', $prompt));
    $positive = mb_substr($positive !== '' ? $positive : $prompt, 0, 280);
    $qs = http_build_query([
        'width' => $width,
        'height' => $height,
        'nologo' => 'true',
        'seed' => $seed,
    ]);
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
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno === 28) {
        return ['ok' => false, 'error' => 'timeout', 'http' => $http];
    }
    if ($errno !== 0 || !is_string($body) || strlen($body) < 1000) {
        return ['ok' => false, 'error' => 'pollinations_busy', 'http' => $http, 'detail' => 'curl_' . $errno];
    }
    if (in_array($http, [402, 429], true)) {
        return ['ok' => false, 'error' => 'http_' . $http, 'http' => $http, 'detail' => 'pollinations_quota'];
    }
    if ($http !== 200) {
        return ['ok' => false, 'error' => 'http_' . $http, 'http' => $http];
    }
    $mime = rateb_image_provider_mime($body);
    if ($mime === null) {
        return ['ok' => false, 'error' => 'mime', 'http' => $http];
    }
    if (strlen($body) < 90000 || rateb_image_pollinations_is_limit_card($body)) {
        return ['ok' => false, 'error' => 'pollinations_busy', 'http' => $http, 'detail' => 'limit_card_or_small'];
    }
    return [
        'ok' => true,
        'bytes' => $body,
        'mime' => $mime,
        'model' => 'pollinations:default',
        'http' => 200,
    ];
}

function rateb_image_pollinations_is_limit_card(string $bytes): bool
{
    if (strlen($bytes) < 8000 || strlen($bytes) > 40000) {
        return false;
    }
    if (!function_exists('imagecreatefromstring')) {
        return strlen($bytes) === 25348;
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
 * Try each sync provider once in configured order.
 * $mocks: optional map provider => callable(prompt,w,h): array for offline tests.
 *
 * @param array<string,callable>|null $mocks
 * @return array{
 *   ok:bool,
 *   bytes?:string,
 *   mime?:string,
 *   model?:string,
 *   provider?:string,
 *   error?:string,
 *   error_class?:string,
 *   detail?:string,
 *   attempts:list<array<string,mixed>>
 * }
 */
function rateb_image_generate_sync(string $prompt, int $width, int $height, ?array $mocks = null): array
{
    $attempts = [];
    $providers = rateb_image_sync_provider_order();
    if ($providers === []) {
        return [
            'ok' => false,
            'error' => 'sync_providers_failed',
            'error_class' => 'config',
            'attempts' => [],
        ];
    }

    foreach ($providers as $provider) {
        $started = microtime(true);
        if (is_array($mocks) && array_key_exists($provider, $mocks)) {
            $handler = $mocks[$provider];
            $r = is_callable($handler) ? (array) $handler($prompt, $width, $height) : ['ok' => false, 'error' => 'not_configured'];
        } elseif ($provider === 'cloudflare') {
            if (!rateb_image_cf_configured()) {
                $r = ['ok' => false, 'error' => 'not_configured'];
            } else {
                $r = rateb_image_cf_generate($prompt, $width, $height);
            }
        } elseif ($provider === 'pollinations') {
            $r = rateb_image_pollinations_generate($prompt, $width, $height);
        } else {
            $r = ['ok' => false, 'error' => 'not_configured'];
        }

        $durationMs = (int) max(0, round((microtime(true) - $started) * 1000));
        $error = (string) ($r['error'] ?? '');
        $detail = rateb_image_provider_sanitize_detail((string) ($r['detail'] ?? ''));
        $http = isset($r['http']) ? (int) $r['http'] : null;
        $ok = !empty($r['ok']);
        $class = $ok ? 'ok' : rateb_image_provider_classify_failure($error, $detail);
        $attempt = [
            'provider' => $provider,
            'model' => (string) ($r['model'] ?? ''),
            'ok' => $ok,
            'http' => $http,
            'error' => $error,
            'error_class' => $class,
            'detail' => $detail,
            'duration_ms' => $durationMs,
        ];
        $attempts[] = $attempt;

        if ($ok) {
            return [
                'ok' => true,
                'bytes' => (string) ($r['bytes'] ?? ''),
                'mime' => (string) ($r['mime'] ?? 'image/jpeg'),
                'model' => (string) ($r['model'] ?? ''),
                'provider' => $provider,
                'http' => $http ?? 200,
                'attempts' => $attempts,
            ];
        }

        // Invalid prompt/request — do not burn remaining providers.
        if ($class === 'fatal') {
            return [
                'ok' => false,
                'error' => $error !== '' ? $error : 'fatal_provider_error',
                'error_class' => 'fatal',
                'detail' => $detail,
                'attempts' => $attempts,
            ];
        }
        // config + retryable (incl. quota) → next eligible provider once.
    }

    $last = $attempts !== [] ? $attempts[array_key_last($attempts)] : null;
    return [
        'ok' => false,
        'error' => 'sync_providers_failed',
        'error_class' => is_array($last) ? (string) ($last['error_class'] ?? 'retryable') : 'retryable',
        'detail' => is_array($last) ? (string) ($last['detail'] ?? '') : '',
        'attempts' => $attempts,
    ];
}
