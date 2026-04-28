<?php
declare(strict_types=1);

/**
 * CoreAI bootstrap.
 * Place global initialization logic here as the project grows.
 */

date_default_timezone_set('UTC');

/**
 * AI provider env examples (free-provider layer).
 * أمثلة متغيرات البيئة لطبقة مزودي الذكاء الاصطناعي المجانية.
 *
 * GEMINI_API_KEY=your_gemini_key_here
 * GROQ_API_KEY=your_groq_key_here
 * OPENROUTER_API_KEY=your_openrouter_key_here
 */

/**
 * Compatibility polyfills for older PHP runtimes.
 * دوال توافق لإصدارات PHP الأقدم.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }
        return true;
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return strlen($string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

/**
 * Read environment variable with fallback.
 * قراءة متغيرات البيئة مع قيمة افتراضية.
 */
function coreai_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

/**
 * Ensure session is started for CoreAI SaaS identity.
 * بدء الجلسة لهوية CoreAI بنمط SaaS.
 */
function coreai_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Resolve CoreAI SaaS users storage path.
 * تحديد مسار تخزين مستخدمي CoreAI SaaS.
 */
function coreai_users_file_path(): string
{
    return dirname(__DIR__) . '/context/saas/users.json';
}

/**
 * Load SaaS users.
 * تحميل مستخدمي نظام SaaS.
 *
 * @return array<int, array<string, mixed>>
 */
function coreai_load_users(): array
{
    $path = coreai_users_file_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

/**
 * Save SaaS users.
 * حفظ مستخدمي نظام SaaS.
 *
 * @param array<int, array<string, mixed>> $users
 * @return bool
 */
function coreai_save_users(array $users): bool
{
    $path = coreai_users_file_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
    }
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
    }
    return false;
}

/**
 * Register new SaaS account.
 * تسجيل حساب جديد لنظام SaaS.
 *
 * @return array{ok:bool,error:string,user?:array<string,mixed>}
 */
function coreai_register_user(string $username, string $password): array
{
    $username = trim($username);
    if ($username === '' || strlen($username) < 3) {
        return ['ok' => false, 'error' => 'Username must be at least 3 characters.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $users = coreai_load_users();
    foreach ($users as $u) {
        if (strtolower((string)($u['username'] ?? '')) === strtolower($username)) {
            return ['ok' => false, 'error' => 'Username already exists.'];
        }
    }

    $userId = 'u-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
    $users[] = [
        'id' => $userId,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'api_key' => '',
        'plan' => 'free',
        'team_id' => '',
        'created_at' => gmdate(DATE_ATOM),
    ];
    coreai_save_users($users);

    return [
        'ok' => true,
        'error' => '',
        'user' => ['id' => $userId, 'username' => $username],
    ];
}

/**
 * Authenticate SaaS account credentials.
 * التحقق من بيانات اعتماد حساب SaaS.
 *
 * @return array{ok:bool,error:string,user?:array<string,mixed>}
 */
function coreai_authenticate_user(string $username, string $password): array
{
    $users = coreai_load_users();
    foreach ($users as $u) {
        $storedUsername = (string)($u['username'] ?? '');
        if (strtolower($storedUsername) !== strtolower(trim($username))) {
            continue;
        }
        $hash = (string)($u['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }
        return [
            'ok' => true,
            'error' => '',
            'user' => ['id' => (string)$u['id'], 'username' => $storedUsername],
        ];
    }
    return ['ok' => false, 'error' => 'Invalid username or password.'];
}

/**
 * Resolve currently logged-in user.
 * جلب المستخدم المسجل حاليا.
 *
 * @return array<string,mixed>|null
 */
function coreai_current_user(): ?array
{
    coreai_start_session();
    $uid = trim((string)($_SESSION['coreai_user_id'] ?? ''));
    if ($uid === '') {
        return null;
    }
    foreach (coreai_load_users() as $u) {
        if ((string)($u['id'] ?? '') === $uid) {
            $storedApiKey = (string)($u['api_key'] ?? '');
            $sessionApiKey = trim((string)($_SESSION['coreai_api_key'] ?? ''));
            $fileApiKey = coreai_read_user_api_key_file($uid);
            $resolvedApiKey = $sessionApiKey !== '' ? $sessionApiKey : ($fileApiKey !== '' ? $fileApiKey : $storedApiKey);
            return [
                'id' => (string)$u['id'],
                'username' => (string)($u['username'] ?? ''),
                'api_key' => $resolvedApiKey,
                'plan' => strtolower((string)($u['plan'] ?? 'free')),
                'team_id' => (string)($u['team_id'] ?? ''),
            ];
        }
    }
    return null;
}

/**
 * Require authenticated user for CoreAI APIs.
 * فرض وجود مستخدم مصادق عليه لواجهات CoreAI.
 *
 * @return array<string,mixed>
 */
function coreai_require_auth(bool $jsonError = true): array
{
    $user = coreai_current_user();
    if ($user !== null) {
        return $user;
    }
    if ($jsonError) {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) {
            http_response_code(401);
        }
        echo '[ERROR] Authentication required.';
    }
    exit;
}

/**
 * Current user storage key for isolation.
 * مفتاح التخزين الحالي لعزل بيانات المستخدم.
 */
function coreai_current_user_key(): string
{
    $user = coreai_current_user();
    if ($user === null) {
        return 'anonymous';
    }
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$user['id']) ?? 'anonymous';
}

/**
 * Resolve per-user context root.
 * تحديد جذر سياق المستخدم المعزول.
 */
function coreai_user_context_root(): string
{
    return dirname(__DIR__) . '/context/tenants/' . coreai_current_user_key();
}

/**
 * Resolve per-user isolated project root.
 * تحديد جذر مشروع معزول لكل مستخدم.
 */
function coreai_user_project_root(): string
{
    $root = dirname(__DIR__) . '/projects/' . coreai_current_user_key();
    if (!is_dir($root)) {
        mkdir($root, 0777, true);
    }
    return $root;
}

/**
 * Resolve fallback API key file path per user.
 * تحديد مسار ملف احتياطي لمفتاح API لكل مستخدم.
 */
function coreai_user_api_key_path(string $userId): string
{
    $safeUserId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) ?? 'anonymous';
    return dirname(__DIR__) . '/context/tenants/' . $safeUserId . '/secrets/api_key.txt';
}

/**
 * Read API key from per-user fallback file.
 * قراءة مفتاح API من ملف احتياطي خاص بالمستخدم.
 */
function coreai_read_user_api_key_file(string $userId): string
{
    $path = coreai_user_api_key_path($userId);
    if (!is_file($path)) {
        return '';
    }
    $raw = file_get_contents($path);
    return is_string($raw) ? trim($raw) : '';
}

/**
 * Save API key to per-user fallback file.
 * حفظ مفتاح API في ملف احتياطي خاص بالمستخدم.
 */
function coreai_save_user_api_key_file(string $userId, string $apiKey): bool
{
    $path = coreai_user_api_key_path($userId);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }
    return file_put_contents($path, trim($apiKey) . PHP_EOL, LOCK_EX) !== false;
}

/**
 * Update current user API key.
 * تحديث مفتاح API للمستخدم الحالي.
 */
function coreai_set_current_user_api_key(string $apiKey): bool
{
    coreai_start_session();
    $user = coreai_current_user();
    if ($user === null) {
        return false;
    }
    $cleanKey = trim($apiKey);
    if ($cleanKey === '') {
        return false;
    }
    // Always keep a session-level key so CoreAI works
    // even if file persistence is blocked by permissions.
    // حفظ المفتاح في الجلسة دائمًا حتى لو فشل الحفظ في الملف.
    $_SESSION['coreai_api_key'] = $cleanKey;
    $savedToFallbackFile = coreai_save_user_api_key_file((string)$user['id'], $cleanKey);

    $users = coreai_load_users();
    foreach ($users as &$u) {
        if ((string)($u['id'] ?? '') !== (string)$user['id']) {
            continue;
        }
        $u['api_key'] = $cleanKey;
        $u['api_key_updated_at'] = gmdate(DATE_ATOM);
        return coreai_save_users($users) || $savedToFallbackFile || isset($_SESSION['coreai_api_key']);
    }
    unset($u);
    return $savedToFallbackFile || isset($_SESSION['coreai_api_key']);
}

/**
 * Track per-user usage metrics.
 * تتبع استهلاك المستخدم بشكل معزول.
 */
function coreai_track_usage(string $event, array $metrics = []): void
{
    $user = coreai_current_user();
    if ($user === null) {
        return;
    }
    $path = coreai_user_context_root() . '/usage.json';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $usage = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $usage = $decoded;
        }
    }
    $today = gmdate('Y-m-d');
    $month = gmdate('Y-m');
    $usage['user_id'] = (string)$user['id'];
    $usage['updated_at'] = gmdate(DATE_ATOM);
    $usage['totals'] = is_array($usage['totals'] ?? null) ? $usage['totals'] : [];
    $usage['daily'] = is_array($usage['daily'] ?? null) ? $usage['daily'] : [];
    $usage['monthly'] = is_array($usage['monthly'] ?? null) ? $usage['monthly'] : [];
    $usage['totals'][$event] = (int)($usage['totals'][$event] ?? 0) + 1;
    $usage['daily'][$today][$event] = (int)($usage['daily'][$today][$event] ?? 0) + 1;
    $usage['monthly'][$month][$event] = (int)($usage['monthly'][$month][$event] ?? 0) + 1;
    $usage['last_events'] = is_array($usage['last_events'] ?? null) ? $usage['last_events'] : [];
    $usage['last_events'][] = [
        'timestamp' => gmdate(DATE_ATOM),
        'event' => $event,
        'metrics' => $metrics,
    ];
    if (count($usage['last_events']) > 200) {
        $usage['last_events'] = array_slice($usage['last_events'], -200);
    }
    $json = json_encode($usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }
}

/**
 * Subscription plans catalog (SaaS).
 * كتالوج خطط الاشتراك لنظام SaaS.
 *
 * @return array<string, array<string, mixed>>
 */
function coreai_subscription_catalog(): array
{
    return [
        'free' => [
            'label' => 'Free',
            'quotas' => [
                'ai_request_daily' => 40,
                'ai_request_monthly' => 400,
                'plan_request_daily' => 25,
                'architecture_decision_request_daily' => 10,
            ],
            'request_limits' => [
                'per_minute' => 10,
            ],
            'features' => [
                'ai_chat' => true,
                'ai_execution' => false,
                'team_features' => false,
                'architecture_decision' => true,
                'planning' => true,
            ],
        ],
        'pro' => [
            'label' => 'Pro',
            'quotas' => [
                'ai_request_daily' => 500,
                'ai_request_monthly' => 10000,
                'plan_request_daily' => 300,
                'architecture_decision_request_daily' => 200,
                'execute_request_daily' => 120,
            ],
            'request_limits' => [
                'per_minute' => 60,
            ],
            'features' => [
                'ai_chat' => true,
                'ai_execution' => true,
                'team_features' => false,
                'architecture_decision' => true,
                'planning' => true,
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'quotas' => [
                'ai_request_daily' => -1,
                'ai_request_monthly' => -1,
                'plan_request_daily' => -1,
                'architecture_decision_request_daily' => -1,
                'execute_request_daily' => -1,
            ],
            'request_limits' => [
                'per_minute' => 150,
            ],
            'features' => [
                'ai_chat' => true,
                'ai_execution' => true,
                'team_features' => true,
                'architecture_decision' => true,
                'planning' => true,
            ],
        ],
    ];
}

/**
 * Resolve user plan slug with safe fallback.
 * تحديد خطة المستخدم مع قيمة افتراضية آمنة.
 */
function coreai_user_plan_slug(array $user): string
{
    $plan = strtolower((string)($user['plan'] ?? 'free'));
    $catalog = coreai_subscription_catalog();
    return isset($catalog[$plan]) ? $plan : 'free';
}

/**
 * Read current usage payload.
 * قراءة ملف الاستهلاك الحالي.
 *
 * @return array<string, mixed>
 */
function coreai_load_usage_payload(): array
{
    $path = coreai_user_context_root() . '/usage.json';
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Build subscription state for current user.
 * بناء حالة الاشتراك للمستخدم الحالي.
 *
 * @return array<string,mixed>
 */
function coreai_get_subscription_state(array $user): array
{
    $catalog = coreai_subscription_catalog();
    $planSlug = coreai_user_plan_slug($user);
    $plan = $catalog[$planSlug];
    $usage = coreai_load_usage_payload();
    $today = gmdate('Y-m-d');
    $month = gmdate('Y-m');
    $daily = is_array($usage['daily'][$today] ?? null) ? $usage['daily'][$today] : [];
    $monthly = is_array($usage['monthly'][$month] ?? null) ? $usage['monthly'][$month] : [];
    return [
        'plan_slug' => $planSlug,
        'plan' => $plan,
        'usage_daily' => $daily,
        'usage_monthly' => $monthly,
        'usage' => $usage,
    ];
}

/**
 * Update current user plan.
 * تحديث خطة المستخدم الحالي.
 */
function coreai_set_current_user_plan(string $planSlug): bool
{
    $user = coreai_current_user();
    if ($user === null) {
        return false;
    }
    $planSlug = strtolower(trim($planSlug));
    $catalog = coreai_subscription_catalog();
    if (!isset($catalog[$planSlug])) {
        return false;
    }
    $users = coreai_load_users();
    foreach ($users as &$u) {
        if ((string)($u['id'] ?? '') !== (string)$user['id']) {
            continue;
        }
        $u['plan'] = $planSlug;
        $u['plan_updated_at'] = gmdate(DATE_ATOM);
        coreai_save_users($users);
        return true;
    }
    unset($u);
    return false;
}

/**
 * Enforce feature gates + quotas + request limits.
 * فرض بوابات الميزات + الحصص + حدود الطلبات.
 *
 * @return array{ok:bool,error:string,code:int,plan:string}
 */
function coreai_enforce_subscription(array $user, string $event, string $feature): array
{
    $state = coreai_get_subscription_state($user);
    $planSlug = (string)$state['plan_slug'];
    $plan = is_array($state['plan'] ?? null) ? $state['plan'] : [];
    $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];

    if ($feature !== '' && (($features[$feature] ?? false) !== true)) {
        return ['ok' => false, 'error' => "Feature '{$feature}' requires a higher plan.", 'code' => 403, 'plan' => $planSlug];
    }

    $quotas = is_array($plan['quotas'] ?? null) ? $plan['quotas'] : [];
    $dailyLimit = (int)($quotas[$event . '_daily'] ?? -1);
    $monthlyLimit = (int)($quotas[$event . '_monthly'] ?? -1);
    $dailyCount = (int)($state['usage_daily'][$event] ?? 0);
    $monthlyCount = (int)($state['usage_monthly'][$event] ?? 0);

    if ($dailyLimit >= 0 && $dailyCount >= $dailyLimit) {
        return ['ok' => false, 'error' => "Daily quota reached for {$event}.", 'code' => 429, 'plan' => $planSlug];
    }
    if ($monthlyLimit >= 0 && $monthlyCount >= $monthlyLimit) {
        return ['ok' => false, 'error' => "Monthly quota reached for {$event}.", 'code' => 429, 'plan' => $planSlug];
    }

    $limits = is_array($plan['request_limits'] ?? null) ? $plan['request_limits'] : [];
    $perMinute = (int)($limits['per_minute'] ?? 0);
    if ($perMinute > 0) {
        $recent = 0;
        $threshold = time() - 60;
        $lastEvents = is_array($state['usage']['last_events'] ?? null) ? $state['usage']['last_events'] : [];
        for ($i = count($lastEvents) - 1; $i >= 0; $i--) {
            $ev = $lastEvents[$i];
            if (!is_array($ev)) {
                continue;
            }
            $ts = strtotime((string)($ev['timestamp'] ?? ''));
            if ($ts === false || $ts < $threshold) {
                break;
            }
            $recent++;
        }
        if ($recent >= $perMinute) {
            return ['ok' => false, 'error' => 'Request limit exceeded. Please wait and retry.', 'code' => 429, 'plan' => $planSlug];
        }
    }

    return ['ok' => true, 'error' => '', 'code' => 200, 'plan' => $planSlug];
}

/**
 * Enforce subscription and exit with formatted error.
 * فرض الاشتراك وإيقاف التنفيذ برسالة خطأ مناسبة.
 */
function coreai_enforce_subscription_or_exit(array $user, string $event, string $feature, bool $plainText = false): void
{
    $gate = coreai_enforce_subscription($user, $event, $feature);
    if (($gate['ok'] ?? false) === true) {
        return;
    }
    $code = (int)($gate['code'] ?? 403);
    $error = (string)($gate['error'] ?? 'Subscription policy blocked this request.');
    if (!headers_sent()) {
        http_response_code($code);
    }
    if ($plainText) {
        echo '[ERROR] ' . $error;
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => $error, 'plan' => (string)($gate['plan'] ?? 'free')], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * Resolve persistent memory JSON path.
 * تحديد مسار ملف الذاكرة الدائمة بصيغة JSON.
 */
function coreai_memory_file_path(): string
{
    return coreai_user_context_root() . '/memory/chat_history.json';
}

/**
 * Read project-level persistent memory.
 * قراءة الذاكرة الدائمة على مستوى المشروع.
 *
 * @return array<int, array{role:string, content:string, timestamp:string}>
 */
function coreai_read_persistent_history(): array
{
    $filePath = coreai_memory_file_path();
    if (!is_file($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return [];
    }

    $history = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = (string)($entry['role'] ?? '');
        $text = (string)($entry['content'] ?? '');
        $timestamp = (string)($entry['timestamp'] ?? '');
        if (($role !== 'user' && $role !== 'assistant') || trim($text) === '') {
            continue;
        }

        $history[] = [
            'role' => $role,
            'content' => $text,
            'timestamp' => $timestamp !== '' ? $timestamp : gmdate(DATE_ATOM),
        ];
    }

    return $history;
}

/**
 * Write project-level persistent memory atomically.
 * حفظ الذاكرة الدائمة للمشروع بطريقة آمنة.
 */
function coreai_write_persistent_history(array $history): bool
{
    $filePath = coreai_memory_file_path();
    $directory = dirname($filePath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        return false;
    }

    $json = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents($filePath, $json . PHP_EOL, LOCK_EX) !== false;
}

/**
 * Append one message into persistent memory.
 * إضافة رسالة واحدة إلى الذاكرة الدائمة.
 */
function coreai_append_persistent_message(string $role, string $content): bool
{
    if (($role !== 'user' && $role !== 'assistant') || trim($content) === '') {
        return false;
    }

    $history = coreai_read_persistent_history();
    $history[] = [
        'role' => $role,
        'content' => $content,
        'timestamp' => gmdate(DATE_ATOM),
    ];

    return coreai_write_persistent_history($history);
}

/**
 * Build lightweight keywords from text for relevance matching.
 * استخراج كلمات مفتاحية خفيفة من النص لمطابقة الصلة.
 *
 * @return array<int, string>
 */
function coreai_extract_keywords(string $text): array
{
    $text = mb_strtolower($text, 'UTF-8');
    $parts = preg_split('/[^\p{L}\p{N}_]+/u', $text);
    if ($parts === false) {
        return [];
    }

    $stopWords = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'your', 'you', 'are',
        'الى', 'على', 'هذا', 'هذه', 'من', 'في', 'عن', 'مع', 'الى', 'او', 'ثم', 'لكن',
    ];
    $stopMap = array_fill_keys($stopWords, true);

    $keywords = [];
    foreach ($parts as $part) {
        $token = trim($part);
        if ($token === '' || mb_strlen($token, 'UTF-8') < 3) {
            continue;
        }
        if (isset($stopMap[$token])) {
            continue;
        }
        $keywords[$token] = true;
    }

    return array_keys($keywords);
}

/**
 * Create a compact textual summary from older chat messages.
 * إنشاء ملخص نصي مختصر من الرسائل الأقدم.
 */
function coreai_summarize_old_history(array $messages, int $maxLines = 8): string
{
    if ($messages === []) {
        return '';
    }

    $lines = [];
    foreach ($messages as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $role = (string)($entry['role'] ?? 'user');
        $content = trim((string)($entry['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $prefix = $role === 'assistant' ? 'Assistant' : 'User';
        $short = preg_replace('/\s+/u', ' ', $content);
        if ($short === null) {
            $short = $content;
        }
        if (mb_strlen($short, 'UTF-8') > 140) {
            $short = mb_substr($short, 0, 140, 'UTF-8') . '...';
        }
        $lines[] = "- {$prefix}: {$short}";
    }

    if ($lines === []) {
        return '';
    }

    $lines = array_slice($lines, -$maxLines);
    return implode("\n", $lines);
}

/**
 * Select the most relevant memories for the current user request.
 * اختيار الذكريات الأكثر صلة بطلب المستخدم الحالي.
 *
 * @return array<int, array{role:string, content:string}>
 */
function coreai_select_relevant_memories(array $messages, string $query, int $limit = 8): array
{
    $queryKeywords = coreai_extract_keywords($query);
    if ($queryKeywords === []) {
        return array_slice($messages, -$limit);
    }
    $queryMap = array_fill_keys($queryKeywords, true);

    $scored = [];
    foreach ($messages as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $role = (string)($entry['role'] ?? '');
        $content = trim((string)($entry['content'] ?? ''));
        if (($role !== 'user' && $role !== 'assistant') || $content === '') {
            continue;
        }

        $msgKeywords = coreai_extract_keywords($content);
        $overlap = 0;
        foreach ($msgKeywords as $keyword) {
            if (isset($queryMap[$keyword])) {
                $overlap++;
            }
        }

        // Small recency bias keeps latest context useful.
        // إضافة انحياز بسيط للحداثة للحفاظ على السياق الحديث.
        $recencyBonus = ($index + 1) / (max(count($messages), 1) * 10);
        $score = $overlap + $recencyBonus;
        if ($score <= 0) {
            continue;
        }

        $scored[] = [
            'score' => $score,
            'index' => $index,
            'role' => $role,
            'content' => $content,
        ];
    }

    usort(
        $scored,
        static fn(array $a, array $b): int => $b['score'] <=> $a['score']
    );
    $top = array_slice($scored, 0, $limit);
    usort(
        $top,
        static fn(array $a, array $b): int => $a['index'] <=> $b['index']
    );

    return array_map(
        static fn(array $item): array => ['role' => $item['role'], 'content' => $item['content']],
        $top
    );
}

/**
 * Build intelligent memory context without sending full history.
 * بناء سياق ذاكرة ذكي بدون إرسال السجل الكامل.
 *
 * @return array{
 *     summary:string,
 *     relevant:array<int, array{role:string, content:string}>
 * }
 */
function coreai_build_intelligent_memory_context(array $messages, string $query): array
{
    $recentCount = 12;
    $recentMessages = array_slice($messages, -$recentCount);
    $olderMessages = array_slice($messages, 0, max(count($messages) - $recentCount, 0));

    $summary = coreai_summarize_old_history($olderMessages, 8);
    $relevant = coreai_select_relevant_memories($recentMessages, $query, 8);

    return [
        'summary' => $summary,
        'relevant' => $relevant,
    ];
}

/**
 * Analyze user intent and classify request type.
 * تحليل نية المستخدم وتصنيف نوع الطلب.
 *
 * @return array{
 *   type:string,
 *   signals:array<int, string>
 * }
 */
function coreai_analyze_intent(string $query): array
{
    $q = mb_strtolower($query, 'UTF-8');
    $types = [
        'debug' => ['debug', 'bug', 'error', 'fix', 'issue', 'exception', 'trace', 'not working', 'problem', 'خطأ', 'مشكلة', 'تصحيح'],
        'build' => ['build', 'create', 'add', 'implement', 'generate', 'setup', 'new feature', 'انشئ', 'بناء', 'اضف', 'تنفيذ'],
        'explain' => ['explain', 'why', 'what is', 'how does', 'clarify', 'describe', 'اشرح', 'وضح', 'ما هو', 'كيف'],
        'refactor' => ['refactor', 'cleanup', 'optimize', 'restructure', 'improve code', 'rework', 'اعادة هيكلة', 'تحسين', 'تنظيف'],
    ];

    $scores = [
        'debug' => 0,
        'build' => 0,
        'explain' => 0,
        'refactor' => 0,
    ];
    $signals = [];

    foreach ($types as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($q, $keyword) !== false) {
                $scores[$type]++;
                $signals[] = $keyword;
            }
        }
    }

    $selectedType = 'explain';
    $maxScore = -1;
    foreach ($scores as $type => $score) {
        if ($score > $maxScore) {
            $selectedType = $type;
            $maxScore = $score;
        }
    }

    // Fallback heuristics for common coding verbs.
    // قواعد احتياطية لأفعال البرمجة الشائعة.
    if ($maxScore <= 0) {
        if (preg_match('/\b(fix|bug|error|issue)\b/i', $query) === 1) {
            $selectedType = 'debug';
        } elseif (preg_match('/\b(refactor|optimi[sz]e|cleanup)\b/i', $query) === 1) {
            $selectedType = 'refactor';
        } elseif (preg_match('/\b(create|build|implement|add)\b/i', $query) === 1) {
            $selectedType = 'build';
        }
    }

    return [
        'type' => $selectedType,
        'signals' => array_values(array_unique($signals)),
    ];
}

/**
 * Define context strategy based on request type.
 * تحديد استراتيجية السياق حسب نوع الطلب.
 *
 * @return array{
 *   recent_count:int,
 *   summary_lines:int,
 *   relevant_limit:int,
 *   file_chars:int,
 *   selected_code_priority:bool
 * }
 */
function coreai_reasoning_strategy_for_type(string $requestType): array
{
    $defaults = [
        'recent_count' => 12,
        'summary_lines' => 8,
        'relevant_limit' => 8,
        'file_chars' => 12000,
        'selected_code_priority' => false,
    ];

    if ($requestType === 'debug') {
        return [
            'recent_count' => 16,
            'summary_lines' => 6,
            'relevant_limit' => 10,
            'file_chars' => 14000,
            'selected_code_priority' => true,
        ];
    }
    if ($requestType === 'build') {
        return [
            'recent_count' => 10,
            'summary_lines' => 7,
            'relevant_limit' => 7,
            'file_chars' => 11000,
            'selected_code_priority' => false,
        ];
    }
    if ($requestType === 'refactor') {
        return [
            'recent_count' => 14,
            'summary_lines' => 10,
            'relevant_limit' => 10,
            'file_chars' => 13000,
            'selected_code_priority' => true,
        ];
    }
    if ($requestType === 'explain') {
        return [
            'recent_count' => 8,
            'summary_lines' => 6,
            'relevant_limit' => 6,
            'file_chars' => 9000,
            'selected_code_priority' => false,
        ];
    }

    return $defaults;
}

/**
 * Build memory with reasoning-aware prioritization.
 * بناء الذاكرة مع ترتيب الأولوية بناء على طبقة التفكير.
 *
 * @return array{
 *     summary:string,
 *     relevant:array<int, array{role:string, content:string}>
 * }
 */
function coreai_build_reasoned_memory_context(array $messages, string $query, array $strategy): array
{
    $recentCount = max((int)($strategy['recent_count'] ?? 12), 1);
    $summaryLines = max((int)($strategy['summary_lines'] ?? 8), 1);
    $relevantLimit = max((int)($strategy['relevant_limit'] ?? 8), 1);

    $recentMessages = array_slice($messages, -$recentCount);
    $olderMessages = array_slice($messages, 0, max(count($messages) - $recentCount, 0));

    $summary = coreai_summarize_old_history($olderMessages, $summaryLines);
    $relevant = coreai_select_relevant_memories($recentMessages, $query, $relevantLimit);

    return [
        'summary' => $summary,
        'relevant' => $relevant,
    ];
}

/**
 * Decide whether the assistant should output actions or plain text.
 * تحديد ما إذا كان المطلوب إخراج إجراءات منظمة أو نص عادي.
 *
 * @return array{
 *   requires_action:bool,
 *   mode:string,
 *   action_type:string
 * }
 */
function coreai_decide_action_mode(string $intentType): array
{
    if ($intentType === 'build') {
        return [
            'requires_action' => true,
            'mode' => 'json_action',
            'action_type' => 'create_structure',
        ];
    }
    if ($intentType === 'refactor') {
        return [
            'requires_action' => true,
            'mode' => 'json_action',
            'action_type' => 'modify_files',
        ];
    }
    if ($intentType === 'debug') {
        return [
            'requires_action' => true,
            'mode' => 'json_action',
            'action_type' => 'inspect_and_fix',
        ];
    }

    return [
        'requires_action' => false,
        'mode' => 'text',
        'action_type' => 'none',
    ];
}

/**
 * Build JSON schema for action-based AI outputs.
 * إنشاء مخطط JSON لإخراجات الذكاء الاصطناعي المعتمدة على الإجراءات.
 */
function coreai_action_response_schema(string $actionType): array
{
    return [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'coreai_action_response',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'intent' => [
                        'type' => 'string',
                        'enum' => ['build', 'refactor', 'debug'],
                    ],
                    'requires_action' => ['type' => 'boolean'],
                    'action_type' => [
                        'type' => 'string',
                        'enum' => ['create_structure', 'modify_files', 'inspect_and_fix'],
                    ],
                    'summary' => ['type' => 'string'],
                    'actions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'step' => ['type' => 'integer'],
                                'target' => ['type' => 'string'],
                                'operation' => ['type' => 'string'],
                                'details' => ['type' => 'string'],
                            ],
                            'required' => ['step', 'target', 'operation', 'details'],
                        ],
                    ],
                    'risks' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['intent', 'requires_action', 'action_type', 'summary', 'actions', 'risks'],
            ],
        ],
    ];
}

/**
 * Resolve operation log file for safe execution engine.
 * تحديد مسار ملف السجل لمحرك التنفيذ الآمن.
 */
function coreai_execution_log_path(): string
{
    return coreai_user_context_root() . '/logs/execution.log';
}

/**
 * Append one line to execution log.
 * إضافة سطر واحد إلى سجل التنفيذ.
 */
function coreai_log_execution(string $status, string $operation, string $target, string $message, array $context = []): void
{
    $logPath = coreai_execution_log_path();
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $contextJson = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $line = sprintf(
        "[%s] status=%s operation=%s target=%s message=%s context=%s\n",
        gmdate(DATE_ATOM),
        $status,
        $operation,
        $target,
        preg_replace('/\s+/u', ' ', $message) ?? $message,
        $contextJson !== false ? $contextJson : ''
    );
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Normalize and validate a path inside /coreai only.
 * تطبيع المسار والتحقق من أنه داخل /coreai فقط.
 */
function coreai_resolve_safe_path(string $relativePath): ?string
{
    $baseDir = realpath(coreai_user_project_root());
    if ($baseDir === false) {
        return null;
    }

    $path = trim($relativePath);
    if ($path === '') {
        return null;
    }
    if (strpos($path, "\0") !== false) {
        return null;
    }

    $normalized = str_replace('\\', '/', $path);
    if (preg_match('/[\x00-\x1F]/', $normalized) === 1) {
        return null;
    }
    if (preg_match('/^[a-zA-Z]:\//', $normalized) === 1 || str_starts_with($normalized, '/')) {
        return null;
    }

    $parts = explode('/', $normalized);
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            return null;
        }
    }

    $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $existingRealPath = realpath($candidate);
    if ($existingRealPath !== false) {
        if (!str_starts_with($existingRealPath, $baseDir . DIRECTORY_SEPARATOR) && $existingRealPath !== $baseDir) {
            return null;
        }
        return $existingRealPath;
    }

    $parentDir = dirname($candidate);
    $parentRealPath = realpath($parentDir);
    if ($parentRealPath === false) {
        return null;
    }
    if (!str_starts_with($parentRealPath, $baseDir . DIRECTORY_SEPARATOR) && $parentRealPath !== $baseDir) {
        return null;
    }

    return $candidate;
}

/**
 * Validate approved actions payload before execution.
 * التحقق من حمولة الإجراءات المعتمدة قبل التنفيذ.
 *
 * @return array{ok:bool,error:string}
 */
function coreai_validate_approved_actions(array $actions): array
{
    if ($actions === []) {
        return ['ok' => false, 'error' => 'No approved actions provided.'];
    }

    foreach ($actions as $index => $action) {
        if (!is_array($action)) {
            return ['ok' => false, 'error' => "Invalid action format at index {$index}."];
        }

        if (($action['approved'] ?? false) !== true) {
            return ['ok' => false, 'error' => "Action at index {$index} is not explicitly approved."];
        }

        $operation = strtolower(trim((string)($action['operation'] ?? '')));
        if (!in_array($operation, ['create', 'update', 'modify', 'delete'], true)) {
            return ['ok' => false, 'error' => "Unsupported operation at index {$index}."];
        }

        $target = trim((string)($action['target'] ?? ''));
        if (coreai_resolve_safe_path($target) === null) {
            return ['ok' => false, 'error' => "Unsafe target path at index {$index}."];
        }
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Execute approved file actions inside /coreai only.
 * تنفيذ الإجراءات المعتمدة على الملفات داخل /coreai فقط.
 *
 * @param array<int, array{operation:string,target:string,details:string,approved?:bool}> $actions
 * @return array{results:array<int, array<string, string|bool>>, summary:string}
 */
function coreai_execute_approved_actions(array $actions): array
{
    $results = [];
    $successCount = 0;

    foreach ($actions as $index => $action) {
        $operation = strtolower(trim((string)($action['operation'] ?? '')));
        $target = trim((string)($action['target'] ?? ''));
        $details = (string)($action['details'] ?? '');
        $row = [
            'step' => (string)($index + 1),
            'operation' => $operation,
            'target' => $target,
            'ok' => false,
            'message' => '',
        ];

        $approved = ($action['approved'] ?? false) === true;
        $safePath = coreai_resolve_safe_path($target);
        if (!$approved) {
            $row['message'] = 'Blocked: action is not approved.';
            coreai_log_execution('blocked', $operation, $target, $row['message']);
            $results[] = $row;
            continue;
        }
        if ($safePath === null) {
            $row['message'] = 'Blocked: invalid or external path.';
            coreai_log_execution('blocked', $operation, $target, $row['message']);
            $results[] = $row;
            continue;
        }

        try {
            if ($operation === 'create') {
                if (is_file($safePath)) {
                    throw new RuntimeException('Create failed: file already exists.');
                }
                $parent = dirname($safePath);
                if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                    throw new RuntimeException('Create failed: unable to create parent directory.');
                }
                if (file_put_contents($safePath, $details, LOCK_EX) === false) {
                    throw new RuntimeException('Create failed: unable to write file.');
                }
                $row['ok'] = true;
                $row['message'] = 'File created.';
                coreai_log_execution(
                    'success',
                    $operation,
                    $target,
                    $row['message'],
                    ['before' => null, 'after' => $details]
                );
            } elseif ($operation === 'modify' || $operation === 'update') {
                if (!is_file($safePath)) {
                    throw new RuntimeException('Update failed: target file does not exist.');
                }
                $before = (string)(file_get_contents($safePath) ?: '');
                if (file_put_contents($safePath, $details, LOCK_EX) === false) {
                    throw new RuntimeException('Update failed: unable to write file.');
                }
                $row['ok'] = true;
                $row['message'] = 'File updated.';
                coreai_log_execution(
                    'success',
                    $operation,
                    $target,
                    $row['message'],
                    ['before' => $before, 'after' => $details]
                );
            } elseif ($operation === 'delete') {
                if (!is_file($safePath)) {
                    throw new RuntimeException('Delete failed: target file does not exist.');
                }
                $before = (string)(file_get_contents($safePath) ?: '');
                if (!unlink($safePath)) {
                    throw new RuntimeException('Delete failed: unable to remove file.');
                }
                $row['ok'] = true;
                $row['message'] = 'File deleted.';
                coreai_log_execution(
                    'success',
                    $operation,
                    $target,
                    $row['message'],
                    ['before' => $before, 'after' => null]
                );
            } else {
                throw new RuntimeException('Unsupported operation.');
            }

            $successCount++;
        } catch (Throwable $exception) {
            $row['message'] = $exception->getMessage();
            coreai_log_execution('error', $operation, $target, (string)$row['message']);
        }

        $results[] = $row;
    }

    $summary = sprintf('Executed %d/%d approved actions.', $successCount, count($actions));
    return ['results' => $results, 'summary' => $summary];
}

/**
 * Validate multi-file action groups before execution.
 * التحقق من مجموعات الإجراءات متعددة الملفات قبل التنفيذ.
 *
 * @param array<int, array{id:string,actions:array<int, array<string, mixed>>}> $groups
 * @return array{ok:bool,error:string}
 */
function coreai_validate_action_groups(array $groups): array
{
    if ($groups === []) {
        return ['ok' => false, 'error' => 'No action groups provided.'];
    }

    foreach ($groups as $groupIndex => $group) {
        if (!is_array($group)) {
            return ['ok' => false, 'error' => "Invalid group format at index {$groupIndex}."];
        }
        $groupId = trim((string)($group['id'] ?? ''));
        $actions = $group['actions'] ?? [];
        if ($groupId === '' || !is_array($actions) || $actions === []) {
            return ['ok' => false, 'error' => "Invalid or empty group at index {$groupIndex}."];
        }

        $seenTargets = [];
        foreach ($actions as $actionIndex => $action) {
            if (!is_array($action)) {
                return ['ok' => false, 'error' => "Invalid action format in group {$groupId}."];
            }
            if (($action['approved'] ?? false) !== true) {
                return ['ok' => false, 'error' => "Unapproved action in group {$groupId}."];
            }

            $operation = strtolower(trim((string)($action['operation'] ?? '')));
            $target = trim((string)($action['target'] ?? ''));
            if (!in_array($operation, ['create', 'update', 'modify', 'delete'], true)) {
                return ['ok' => false, 'error' => "Unsupported operation in group {$groupId}."];
            }
            if (coreai_resolve_safe_path($target) === null) {
                return ['ok' => false, 'error' => "Unsafe target path in group {$groupId}."];
            }

            // Dependency-safety guard: one target per group step plan.
            // حارس أمان الاعتمادية: منع تكرار نفس الملف داخل نفس المجموعة.
            if (isset($seenTargets[$target])) {
                return [
                    'ok' => false,
                    'error' => "Duplicate target '{$target}' in group {$groupId} (action {$actionIndex}).",
                ];
            }
            $seenTargets[$target] = true;
        }
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Execute one multi-file group with rollback on failure.
 * تنفيذ مجموعة ملفات متعددة مع التراجع عند الفشل.
 *
 * @param array{id:string,actions:array<int, array<string, mixed>>} $group
 * @return array{group_id:string,ok:bool,rolled_back:bool,results:array<int, array<string, mixed>>,summary:string}
 */
function coreai_execute_action_group(array $group): array
{
    $groupId = (string)($group['id'] ?? 'group');
    $actions = is_array($group['actions'] ?? null) ? $group['actions'] : [];
    $results = [];
    $snapshots = [];
    $failed = false;
    $failureMessage = '';

    // Coordinated ordering for consistency:
    // create -> update/modify -> delete
    // ترتيب منسق لضمان الاتساق: إنشاء ثم تعديل ثم حذف.
    usort($actions, static function (array $a, array $b): int {
        $rank = static function (string $op): int {
            $o = strtolower(trim($op));
            if ($o === 'create') {
                return 1;
            }
            if ($o === 'update' || $o === 'modify') {
                return 2;
            }
            if ($o === 'delete') {
                return 3;
            }
            return 9;
        };
        return $rank((string)($a['operation'] ?? '')) <=> $rank((string)($b['operation'] ?? ''));
    });

    foreach ($actions as $index => $action) {
        $operation = strtolower(trim((string)($action['operation'] ?? '')));
        $target = trim((string)($action['target'] ?? ''));
        $details = (string)($action['details'] ?? '');
        $safePath = coreai_resolve_safe_path($target);

        $row = [
            'group_id' => $groupId,
            'step' => $index + 1,
            'operation' => $operation,
            'target' => $target,
            'ok' => false,
            'message' => '',
            'before_content' => '',
            'after_content' => '',
        ];

        if ($safePath === null) {
            $failed = true;
            $failureMessage = 'Unsafe path detected during execution.';
            $row['message'] = $failureMessage;
            $results[] = $row;
            coreai_log_execution('blocked', $operation, $target, $failureMessage, ['group_id' => $groupId]);
            break;
        }

        $existedBefore = is_file($safePath);
        $beforeContent = $existedBefore ? (string)(file_get_contents($safePath) ?: '') : null;

        $snapshots[] = [
            'path' => $safePath,
            'target' => $target,
            'existed_before' => $existedBefore,
            'before' => $beforeContent,
        ];

        try {
            if ($operation === 'create') {
                if ($existedBefore) {
                    throw new RuntimeException('Create failed: file already exists.');
                }
                $parent = dirname($safePath);
                if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                    throw new RuntimeException('Create failed: unable to create parent directory.');
                }
                if (file_put_contents($safePath, $details, LOCK_EX) === false) {
                    throw new RuntimeException('Create failed: unable to write file.');
                }
                $row['before_content'] = '';
                $row['after_content'] = $details;
            } elseif ($operation === 'update' || $operation === 'modify') {
                if (!$existedBefore) {
                    throw new RuntimeException('Update failed: target file does not exist.');
                }
                if (file_put_contents($safePath, $details, LOCK_EX) === false) {
                    throw new RuntimeException('Update failed: unable to write file.');
                }
                $row['before_content'] = (string)$beforeContent;
                $row['after_content'] = $details;
            } elseif ($operation === 'delete') {
                if (!$existedBefore) {
                    throw new RuntimeException('Delete failed: target file does not exist.');
                }
                if (!unlink($safePath)) {
                    throw new RuntimeException('Delete failed: unable to remove file.');
                }
                $row['before_content'] = (string)$beforeContent;
                $row['after_content'] = '';
            } else {
                throw new RuntimeException('Unsupported operation.');
            }

            $row['ok'] = true;
            $row['message'] = 'Applied.';
            coreai_log_execution(
                'success',
                $operation,
                $target,
                'Applied in coordinated group.',
                ['group_id' => $groupId, 'before' => $beforeContent, 'after' => $operation === 'delete' ? null : $details]
            );
        } catch (Throwable $exception) {
            $failed = true;
            $failureMessage = $exception->getMessage();
            $row['message'] = $failureMessage;
            coreai_log_execution('error', $operation, $target, $failureMessage, ['group_id' => $groupId]);
        }

        $results[] = $row;
        if ($failed) {
            break;
        }
    }

    $rolledBack = false;
    if ($failed) {
        // Rollback in reverse order for transactional safety.
        // التراجع بترتيب عكسي لضمان أمان شبيه بالمعاملات.
        for ($i = count($snapshots) - 1; $i >= 0; $i--) {
            $snapshot = $snapshots[$i];
            $path = (string)$snapshot['path'];
            $target = (string)$snapshot['target'];
            $existedBefore = (bool)$snapshot['existed_before'];
            $before = $snapshot['before'];

            try {
                if ($existedBefore) {
                    if (file_put_contents($path, (string)$before, LOCK_EX) === false) {
                        throw new RuntimeException('Rollback write failed.');
                    }
                } else {
                    if (is_file($path) && !unlink($path)) {
                        throw new RuntimeException('Rollback delete failed.');
                    }
                }
                coreai_log_execution('rollback', 'rollback', $target, 'Rollback applied.', ['group_id' => $groupId]);
                $rolledBack = true;
            } catch (Throwable $exception) {
                coreai_log_execution('rollback_error', 'rollback', $target, $exception->getMessage(), ['group_id' => $groupId]);
            }
        }
    }

    return [
        'group_id' => $groupId,
        'ok' => !$failed,
        'rolled_back' => $rolledBack,
        'results' => $results,
        'summary' => !$failed
            ? "Group {$groupId} executed successfully."
            : "Group {$groupId} failed: {$failureMessage}" . ($rolledBack ? ' (rollback applied)' : ''),
    ];
}

/**
 * Execute multiple coordinated action groups.
 * تنفيذ عدة مجموعات إجراءات منسقة.
 *
 * @param array<int, array{id:string,actions:array<int, array<string, mixed>>}> $groups
 * @return array{ok:bool,groups:array<int, array<string, mixed>>,summary:string}
 */
function coreai_execute_action_groups(array $groups): array
{
    $groupResults = [];
    $allOk = true;
    foreach ($groups as $group) {
        $result = coreai_execute_action_group($group);
        $groupResults[] = $result;
        if (($result['ok'] ?? false) !== true) {
            $allOk = false;
        }
    }

    $successCount = 0;
    foreach ($groupResults as $gr) {
        if (($gr['ok'] ?? false) === true) {
            $successCount++;
        }
    }

    return [
        'ok' => $allOk,
        'groups' => $groupResults,
        'summary' => "Executed {$successCount}/" . count($groupResults) . ' groups.',
    ];
}

/**
 * Resolve execution plan output path.
 * تحديد مسار ملف خطة التنفيذ.
 */
function coreai_execution_plan_path(): string
{
    return dirname(__DIR__) . '/execution_plan.json';
}

/**
 * Resolve semantic graph JSON path.
 * تحديد مسار ملف المخطط الدلالي JSON.
 */
function coreai_semantic_graph_path(): string
{
    return coreai_user_context_root() . '/semantic_graph.json';
}

/**
 * Load semantic graph nodes/edges safely.
 * تحميل عقد وروابط المخطط الدلالي بشكل آمن.
 *
 * @return array{nodes:array<int, array<string, mixed>>, semantic_edges:array<int, array<string, mixed>>}
 */
function coreai_load_semantic_graph(): array
{
    $path = coreai_semantic_graph_path();
    if (!is_file($path)) {
        return ['nodes' => [], 'semantic_edges' => []];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return ['nodes' => [], 'semantic_edges' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['nodes' => [], 'semantic_edges' => []];
    }

    return [
        'nodes' => is_array($decoded['nodes'] ?? null) ? $decoded['nodes'] : [],
        'semantic_edges' => is_array($decoded['semantic_edges'] ?? null) ? $decoded['semantic_edges'] : [],
    ];
}

/**
 * Analyze direct + indirect graph side-effects for a set of files.
 * تحليل الآثار المباشرة وغير المباشرة في المخطط لمجموعة ملفات.
 *
 * @param array<int, string> $seedFiles
 * @param array<int, array<string, mixed>> $edges
 * @return array{impacted_files:array<int, string>, indirect_side_effects:array<int, array<string, string>>}
 */
function coreai_analyze_graph_impact(array $seedFiles, array $edges): array
{
    $seeds = array_values(array_unique(array_filter($seedFiles, static fn(string $v): bool => trim($v) !== '')));
    $direct = [];
    $queue = [];
    $seen = [];
    foreach ($seeds as $seed) {
        $seen[$seed] = true;
        $queue[] = ['node' => $seed, 'depth' => 0];
    }

    $indirectEffects = [];
    while ($queue !== []) {
        $current = array_shift($queue);
        if (!is_array($current)) {
            continue;
        }
        $node = (string)($current['node'] ?? '');
        $depth = (int)($current['depth'] ?? 0);

        foreach ($edges as $edge) {
            if (!is_array($edge)) {
                continue;
            }
            $from = (string)($edge['from'] ?? '');
            $to = (string)($edge['to'] ?? '');
            $type = (string)($edge['type'] ?? '');

            $neighbor = null;
            if ($from === $node) {
                $neighbor = $to;
            } elseif ($to === $node) {
                $neighbor = $from;
            }
            if ($neighbor === null || $neighbor === '') {
                continue;
            }

            if ($depth === 0) {
                $direct[] = $neighbor;
            } else {
                $indirectEffects[] = [
                    'from' => $node,
                    'to' => $neighbor,
                    'via' => $type,
                ];
            }

            if (!isset($seen[$neighbor]) && $depth < 2) {
                $seen[$neighbor] = true;
                $queue[] = ['node' => $neighbor, 'depth' => $depth + 1];
            }
        }
    }

    $impacted = array_values(array_unique(array_merge($seeds, $direct, array_keys($seen))));
    return [
        'impacted_files' => $impacted,
        'indirect_side_effects' => $indirectEffects,
    ];
}

/**
 * Calculate risk score for one action group.
 * حساب درجة المخاطر لمجموعة إجراءات واحدة.
 */
function coreai_calculate_group_risk_score(array $operations, array $dependencyChanges, array $impactedFiles): int
{
    $score = 0;
    foreach ($operations as $op) {
        $operation = strtolower((string)($op['operation'] ?? ''));
        if ($operation === 'delete') {
            $score += 40;
        } elseif ($operation === 'update' || $operation === 'modify') {
            $score += 20;
        } elseif ($operation === 'create') {
            $score += 10;
        }
    }

    $score += min(count($dependencyChanges) * 5, 25);
    $score += min(max(count($impactedFiles) - 1, 0) * 3, 25);

    if ($score > 100) {
        $score = 100;
    }
    return $score;
}

/**
 * Semantic-aware risk model using logic-level signals.
 * نموذج مخاطر دلالي يعتمد على إشارات المنطق.
 *
 * Factors:
 * - business_logic_sensitivity
 * - api_criticality
 * - dependency_depth
 * - cascade_probability
 *
 * @param array<int, string> $affectedFiles
 * @param array<int, array<int, string>> $chainPaths
 * @param array<int, array<string, mixed>> $semanticNodes
 * @param array<int, array<string, mixed>> $operations
 * @return array{
 *   semantic_risk_score:int,
 *   risk_category:string,
 *   factors:array<string, int>
 * }
 */
function coreai_calculate_semantic_risk_score(
    array $affectedFiles,
    array $chainPaths,
    array $semanticNodes,
    array $operations,
    array $strategy = []
): array {
    $adaptive = coreai_get_adaptive_risk_weights($strategy);
    $nodeMap = [];
    foreach ($semanticNodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $path = (string)($node['path'] ?? '');
        if ($path !== '') {
            $nodeMap[$path] = $node;
        }
    }

    $businessLogicSensitivity = 0;
    $apiCriticality = 0;
    foreach ($affectedFiles as $file) {
        $node = $nodeMap[$file] ?? null;
        if (!is_array($node)) {
            continue;
        }

        $pathLower = strtolower((string)$file);
        $functions = is_array($node['functions'] ?? null) ? $node['functions'] : [];
        $apiFlows = is_array($node['api_flows'] ?? null) ? $node['api_flows'] : [];
        $fileTag = strtoupper((string)($node['file_tag'] ?? 'HELPER'));

        // UI-only areas are lower business-risk unless they carry critical APIs.
        // أجزاء الواجهة فقط أقل خطورة ما لم تحتوي على واجهات API حرجة.
        $isUiOnlyPath = str_contains($pathLower, 'css/')
            || str_contains($pathLower, 'js/')
            || str_contains($pathLower, 'frontend/')
            || str_ends_with($pathLower, '.css');
        if ($isUiOnlyPath && $apiFlows === []) {
            $businessLogicSensitivity += 1;
        }
        if ($fileTag === 'CORE_LOGIC') {
            $businessLogicSensitivity += 6;
        } elseif ($fileTag === 'UI_LAYER') {
            $businessLogicSensitivity += 1;
        } elseif ($fileTag === 'CONFIG') {
            $businessLogicSensitivity += 3;
        }

        foreach ($functions as $fn) {
            if (!is_array($fn)) {
                continue;
            }
            $role = strtolower((string)($fn['semantic_role'] ?? 'general_logic'));
            $fnTag = strtoupper((string)($fn['tag'] ?? $fileTag));
            if (in_array($role, ['execution', 'validation', 'analysis'], true)) {
                $businessLogicSensitivity += 7;
            } elseif (in_array($role, ['data_write', 'data_read'], true)) {
                $businessLogicSensitivity += 4;
            } else {
                $businessLogicSensitivity += 2;
            }
            if ($fnTag === 'CORE_LOGIC') {
                $businessLogicSensitivity += 2;
            } elseif ($fnTag === 'UI_LAYER') {
                $businessLogicSensitivity = max($businessLogicSensitivity - 1, 0);
            }
        }

        foreach ($apiFlows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $flowType = strtolower((string)($flow['flow_type'] ?? ''));
            $endpoint = strtolower((string)($flow['endpoint'] ?? ''));
            $isPublicApi = str_contains($endpoint, '/api/')
                || str_contains($endpoint, 'http://')
                || str_contains($endpoint, 'https://');

            if ($flowType === 'api_handler' || $isPublicApi) {
                $apiCriticality += 11;
            } elseif ($flowType === 'internal_api_call') {
                $apiCriticality += 5;
            } elseif ($flowType === 'external_api_call') {
                $apiCriticality += 9;
            }
        }
    }

    // Dependency depth: longer chains indicate wider coupling.
    // عمق الاعتمادية: السلاسل الأطول تعني ترابطا أوسع.
    $maxDepth = 1;
    foreach ($chainPaths as $path) {
        $depth = count($path);
        if ($depth > $maxDepth) {
            $maxDepth = $depth;
        }
    }
    $dependencyDepth = min(($maxDepth - 1) * 8, 30);

    // Cascade probability: chain density + destructive ops.
    // احتمال التأثير المتسلسل: كثافة السلاسل + العمليات المدمرة.
    $deleteCount = 0;
    foreach ($operations as $op) {
        $operation = strtolower((string)($op['operation'] ?? ''));
        if ($operation === 'delete') {
            $deleteCount++;
        }
    }
    $cascadeProbability = min((count($chainPaths) * 3) + ($deleteCount * 12), 30);

    // Normalize each factor to 0-25, then aggregate to 0-100.
    // تطبيع كل عامل إلى 0-25 ثم جمعها إلى 0-100.
    $factorBusiness = min((int)round(($businessLogicSensitivity / 2) * (float)$adaptive['business_weight']), 25);
    $factorApi = min((int)round(($apiCriticality / 2) * (float)$adaptive['api_weight']), 25);
    $factorDepth = min((int)round($dependencyDepth * (float)$adaptive['depth_weight']), 25);
    $factorCascade = min((int)round($cascadeProbability * (float)$adaptive['cascade_weight']), 25);

    $semanticRiskScore = $factorBusiness + $factorApi + $factorDepth + $factorCascade;
    if ($semanticRiskScore > 100) {
        $semanticRiskScore = 100;
    }

    $riskCategory = 'low';
    if ($semanticRiskScore >= 85) {
        $riskCategory = 'critical';
    } elseif ($semanticRiskScore >= 65) {
        $riskCategory = 'high';
    } elseif ($semanticRiskScore >= 35) {
        $riskCategory = 'medium';
    }

    return [
        'semantic_risk_score' => $semanticRiskScore,
        'risk_category' => $riskCategory,
        'factors' => [
            'business_logic_sensitivity' => $factorBusiness,
            'api_criticality' => $factorApi,
            'dependency_depth' => $factorDepth,
            'cascade_probability' => $factorCascade,
        ],
        'adaptive_weights' => $adaptive,
        'strategy_mode' => (string)($strategy['mode'] ?? 'safety-first'),
    ];
}

/**
 * Predict semantic system state after proposed changes.
 * التنبؤ بالحالة الدلالية للنظام بعد التغييرات المقترحة.
 *
 * @param array<int, string> $affectedFiles
 * @param array<int, string> $impactedFiles
 * @param array<int, array<int, string>> $chainPaths
 * @param array<int, array<string, mixed>> $semanticNodes
 * @return array{
 *   predicted_system_state:array<string, mixed>,
 *   risk_hotspots_map:array<int, array<string, mixed>>,
 *   risk_simulation_report:array<string, mixed>,
 *   stability_score:int,
 *   failure_probability_map:array<int, array<string, mixed>>
 * }
 */
function coreai_predict_semantic_system_state(
    array $affectedFiles,
    array $impactedFiles,
    array $chainPaths,
    array $semanticNodes,
    array $operations = []
): array {
    $nodeMap = [];
    foreach ($semanticNodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $path = (string)($node['path'] ?? '');
        if ($path !== '') {
            $nodeMap[$path] = $node;
        }
    }

    $breakingPoints = [];
    $unstableModules = [];
    $cascadingFailures = [];
    $riskHotspots = [];
    $unstableFlows = [];
    $failureProbabilityMap = [];

    foreach ($impactedFiles as $path) {
        $node = $nodeMap[$path] ?? null;
        $fileTag = strtoupper((string)($node['file_tag'] ?? 'HELPER'));
        $functions = is_array($node['functions'] ?? null) ? $node['functions'] : [];
        $apiFlows = is_array($node['api_flows'] ?? null) ? $node['api_flows'] : [];
        $dataFlows = is_array($node['data_flows'] ?? null) ? $node['data_flows'] : [];

        $hotspotScore = 0;
        $reasons = [];

        if (in_array($path, $affectedFiles, true)) {
            $hotspotScore += 20;
            $reasons[] = 'directly_changed';
        }
        if ($fileTag === 'CORE_LOGIC') {
            $hotspotScore += 25;
            $reasons[] = 'core_logic_module';
        } elseif ($fileTag === 'API_LAYER') {
            $hotspotScore += 22;
            $reasons[] = 'api_surface_module';
        } elseif ($fileTag === 'CONFIG') {
            $hotspotScore += 18;
            $reasons[] = 'configuration_module';
        } else {
            $hotspotScore += 8;
        }

        foreach ($functions as $fn) {
            if (!is_array($fn)) {
                continue;
            }
            $role = strtolower((string)($fn['semantic_role'] ?? 'general_logic'));
            if (in_array($role, ['execution', 'validation', 'analysis'], true)) {
                $hotspotScore += 6;
                $reasons[] = "sensitive_function_role:{$role}";
            } elseif (in_array($role, ['data_write'], true)) {
                $hotspotScore += 4;
            }
        }

        if ($apiFlows !== []) {
            $hotspotScore += 6;
            $reasons[] = 'api_connected';
        }
        if (in_array('file_write', $dataFlows, true) || in_array('http_input', $dataFlows, true)) {
            $hotspotScore += 5;
            $reasons[] = 'stateful_or_request_path';
        }

        if ($hotspotScore >= 55) {
            $breakingPoints[] = $path;
        } elseif ($hotspotScore >= 40) {
            $unstableModules[] = $path;
        }

        if (
            $apiFlows !== []
            && in_array('http_input', $dataFlows, true)
            && ($hotspotScore >= 40)
        ) {
            $unstableFlows[] = [
                'module' => $path,
                'flow' => 'request_to_api_execution',
                'reason' => 'high hotspot on request/API path',
            ];
        }

        $riskHotspots[] = [
            'module' => $path,
            'hotspot_score' => min($hotspotScore, 100),
            'file_tag' => $fileTag,
            'reasons' => array_values(array_unique($reasons)),
        ];

        // Dynamic failure probability at module-level.
        // احتمال الفشل الديناميكي على مستوى الوحدة.
        $baseFailure = 0.05;
        $failure = $baseFailure + (min($hotspotScore, 100) / 140);
        if (in_array($path, $affectedFiles, true)) {
            $failure += 0.08;
        }
        if ($fileTag === 'CORE_LOGIC' || $fileTag === 'API_LAYER') {
            $failure += 0.07;
        }
        if ($fileTag === 'DATA_LAYER' || $fileTag === 'CONFIG') {
            $failure += 0.04;
        }
        if ($apiFlows !== []) {
            $failure += 0.05;
        }
        if (in_array('network_io', $dataFlows, true) || in_array('http_input', $dataFlows, true)) {
            $failure += 0.04;
        }
        $failure = min($failure, 0.99);

        $failureProbabilityMap[] = [
            'module' => $path,
            'failure_probability' => round($failure, 3),
            'risk_band' => $failure >= 0.7 ? 'critical' : ($failure >= 0.45 ? 'high' : ($failure >= 0.25 ? 'medium' : 'low')),
        ];
    }

    foreach ($chainPaths as $chain) {
        if (count($chain) >= 3) {
            $cascadingFailures[] = $chain;
            $unstableFlows[] = [
                'module' => (string)($chain[0] ?? ''),
                'flow' => 'cascade_chain',
                'reason' => 'multi-hop dependency propagation',
            ];
        }
    }

    // Cascading failure simulation effect.
    // تأثير محاكاة الأعطال المتسلسلة.
    $cascadePenalty = min(count($cascadingFailures) * 8, 35);
    foreach ($failureProbabilityMap as &$fp) {
        $p = (float)($fp['failure_probability'] ?? 0.0);
        $p += ($cascadePenalty / 200);
        if ($p > 0.99) {
            $p = 0.99;
        }
        $fp['failure_probability'] = round($p, 3);
        $fp['risk_band'] = $p >= 0.7 ? 'critical' : ($p >= 0.45 ? 'high' : ($p >= 0.25 ? 'medium' : 'low'));
    }
    unset($fp);

    usort(
        $riskHotspots,
        static fn(array $a, array $b): int => ((int)$b['hotspot_score']) <=> ((int)$a['hotspot_score'])
    );

    $runtimeBehaviorChanges = [];
    foreach ($operations as $op) {
        if (!is_array($op)) {
            continue;
        }
        $operation = strtolower((string)($op['operation'] ?? ''));
        $target = (string)($op['target'] ?? '');
        if ($operation === 'delete') {
            $runtimeBehaviorChanges[] = "Deleted module may remove runtime branch: {$target}";
        } elseif ($operation === 'update' || $operation === 'modify') {
            $runtimeBehaviorChanges[] = "Updated logic may alter runtime behavior: {$target}";
        } elseif ($operation === 'create') {
            $runtimeBehaviorChanges[] = "New module may introduce new runtime path: {$target}";
        }
    }

    $overallRisk = 'low';
    if (count($breakingPoints) > 0 || count($cascadingFailures) >= 2) {
        $overallRisk = 'high';
    } elseif (count($unstableModules) > 0 || count($unstableFlows) > 0) {
        $overallRisk = 'medium';
    }

    $avgFailure = 0.0;
    if ($failureProbabilityMap !== []) {
        $sum = 0.0;
        foreach ($failureProbabilityMap as $fp) {
            $sum += (float)($fp['failure_probability'] ?? 0.0);
        }
        $avgFailure = $sum / count($failureProbabilityMap);
    }

    // System stability score (higher is more stable).
    // درجة استقرار النظام (الأعلى يعني استقرارا أفضل).
    $stabilityScore = (int)round(100 - min(100, ($avgFailure * 100) + ($cascadePenalty * 0.8)));
    if ($stabilityScore < 0) {
        $stabilityScore = 0;
    }

    return [
        'predicted_system_state' => [
            'breaking_points' => array_values(array_unique($breakingPoints)),
            'unstable_modules' => array_values(array_unique($unstableModules)),
            'cascading_failures' => $cascadingFailures,
            'stability_forecast' => (count($breakingPoints) > 0 || count($cascadingFailures) > 0) ? 'fragile' : 'stable_with_monitoring',
        ],
        'risk_hotspots_map' => $riskHotspots,
        'risk_simulation_report' => [
            'runtime_behavior_changes' => $runtimeBehaviorChanges,
            'unstable_flows' => $unstableFlows,
            'break_propagation_paths' => $cascadingFailures,
            'overall_simulated_risk' => $overallRisk,
        ],
        'stability_score' => $stabilityScore,
        'failure_probability_map' => $failureProbabilityMap,
    ];
}

/**
 * Resolve prediction validation metrics path.
 * تحديد مسار حفظ قياسات التحقق من التنبؤ.
 */
function coreai_prediction_validation_path(): string
{
    return coreai_user_context_root() . '/memory/prediction_validation.json';
}

/**
 * Build actual system state snapshot after execution.
 * بناء لقطة الحالة الفعلية للنظام بعد التنفيذ.
 *
 * @param array<int, string> $affectedFiles
 * @return array<string, mixed>
 */
function coreai_collect_actual_system_state(array $affectedFiles): array
{
    $graph = coreai_load_project_graph();
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $semantic = coreai_load_semantic_graph();
    $nodes = is_array($semantic['nodes'] ?? null) ? $semantic['nodes'] : [];

    $impact = coreai_analyze_graph_impact($affectedFiles, $edges);
    $cascade = coreai_predict_cascade_impact($affectedFiles, $edges);
    $state = coreai_predict_semantic_system_state(
        $affectedFiles,
        $impact['impacted_files'],
        $cascade['affected_chain_paths'],
        $nodes,
        []
    );

    return $state;
}

/**
 * Compare predicted vs actual state and compute accuracy/deviation.
 * مقارنة الحالة المتوقعة بالحالة الفعلية وحساب الدقة والانحراف.
 *
 * @param array<string, mixed> $predicted
 * @param array<string, mixed> $actual
 * @return array{prediction_accuracy_score:int,deviation_report:array<string, mixed>}
 */
function coreai_validate_prediction_accuracy(array $predicted, array $actual): array
{
    $predState = is_array($predicted['predicted_system_state'] ?? null) ? $predicted['predicted_system_state'] : [];
    $actState = is_array($actual['predicted_system_state'] ?? null) ? $actual['predicted_system_state'] : [];

    $predBreak = array_values(array_unique(is_array($predState['breaking_points'] ?? null) ? $predState['breaking_points'] : []));
    $actBreak = array_values(array_unique(is_array($actState['breaking_points'] ?? null) ? $actState['breaking_points'] : []));

    $predUnstable = array_values(array_unique(is_array($predState['unstable_modules'] ?? null) ? $predState['unstable_modules'] : []));
    $actUnstable = array_values(array_unique(is_array($actState['unstable_modules'] ?? null) ? $actState['unstable_modules'] : []));

    $breakMatches = count(array_intersect($predBreak, $actBreak));
    $unstableMatches = count(array_intersect($predUnstable, $actUnstable));
    $breakBase = max(count($predBreak), count($actBreak), 1);
    $unstableBase = max(count($predUnstable), count($actUnstable), 1);

    $breakAccuracy = $breakMatches / $breakBase;
    $unstableAccuracy = $unstableMatches / $unstableBase;

    $predStability = (int)($predicted['stability_score'] ?? 0);
    $actStability = (int)($actual['stability_score'] ?? 0);
    $stabilityGap = abs($predStability - $actStability);
    $stabilityAccuracy = max(0.0, 1.0 - ($stabilityGap / 100));

    $score = (int)round((($breakAccuracy * 0.4) + ($unstableAccuracy * 0.35) + ($stabilityAccuracy * 0.25)) * 100);
    if ($score < 0) {
        $score = 0;
    }
    if ($score > 100) {
        $score = 100;
    }

    $report = [
        'breaking_points' => [
            'predicted' => $predBreak,
            'actual' => $actBreak,
            'missed' => array_values(array_diff($actBreak, $predBreak)),
            'false_positive' => array_values(array_diff($predBreak, $actBreak)),
            'accuracy' => round($breakAccuracy, 3),
        ],
        'unstable_modules' => [
            'predicted' => $predUnstable,
            'actual' => $actUnstable,
            'missed' => array_values(array_diff($actUnstable, $predUnstable)),
            'false_positive' => array_values(array_diff($predUnstable, $actUnstable)),
            'accuracy' => round($unstableAccuracy, 3),
        ],
        'stability' => [
            'predicted_score' => $predStability,
            'actual_score' => $actStability,
            'gap' => $stabilityGap,
            'accuracy' => round($stabilityAccuracy, 3),
        ],
    ];

    return [
        'prediction_accuracy_score' => $score,
        'deviation_report' => $report,
    ];
}

/**
 * Persist prediction validation result history.
 * حفظ سجل نتائج التحقق من دقة التنبؤ.
 *
 * @param array<string, mixed> $record
 */
function coreai_store_prediction_validation(array $record): void
{
    $path = coreai_prediction_validation_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $history = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        }
    }

    $history[] = $record;
    if (count($history) > 200) {
        $history = array_slice($history, -200);
    }

    $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }
}

/**
 * Resolve architecture learning memory path.
 * تحديد مسار ذاكرة التعلم المعماري.
 */
function coreai_architecture_learning_memory_path(): string
{
    return coreai_user_context_root() . '/memory/architecture_learning_memory.json';
}

/**
 * Load architecture learning memory state.
 * تحميل حالة ذاكرة التعلم المعماري.
 *
 * @return array<string, mixed>
 */
function coreai_load_architecture_learning_memory(): array
{
    $path = coreai_architecture_learning_memory_path();
    if (!is_file($path)) {
        return [
            'updated_at' => gmdate(DATE_ATOM),
            'decision_history' => [],
            'pattern_outcomes' => [],
            'stable_patterns' => [],
            'risky_patterns' => [],
        ];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [
            'updated_at' => gmdate(DATE_ATOM),
            'decision_history' => [],
            'pattern_outcomes' => [],
            'stable_patterns' => [],
            'risky_patterns' => [],
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'updated_at' => gmdate(DATE_ATOM),
            'decision_history' => [],
            'pattern_outcomes' => [],
            'stable_patterns' => [],
            'risky_patterns' => [],
        ];
    }

    return $decoded;
}

/**
 * Save architecture learning memory state.
 * حفظ حالة ذاكرة التعلم المعماري.
 */
function coreai_save_architecture_learning_memory(array $memory): void
{
    $path = coreai_architecture_learning_memory_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $memory['updated_at'] = gmdate(DATE_ATOM);
    $json = json_encode($memory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }
}

/**
 * Update self-learning memory from executed architectural decisions.
 * تحديث ذاكرة التعلم الذاتي من نتائج القرارات المعمارية المنفذة.
 *
 * @param array<string, mixed> $decisionRecord
 */
function coreai_update_architecture_learning_memory(array $decisionRecord): void
{
    $memory = coreai_load_architecture_learning_memory();
    $history = is_array($memory['decision_history'] ?? null) ? $memory['decision_history'] : [];
    $patterns = is_array($memory['pattern_outcomes'] ?? null) ? $memory['pattern_outcomes'] : [];

    $record = [
        'timestamp' => gmdate(DATE_ATOM),
        'group_id' => (string)($decisionRecord['group_id'] ?? 'unknown'),
        'strategy_mode_used' => strtolower((string)($decisionRecord['strategy_mode_used'] ?? 'safety-first')),
        'reason_for_change' => (string)($decisionRecord['reason_for_change'] ?? 'execution_update'),
        'risk_category' => (string)($decisionRecord['risk_category'] ?? 'unknown'),
        'semantic_risk_score' => (int)($decisionRecord['semantic_risk_score'] ?? 0),
        'prediction_accuracy_score' => (int)($decisionRecord['prediction_accuracy_score'] ?? 0),
        'execution_ok' => ((bool)($decisionRecord['ok'] ?? false)),
        'rolled_back' => ((bool)($decisionRecord['rolled_back'] ?? false)),
        'operations' => is_array($decisionRecord['operations'] ?? null) ? $decisionRecord['operations'] : [],
        'semantic_risk_factors' => is_array($decisionRecord['semantic_risk_factors'] ?? null) ? $decisionRecord['semantic_risk_factors'] : [],
    ];
    $history[] = $record;
    if (count($history) > 400) {
        $history = array_slice($history, -400);
    }

    // Learn pattern stability over time.
    // تعلم استقرار الأنماط مع مرور الوقت.
    $opKinds = [];
    foreach (($record['operations'] ?? []) as $op) {
        if (!is_array($op)) {
            continue;
        }
        $k = strtolower((string)($op['operation'] ?? 'unknown'));
        if ($k !== '') {
            $opKinds[$k] = true;
        }
    }

    $outcomeSuccess = $record['execution_ok'] && !$record['rolled_back'] && $record['prediction_accuracy_score'] >= 60;
    foreach (array_keys($opKinds) as $opKey) {
        $patternKey = 'op:' . $opKey . '|risk:' . strtolower((string)$record['risk_category']);
        if (!isset($patterns[$patternKey]) || !is_array($patterns[$patternKey])) {
            $patterns[$patternKey] = [
                'attempts' => 0,
                'success' => 0,
                'failure' => 0,
                'success_rate' => 0,
            ];
        }

        $patterns[$patternKey]['attempts'] = (int)($patterns[$patternKey]['attempts'] ?? 0) + 1;
        if ($outcomeSuccess) {
            $patterns[$patternKey]['success'] = (int)($patterns[$patternKey]['success'] ?? 0) + 1;
        } else {
            $patterns[$patternKey]['failure'] = (int)($patterns[$patternKey]['failure'] ?? 0) + 1;
        }

        $attempts = max((int)$patterns[$patternKey]['attempts'], 1);
        $patterns[$patternKey]['success_rate'] = round(((int)$patterns[$patternKey]['success'] / $attempts) * 100, 2);
    }

    // Learn strategy+reason pattern outcomes to optimize future recommendations.
    // تعلم نتائج أنماط الاستراتيجية+السبب لتحسين التوصيات المستقبلية.
    $strategyPatternKey = 'strategy:' . strtolower((string)$record['strategy_mode_used']) . '|reason:' . strtolower((string)$record['reason_for_change']);
    if (!isset($patterns[$strategyPatternKey]) || !is_array($patterns[$strategyPatternKey])) {
        $patterns[$strategyPatternKey] = [
            'attempts' => 0,
            'success' => 0,
            'failure' => 0,
            'success_rate' => 0,
        ];
    }
    $patterns[$strategyPatternKey]['attempts'] = (int)($patterns[$strategyPatternKey]['attempts'] ?? 0) + 1;
    if ($outcomeSuccess) {
        $patterns[$strategyPatternKey]['success'] = (int)($patterns[$strategyPatternKey]['success'] ?? 0) + 1;
    } else {
        $patterns[$strategyPatternKey]['failure'] = (int)($patterns[$strategyPatternKey]['failure'] ?? 0) + 1;
    }
    $sAttempts = max((int)$patterns[$strategyPatternKey]['attempts'], 1);
    $patterns[$strategyPatternKey]['success_rate'] = round(((int)$patterns[$strategyPatternKey]['success'] / $sAttempts) * 100, 2);

    $stable = [];
    $risky = [];
    foreach ($patterns as $key => $stats) {
        if (!is_array($stats)) {
            continue;
        }
        $attempts = (int)($stats['attempts'] ?? 0);
        $successRate = (float)($stats['success_rate'] ?? 0.0);
        if ($attempts >= 3 && $successRate >= 75) {
            $stable[$key] = $stats;
        }
        if ($attempts >= 2 && $successRate <= 45) {
            $risky[$key] = $stats;
        }
    }

    $memory['decision_history'] = $history;
    $memory['pattern_outcomes'] = $patterns;
    $memory['stable_patterns'] = $stable;
    $memory['risky_patterns'] = $risky;
    $memory['explanation'] = [
        'en' => 'Self-learning architecture memory tracks decisions, outcomes, and pattern stability over time.',
        'ar' => 'ذاكرة معمارية ذاتية التعلم تتتبع القرارات والنتائج واستقرار الأنماط عبر الزمن.',
    ];
    coreai_save_architecture_learning_memory($memory);
}

/**
 * Build adaptive weights from real execution outcomes.
 * بناء أوزان تكيفية من نتائج التنفيذ الفعلية.
 *
 * @return array{
 *   business_weight:float,
 *   api_weight:float,
 *   depth_weight:float,
 *   cascade_weight:float,
 *   chain_weight:float
 * }
 */
function coreai_get_adaptive_risk_weights(array $strategy = []): array
{
    $weights = [
        'business_weight' => 1.0,
        'api_weight' => 1.0,
        'depth_weight' => 1.0,
        'cascade_weight' => 1.0,
        'chain_weight' => 1.0,
    ];

    $learning = coreai_load_architecture_learning_memory();
    $patterns = is_array($learning['pattern_outcomes'] ?? null) ? $learning['pattern_outcomes'] : [];

    foreach ($patterns as $key => $stats) {
        if (!is_array($stats)) {
            continue;
        }
        $attempts = (int)($stats['attempts'] ?? 0);
        $successRate = (float)($stats['success_rate'] ?? 0.0);
        if ($attempts < 2) {
            continue;
        }

        if ($successRate <= 45.0) {
            if (str_contains((string)$key, 'api')) {
                $weights['api_weight'] += 0.08;
            }
            if (str_contains((string)$key, 'delete') || str_contains((string)$key, 'critical')) {
                $weights['cascade_weight'] += 0.10;
                $weights['depth_weight'] += 0.06;
                $weights['chain_weight'] += 0.08;
            }
        } elseif ($successRate >= 80.0) {
            if (str_contains((string)$key, 'update') || str_contains((string)$key, 'medium')) {
                $weights['business_weight'] -= 0.04;
                $weights['depth_weight'] -= 0.02;
            }
        }
    }

    // Feedback from prediction deviation history.
    // تغذية راجعة من سجل انحراف التنبؤ.
    $validationPath = coreai_prediction_validation_path();
    if (is_file($validationPath)) {
        $raw = file_get_contents($validationPath);
        $records = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($records) && $records !== []) {
            $gapSum = 0.0;
            $n = 0;
            foreach ($records as $rec) {
                if (!is_array($rec)) {
                    continue;
                }
                $dev = is_array($rec['deviation_report'] ?? null) ? $rec['deviation_report'] : [];
                $stability = is_array($dev['stability'] ?? null) ? $dev['stability'] : [];
                $gapSum += (float)($stability['gap'] ?? 0.0);
                $n++;
            }
            if ($n > 0) {
                $avgGap = $gapSum / $n;
                if ($avgGap >= 15) {
                    $weights['cascade_weight'] += 0.10;
                    $weights['chain_weight'] += 0.10;
                } elseif ($avgGap <= 6) {
                    $weights['cascade_weight'] -= 0.03;
                    $weights['depth_weight'] -= 0.03;
                }
            }
        }
    }

    // Apply strategy profile on top of learned weights.
    // تطبيق نمط الاستراتيجية فوق الأوزان المتعلمة.
    $mode = (string)($strategy['mode'] ?? 'safety-first');
    if ($mode === 'stability-focused') {
        $weights['business_weight'] += 0.05;
        $weights['depth_weight'] += 0.08;
        $weights['cascade_weight'] += 0.10;
        $weights['chain_weight'] += 0.08;
    } elseif ($mode === 'speed-focused') {
        $weights['business_weight'] -= 0.05;
        $weights['depth_weight'] -= 0.07;
        $weights['cascade_weight'] -= 0.08;
        $weights['chain_weight'] -= 0.06;
    } elseif ($mode === 'refactor-heavy') {
        $weights['business_weight'] += 0.04;
        $weights['depth_weight'] += 0.10;
        $weights['cascade_weight'] += 0.06;
    } elseif ($mode === 'safety-first') {
        $weights['api_weight'] += 0.06;
        $weights['cascade_weight'] += 0.10;
        $weights['chain_weight'] += 0.10;
    }

    foreach ($weights as $k => $v) {
        if ($v < 0.75) {
            $weights[$k] = 0.75;
        } elseif ($v > 1.45) {
            $weights[$k] = 1.45;
        }
    }

    return $weights;
}

/**
 * Choose AI strategy mode from current project state.
 * اختيار نمط استراتيجية الذكاء من حالة المشروع الحالية.
 *
 * @param array<string, mixed> $projectIntelligence
 * @param array<string, mixed> $context
 * @return array{mode:string,reason:string}
 */
function coreai_select_strategy_mode(array $projectIntelligence, array $context = []): array
{
    $patterns = is_array($projectIntelligence['system_patterns'] ?? null) ? $projectIntelligence['system_patterns'] : [];
    $criticalPaths = is_array($projectIntelligence['critical_paths'] ?? null) ? $projectIntelligence['critical_paths'] : [];
    $learning = coreai_load_architecture_learning_memory();
    $riskyPatterns = is_array($learning['risky_patterns'] ?? null) ? $learning['risky_patterns'] : [];

    $requestedMode = strtolower((string)($context['preferred_mode'] ?? ''));
    if (in_array($requestedMode, ['stability-focused', 'speed-focused', 'refactor-heavy', 'safety-first'], true)) {
        return ['mode' => $requestedMode, 'reason' => 'Requested explicitly by context.'];
    }

    $apiFlowCount = (int)($patterns['api_flow_count'] ?? 0);
    $criticalCount = count($criticalPaths);
    $riskyCount = count($riskyPatterns);

    if ($riskyCount >= 4 || $criticalCount >= 14) {
        return ['mode' => 'safety-first', 'reason' => 'High risk baseline or many critical paths.'];
    }
    if ($criticalCount >= 9) {
        return ['mode' => 'stability-focused', 'reason' => 'Elevated dependency criticality.'];
    }
    if ($apiFlowCount <= 2 && $criticalCount <= 4) {
        return ['mode' => 'speed-focused', 'reason' => 'Low complexity and low-risk structure.'];
    }
    return ['mode' => 'refactor-heavy', 'reason' => 'Balanced state with optimization opportunity.'];
}

/**
 * Predict cascade chains and downstream break propagation risk.
 * توقع سلاسل التأثير المتتابع ومخاطر انتشار الأعطال.
 *
 * @param array<int, string> $seedFiles
 * @param array<int, array<string, mixed>> $edges
 * @return array{cascade_risk_score:int, affected_chain_paths:array<int, array<int, string>>}
 */
function coreai_predict_cascade_impact(array $seedFiles, array $edges, array $strategy = []): array
{
    $adaptive = coreai_get_adaptive_risk_weights($strategy);
    $seeds = array_values(array_unique(array_filter($seedFiles, static fn(string $v): bool => trim($v) !== '')));
    $chainPaths = [];

    /**
     * Build adjacency for fast traversal.
     * بناء قائمة الجوار لتسريع الاستكشاف.
     */
    $adj = [];
    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if ($from === '' || $to === '') {
            continue;
        }
        $adj[$from][] = $to;
    }

    // Depth-limited DFS to detect downstream chains.
    // بحث عمقي محدود لاكتشاف السلاسل الهابطة.
    $maxDepth = 4;
    $dfs = static function (string $node, array $path, int $depth) use (&$dfs, &$adj, &$chainPaths, $maxDepth): void {
        if ($depth >= $maxDepth) {
            return;
        }
        foreach (($adj[$node] ?? []) as $next) {
            if (in_array($next, $path, true)) {
                continue;
            }
            $newPath = [...$path, $next];
            if (count($newPath) >= 2) {
                $chainPaths[] = $newPath;
            }
            $dfs($next, $newPath, $depth + 1);
        }
    };

    foreach ($seeds as $seed) {
        $dfs($seed, [$seed], 0);
    }

    // Score based on number and length of chains (higher = riskier).
    // حساب المخاطر بناءً على عدد السلاسل وطولها (الأعلى أخطر).
    $score = 0;
    foreach ($chainPaths as $chain) {
        $length = count($chain);
        if ($length >= 2) {
            $score += (8 + (($length - 2) * 6)) * (float)$adaptive['chain_weight'];
        }
    }
    $score *= (float)$adaptive['cascade_weight'];
    $mode = (string)($strategy['mode'] ?? 'safety-first');
    if ($mode === 'stability-focused' || $mode === 'safety-first') {
        $score *= 1.08;
    } elseif ($mode === 'speed-focused') {
        $score *= 0.90;
    }
    if ($score > 100) {
        $score = 100;
    }

    return [
        'cascade_risk_score' => $score,
        'affected_chain_paths' => $chainPaths,
    ];
}

/**
 * Build pre-execution impact plan for action groups.
 * بناء خطة تأثير قبل التنفيذ لمجموعات الإجراءات.
 *
 * @param array<int, array{id:string,actions:array<int, array<string, mixed>>}> $groups
 * @return array<string, mixed>
 */
function coreai_build_execution_plan(array $groups): array
{
    $graph = coreai_load_project_graph();
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $semanticGraph = coreai_load_semantic_graph();
    $semanticNodes = is_array($semanticGraph['nodes'] ?? null) ? $semanticGraph['nodes'] : [];
    $projectIntelligence = coreai_load_project_intelligence_memory();
    $strategy = coreai_select_strategy_mode($projectIntelligence, []);

    $allAffected = [];
    $groupPlans = [];

    foreach ($groups as $group) {
        $groupId = (string)($group['id'] ?? 'group');
        $actions = is_array($group['actions'] ?? null) ? $group['actions'] : [];
        $groupAffected = [];
        $dependencyChanges = [];
        $operations = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $target = trim((string)($action['target'] ?? ''));
            $operation = strtolower(trim((string)($action['operation'] ?? '')));
            if ($target === '') {
                continue;
            }

            $groupAffected[] = $target;
            $allAffected[] = $target;
            $operations[] = [
                'operation' => $operation,
                'target' => $target,
            ];

            // Predict dependency changes from project graph neighbors.
            // توقع تغييرات الاعتمادية من الجيران داخل مخطط المشروع.
            foreach ($edges as $edge) {
                if (!is_array($edge)) {
                    continue;
                }
                $from = (string)($edge['from'] ?? '');
                $to = (string)($edge['to'] ?? '');
                $type = (string)($edge['type'] ?? '');

                if ($from === $target || $to === $target) {
                    $predictedChange = 'relation may need verification';
                    if ($operation === 'delete') {
                        $predictedChange = 'high-impact: relation may break';
                    } elseif ($operation === 'create') {
                        $predictedChange = 'new relation may be required';
                    }
                    $dependencyChanges[] = [
                        'type' => $type,
                        'from' => $from,
                        'to' => $to,
                        'predicted_change' => $predictedChange,
                    ];
                }
            }
        }

        $impact = coreai_analyze_graph_impact($groupAffected, $edges);
        $cascade = coreai_predict_cascade_impact($groupAffected, $edges, $strategy);
        $semanticRisk = coreai_calculate_semantic_risk_score(
            $impact['impacted_files'],
            $cascade['affected_chain_paths'],
            $semanticNodes,
            $operations,
            $strategy
        );
        $semanticState = coreai_predict_semantic_system_state(
            array_values(array_unique($groupAffected)),
            $impact['impacted_files'],
            $cascade['affected_chain_paths'],
            $semanticNodes,
            $operations
        );

        $groupPlans[] = [
            'group_id' => $groupId,
            'affected_files' => array_values(array_unique($groupAffected)),
            'impacted_files' => $impact['impacted_files'],
            'indirect_side_effects' => $impact['indirect_side_effects'],
            'semantic_risk_score' => $semanticRisk['semantic_risk_score'],
            'risk_category' => $semanticRisk['risk_category'],
            'risk_score' => $semanticRisk['semantic_risk_score'],
            'semantic_risk_factors' => $semanticRisk['factors'],
            'adaptive_weights' => $semanticRisk['adaptive_weights'],
            'strategy_mode' => $semanticRisk['strategy_mode'],
            'cascade_risk_score' => $cascade['cascade_risk_score'],
            'affected_chain_paths' => $cascade['affected_chain_paths'],
            'predicted_system_state' => $semanticState['predicted_system_state'],
            'risk_hotspots_map' => $semanticState['risk_hotspots_map'],
            'risk_simulation_report' => $semanticState['risk_simulation_report'],
            'stability_score' => $semanticState['stability_score'],
            'failure_probability_map' => $semanticState['failure_probability_map'],
            'operations' => $operations,
            'dependency_changes' => $dependencyChanges,
        ];
    }

    $plan = [
        'generated_at' => gmdate(DATE_ATOM),
        'group_count' => count($groupPlans),
        'affected_files' => array_values(array_unique($allAffected)),
        'groups' => $groupPlans,
        'impact_summary' => [
            'strategy_mode' => $strategy['mode'],
            'strategy_reason' => $strategy['reason'],
            'total_affected_files' => count(array_unique($allAffected)),
            'contains_delete' => (bool)array_filter(
                $groupPlans,
                static fn(array $g): bool => (bool)array_filter(
                    $g['operations'] ?? [],
                    static fn(array $op): bool => (($op['operation'] ?? '') === 'delete')
                )
            ),
            'max_risk_score' => max(array_map(static fn(array $g): int => (int)($g['risk_score'] ?? 0), $groupPlans ?: [['risk_score' => 0]])),
            'max_cascade_risk_score' => max(array_map(static fn(array $g): int => (int)($g['cascade_risk_score'] ?? 0), $groupPlans ?: [['cascade_risk_score' => 0]])),
        ],
    ];

    $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents(coreai_execution_plan_path(), $json . PHP_EOL, LOCK_EX);
    }

    return $plan;
}

/**
 * Resolve project graph JSON path.
 * تحديد مسار ملف مخطط المشروع JSON.
 */
function coreai_project_graph_path(): string
{
    return coreai_user_context_root() . '/project_graph.json';
}

/**
 * Load and validate project graph payload.
 * تحميل والتحقق من بيانات مخطط المشروع.
 *
 * @return array{files:array<int, array<string, mixed>>, edges:array<int, array<string, mixed>>}
 */
function coreai_load_project_graph(): array
{
    $path = coreai_project_graph_path();
    if (!is_file($path)) {
        return ['files' => [], 'edges' => []];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return ['files' => [], 'edges' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['files' => [], 'edges' => []];
    }

    $files = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
    $edges = is_array($decoded['edges'] ?? null) ? $decoded['edges'] : [];
    return ['files' => $files, 'edges' => $edges];
}

/**
 * Match active file to graph node path.
 * مطابقة الملف النشط مع عقدة المخطط.
 */
function coreai_match_graph_file_path(string $activeFileName, array $graphFiles): ?string
{
    $needle = trim($activeFileName);
    if ($needle === '') {
        return null;
    }

    foreach ($graphFiles as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = (string)($file['path'] ?? '');
        if ($path === '') {
            continue;
        }
        if ($path === $needle || basename(str_replace('\\', '/', $path)) === $needle) {
            return $path;
        }
    }

    return null;
}

/**
 * Build graph-aware context for AI prompt.
 * بناء سياق معتمد على مخطط المشروع لرسالة الذكاء الاصطناعي.
 *
 * @return array{
 *   active_graph_file:string,
 *   related_files:array<int, string>,
 *   dependent_modules:array<int, string>,
 *   connected_components:array<int, string>
 * }
 */
function coreai_build_graph_context(string $activeFileName, string $userQuery): array
{
    $graph = coreai_load_project_graph();
    $files = $graph['files'];
    $edges = $graph['edges'];
    $activePath = coreai_match_graph_file_path($activeFileName, $files);

    if ($activePath === null) {
        return [
            'active_graph_file' => '[unmatched]',
            'related_files' => [],
            'dependent_modules' => [],
            'connected_components' => [],
        ];
    }

    $related = [];
    $dependents = [];
    $connected = [];

    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        $type = (string)($edge['type'] ?? '');

        if ($from === $activePath && ($type === 'import' || $type === 'api_connection')) {
            $related[] = $to;
        }
        if ($to === $activePath && ($type === 'import' || $type === 'function_usage' || $type === 'api_connection')) {
            $dependents[] = $from;
        }
        if ($from === $activePath || $to === $activePath) {
            $connected[] = $from === $activePath ? $to : $from;
        }
    }

    // Relevance filter based on user query keywords.
    // تصفية الصلة حسب الكلمات المفتاحية في طلب المستخدم.
    $keywords = coreai_extract_keywords($userQuery);
    $keywordMap = $keywords !== [] ? array_fill_keys($keywords, true) : [];
    $scorePath = static function (string $path) use ($keywordMap): int {
        if ($keywordMap === []) {
            return 1;
        }
        $parts = coreai_extract_keywords($path);
        $score = 0;
        foreach ($parts as $part) {
            if (isset($keywordMap[$part])) {
                $score++;
            }
        }
        return $score;
    };

    $rankAndLimit = static function (array $items, int $limit) use ($scorePath): array {
        $unique = array_values(array_unique(array_filter($items, static fn(string $v): bool => $v !== '')));
        usort(
            $unique,
            static fn(string $a, string $b): int => $scorePath($b) <=> $scorePath($a)
        );
        return array_slice($unique, 0, $limit);
    };

    return [
        'active_graph_file' => $activePath,
        'related_files' => $rankAndLimit($related, 8),
        'dependent_modules' => $rankAndLimit($dependents, 8),
        'connected_components' => $rankAndLimit($connected, 12),
    ];
}

/**
 * Resolve persistent project intelligence memory path.
 * تحديد مسار ذاكرة ذكاء المشروع الدائمة.
 */
function coreai_project_intelligence_path(): string
{
    return coreai_user_context_root() . '/memory/project_intelligence.json';
}

/**
 * Get latest modification timestamp across coreai source files.
 * جلب أحدث وقت تعديل عبر ملفات المصدر داخل coreai.
 */
function coreai_latest_source_mtime(): int
{
    $root = realpath(coreai_user_project_root());
    if ($root === false) {
        return time();
    }

    $latest = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo || !$item->isFile()) {
            continue;
        }
        $path = str_replace('\\', '/', $item->getPathname());
        if (
            str_contains($path, '/context/logs/')
            || str_contains($path, '/context/memory/project_intelligence.json')
            || str_ends_with($path, '/project_graph.json')
            || str_ends_with($path, '/semantic_graph.json')
        ) {
            continue;
        }
        $mtime = (int)$item->getMTime();
        if ($mtime > $latest) {
            $latest = $mtime;
        }
    }

    return $latest > 0 ? $latest : time();
}

/**
 * Regenerate graph artifacts when source code changed.
 * إعادة توليد ملفات الرسوم البيانية عند تغير ملفات المصدر.
 */
function coreai_refresh_graph_artifacts_if_stale(): void
{
    $projectGraph = coreai_project_graph_path();
    $semanticGraph = coreai_semantic_graph_path();
    $latestSource = coreai_latest_source_mtime();

    $projectMtime = is_file($projectGraph) ? (int)filemtime($projectGraph) : 0;
    $semanticMtime = is_file($semanticGraph) ? (int)filemtime($semanticGraph) : 0;

    if ($projectMtime >= $latestSource && $semanticMtime >= $latestSource) {
        return;
    }

    // Use PHP CLI scripts to refresh both graphs dynamically.
    // استخدام سكربتات PHP CLI لتحديث الرسمين بشكل ديناميكي.
    $phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $root = dirname(__DIR__);
    $projectScript = $root . '/php/project_graph.php';
    $semanticScript = $root . '/php/semantic_graph.php';

    @shell_exec('"' . $phpBin . '" "' . $projectScript . '"');
    @shell_exec('"' . $phpBin . '" "' . $semanticScript . '"');
}

/**
 * Build dependency hierarchy levels from graph edges.
 * بناء مستويات الهرمية الاعتمادية من روابط الرسم البياني.
 *
 * @param array<int, array<string, mixed>> $files
 * @param array<int, array<string, mixed>> $edges
 * @return array<int, array{level:int,modules:array<int, string>}>
 */
function coreai_build_dependency_hierarchy(array $files, array $edges): array
{
    $nodes = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = (string)($file['path'] ?? '');
        if ($path !== '') {
            $nodes[$path] = true;
        }
    }

    $inDegree = [];
    foreach (array_keys($nodes) as $n) {
        $inDegree[$n] = 0;
    }

    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if (isset($nodes[$to]) && isset($nodes[$from]) && $from !== $to) {
            $inDegree[$to]++;
        }
    }

    // Lower indegree => more foundational module.
    // درجة دخول أقل تعني أن الوحدة أكثر أساسية.
    asort($inDegree);
    $levels = [];
    foreach ($inDegree as $module => $degree) {
        $level = 0;
        if ($degree >= 6) {
            $level = 3;
        } elseif ($degree >= 3) {
            $level = 2;
        } elseif ($degree >= 1) {
            $level = 1;
        }
        if (!isset($levels[$level])) {
            $levels[$level] = [];
        }
        $levels[$level][] = $module;
    }

    ksort($levels);
    $output = [];
    foreach ($levels as $level => $modules) {
        $output[] = ['level' => (int)$level, 'modules' => array_values($modules)];
    }
    return $output;
}

/**
 * Extract critical paths from semantic logic + API edges.
 * استخراج المسارات الحرجة من روابط المنطق وواجهات API.
 *
 * @param array<int, array<string, mixed>> $semanticEdges
 * @return array<int, array<string, string>>
 */
function coreai_extract_critical_paths(array $semanticEdges): array
{
    $critical = [];
    foreach ($semanticEdges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $type = (string)($edge['type'] ?? '');
        if ($type !== 'logic_flow' && $type !== 'api_flow') {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if ($from === '' || $to === '') {
            continue;
        }
        $reason = (string)($edge['reason'] ?? 'critical semantic link');
        $critical[] = ['from' => $from, 'to' => $to, 'reason' => $reason];
    }
    return $critical;
}

/**
 * Build module intelligence map with semantic role + criticality.
 * بناء خريطة ذكاء الوحدات مع الدور الدلالي + الحرجية.
 *
 * @param array<int, array<string, mixed>> $nodes
 * @param array<int, array<string, mixed>> $edges
 * @param array<int, array<string, string>> $criticalPaths
 * @param array<int, array{level:int,modules:array<int, string>}> $dependencyHierarchy
 * @return array<string, array<string, mixed>>
 */
function coreai_build_module_intelligence_map(
    array $nodes,
    array $edges,
    array $criticalPaths,
    array $dependencyHierarchy
): array {
    $criticalSet = [];
    foreach ($criticalPaths as $cp) {
        if (!is_array($cp)) {
            continue;
        }
        $from = (string)($cp['from'] ?? '');
        $to = (string)($cp['to'] ?? '');
        if ($from !== '') {
            $criticalSet[$from] = true;
        }
        if ($to !== '') {
            $criticalSet[$to] = true;
        }
    }

    $dependencyLevelMap = [];
    foreach ($dependencyHierarchy as $row) {
        if (!is_array($row)) {
            continue;
        }
        $level = (int)($row['level'] ?? 0);
        $modules = is_array($row['modules'] ?? null) ? $row['modules'] : [];
        foreach ($modules as $module) {
            $dependencyLevelMap[(string)$module] = $level;
        }
    }

    $dependsOnMap = [];
    $dependentsCount = [];
    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if ($from === '' || $to === '' || $from === $to) {
            continue;
        }
        $dependsOnMap[$from][] = $to;
        $dependentsCount[$to] = ($dependentsCount[$to] ?? 0) + 1;
    }

    $modules = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $path = (string)($node['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $fileTag = strtoupper((string)($node['file_tag'] ?? 'HELPER'));
        $flows = is_array($node['data_flows'] ?? null) ? $node['data_flows'] : [];
        $apiFlows = is_array($node['api_flows'] ?? null) ? $node['api_flows'] : [];

        // Promote to DATA_LAYER when persistent data access signals are present.
        // ترقية الدور إلى DATA_LAYER عند وجود إشارات وصول للبيانات.
        $semanticRole = $fileTag;
        if (
            in_array('file_read', $flows, true)
            || in_array('file_write', $flows, true)
            || in_array('json_transform', $flows, true)
        ) {
            if ($semanticRole === 'HELPER' || $semanticRole === 'CORE_LOGIC') {
                $semanticRole = 'DATA_LAYER';
            }
        }
        if ($apiFlows !== [] && $semanticRole !== 'UI_LAYER') {
            $semanticRole = 'API_LAYER';
        }

        $criticalPath = isset($criticalSet[$path]);
        $level = (int)($dependencyLevelMap[$path] ?? 0);
        $dependsOn = array_values(array_unique($dependsOnMap[$path] ?? []));
        $dependents = (int)($dependentsCount[$path] ?? 0);

        $severity = 'low';
        if ($criticalPath || $dependents >= 5 || $level >= 3) {
            $severity = 'high';
        } elseif ($dependents >= 2 || $level >= 2) {
            $severity = 'medium';
        }

        $modules[$path] = [
            'semantic_role' => $semanticRole,
            'critical_path' => $criticalPath,
            'severity_level' => $severity,
            'dependency_level' => $level,
            'depends_on' => $dependsOn,
        ];
    }

    return $modules;
}

/**
 * Build parent-child dependency tree structure.
 * بناء شجرة اعتماديات بصيغة أب -> ابن.
 *
 * @param array<int, array<string, mixed>> $edges
 * @return array<string, array<int, string>>
 */
function coreai_build_dependency_tree(array $edges): array
{
    $tree = [];
    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = (string)($edge['from'] ?? '');
        $to = (string)($edge['to'] ?? '');
        if ($from === '' || $to === '' || $from === $to) {
            continue;
        }
        if (!isset($tree[$from])) {
            $tree[$from] = [];
        }
        $tree[$from][] = $to;
    }
    foreach ($tree as $parent => $children) {
        $tree[$parent] = array_values(array_unique($children));
    }
    return $tree;
}

/**
 * Build project-wide intelligence memory from structural graphs.
 * بناء ذاكرة ذكاء على مستوى المشروع من المخططات البنيوية.
 *
 * @return array<string, mixed>
 */
function coreai_build_project_intelligence_memory(): array
{
    coreai_refresh_graph_artifacts_if_stale();

    $projectGraph = coreai_load_project_graph();
    $semanticGraph = coreai_load_semantic_graph();

    $files = is_array($projectGraph['files'] ?? null) ? $projectGraph['files'] : [];
    $edges = is_array($projectGraph['edges'] ?? null) ? $projectGraph['edges'] : [];
    $nodes = is_array($semanticGraph['nodes'] ?? null) ? $semanticGraph['nodes'] : [];
    $semanticEdges = is_array($semanticGraph['semantic_edges'] ?? null) ? $semanticGraph['semantic_edges'] : [];

    $languageMap = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $lang = (string)($file['language'] ?? 'other');
        if (!isset($languageMap[$lang])) {
            $languageMap[$lang] = 0;
        }
        $languageMap[$lang]++;
    }

    $patternCounts = [
        'api_oriented' => 0,
        'execution_safety' => 0,
        'memory_persistence' => 0,
        'graph_driven_analysis' => 0,
    ];
    $apiFlows = 0;
    $dataFlowSignals = [];
    $moduleSemanticRoles = [];

    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $pathRaw = (string)($node['path'] ?? '');
        $path = strtolower($pathRaw);
        $functions = is_array($node['functions'] ?? null) ? $node['functions'] : [];
        $api = is_array($node['api_flows'] ?? null) ? $node['api_flows'] : [];
        $flows = is_array($node['data_flows'] ?? null) ? $node['data_flows'] : [];
        $moduleSemanticRoles[$pathRaw] = (string)($node['file_tag'] ?? 'HELPER');

        if ($api !== []) {
            $patternCounts['api_oriented']++;
            $apiFlows += count($api);
        }
        foreach ($flows as $flow) {
            $key = (string)$flow;
            $dataFlowSignals[$key] = ($dataFlowSignals[$key] ?? 0) + 1;
        }

        foreach ($functions as $fn) {
            if (!is_array($fn)) {
                continue;
            }
            $role = strtolower((string)($fn['semantic_role'] ?? ''));
            if ($role === 'execution' || str_contains($path, 'execute') || str_contains($path, 'plan')) {
                $patternCounts['execution_safety']++;
            }
            if ($role === 'data_write' || str_contains($path, 'memory')) {
                $patternCounts['memory_persistence']++;
            }
            if ($role === 'analysis' || str_contains($path, 'graph')) {
                $patternCounts['graph_driven_analysis']++;
            }
        }
    }

    $architecture = [
        'layers' => ['frontend', 'backend', 'php', 'ai', 'context', 'agent'],
        'language_distribution' => $languageMap,
        'edge_overview' => [
            'total_edges' => count($edges),
            'api_edges' => count(array_filter($edges, static fn(array $e): bool => (($e['type'] ?? '') === 'api_connection'))),
            'function_usage_edges' => count(array_filter($edges, static fn(array $e): bool => (($e['type'] ?? '') === 'function_usage'))),
        ],
        'dependency_hierarchy' => coreai_build_dependency_hierarchy($files, $edges),
    ];

    $criticalPaths = coreai_extract_critical_paths($semanticEdges);
    $moduleMap = coreai_build_module_intelligence_map($nodes, $edges, $criticalPaths, $architecture['dependency_hierarchy']);
    $dependencyTree = coreai_build_dependency_tree($edges);

    $memory = [
        'generated_at' => gmdate(DATE_ATOM),
        'metadata' => [
            'source_mtime' => coreai_latest_source_mtime(),
            'project_graph_mtime' => is_file(coreai_project_graph_path()) ? (int)filemtime(coreai_project_graph_path()) : 0,
            'semantic_graph_mtime' => is_file(coreai_semantic_graph_path()) ? (int)filemtime(coreai_semantic_graph_path()) : 0,
        ],
        'architecture_structure' => $architecture,
        'modules' => $moduleMap,
        'semantic_module_roles' => $moduleSemanticRoles,
        'critical_paths' => $criticalPaths,
        'system_dependency_hierarchy' => $architecture['dependency_hierarchy'],
        'dependency_tree' => $dependencyTree,
        'system_patterns' => [
            'api_flow_count' => $apiFlows,
            'dominant_data_flows' => $dataFlowSignals,
            'execution_engine_present' => $patternCounts['execution_safety'] > 0,
            'graph_analysis_present' => $patternCounts['graph_driven_analysis'] > 0,
        ],
        'repeated_design_patterns' => [
            'api_oriented_files' => $patternCounts['api_oriented'],
            'execution_safety_patterns' => $patternCounts['execution_safety'],
            'memory_persistence_patterns' => $patternCounts['memory_persistence'],
            'graph_driven_patterns' => $patternCounts['graph_driven_analysis'],
        ],
        'explanation' => [
            'en' => 'This system builds a semantic understanding of the project by assigning roles to modules, identifying critical execution paths, and constructing a full dependency hierarchy. It ensures the AI understands not just files, but system architecture meaning.',
            'ar' => 'هذا النظام يبني فهما دلاليا للمشروع من خلال تحديد دور كل وحدة، وتحديد المسارات الحرجة في التنفيذ، وبناء هرمية الاعتماديات بين الملفات، مما يجعل الذكاء الاصطناعي يفهم بنية النظام وليس فقط الملفات.',
        ],
    ];

    $json = json_encode($memory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        $path = coreai_project_intelligence_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }

    return $memory;
}

/**
 * Load persistent project intelligence memory.
 * تحميل ذاكرة ذكاء المشروع الدائمة.
 *
 * @return array<string, mixed>
 */
function coreai_load_project_intelligence_memory(): array
{
    $path = coreai_project_intelligence_path();
    if (!is_file($path)) {
        return coreai_build_project_intelligence_memory();
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return coreai_build_project_intelligence_memory();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return coreai_build_project_intelligence_memory();
    }

    // Dynamic refresh when source/graph changed after memory generation.
    // تحديث ديناميكي عندما تتغير ملفات المصدر أو الرسوم بعد توليد الذاكرة.
    $sourceMtime = coreai_latest_source_mtime();
    $meta = is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [];
    $storedSourceMtime = (int)($meta['source_mtime'] ?? 0);
    $storedProjectMtime = (int)($meta['project_graph_mtime'] ?? 0);
    $storedSemanticMtime = (int)($meta['semantic_graph_mtime'] ?? 0);
    $currentProjectMtime = is_file(coreai_project_graph_path()) ? (int)filemtime(coreai_project_graph_path()) : 0;
    $currentSemanticMtime = is_file(coreai_semantic_graph_path()) ? (int)filemtime(coreai_semantic_graph_path()) : 0;

    if (
        $storedSourceMtime < $sourceMtime
        || $storedProjectMtime < $currentProjectMtime
        || $storedSemanticMtime < $currentSemanticMtime
    ) {
        return coreai_build_project_intelligence_memory();
    }

    return $decoded;
}

/**
 * Evaluate architecture alternatives and choose best-fit recommendation.
 * تقييم بدائل المعمارية واختيار التوصية الأنسب.
 *
 * @param array<string, mixed> $projectIntelligence
 * @param array<string, mixed> $context
 * @return array{
 *   recommended_architecture_changes:array<int, array<string, mixed>>,
 *   reasoning_explanation:array<string, mixed>
 * }
 */
function coreai_run_architectural_decision_engine(array $projectIntelligence, array $context = []): array
{
    $modules = is_array($projectIntelligence['modules'] ?? null) ? $projectIntelligence['modules'] : [];
    $criticalPaths = is_array($projectIntelligence['critical_paths'] ?? null) ? $projectIntelligence['critical_paths'] : [];
    $patterns = is_array($projectIntelligence['system_patterns'] ?? null) ? $projectIntelligence['system_patterns'] : [];
    $strategy = coreai_select_strategy_mode($projectIntelligence, $context);
    $optimization = coreai_build_decision_optimization_profile();

    $moduleCount = count($modules);
    $criticalCount = count($criticalPaths);
    $apiFlowCount = (int)($patterns['api_flow_count'] ?? 0);

    /**
     * Decision Optimization Engine:
     * This engine improves decision quality by learning from historical architectural outcomes and optimizing future recommendations accordingly.
     * هذا المحرك يحسن جودة القرارات من خلال التعلم من نتائج القرارات السابقة وتعديل التوصيات المستقبلية بناءً عليها.
     */
    $alternatives = [
        [
            'id' => 'layer_hardening',
            'title' => 'Layer Hardening',
            'change' => 'Strengthen module boundaries between UI/API/CORE/DATA layers.',
            'performance_score' => 76,
            'safety_score' => 92,
            'tradeoff' => 'Slightly higher integration overhead, but much safer change isolation.',
            'strategy_mode' => 'stability-focused',
            'pattern_tags' => ['boundary', 'stability', 'layering'],
        ],
        [
            'id' => 'api_gateway',
            'title' => 'Internal API Gateway',
            'change' => 'Route critical runtime operations through a single validated API gateway.',
            'performance_score' => 81,
            'safety_score' => 88,
            'tradeoff' => 'Adds one hop to requests, improves consistency and policy enforcement.',
            'strategy_mode' => 'safety-first',
            'pattern_tags' => ['api', 'gateway', 'validation'],
        ],
        [
            'id' => 'modular_runtime_profiles',
            'title' => 'Modular Runtime Profiles',
            'change' => 'Split execution plans by risk profile (safe/standard/critical) before runtime apply.',
            'performance_score' => 73,
            'safety_score' => 95,
            'tradeoff' => 'Lower throughput for critical flows, highest runtime control and rollback safety.',
            'strategy_mode' => 'refactor-heavy',
            'pattern_tags' => ['risk-profile', 'execution', 'modularity'],
        ],
    ];

    foreach ($alternatives as &$alt) {
        $baseWeighted = (int)round(($alt['performance_score'] * 0.35) + ($alt['safety_score'] * 0.65));
        $strategyMode = (string)($alt['strategy_mode'] ?? 'safety-first');
        $timelineSuccess = (float)($optimization['timeline_strategy_success_rates'][$strategyMode] ?? 55.0);
        $learningSuccess = (float)($optimization['learning_success_rate'] ?? 55.0);
        $historicalSuccess = round(($timelineSuccess * 0.55) + ($learningSuccess * 0.45), 2);
        $historicalRisk = round(100 - $historicalSuccess, 2);

        // Prioritize alternatives aligned with selected strategy mode.
        // إعطاء أولوية للبدائل المتوافقة مع نمط الاستراتيجية المختار.
        $strategyBoost = ($strategy['mode'] === $strategyMode) ? 8 : 0;
        $riskPenalty = (int)round($historicalRisk * 0.22);
        $successBoost = (int)round($historicalSuccess * 0.18);

        $alt['success_probability'] = (int)max(0, min(100, round($historicalSuccess)));
        $alt['risk_probability'] = (int)max(0, min(100, round($historicalRisk)));
        $alt['historical_weighted_score'] = $baseWeighted + $successBoost + $strategyBoost - $riskPenalty;
        $alt['matched_past_patterns'] = array_values(array_slice(
            coreai_match_patterns_for_tags(
                is_array($alt['pattern_tags'] ?? null) ? $alt['pattern_tags'] : [],
                is_array($optimization['stable_patterns'] ?? null) ? $optimization['stable_patterns'] : []
            ),
            0,
            5
        ));
        $alt['avoided_risk_patterns'] = array_values(array_slice(
            coreai_match_patterns_for_tags(
                is_array($alt['pattern_tags'] ?? null) ? $alt['pattern_tags'] : [],
                is_array($optimization['risky_patterns'] ?? null) ? $optimization['risky_patterns'] : []
            ),
            0,
            5
        ));
    }
    unset($alt);

    usort($alternatives, static fn(array $a, array $b): int => ((int)$b['historical_weighted_score']) <=> ((int)$a['historical_weighted_score']));
    $top = array_slice($alternatives, 0, 2);

    $recommended = [];
    $optimized = [];
    $matchedPatterns = [];
    $avoidedPatterns = [];
    foreach ($top as $idx => $candidate) {
        $entry = [
            'priority' => $idx + 1,
            'architecture_option' => $candidate['title'],
            'proposed_change' => $candidate['change'],
            'expected_impact' => [
                'critical_path_reduction' => max((int)round($criticalCount * 0.15) - $idx, 1),
                'risk_reduction_estimate' => min((int)round($candidate['safety_score'] * 0.55), 60),
                'module_stability_gain' => max((int)round($moduleCount * 0.08), 1),
            ],
            'tradeoff' => $candidate['tradeoff'],
            'strategy_mode' => (string)($candidate['strategy_mode'] ?? $strategy['mode']),
            'success_probability' => (int)($candidate['success_probability'] ?? 50),
            'risk_probability' => (int)($candidate['risk_probability'] ?? 50),
            'matched_past_patterns' => is_array($candidate['matched_past_patterns'] ?? null) ? $candidate['matched_past_patterns'] : [],
            'avoided_risk_patterns' => is_array($candidate['avoided_risk_patterns'] ?? null) ? $candidate['avoided_risk_patterns'] : [],
        ];
        $recommended[] = $entry;
        $optimized[] = $entry;
        $matchedPatterns = [...$matchedPatterns, ...$entry['matched_past_patterns']];
        $avoidedPatterns = [...$avoidedPatterns, ...$entry['avoided_risk_patterns']];
    }

    $reasoning = [
        'summary' => 'Recommendations prioritize safety and architectural consistency while preserving acceptable runtime performance.',
        'context_signals' => [
            'module_count' => $moduleCount,
            'critical_path_count' => $criticalCount,
            'api_flow_count' => $apiFlowCount,
            'strategy_mode' => $strategy['mode'],
        ],
        'alternative_comparison' => $alternatives,
        'performance_vs_safety_policy' => 'Safety-weighted decision model with historical optimization.',
        'explanation' => [
            'en' => 'This engine compares architecture alternatives using a safety-first weighted model, then optimizes recommendation ranking using historical evolution outcomes and learning-memory pattern success rates.',
            'ar' => 'يقارن هذا المحرك بين بدائل معمارية باستخدام نموذج موزون يعطي أولوية للأمان، ثم يحسّن ترتيب التوصيات عبر نتائج التطور التاريخية ومعدلات نجاح الأنماط من ذاكرة التعلم.',
        ],
    ];

    return [
        'recommended_architecture_changes' => $recommended,
        'optimized_recommendations' => $optimized,
        'historical_confidence_score' => (int)round((float)($optimization['historical_confidence_score'] ?? 55)),
        'matched_past_patterns' => array_values(array_unique($matchedPatterns)),
        'avoided_risk_patterns' => array_values(array_unique($avoidedPatterns)),
        'reasoning_explanation' => $reasoning,
    ];
}

/**
 * Build decision optimization profile from evolution timeline + learning memory.
 * بناء ملف تحسين القرار من الخط الزمني للتطور + ذاكرة التعلم.
 *
 * @return array<string, mixed>
 */
function coreai_build_decision_optimization_profile(): array
{
    $timelinePayload = coreai_load_evolution_timeline();
    $timeline = is_array($timelinePayload['timeline'] ?? null) ? $timelinePayload['timeline'] : [];
    $learning = coreai_load_architecture_learning_memory();
    $patternOutcomes = is_array($learning['pattern_outcomes'] ?? null) ? $learning['pattern_outcomes'] : [];
    $stablePatterns = is_array($learning['stable_patterns'] ?? null) ? $learning['stable_patterns'] : [];
    $riskyPatterns = is_array($learning['risky_patterns'] ?? null) ? $learning['risky_patterns'] : [];

    $strategyStats = [];
    foreach ($timeline as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $mode = strtolower((string)($entry['strategy_mode_used'] ?? 'unknown'));
        if (!isset($strategyStats[$mode])) {
            $strategyStats[$mode] = ['attempts' => 0, 'success' => 0];
        }
        $strategyStats[$mode]['attempts']++;

        $riskBefore = (int)($entry['risk_score_before'] ?? 0);
        $riskAfter = (int)($entry['risk_score_after'] ?? 0);
        $stabilityBefore = (int)($entry['stability_score_before'] ?? 0);
        $stabilityAfter = (int)($entry['stability_score_after'] ?? 0);
        if ($riskAfter <= $riskBefore && $stabilityAfter >= $stabilityBefore) {
            $strategyStats[$mode]['success']++;
        }
    }

    $strategyRates = [];
    foreach ($strategyStats as $mode => $stat) {
        $attempts = max((int)($stat['attempts'] ?? 0), 1);
        $strategyRates[$mode] = round((((int)($stat['success'] ?? 0)) / $attempts) * 100, 2);
    }

    $learningRates = [];
    foreach ($patternOutcomes as $key => $stat) {
        if (!is_array($stat)) {
            continue;
        }
        $attempts = (int)($stat['attempts'] ?? 0);
        if ($attempts < 1) {
            continue;
        }
        $learningRates[] = (float)($stat['success_rate'] ?? 0);
    }
    $learningSuccessRate = ($learningRates === []) ? 55.0 : (array_sum($learningRates) / count($learningRates));

    $timelineConfidence = min(count($timeline), 100);
    $learningConfidence = min(count($patternOutcomes) * 2, 100);
    $historicalConfidence = (int)round(($timelineConfidence * 0.5) + ($learningConfidence * 0.5));

    return [
        'timeline_strategy_success_rates' => $strategyRates,
        'learning_success_rate' => round($learningSuccessRate, 2),
        'historical_confidence_score' => max(15, min(100, $historicalConfidence)),
        'stable_patterns' => array_keys($stablePatterns),
        'risky_patterns' => array_keys($riskyPatterns),
    ];
}

/**
 * Match candidate tags with historical pattern keys.
 * مطابقة وسوم المرشح مع مفاتيح الأنماط التاريخية.
 *
 * @param array<int, string> $tags
 * @param array<int, string> $patternKeys
 * @return array<int, string>
 */
function coreai_match_patterns_for_tags(array $tags, array $patternKeys): array
{
    $matched = [];
    foreach ($patternKeys as $pattern) {
        $p = strtolower((string)$pattern);
        foreach ($tags as $tag) {
            $t = strtolower((string)$tag);
            if ($t === '') {
                continue;
            }
            if (str_contains($p, $t)) {
                $matched[] = $pattern;
                break;
            }
        }
    }
    return array_values(array_unique($matched));
}

/**
 * Resolve architecture decision history storage path.
 * تحديد مسار حفظ سجل القرارات المعمارية.
 */
function coreai_architecture_decision_history_path(): string
{
    return coreai_user_context_root() . '/memory/architecture_decision_history.json';
}

/**
 * Load architecture decision history records.
 * تحميل سجلات القرارات المعمارية.
 *
 * @return array<int, array<string, mixed>>
 */
function coreai_load_architecture_decision_history(): array
{
    $path = coreai_architecture_decision_history_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Save architecture decision history records.
 * حفظ سجلات القرارات المعمارية.
 *
 * @param array<int, array<string, mixed>> $history
 */
function coreai_save_architecture_decision_history(array $history): void
{
    $path = coreai_architecture_decision_history_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (count($history) > 500) {
        $history = array_slice($history, -500);
    }
    $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }
}

/**
 * Store a new AI architectural decision (expected outcome phase).
 * تخزين قرار معماري جديد من الذكاء الاصطناعي (مرحلة النتيجة المتوقعة).
 *
 * @param array<string, mixed> $decision
 */
function coreai_store_architecture_decision(array $decision): string
{
    $history = coreai_load_architecture_decision_history();
    $decisionId = 'arch-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));

    $history[] = [
        'decision_id' => $decisionId,
        'timestamp' => gmdate(DATE_ATOM),
        'reason' => (string)($decision['reason'] ?? ''),
        'context' => is_array($decision['context'] ?? null) ? $decision['context'] : [],
        'expected_outcome' => is_array($decision['expected_outcome'] ?? null) ? $decision['expected_outcome'] : [],
        'actual_outcome' => null,
    ];
    coreai_save_architecture_decision_history($history);
    return $decisionId;
}

/**
 * Update actual outcome for an existing decision.
 * تحديث النتيجة الفعلية لقرار معماري محفوظ.
 *
 * @param array<string, mixed> $actualOutcome
 */
function coreai_update_architecture_decision_actual_outcome(string $decisionId, array $actualOutcome): void
{
    if ($decisionId === '') {
        return;
    }
    $history = coreai_load_architecture_decision_history();
    foreach ($history as &$record) {
        if (!is_array($record)) {
            continue;
        }
        if ((string)($record['decision_id'] ?? '') !== $decisionId) {
            continue;
        }
        $record['actual_outcome'] = $actualOutcome;
        $record['updated_at'] = gmdate(DATE_ATOM);
        break;
    }
    unset($record);
    coreai_save_architecture_decision_history($history);
}

/**
 * Resolve architecture evolution timeline path.
 * تحديد مسار الخط الزمني لتطور المعمارية.
 */
function coreai_evolution_timeline_path(): string
{
    return coreai_user_context_root() . '/memory/evolution_timeline.json';
}

/**
 * Load architecture evolution timeline payload.
 * تحميل حمولة الخط الزمني لتطور المعمارية.
 *
 * @return array{timeline:array<int, array<string, mixed>>}
 */
function coreai_load_evolution_timeline(): array
{
    $path = coreai_evolution_timeline_path();
    if (!is_file($path)) {
        return ['timeline' => []];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return ['timeline' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['timeline' => []];
    }

    // Backward compatibility with old flat array format.
    // توافق خلفي مع التنسيق القديم (مصفوفة مباشرة).
    if (array_is_list($decoded)) {
        return ['timeline' => $decoded];
    }
    $timeline = is_array($decoded['timeline'] ?? null) ? $decoded['timeline'] : [];
    return ['timeline' => $timeline];
}

/**
 * Save architecture evolution timeline payload.
 * حفظ حمولة الخط الزمني لتطور المعمارية.
 *
 * @param array{timeline:array<int, array<string, mixed>>} $payload
 */
function coreai_save_evolution_timeline(array $payload): void
{
    $path = coreai_evolution_timeline_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];
    if (count($timeline) > 800) {
        $timeline = array_slice($timeline, -800);
    }
    $output = ['timeline' => array_values($timeline)];
    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }
}

/**
 * Extract compact architecture snapshot for timeline logging.
 * استخراج لقطة معمارية مختصرة للتسجيل الزمني.
 *
 * @param array<string, mixed> $memory
 * @return array<string, mixed>
 */
function coreai_extract_architecture_snapshot(array $memory): array
{
    return [
        'generated_at' => (string)($memory['generated_at'] ?? gmdate(DATE_ATOM)),
        'architecture_structure' => is_array($memory['architecture_structure'] ?? null) ? $memory['architecture_structure'] : [],
        'modules' => is_array($memory['modules'] ?? null) ? $memory['modules'] : [],
        'critical_paths' => is_array($memory['critical_paths'] ?? null) ? $memory['critical_paths'] : [],
        'system_dependency_hierarchy' => is_array($memory['system_dependency_hierarchy'] ?? null) ? $memory['system_dependency_hierarchy'] : [],
    ];
}

/**
 * Append one evolution entry (before/after + reason).
 * إضافة سجل تطور واحد (قبل/بعد + سبب التغيير).
 *
 * @param array<string, mixed> $beforeMemory
 * @param array<string, mixed> $afterMemory
 * @param array<string, mixed> $meta
 */
function coreai_append_evolution_timeline_entry(array $beforeMemory, array $afterMemory, array $meta = []): void
{
    $payload = coreai_load_evolution_timeline();
    $timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];
    $versionIndex = count($timeline) + 1;
    $changeId = (string)($meta['change_id'] ?? ('chg-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3))));

    $timeline[] = [
        'id' => $changeId,
        'version_index' => $versionIndex,
        'timestamp' => gmdate(DATE_ATOM),
        'reason_for_change' => (string)($meta['reason_for_change'] ?? 'execution_update'),
        'strategy_mode_used' => (string)($meta['strategy_mode_used'] ?? 'safety-first'),
        'decision_id' => (string)($meta['decision_id'] ?? ''),
        'execution_summary' => (string)($meta['execution_summary'] ?? ''),
        'before_architecture_snapshot' => coreai_extract_architecture_snapshot($beforeMemory),
        'after_architecture_snapshot' => coreai_extract_architecture_snapshot($afterMemory),
        'affected_modules' => is_array($meta['affected_modules'] ?? null) ? array_values(array_unique($meta['affected_modules'])) : [],
        'risk_score_before' => (int)($meta['risk_score_before'] ?? 0),
        'risk_score_after' => (int)($meta['risk_score_after'] ?? 0),
        'stability_score_before' => (int)($meta['stability_score_before'] ?? 0),
        'stability_score_after' => (int)($meta['stability_score_after'] ?? 0),
    ];

    coreai_save_evolution_timeline(['timeline' => $timeline]);
}

/**
 * Get timeline analytics and evolution insights.
 * جلب تحليلات الخط الزمني ورؤى التطور.
 *
 * @return array<string, mixed>
 */
function coreai_get_evolution_insights(): array
{
    $payload = coreai_load_evolution_timeline();
    $timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];

    $patternCounts = [];
    $risky = [];
    $stable = [];

    foreach ($timeline as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $reason = (string)($entry['reason_for_change'] ?? 'unknown');
        $strategy = (string)($entry['strategy_mode_used'] ?? 'unknown');
        $patternKey = $reason . '|' . $strategy;
        $patternCounts[$patternKey] = ($patternCounts[$patternKey] ?? 0) + 1;

        $riskAfter = (int)($entry['risk_score_after'] ?? 0);
        $stabilityAfter = (int)($entry['stability_score_after'] ?? 0);
        if ($riskAfter >= 70 || $stabilityAfter <= 35) {
            $risky[] = $entry;
        }
        if ($riskAfter <= 35 && $stabilityAfter >= 70) {
            $stable[] = $entry;
        }
    }

    arsort($patternCounts);
    usort($risky, static fn(array $a, array $b): int => ((int)($b['risk_score_after'] ?? 0)) <=> ((int)($a['risk_score_after'] ?? 0)));
    usort($stable, static fn(array $a, array $b): int => ((int)($b['stability_score_after'] ?? 0)) <=> ((int)($a['stability_score_after'] ?? 0)));

    return [
        'most_frequent_change_patterns' => array_slice(
            array_map(static fn(string $k, int $v): array => ['pattern' => $k, 'count' => $v], array_keys($patternCounts), $patternCounts),
            0,
            10
        ),
        'most_risky_changes_historically' => array_slice($risky, 0, 10),
        'most_stable_architecture_patterns' => array_slice($stable, 0, 10),
        'timeline_size' => count($timeline),
    ];
}

/**
 * Build user-facing AI analytics from historical records.
 * بناء تحليلات ذكاء اصطناعي موجهة للمستخدم من السجلات التاريخية.
 *
 * @return array<string,mixed>
 */
function coreai_get_ai_analytics_summary(): array
{
    $decisions = coreai_load_architecture_decision_history();
    $timelinePayload = coreai_load_evolution_timeline();
    $timeline = is_array($timelinePayload['timeline'] ?? null) ? $timelinePayload['timeline'] : [];
    $learning = coreai_load_architecture_learning_memory();

    $executed = 0;
    $success = 0;
    foreach ($decisions as $record) {
        if (!is_array($record)) {
            continue;
        }
        $actual = is_array($record['actual_outcome'] ?? null) ? $record['actual_outcome'] : null;
        if ($actual === null) {
            continue;
        }
        $executed++;
        if ((bool)($actual['execution_ok'] ?? false) === true) {
            $success++;
        }
    }
    $decisionSuccessRate = $executed > 0 ? (int)round(($success / $executed) * 100) : 0;

    $recentTimeline = array_slice($timeline, -20);
    $olderTimeline = count($timeline) > 20 ? array_slice($timeline, -40, 20) : [];
    $avgRiskRecent = 0.0;
    $avgRiskOlder = 0.0;
    $avgStabilityRecent = 0.0;
    if ($recentTimeline !== []) {
        $riskValues = array_map(static fn(array $e): int => (int)($e['risk_score_after'] ?? 0), $recentTimeline);
        $stabilityValues = array_map(static fn(array $e): int => (int)($e['stability_score_after'] ?? 0), $recentTimeline);
        $avgRiskRecent = array_sum($riskValues) / max(count($riskValues), 1);
        $avgStabilityRecent = array_sum($stabilityValues) / max(count($stabilityValues), 1);
    }
    if ($olderTimeline !== []) {
        $olderRiskValues = array_map(static fn(array $e): int => (int)($e['risk_score_after'] ?? 0), $olderTimeline);
        $avgRiskOlder = array_sum($olderRiskValues) / max(count($olderRiskValues), 1);
    } else {
        $avgRiskOlder = $avgRiskRecent;
    }
    $riskDelta = $avgRiskRecent - $avgRiskOlder;
    $riskTrendLabel = 'stable';
    if ($riskDelta <= -4) {
        $riskTrendLabel = 'improving';
    } elseif ($riskDelta >= 4) {
        $riskTrendLabel = 'rising';
    }

    $learningHistory = is_array($learning['decision_history'] ?? null) ? $learning['decision_history'] : [];
    $recentLearning = array_slice($learningHistory, -20);
    $olderLearning = count($learningHistory) > 20 ? array_slice($learningHistory, -40, 20) : [];
    $avgAccRecent = 0.0;
    $avgAccOlder = 0.0;
    if ($recentLearning !== []) {
        $recentAccuracy = array_map(static fn(array $e): int => (int)($e['prediction_accuracy_score'] ?? 0), $recentLearning);
        $avgAccRecent = array_sum($recentAccuracy) / max(count($recentAccuracy), 1);
    }
    if ($olderLearning !== []) {
        $olderAccuracy = array_map(static fn(array $e): int => (int)($e['prediction_accuracy_score'] ?? 0), $olderLearning);
        $avgAccOlder = array_sum($olderAccuracy) / max(count($olderAccuracy), 1);
    } else {
        $avgAccOlder = $avgAccRecent;
    }
    $learningImprovement = (int)max(0, min(100, round($avgAccRecent + (($avgAccRecent - $avgAccOlder) * 0.5))));

    $stablePatterns = is_array($learning['stable_patterns'] ?? null) ? $learning['stable_patterns'] : [];
    $riskyPatterns = is_array($learning['risky_patterns'] ?? null) ? $learning['risky_patterns'] : [];

    return [
        'decision_success_rate' => max(0, min(100, $decisionSuccessRate)),
        'risk_trend_label' => $riskTrendLabel,
        'risk_trend_delta' => round($riskDelta, 2),
        'system_stability_score' => (int)max(0, min(100, round($avgStabilityRecent))),
        'learning_improvement_score' => $learningImprovement,
        'learning_accuracy_recent' => (int)round($avgAccRecent),
        'learning_accuracy_previous' => (int)round($avgAccOlder),
        'stable_patterns_count' => count($stablePatterns),
        'risky_patterns_count' => count($riskyPatterns),
        'executed_decision_count' => $executed,
        'timeline_entries' => count($timeline),
    ];
}

/**
 * Resolve public share storage directory.
 * تحديد مجلد تخزين المشاركات العامة.
 */
function coreai_public_share_dir(): string
{
    return dirname(__DIR__) . '/context/public-shares';
}

/**
 * Save one public share payload.
 * حفظ حمولة مشاركة عامة واحدة.
 *
 * @param array<string,mixed> $payload
 * @return string share token
 */
function coreai_save_public_share(array $payload): string
{
    $dir = coreai_public_share_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $token = 'shr-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
    $payload['share_token'] = $token;
    $payload['shared_at'] = gmdate(DATE_ATOM);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($dir . '/' . $token . '.json', $json . PHP_EOL, LOCK_EX);
    }
    return $token;
}

/**
 * Read public share by token.
 * قراءة المشاركة العامة عبر الرمز.
 *
 * @return array<string,mixed>|null
 */
function coreai_load_public_share(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || preg_match('/^[a-zA-Z0-9\-_]+$/', $token) !== 1) {
        return null;
    }
    $path = coreai_public_share_dir() . '/' . $token . '.json';
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : null;
}

/**
 * Build lightweight line-based diff text.
 * بناء فرق نصي خفيف يعتمد على الأسطر.
 */
function coreai_build_text_diff(string $before, string $after, int $limit = 120): string
{
    $beforeLines = preg_split("/\r\n|\n|\r/", $before);
    $afterLines = preg_split("/\r\n|\n|\r/", $after);
    if ($beforeLines === false) {
        $beforeLines = [];
    }
    if ($afterLines === false) {
        $afterLines = [];
    }
    $max = max(count($beforeLines), count($afterLines));
    $out = [];
    for ($i = 0; $i < $max; $i++) {
        $b = $beforeLines[$i] ?? null;
        $a = $afterLines[$i] ?? null;
        if ($b === $a) {
            continue;
        }
        if ($b !== null) {
            $out[] = '- ' . $b;
        }
        if ($a !== null) {
            $out[] = '+ ' . $a;
        }
        if (count($out) >= $limit) {
            $out[] = '...diff truncated...';
            break;
        }
    }
    if ($out === []) {
        return "No textual diff detected.";
    }
    return implode("\n", $out);
}
