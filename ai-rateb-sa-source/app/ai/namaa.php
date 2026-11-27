<?php
declare(strict_types=1);

/**
 * NAMAA-Saudi-TTS — target Saudi Arabic voice for RATEB AI.
 * Dedicated inference only (NAMAA_INFERENCE_URL). Never the public HF demo Space.
 */

function rateb_namaa_inference_url(): string
{
    return rtrim(trim((string) (getenv('NAMAA_INFERENCE_URL') ?: '')), '/');
}

function rateb_namaa_inference_secret(): string
{
    return trim((string) (getenv('NAMAA_INFERENCE_SECRET') ?: ''));
}

function rateb_namaa_configured(): bool
{
    $url = rateb_namaa_inference_url();
    $secret = rateb_namaa_inference_secret();
    if ($url === '' || $secret === '') {
        return false;
    }
    // Refuse the public demo Space — not a production inference backend.
    if (stripos($url, 'omarelshehy-namaa-saudi-voice') !== false
        || stripos($url, 'huggingface.co/spaces/omarelshehy') !== false) {
        return false;
    }

    return true;
}

/** @return list<array<string,mixed>> */
function rateb_namaa_saudi_voices(): array
{
    $arabic = function_exists('ui_language') && ui_language() === 'ar';
    $ready = rateb_namaa_configured();

    return [
        [
            'id' => 'namaa_saudi_m1',
            'name' => $arabic ? 'رَتِّب — صوت سعودي (ناما)' : 'RATEB — Saudi NAMAA voice',
            'status' => $ready ? 'ready' : 'setup',
            'dialect' => 'saudi-najdi',
            'gender' => 'male',
            'age' => null,
            'use_case' => 'advertising',
            'preview_url' => null,
            'sharing_status' => 'public',
            'liked_by_count' => null,
            'description' => $arabic
                ? 'تعليق صوتي بلهجة سعودية طبيعية — المزود المستهدف NAMAA-Saudi-TTS'
                : 'Natural Saudi dialect voice-over — target provider NAMAA-Saudi-TTS',
            'preview_text' => null,
            'continuation_status' => null,
            'created_at' => null,
            'provider' => 'namaa',
        ],
    ];
}

function rateb_namaa_find(string $id): ?array
{
    if ($id !== 'namaa_saudi_m1') {
        return null;
    }
    foreach (rateb_namaa_saudi_voices() as $voice) {
        if ((string) ($voice['id'] ?? '') === $id) {
            return $voice;
        }
    }

    return null;
}

/**
 * @return array{ok:bool,bytes:string,mime:string,error:string}
 */
function rateb_namaa_http_json(string $url, string $method, ?string $body, array $headers, int $timeout = 120): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'ctype' => '', 'error' => 'curl'];
    }
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return [
        'ok' => is_string($resp) && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_string($resp) ? $resp : '',
        'ctype' => $ctype,
        'error' => '',
    ];
}

/**
 * Call dedicated NAMAA Gradio ZeroGPU Space (or compatible /tts JSON endpoint).
 *
 * @return array{ok:bool,bytes:string,mime:string,error:string}
 */
function rateb_namaa_speech(string $text): array
{
    $empty = ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'not_configured'];
    if (!rateb_namaa_configured()) {
        return $empty;
    }
    $base = rateb_namaa_inference_url();
    $secret = rateb_namaa_inference_secret();
    $authHeaders = [
        'Content-Type: application/json',
        'Accept: application/json, text/event-stream, audio/wav, audio/mpeg',
        'X-Rateb-Namaa-Secret: ' . $secret,
    ];

    // Preferred Gradio queue API used by ZeroGPU Spaces.
    $payload = json_encode(['data' => [$text]], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'encode'];
    }
    $submit = rateb_namaa_http_json($base . '/gradio_api/call/tts', 'POST', $payload, $authHeaders, 60);
    if (!$submit['ok']) {
        $bodyLower = strtolower($submit['body']);
        if ($submit['status'] === 429 || str_contains($bodyLower, 'quota') || str_contains($bodyLower, 'gpu quota')) {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'quota'];
        }
        if ($submit['status'] === 401 || $submit['status'] === 403 || str_contains($bodyLower, 'unauthorized')) {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'unauthorized'];
        }
        // Fallback: simple JSON /tts for non-Gradio dedicated hosts.
        $legacy = rateb_namaa_http_json(
            $base . '/tts',
            'POST',
            json_encode(['text' => $text, 'language_id' => 'ar'], JSON_UNESCAPED_UNICODE) ?: '{}',
            $authHeaders,
            120
        );
        if (!$legacy['ok']) {
            if ($legacy['status'] === 429) {
                return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'quota'];
            }

            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }

        return rateb_namaa_decode_audio_response($legacy['body'], $legacy['ctype']);
    }

    $event = json_decode($submit['body'], true);
    $eventId = is_array($event) ? (string) ($event['event_id'] ?? '') : '';
    if ($eventId === '') {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
    }

    $poll = rateb_namaa_http_json(
        $base . '/gradio_api/call/tts/' . rawurlencode($eventId),
        'GET',
        null,
        [
            'Accept: text/event-stream',
            'X-Rateb-Namaa-Secret: ' . $secret,
        ],
        180
    );
    if (!$poll['ok'] && $poll['body'] === '') {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'queue'];
    }
    $sse = $poll['body'];
    $lower = strtolower($sse);
    if (str_contains($lower, 'quota') || str_contains($lower, 'gpu quota')) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'quota'];
    }
    if (str_contains($lower, 'unauthorized')) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'unauthorized'];
    }
    if (!preg_match('/event:\s*complete\s*\ndata:\s*(\[.*?\])\s*(?:\n|$)/s', $sse, $m)) {
        if (str_contains($lower, 'event: error')) {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }

        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'queue'];
    }
    $data = json_decode($m[1], true);
    if (!is_array($data) || $data === []) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
    }
    $file = $data[0] ?? null;
    $fileUrl = '';
    if (is_string($file)) {
        $fileUrl = $file;
    } elseif (is_array($file)) {
        $fileUrl = (string) ($file['url'] ?? $file['path'] ?? '');
    }
    if ($fileUrl === '') {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
    }
    if (str_starts_with($fileUrl, '/')) {
        $fileUrl = $base . $fileUrl;
    } elseif (!str_starts_with($fileUrl, 'http')) {
        $fileUrl = $base . '/gradio_api/file=' . ltrim($fileUrl, '/');
    }

    $audio = rateb_namaa_http_json($fileUrl, 'GET', null, [
        'X-Rateb-Namaa-Secret: ' . $secret,
        'Accept: audio/wav, audio/mpeg, */*',
    ], 60);
    if (!$audio['ok'] || $audio['body'] === '') {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
    }
    $mime = 'audio/wav';
    if (stripos($audio['ctype'], 'mpeg') !== false || stripos($audio['ctype'], 'mp3') !== false) {
        $mime = 'audio/mpeg';
    }

    return ['ok' => true, 'bytes' => $audio['body'], 'mime' => $mime, 'error' => ''];
}

/**
 * @return array{ok:bool,bytes:string,mime:string,error:string}
 */
function rateb_namaa_decode_audio_response(string $body, string $ctype): array
{
    if (stripos($ctype, 'application/json') !== false || (str_starts_with(ltrim($body), '{') && str_contains($body, 'audio'))) {
        $json = json_decode($body, true);
        $b64 = is_array($json) ? (string) ($json['audio_base64'] ?? $json['audio'] ?? '') : '';
        if ($b64 === '') {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }
        $mime = is_array($json) ? (string) ($json['mime'] ?? 'audio/wav') : 'audio/wav';

        return ['ok' => true, 'bytes' => $bytes, 'mime' => $mime !== '' ? $mime : 'audio/wav', 'error' => ''];
    }
    $mime = 'audio/wav';
    if (stripos($ctype, 'mpeg') !== false || stripos($ctype, 'mp3') !== false) {
        $mime = 'audio/mpeg';
    }

    return ['ok' => true, 'bytes' => $body, 'mime' => $mime, 'error' => ''];
}
