<?php
declare(strict_types=1);

function rateb_vision_provider(): ?string
{
    if (strtolower(trim((string) getenv('RATEB_VISION_PROVIDER'))) !== 'cloudflare') {
        return null;
    }
    $config = rateb_vision_config();
    if ($config['account_id'] === '' || $config['token'] === '' || $config['model'] === '') {
        return null;
    }
    return 'cloudflare';
}

/** @return array{account_id:string,token:string,model:string} */
function rateb_vision_config(): array
{
    $ai = require dirname(__DIR__) . '/config/ai.php';
    $model = trim((string) (getenv('RATEB_VISION_MODEL') ?: '@cf/llava-hf/llava-1.5-7b-hf'));
    if (preg_match('/^@cf\/[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $model) !== 1) {
        $model = '';
    }
    return [
        'account_id' => trim((string) ($ai['cloudflare_account_id'] ?? '')),
        'token' => trim((string) ($ai['cloudflare_api_token'] ?? '')),
        'model' => $model,
    ];
}

function rateb_vision_analyze(string $bytes, string $mime): ?array
{
    if (rateb_vision_provider() !== 'cloudflare' || $bytes === '' || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }
    if (strlen($bytes) > 1500000) {
        return null;
    }
    $config = rateb_vision_config();
    $pixels = array_values(unpack('C*', $bytes));
    $prompt = 'Describe this uploaded advertisement reference. Return one JSON object with keys summary, subjects, colors, visible_text, products, setting, brand_cues. summary is one short sentence. The other keys are arrays of short strings, except setting which is one short string. Do not invent a price, phone number, or claim that is not visible.';
    $payload = json_encode([
        'image' => $pixels,
        'prompt' => $prompt,
        'max_tokens' => 500,
    ]);
    if ($payload === false) {
        return null;
    }
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . $config['account_id'] . '/ai/run/' . $config['model'];
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $config['token']],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($handle);
    $http = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($body === false || $http !== 200) {
        return null;
    }
    return rateb_vision_parse((string) $body);
}

function rateb_vision_parse(string $body): ?array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || (array_key_exists('success', $decoded) && $decoded['success'] !== true)) {
        return null;
    }
    $result = $decoded['result'] ?? $decoded;
    $text = '';
    if (is_string($result)) {
        $text = $result;
    } elseif (is_array($result)) {
        foreach (['description', 'response', 'summary'] as $key) {
            if (is_string($result[$key] ?? null) && trim($result[$key]) !== '') {
                $text = $result[$key];
                break;
            }
        }
    }
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    $structured = rateb_vision_object($text);
    if ($structured === null) {
        return rateb_vision_shape(['summary' => mb_substr($text, 0, 500)]);
    }
    return $structured;
}

function rateb_vision_object(string $text): ?array
{
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($decoded) || trim((string) ($decoded['summary'] ?? '')) === '') {
        return null;
    }
    return rateb_vision_shape($decoded);
}

/** @param array<string,mixed> $input */
function rateb_vision_shape(array $input): ?array
{
    $summary = trim((string) ($input['summary'] ?? ''));
    if ($summary === '') {
        return null;
    }
    $list = static function (mixed $value): array {
        if (!is_array($value)) {
            $value = trim((string) $value);
            return $value === '' ? [] : [mb_substr($value, 0, 80)];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = mb_substr($item, 0, 80);
            }
        }
        return array_slice($items, 0, 8);
    };
    return [
        'summary' => mb_substr($summary, 0, 500),
        'subjects' => $list($input['subjects'] ?? []),
        'colors' => $list($input['colors'] ?? []),
        'visible_text' => $list($input['visible_text'] ?? []),
        'products' => $list($input['products'] ?? []),
        'setting' => mb_substr(trim((string) ($input['setting'] ?? '')), 0, 160),
        'brand_cues' => $list($input['brand_cues'] ?? []),
    ];
}
