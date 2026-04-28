<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ai_providers.php';

try {

/**
 * AI streaming endpoint.
 * نقطة ربط الذكاء الاصطناعي ببث مباشر.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');

/**
 * Runtime diagnostics for production debugging.
 * تشخيص أخطاء وقت التشغيل مباشرة لتسهيل تتبع سبب 500.
 */
register_shutdown_function(static function (): void {
    $last = error_get_last();
    if (!is_array($last)) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($last['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
    }
    $msg = (string)($last['message'] ?? 'Unknown fatal error');
    $file = (string)($last['file'] ?? 'unknown');
    $line = (int)($last['line'] ?? 0);
    echo json_encode([
        'provider_failed' => 'internal',
        'reason' => "Fatal runtime error: {$msg} @ {$file}:{$line}",
        'fallback_attempted' => true,
    ], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '[ERROR] Method not allowed.';
    exit;
}

$currentUser = coreai_require_auth(false);
coreai_enforce_subscription_or_exit($currentUser, 'ai_request', 'ai_chat', true);

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    http_response_code(400);
    echo '[ERROR] Empty request body.';
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo '[ERROR] Invalid JSON payload.';
    exit;
}

$userMessage = trim((string)($payload['userMessage'] ?? ''));
$activeFileName = trim((string)($payload['activeFileName'] ?? 'unknown'));
$activeFileContent = (string)($payload['activeFileContent'] ?? '');
$selectedCode = (string)($payload['selectedCode'] ?? '');
$history = $payload['history'] ?? [];

if ($userMessage === '') {
    http_response_code(400);
    echo '[ERROR] userMessage is required.';
    exit;
}

/**
 * Validate and sanitize incoming history.
 * التحقق من سجل المحادثة وتنقيته قبل الإرسال للمزود.
 */
$historyMessages = [];
if (is_array($history)) {
    $trimmedHistory = array_slice($history, -20);
    foreach ($trimmedHistory as $item) {
        if (!is_array($item)) {
            continue;
        }

        $role = (string)($item['role'] ?? '');
        $content = trim((string)($item['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if ($role !== 'user' && $role !== 'assistant' && $role !== 'ai') {
            continue;
        }

        $historyMessages[] = [
            'role' => $role === 'ai' ? 'assistant' : $role,
            'content' => $content,
        ];
    }
}

/**
 * Load long-term persistent memory and merge with session memory.
 * تحميل الذاكرة الدائمة ودمجها مع ذاكرة الجلسة.
 */
$persistentHistory = coreai_read_persistent_history();
$persistentMessages = array_map(
    static function (array $entry): array {
        return [
            'role' => $entry['role'],
            'content' => $entry['content'],
        ];
    },
    $persistentHistory
);
$combinedMemory = array_merge($persistentMessages, $historyMessages);

/**
 * Reasoning layer before context construction.
 * طبقة التفكير قبل بناء السياق.
 */
$intent = coreai_analyze_intent($userMessage);
$strategy = coreai_reasoning_strategy_for_type($intent['type']);
$intelligentMemory = coreai_build_reasoned_memory_context($combinedMemory, $userMessage, $strategy);
$actionDecision = coreai_decide_action_mode($intent['type']);
$graphContext = coreai_build_graph_context($activeFileName, $userMessage);
$projectIntelligence = coreai_load_project_intelligence_memory();

/**
 * Keep normal text replies by default.
 * JSON action mode is enabled only when user explicitly asks to execute/apply.
 * إبقاء الرد النصي هو الافتراضي، وتفعيل وضع JSON فقط عند طلب تنفيذ صريح.
 */
$explicitExecutionRequested = preg_match(
    '/\b(apply|execute|execution|run|implement|patch|modify file|do it now|create|build|make|generate)\b|نفذ|طبق|تطبيق|نفّذ|اعمل|انشئ|أنشئ|ابني|سو|سوي/u',
    mb_strtolower($userMessage, 'UTF-8')
) === 1;
if ($explicitExecutionRequested === true) {
    $actionDecision = [
        'requires_action' => true,
        'action_type' => 'build',
    ];
} else {
    $actionDecision = [
        'requires_action' => false,
        'action_type' => 'none',
    ];
}

if (!function_exists('curl_init')) {
    echo json_encode([
        'provider_failed' => 'all',
        'reason' => 'cURL extension is not enabled on this server.',
        'fallback_attempted' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$systemPrompt = "You are CoreAI, an expert coding assistant inside an IDE. "
    . "Answer clearly and focus on the provided file context and selected code when relevant.";

if ($actionDecision['requires_action'] === true) {
    $systemPrompt .= " For this request type, output must be structured JSON actions only.";
}

$maxFileChars = (int)$strategy['file_chars'];
$trimmedFileContent = $activeFileContent;
if (mb_strlen($trimmedFileContent, 'UTF-8') > $maxFileChars) {
    $trimmedFileContent = mb_substr($trimmedFileContent, 0, $maxFileChars, 'UTF-8')
        . "\n...[truncated for token efficiency]";
}

$contextBlock = "Active file: {$activeFileName}\n\n"
    . "Detected intent: {$intent['type']}\n"
    . "Intent signals: " . ($intent['signals'] === [] ? '[none]' : implode(', ', $intent['signals'])) . "\n\n"
    . "Graph active node: {$graphContext['active_graph_file']}\n"
    . "Graph related files: " . ($graphContext['related_files'] === [] ? '[none]' : implode(', ', $graphContext['related_files'])) . "\n"
    . "Graph dependent modules: " . ($graphContext['dependent_modules'] === [] ? '[none]' : implode(', ', $graphContext['dependent_modules'])) . "\n"
    . "Graph connected components: " . ($graphContext['connected_components'] === [] ? '[none]' : implode(', ', $graphContext['connected_components'])) . "\n\n"
    . "File content (possibly trimmed):\n{$trimmedFileContent}\n\n"
    . "Selected code:\n" . ($selectedCode !== '' ? $selectedCode : '[none]');

if (($strategy['selected_code_priority'] ?? false) === true && $selectedCode !== '') {
    $contextBlock = "Active file: {$activeFileName}\n\n"
        . "Detected intent: {$intent['type']}\n"
        . "Intent signals: " . ($intent['signals'] === [] ? '[none]' : implode(', ', $intent['signals'])) . "\n\n"
        . "Graph active node: {$graphContext['active_graph_file']}\n"
        . "Graph related files: " . ($graphContext['related_files'] === [] ? '[none]' : implode(', ', $graphContext['related_files'])) . "\n"
        . "Graph dependent modules: " . ($graphContext['dependent_modules'] === [] ? '[none]' : implode(', ', $graphContext['dependent_modules'])) . "\n"
        . "Graph connected components: " . ($graphContext['connected_components'] === [] ? '[none]' : implode(', ', $graphContext['connected_components'])) . "\n\n"
        . "Selected code (priority):\n{$selectedCode}\n\n"
        . "File content (possibly trimmed):\n{$trimmedFileContent}";
}

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
];

/**
 * Add persistent project intelligence memory to system context.
 * إضافة ذاكرة ذكاء المشروع الدائمة إلى سياق النظام.
 */
$architectureStructure = $projectIntelligence['architecture_structure'] ?? [];
$systemPatterns = $projectIntelligence['system_patterns'] ?? [];
$repeatedPatterns = $projectIntelligence['repeated_design_patterns'] ?? [];
$messages[] = [
    'role' => 'system',
    'content' => "Project intelligence memory:\n"
        . "Architecture: " . json_encode($architectureStructure, JSON_UNESCAPED_UNICODE) . "\n"
        . "System patterns: " . json_encode($systemPatterns, JSON_UNESCAPED_UNICODE) . "\n"
        . "Repeated design patterns: " . json_encode($repeatedPatterns, JSON_UNESCAPED_UNICODE),
];

/**
 * Add action policy instruction before model response.
 * إضافة سياسة الإجراءات قبل توليد رد النموذج.
 */
if ($actionDecision['requires_action'] === true) {
    $messages[] = [
        'role' => 'system',
        'content' => "Action mode is enabled.\n"
            . "Intent: {$intent['type']}\n"
            . "Action type: {$actionDecision['action_type']}\n"
            . "Return valid JSON matching schema with concrete file-level steps.",
    ];
}

/**
 * Add compressed summary + relevant snippets only.
 * إضافة الملخص المضغوط والذكريات الأكثر صلة فقط.
 */
$memorySummary = trim((string)$intelligentMemory['summary']);
if ($memorySummary !== '') {
    $messages[] = [
        'role' => 'system',
        'content' => "Compressed memory summary ({$intent['type']} mode):\n{$memorySummary}",
    ];
}

foreach ($intelligentMemory['relevant'] as $memoryMessage) {
    $messages[] = $memoryMessage;
}

$messages[] = [
    'role' => 'user',
    'content' => $contextBlock . "\n\nUser request:\n" . $userMessage,
];

/**
 * Disable buffers to stream chunks instantly.
 * تعطيل التخزين المؤقت لإرسال الأجزاء مباشرة.
 */
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(1);

/**
 * Persist user turn in long-term memory before AI completion.
 * حفظ رسالة المستخدم في الذاكرة الدائمة قبل اكتمال رد الذكاء الاصطناعي.
 */
coreai_append_persistent_message('user', $userMessage);
$providerResult = coreai_ai_request($messages, [
    'intent' => (string)($intent['type'] ?? ''),
    'user_message' => $userMessage,
    'selected_code' => $selectedCode,
    'action_required' => ($actionDecision['requires_action'] ?? false) === true,
]);

$assistantResponseText = '';
if (($providerResult['ok'] ?? false) !== true) {
    echo json_encode([
        'provider_failed' => (string)($providerResult['provider'] ?? 'all'),
        'reason' => (string)($providerResult['reason'] ?? 'Unknown provider failure'),
        'fallback_attempted' => (bool)($providerResult['fallback_attempted'] ?? false),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Keep streaming-compatible output even for non-stream APIs.
 * الحفاظ على التوافق مع البث حتى عند مزودات تعيد النص دفعة واحدة.
 */
$fullText = (string)($providerResult['content'] ?? '');
$chunkSize = 220;
$offset = 0;
$textLength = strlen($fullText);
while ($offset < $textLength) {
    $chunk = substr($fullText, $offset, $chunkSize);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    flush();
    $assistantResponseText .= $chunk;
    $offset += $chunkSize;
}

/**
 * Persist assistant response for project-level continuity.
 * حفظ رد المساعد لضمان استمرارية السياق على مستوى المشروع.
 */
if (trim($assistantResponseText) !== '') {
    coreai_append_persistent_message('assistant', $assistantResponseText);
}

coreai_track_usage('ai_request', [
    'provider' => (string)($providerResult['provider'] ?? 'unknown'),
    'input_chars' => mb_strlen($userMessage, 'UTF-8'),
    'output_chars' => mb_strlen($assistantResponseText, 'UTF-8'),
    'http_code' => 200,
]);

} catch (Throwable $exception) {
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo json_encode([
        'provider_failed' => 'internal',
        'reason' => $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine(),
        'fallback_attempted' => true,
    ], JSON_UNESCAPED_UNICODE);
}
