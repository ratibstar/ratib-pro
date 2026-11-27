<?php
declare(strict_types=1);

/**
 * EasyVoice Free TTS adapter (OpenAI-compatible speech endpoint).
 * Credentials: EASYVOICE_API_KEY from server secrets only — never log or echo the value.
 */

function rateb_easyvoice_configured(): bool
{
    $key = trim((string) getenv('EASYVOICE_API_KEY'));
    return $key !== '' && str_starts_with($key, 'ev_');
}

function rateb_easyvoice_api_key(): string
{
    return trim((string) getenv('EASYVOICE_API_KEY'));
}

/** Free-tier Arabic MSA voices only (docs: ar_m1 / ar_f1). */
function rateb_easyvoice_free_arabic_voices(): array
{
    return [
        [
            'id' => 'ar_m1',
            'name' => 'EasyVoice Arabic Male 1',
            'status' => 'ready',
            'dialect' => 'msa',
            'gender' => 'male',
            'age' => null,
            'use_case' => 'broadcast',
            'preview_url' => null,
            'sharing_status' => 'public',
            'liked_by_count' => null,
            'description' => 'Neutral MSA male — free EasyVoice tier',
            'preview_text' => null,
            'continuation_status' => null,
            'created_at' => null,
            'provider' => 'easyvoice',
        ],
        [
            'id' => 'ar_f1',
            'name' => 'EasyVoice Arabic Female 1',
            'status' => 'ready',
            'dialect' => 'msa',
            'gender' => 'female',
            'age' => null,
            'use_case' => 'broadcast',
            'preview_url' => null,
            'sharing_status' => 'public',
            'liked_by_count' => null,
            'description' => 'Neutral MSA female — free EasyVoice tier',
            'preview_text' => null,
            'continuation_status' => null,
            'created_at' => null,
            'provider' => 'easyvoice',
        ],
    ];
}

function rateb_easyvoice_is_free_voice(string $id): bool
{
    return in_array($id, ['ar_m1', 'ar_f1'], true);
}

function rateb_easyvoice_find(string $id): ?array
{
    if (!rateb_easyvoice_is_free_voice($id)) {
        return null;
    }
    foreach (rateb_easyvoice_free_arabic_voices() as $voice) {
        if ((string) $voice['id'] === $id) {
            return $voice;
        }
    }
    return null;
}

/**
 * Non-billing MCP get_usage. Never echoes the API key.
 *
 * @return array{ok:bool,http:int,plan:?string,limit:?int,used:?int,remaining:?int,reset_at:?string,error:string}
 */
function rateb_easyvoice_get_usage(): array
{
    $empty = [
        'ok' => false,
        'http' => 0,
        'plan' => null,
        'limit' => null,
        'used' => null,
        'remaining' => null,
        'reset_at' => null,
        'error' => 'not_configured',
    ];
    if (!rateb_easyvoice_configured()) {
        return $empty;
    }
    $key = rateb_easyvoice_api_key();
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_usage',
            'arguments' => new stdClass(),
        ],
    ], JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://easyvoice.ae/api/mcp');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = (string) curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '' || $http < 200 || $http >= 300) {
        return array_merge($empty, ['http' => $http, 'error' => $err !== '' ? 'curl' : 'http_' . $http]);
    }
    $decoded = json_decode($body, true);
    $structured = is_array($decoded['result']['structuredContent'] ?? null)
        ? $decoded['result']['structuredContent']
        : null;
    if (!is_array($structured)) {
        return array_merge($empty, ['http' => $http, 'error' => 'parse']);
    }
    return [
        'ok' => true,
        'http' => $http,
        'plan' => isset($structured['plan']) ? (string) $structured['plan'] : null,
        'limit' => isset($structured['limit']) ? (int) $structured['limit'] : null,
        'used' => isset($structured['used']) ? (int) $structured['used'] : null,
        'remaining' => isset($structured['remaining']) ? (int) $structured['remaining'] : null,
        'reset_at' => isset($structured['reset_at']) ? (string) $structured['reset_at'] : null,
        'error' => '',
    ];
}

/**
 * Provider-supported Arabic advertising delivery defaults (EasyVoice docs).
 * - speed: 0.5–2.0 (supported on ar_*)
 * - ev_tone / ev_volume_db: supported EQ/gain controls
 * - ev_pitch: IGNORED on Arabic ar_* (do not rely on it)
 * - SSML: break tags only; prosody/emphasis are NOT interpreted
 * Free energetic Arabic voices beyond ar_m1/ar_f1 are Pro-only.
 *
 * @return array{speed:float,ev_tone:string,ev_volume_db:float}
 */
function rateb_easyvoice_ad_delivery_defaults(): array
{
    return [
        'speed' => 1.2,
        'ev_tone' => 'bright',
        'ev_volume_db' => 1.5,
    ];
}

/**
 * Delivery text prep for Arabic TTS input only.
 * Evidence (media 274 ASR): EasyVoice Arabic spoke "<break …>" literally, so break
 * markup is NOT applied for ar_*. Natural punctuation alone is kept.
 * Docs advertise break tags broadly, but Arabic Supertonic did not honor them safely.
 */
function rateb_easyvoice_prepare_delivery_input(string $text): string
{
    return trim($text);
}

/**
 * Free-plan speech: OpenAI-compatible POST /api/v1/audio/speech.
 * Caller must pre-check free plan + remaining quota + free voice + input length.
 * Pronunciation dictionary + delivery markup apply to spoken input only (not campaign copy).
 *
 * @param array{speed?:float,ev_tone?:string,ev_volume_db?:float,prepare_delivery?:bool} $options
 * @return array{ok:bool,http:int,bytes:string,mime:string,error:string,spoken_input?:string,request?:array<string,mixed>}
 */
function rateb_easyvoice_speech(string $input, string $voice = 'ar_m1', string $format = 'mp3', float $speed = 0.0, array $options = []): array
{
    require_once __DIR__ . '/tts-pronunciation.php';

    $fail = static fn (int $http, string $error): array => [
        'ok' => false,
        'http' => $http,
        'bytes' => '',
        'mime' => '',
        'error' => $error,
    ];
    if (!rateb_easyvoice_configured()) {
        return $fail(0, 'not_configured');
    }
    if (!rateb_easyvoice_is_free_voice($voice)) {
        return $fail(0, 'voice_not_free');
    }
    $defaults = rateb_easyvoice_ad_delivery_defaults();
    $prepare = ($options['prepare_delivery'] ?? true) === true;
    $input = rateb_tts_normalize_pronunciation(trim($input));
    if ($prepare) {
        $input = rateb_easyvoice_prepare_delivery_input($input);
    }
    if ($input === '') {
        return $fail(0, 'empty_input');
    }
    $chars = mb_strlen($input, 'UTF-8');
    if ($chars > 5000) {
        return $fail(0, 'input_over_daily_pool');
    }
    $speed = isset($options['speed']) ? (float) $options['speed'] : ($speed > 0 ? $speed : (float) $defaults['speed']);
    if ($speed < 0.5 || $speed > 2.0) {
        $speed = max(0.5, min(2.0, $speed));
    }
    $tone = (string) ($options['ev_tone'] ?? $defaults['ev_tone']);
    if (!in_array($tone, ['neutral', 'warm', 'bright', 'bass'], true)) {
        $tone = 'bright';
    }
    $volumeDb = (float) ($options['ev_volume_db'] ?? $defaults['ev_volume_db']);
    if ($volumeDb < -6 || $volumeDb > 6) {
        $volumeDb = max(-6.0, min(6.0, $volumeDb));
    }
    $format = in_array($format, ['mp3', 'wav'], true) ? $format : 'mp3';
    $key = rateb_easyvoice_api_key();
    // Do NOT send ev_pitch for Arabic — provider documents it as ignored on ar_*.
    $payloadArr = [
        'model' => 'kokoro-82m',
        'input' => $input,
        'voice' => $voice,
        'response_format' => $format,
        'speed' => $speed,
        'ev_tone' => $tone,
        'ev_volume_db' => $volumeDb,
    ];
    $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://easyvoice.ae/api/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: audio/mpeg, audio/wav, application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $audio = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($audio === false || $http < 200 || $http >= 300) {
        return $fail($http, 'provider');
    }
    $bytes = (string) $audio;
    $meta = ['spoken_input' => $input, 'request' => [
        'voice' => $voice,
        'speed' => $speed,
        'ev_tone' => $tone,
        'ev_volume_db' => $volumeDb,
        // intentional omission: ev_pitch unsupported/ignored on ar_*
    ]];
    if ($format === 'wav' && str_starts_with($bytes, 'RIFF')) {
        return ['ok' => true, 'http' => $http, 'bytes' => $bytes, 'mime' => 'audio/wav', 'error' => ''] + $meta;
    }
    // MP3: ID3 or frame sync
    if ($format === 'mp3' && (str_starts_with($bytes, 'ID3') || (strlen($bytes) > 2 && (ord($bytes[0]) & 0xFF) === 0xFF))) {
        return ['ok' => true, 'http' => $http, 'bytes' => $bytes, 'mime' => 'audio/mpeg', 'error' => ''] + $meta;
    }
    if (str_contains($ctype, 'audio/mpeg') || str_contains($ctype, 'audio/mp3')) {
        return ['ok' => true, 'http' => $http, 'bytes' => $bytes, 'mime' => 'audio/mpeg', 'error' => ''] + $meta;
    }
    if (str_contains($ctype, 'audio/wav') && str_starts_with($bytes, 'RIFF')) {
        return ['ok' => true, 'http' => $http, 'bytes' => $bytes, 'mime' => 'audio/wav', 'error' => ''] + $meta;
    }
    return $fail($http, 'bad_audio');
}
