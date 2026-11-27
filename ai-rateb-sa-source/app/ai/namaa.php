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

function rateb_namaa_configured(): bool
{
    $url = rateb_namaa_inference_url();
    if ($url === '') {
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
 * Call a dedicated NAMAA inference HTTP endpoint.
 * Expected: POST JSON { "text": "...", "language_id": "ar" } → audio bytes or { "audio_base64": "..." }.
 *
 * @return array{ok:bool,bytes:string,mime:string,error:string}
 */
function rateb_namaa_speech(string $text): array
{
    $empty = ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'not_configured'];
    if (!rateb_namaa_configured()) {
        return $empty;
    }
    $url = rateb_namaa_inference_url() . '/tts';
    $payload = json_encode([
        'text' => $text,
        'language_id' => 'ar',
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'encode'];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'curl'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: audio/wav, audio/mpeg, application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if (!is_string($body) || $body === '' || $code < 200 || $code >= 300) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
    }
    if (stripos($ctype, 'application/json') !== false) {
        $json = json_decode($body, true);
        $b64 = is_array($json) ? (string) ($json['audio_base64'] ?? $json['audio'] ?? '') : '';
        if ($b64 === '') {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'error' => 'provider'];
        }
        $mime = (string) ($json['mime'] ?? 'audio/wav');

        return ['ok' => true, 'bytes' => $bytes, 'mime' => $mime !== '' ? $mime : 'audio/wav', 'error' => ''];
    }

    $mime = 'audio/wav';
    if (stripos($ctype, 'mpeg') !== false || stripos($ctype, 'mp3') !== false) {
        $mime = 'audio/mpeg';
    }

    return ['ok' => true, 'bytes' => $body, 'mime' => $mime, 'error' => ''];
}
