<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Unified AI provider request layer.
 * طبقة موحدة لطلبات مزودي الذكاء الاصطناعي.
 *
 * @param array<int, array<string, string>> $messages
 * @param array<string, mixed> $context
 * @return array{ok:bool,provider:string,content:string,reason:string,fallback_attempted:bool}
 */
function coreai_ai_request(array $messages, array $context = []): array
{
    $taskType = coreai_classify_ai_task($context);
    $attemptOrder = coreai_provider_attempt_order($taskType);
    $attempted = [];
    $fallbackAttempted = false;

    $providerErrors = [];
    foreach ($attemptOrder as $provider) {
        $attempted[] = $provider;
        if (count($attempted) > 1) {
            $fallbackAttempted = true;
        }
        $result = coreai_call_provider($provider, $messages, $context);
        if (($result['ok'] ?? false) === true) {
            coreai_log_execution('success', 'ai_provider', $provider, 'AI provider responded.', [
                'provider' => $provider,
                'task_type' => $taskType,
                'fallback_attempted' => $fallbackAttempted,
            ]);
            return [
                'ok' => true,
                'provider' => $provider,
                'content' => (string)($result['content'] ?? ''),
                'reason' => '',
                'fallback_attempted' => $fallbackAttempted,
            ];
        }
        $providerErrors[$provider] = (string)($result['reason'] ?? 'unknown_error');
    }

    $reason = 'All providers failed.';
    if ($attempted !== []) {
        $reason .= ' Tried: ' . implode(', ', $attempted) . '.';
    }
    if ($providerErrors !== []) {
        $pairs = [];
        foreach ($providerErrors as $name => $errorText) {
            $pairs[] = $name . ': ' . $errorText;
        }
        $reason .= ' Details: ' . implode(' | ', $pairs);
    }
    coreai_log_execution('error', 'ai_provider', 'all', $reason, [
        'task_type' => $taskType,
        'fallback_attempted' => $fallbackAttempted,
        'provider_errors' => $providerErrors,
    ]);
    return [
        'ok' => false,
        'provider' => 'none',
        'content' => '',
        'reason' => $reason,
        'fallback_attempted' => $fallbackAttempted,
    ];
}

/**
 * Classify task for smart provider routing.
 * تصنيف المهمة لتوجيه ذكي بين المزودين.
 */
function coreai_classify_ai_task(array $context): string
{
    $intent = strtolower((string)($context['intent'] ?? ''));
    $userMessage = strtolower((string)($context['user_message'] ?? ''));
    $text = $intent . ' ' . $userMessage;

    if (strpos($text, 'reason') !== false || strpos($text, 'architecture') !== false || strpos($text, 'tradeoff') !== false) {
        return 'complex_reasoning';
    }
    if (
        strpos($text, 'code') !== false
        || strpos($text, 'refactor') !== false
        || strpos($text, 'debug') !== false
        || strpos($text, 'bug') !== false
        || strpos($text, 'build') !== false
    ) {
        return 'code';
    }
    return 'simple';
}

/**
 * Provider order with Gemini as primary default.
 * ترتيب المزودين مع Gemini كمزود افتراضي أساسي.
 *
 * @return array<int, string>
 */
function coreai_provider_attempt_order(string $taskType): array
{
    if ($taskType === 'complex_reasoning') {
        return ['gemini', 'openrouter', 'groq'];
    }
    if ($taskType === 'code') {
        return ['gemini', 'groq', 'openrouter'];
    }
    return ['gemini', 'groq', 'openrouter'];
}

/**
 * Execute one provider call.
 * تنفيذ استدعاء مزود واحد.
 *
 * @param array<int, array<string, string>> $messages
 * @param array<string, mixed> $context
 * @return array{ok:bool,content:string,reason?:string}
 */
function coreai_call_provider(string $provider, array $messages, array $context): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'content' => '', 'reason' => 'curl_missing'];
    }

    if ($provider === 'gemini') {
        return coreai_call_gemini($messages);
    }
    if ($provider === 'groq') {
        return coreai_call_groq($messages);
    }
    if ($provider === 'openrouter') {
        return coreai_call_openrouter($messages);
    }
    return ['ok' => false, 'content' => '', 'reason' => 'unsupported_provider'];
}

/**
 * Gemini provider call (free-tier friendly).
 * استدعاء Gemini (ملائم للخطة المجانية).
 */
function coreai_call_gemini(array $messages): array
{
    $apiKey = coreai_resolve_provider_api_key('gemini', 'GEMINI_API_KEY');
    if ($apiKey === '') {
        return ['ok' => false, 'content' => '', 'reason' => 'gemini_key_missing'];
    }
    $preferredModel = trim((string)(coreai_env('COREAI_GEMINI_MODEL', 'gemini-2.0-flash') ?? 'gemini-2.0-flash'));
    $candidateModels = coreai_gemini_candidate_models($preferredModel);

    $contents = [];
    foreach ($messages as $msg) {
        $role = strtolower((string)($msg['role'] ?? 'user'));
        $text = (string)($msg['content'] ?? '');
        $contents[] = [
            'role' => $role === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $text]],
        ];
    }
    $payload = ['contents' => $contents];

    $modelErrors = [];
    foreach ($candidateModels as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $response = coreai_http_json_post($url, $payload, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ]);
        if (($response['ok'] ?? false) !== true) {
            $modelErrors[] = $model . ' => ' . coreai_extract_provider_error('gemini', $response);
            continue;
        }
        $json = $response['json'] ?? [];
        $text = (string)($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (trim($text) === '') {
            $modelErrors[] = $model . ' => gemini_empty_response';
            continue;
        }
        return ['ok' => true, 'content' => $text];
    }

    return [
        'ok' => false,
        'content' => '',
        'reason' => 'gemini_models_failed: ' . implode(' | ', $modelErrors),
    ];
}

/**
 * Build Gemini model fallback list by availability generation.
 * إنشاء قائمة احتياطية لموديلات Gemini حسب الأكثر شيوعًا.
 *
 * @return array<int, string>
 */
function coreai_gemini_candidate_models(string $preferred): array
{
    $models = [
        $preferred,
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash-latest',
        'gemini-1.5-flash',
    ];
    $unique = [];
    foreach ($models as $model) {
        $clean = trim($model);
        if ($clean === '' || isset($unique[$clean])) {
            continue;
        }
        $unique[$clean] = true;
    }
    return array_keys($unique);
}

/**
 * Groq provider call (OpenAI-compatible API).
 * استدعاء Groq (واجهة متوافقة مع OpenAI).
 */
function coreai_call_groq(array $messages): array
{
    $apiKey = coreai_resolve_provider_api_key('groq', 'GROQ_API_KEY');
    if ($apiKey === '') {
        return ['ok' => false, 'content' => '', 'reason' => 'groq_key_missing'];
    }
    $preferredModel = trim((string)(coreai_env('COREAI_GROQ_MODEL', 'llama-3.3-70b-versatile') ?? 'llama-3.3-70b-versatile'));
    $candidateModels = coreai_groq_candidate_models($preferredModel);
    $modelErrors = [];

    foreach ($candidateModels as $model) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];
        $response = coreai_http_json_post(
            'https://api.groq.com/openai/v1/chat/completions',
            $payload,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]
        );
        if (($response['ok'] ?? false) !== true) {
            $modelErrors[] = $model . ' => ' . coreai_extract_provider_error('groq', $response);
            continue;
        }
        $json = $response['json'] ?? [];
        $text = (string)($json['choices'][0]['message']['content'] ?? '');
        if (trim($text) === '') {
            $modelErrors[] = $model . ' => groq_empty_response';
            continue;
        }
        return ['ok' => true, 'content' => $text];
    }

    return [
        'ok' => false,
        'content' => '',
        'reason' => 'groq_models_failed: ' . implode(' | ', $modelErrors),
    ];
}

/**
 * Build Groq model fallback list with active models.
 * إنشاء قائمة احتياطية لموديلات Groq الحديثة.
 *
 * @return array<int, string>
 */
function coreai_groq_candidate_models(string $preferred): array
{
    $models = [
        $preferred,
        'llama-3.3-70b-versatile',
        'llama-3.1-8b-instant',
        'mixtral-8x7b-32768',
    ];
    $unique = [];
    foreach ($models as $model) {
        $clean = trim($model);
        if ($clean === '' || isset($unique[$clean])) {
            continue;
        }
        $unique[$clean] = true;
    }
    return array_keys($unique);
}

/**
 * OpenRouter provider call (optional fallback).
 * استدعاء OpenRouter كخيار احتياطي اختياري.
 */
function coreai_call_openrouter(array $messages): array
{
    $apiKey = coreai_resolve_provider_api_key('openrouter', 'OPENROUTER_API_KEY');
    if ($apiKey === '') {
        return ['ok' => false, 'content' => '', 'reason' => 'openrouter_key_missing'];
    }
    $model = trim((string)(coreai_env('COREAI_OPENROUTER_MODEL', 'openai/gpt-4o-mini') ?? 'openai/gpt-4o-mini'));
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'stream' => false,
    ];
    $response = coreai_http_json_post(
        'https://openrouter.ai/api/v1/chat/completions',
        $payload,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]
    );
    if (($response['ok'] ?? false) !== true) {
        return ['ok' => false, 'content' => '', 'reason' => coreai_extract_provider_error('openrouter', $response)];
    }
    $json = $response['json'] ?? [];
    $text = (string)($json['choices'][0]['message']['content'] ?? '');
    if (trim($text) === '') {
        return ['ok' => false, 'content' => '', 'reason' => 'openrouter_empty_response'];
    }
    return ['ok' => true, 'content' => $text];
}

/**
 * Generic JSON POST helper.
 * أداة عامة لإرسال طلبات JSON.
 *
 * @param array<int, string> $headers
 * @return array{ok:bool,status:int,json:array<string,mixed>,error?:string}
 */
function coreai_http_json_post(string $url, array $payload, array $headers): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'json' => [], 'error' => 'curl_init_failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $curlError = '';
    if ($raw === false) {
        $curlError = (string)curl_error($ch);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'status' => $status, 'json' => [], 'error' => $curlError !== '' ? $curlError : 'empty_response'];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'json' => [], 'error' => 'invalid_json_response'];
    }
    return ['ok' => ($status >= 200 && $status < 300), 'status' => $status, 'json' => $json];
}

/**
 * Build readable provider error text from HTTP payload.
 * تحويل خطأ المزود لنص واضح قابل للعرض.
 */
function coreai_extract_provider_error(string $provider, array $response): string
{
    $status = (int)($response['status'] ?? 0);
    $json = $response['json'] ?? [];
    $fallbackError = trim((string)($response['error'] ?? 'provider_request_failed'));

    $message = '';
    if (is_array($json)) {
        $message = (string)($json['error']['message'] ?? '');
        if ($message === '') {
            $message = (string)($json['message'] ?? '');
        }
    }

    if ($message !== '') {
        return $provider . '_http_' . $status . ': ' . $message;
    }
    return $provider . '_http_' . $status . ': ' . $fallbackError;
}

/**
 * Resolve provider key from env, then user saved key.
 * جلب مفتاح المزود من البيئة أولاً ثم من المفتاح المحفوظ للمستخدم.
 */
function coreai_resolve_provider_api_key(string $provider, string $envKey): string
{
    $envValue = trim((string)(coreai_env($envKey) ?? ''));
    if ($envValue !== '') {
        return $envValue;
    }

    $user = coreai_current_user();
    $userKey = trim((string)($user['api_key'] ?? ''));
    if ($userKey === '') {
        return '';
    }

    $inferred = coreai_infer_api_key_provider($userKey);
    if ($inferred === $provider) {
        return $userKey;
    }

    // If unknown format, allow it for primary Gemini only.
    // إذا كان نوع المفتاح غير معروف نسمح به لـ Gemini لأنه المزود الأساسي.
    if ($provider === 'gemini' && $inferred === 'unknown') {
        return $userKey;
    }

    return '';
}

/**
 * Infer provider by key format prefix.
 * استنتاج نوع المزود من بادئة المفتاح.
 */
function coreai_infer_api_key_provider(string $key): string
{
    if (strpos($key, 'AIza') === 0) {
        return 'gemini';
    }
    if (strpos($key, 'gsk_') === 0) {
        return 'groq';
    }
    if (strpos($key, 'sk-or-') === 0 || strpos($key, 'or-') === 0) {
        return 'openrouter';
    }
    return 'unknown';
}
