<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventBus.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventBus.php`.
 */
declare(strict_types=1);

if (!defined('EVENT_STRICT_MODE')) {
    $strict = getenv('EVENT_STRICT_MODE');
    define('EVENT_STRICT_MODE', in_array(strtolower((string) $strict), ['1', 'true', 'yes', 'on'], true));
}
if (!defined('EVENT_ASYNC_QUEUE')) {
    $async = getenv('EVENT_ASYNC_QUEUE');
    define('EVENT_ASYNC_QUEUE', !in_array(strtolower((string) $async), ['0', 'false', 'off', 'no'], true));
}

if (!function_exists('eventbus_str_contains')) {
    function eventbus_str_contains(string $haystack, string $needle): bool
    {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

/**
 * @return string[]
 */
function allowedEventTypes(): array
{
    static $types = null;
    if (is_array($types)) {
        return $types;
    }
    $file = __DIR__ . '/../config/event-types.php';
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $types = array_values(array_filter(array_map(static function ($v) {
                return strtoupper(trim((string) $v));
            }, $loaded)));
            if (!in_array('UNKNOWN_EVENT', $types, true)) {
                $types[] = 'UNKNOWN_EVENT';
            }
            return $types;
        }
    }
    $types = ['UNKNOWN_EVENT'];
    return $types;
}

function isAllowedEventType(string $eventType): bool
{
    $eventType = strtoupper(trim($eventType));
    if (in_array($eventType, allowedEventTypes(), true)) {
        return true;
    }
    foreach (['ALERT_', 'CONTROL_', 'QUERY_', 'AUTH_', 'TENANT_', 'ANOMALY_', 'AGENCY_', 'WORKER_'] as $prefix) {
        if (strpos($eventType, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function getRequestId(): string
{
    static $id = null;
    if ($id === null || $id === '') {
        $node = substr(hash('sha256', (string) php_uname('n')), 0, 8);
        $rand = bin2hex(random_bytes(12));
        $id = substr($node . $rand, 0, 32);
    }
    return $id;
}

function clampEventMessage(string $message, int $maxLen = 8192): string
{
    if (strlen($message) <= $maxLen) {
        return $message;
    }
    return substr($message, 0, $maxLen) . '…[truncated]';
}

function sanitizeSqlSnippet(?string $sql): ?string
{
    if ($sql === null || $sql === '') {
        return $sql;
    }
    $s = preg_replace('/--[^\n]*/', '', $sql);
    $s = preg_replace('/\/\*.*?\*\//s', '', $s ?? '');
    $s = trim(preg_replace('/\s+/', ' ', $s ?? ''));
    if (strlen($s) > 2048) {
        $s = substr($s, 0, 2048) . '…';
    }
    return $s;
}

/**
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function prepareEventMetadata(array $meta): array
{
    foreach ($meta as $k => $v) {
        if (!is_string($v)) {
            continue;
        }
        $lk = strtolower((string) $k);
        if ($lk === 'query' || eventbus_str_contains($lk, 'sql')) {
            $meta[$k] = sanitizeSqlSnippet($v) ?? '';
        }
    }
    return $meta;
}

/**
 * @param mixed $v
 * @return mixed
 */
function truncateMetaValues($v, int $maxScalar = 4000)
{
    if (is_string($v)) {
        if (strlen($v) <= $maxScalar) {
            return $v;
        }
        return substr($v, 0, $maxScalar) . '…';
    }
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $item) {
            $out[$k] = truncateMetaValues($item, $maxScalar);
        }
        return $out;
    }
    return $v;
}

function redactPiiScalars(string $s): string
{
    $s = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[email]', $s);
    $s = preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[phone]', $s);
    return $s;
}

function getControlDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dbName = defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== ''
        ? CONTROL_PANEL_DB_NAME
        : (defined('DB_NAME') ? DB_NAME : '');
    if ($dbName === '') {
        throw new RuntimeException('Control DB is not configured.');
    }
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/**
 * @param array<string, mixed> $meta
 */
function emitEvent(string $type, string $level, string $message, array $meta = [], ?PDO $db = null): void
{
    $message = clampEventMessage($message);
    $meta = prepareEventMetadata($meta);

    $typeNorm = strtoupper(trim($type));
    if (!isAllowedEventType($typeNorm)) {
        if (EVENT_STRICT_MODE) {
            return;
        }
        $typeNorm = 'UNKNOWN_EVENT';
    }

    if ($typeNorm === 'QUERY_EXECUTED' && random_int(1, 100) > 20) {
        return;
    }

    if (empty($meta['request_id'])) {
        $meta['request_id'] = getRequestId();
    }
    if (!isset($meta['tenant_id'])) {
        $meta['tenant_id'] = null;
    }

    $normLevel = strtolower(trim($level));
    if (!in_array($normLevel, ['info', 'warn', 'error', 'critical'], true)) {
        $normLevel = 'info';
    }

    if (!isset($meta['endpoint']) || trim((string) $meta['endpoint']) === '') {
        $meta['endpoint'] = (string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'cli'));
    }
    $requiredMetaKeys = ['tenant_id', 'query', 'endpoint', 'mode', 'duration_ms'];
    foreach ($requiredMetaKeys as $k) {
        if (!array_key_exists($k, $meta)) {
            $meta[$k] = null;
        }
    }
    if (EVENT_STRICT_MODE) {
        foreach ($requiredMetaKeys as $k) {
            if (!array_key_exists($k, $meta)) {
                return;
            }
        }
    }

    $tenantId = isset($meta['tenant_id']) && (int) $meta['tenant_id'] > 0 ? (int) $meta['tenant_id'] : null;
    $resolvedAuthUserId = null;
    if (class_exists('Auth') && method_exists('Auth', 'userId')) {
        try {
            $aid = Auth::userId();
            if ($aid !== null && (int) $aid > 0) {
                $resolvedAuthUserId = (int) $aid;
            }
        } catch (Throwable $e) {
            $resolvedAuthUserId = null;
        }
    }
    if ($resolvedAuthUserId === null) {
        $sid = (int) ($_SESSION['user_id'] ?? 0);
        if ($sid > 0) {
            $resolvedAuthUserId = $sid;
        }
    }
    $userId = isset($meta['user_id']) && (int) $meta['user_id'] > 0
        ? (int) $meta['user_id']
        : $resolvedAuthUserId;
    $source = isset($meta['source']) && trim((string) $meta['source']) !== '' ? (string) $meta['source'] : 'control_center';
    $requestId = substr((string) $meta['request_id'], 0, 32);

    $stdMeta = [
        'tenant_id' => $tenantId,
        'query' => isset($meta['query']) ? (string) $meta['query'] : null,
        'endpoint' => (string) $meta['endpoint'],
        'mode' => isset($meta['mode']) ? (string) $meta['mode'] : null,
        'duration_ms' => isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : null,
    ];
    $payloadMeta = maskSensitiveData(array_merge($meta, $stdMeta));
    $payloadMeta['request_id'] = $requestId;
    $payloadMeta['source'] = $source;
    $payloadMeta = truncateMetaValues($payloadMeta);

    try {
        $pdo = $db instanceof PDO ? $db : getControlDB();
        $metaJson = json_encode($payloadMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaJson === false) {
            $payloadMeta = ['json_error' => 'metadata_encoding_failed', 'request_id' => $requestId, 'source' => $source];
            $metaJson = json_encode($payloadMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $eventRow = [
            'event_type' => substr($typeNorm, 0, 100),
            'level' => $normLevel,
            'priority' => isset($meta['priority']) ? (string) $meta['priority'] : null,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'request_id' => $requestId,
            'source' => substr($source, 0, 50),
            'message' => $message,
            'metadata' => $metaJson !== false ? $metaJson : null,
        ];

        if (EVENT_ASYNC_QUEUE) {
            require_once __DIR__ . '/EventQueue.php';
            EventQueue::ensureTable($pdo);
            EventQueue::pushWithCircuitBreaker($pdo, $eventRow);
            return;
        }

        require_once __DIR__ . '/EventRepository.php';
        EventRepository::insert($pdo, $eventRow);

        static $obsHooks = false;
        if (!$obsHooks) {
            require_once __DIR__ . '/EventMetricsAggregator.php';
            require_once __DIR__ . '/EventAnomalyDetector.php';
            $obsHooks = true;
        }
        EventMetricsAggregator::record($typeNorm, $normLevel, $tenantId);
        EventAnomalyDetector::evaluateAfterInsert($pdo, $typeNorm, $normLevel, $message, $payloadMeta);
    } catch (Throwable $e) {
        error_log('emitEvent queue failed: ' . $e->getMessage());
    }
}

/**
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function maskSensitiveData(array $meta): array
{
    $masked = [];
    foreach ($meta as $k => $v) {
        $key = strtolower((string) $k);
        $sensitive = eventbus_str_contains($key, 'password')
            || eventbus_str_contains($key, 'token')
            || eventbus_str_contains($key, 'secret')
            || eventbus_str_contains($key, 'api_key')
            || eventbus_str_contains($key, 'authorization')
            || eventbus_str_contains($key, 'email')
            || eventbus_str_contains($key, 'phone')
            || eventbus_str_contains($key, 'mobile')
            || eventbus_str_contains($key, 'national_id')
            || $key === 'ssn';
        if ($sensitive) {
            $masked[$k] = '[redacted]';
            continue;
        }
        if (is_array($v)) {
            $masked[$k] = maskSensitiveData($v);
            continue;
        }
        if (is_object($v)) {
            $masked[$k] = '[object]';
            continue;
        }
        if (is_string($v)) {
            $masked[$k] = redactPiiScalars($v);
            continue;
        }
        $masked[$k] = $v;
    }
    return $masked;
}

function escalateEventLevel(PDO $pdo, string $type, string $message, string $currentLevel): string
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c
             FROM system_events
             WHERE event_type = :t
               AND message = :m
               AND level IN ("error","critical")
               AND created_at > (NOW() - INTERVAL 1 MINUTE)'
        );
        $stmt->execute([
            ':t' => substr(trim($type), 0, 100),
            ':m' => $message,
        ]);
        $count = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
        if ($currentLevel === 'error' && $count >= 5) {
            return 'critical';
        }
    } catch (Throwable $e) {
        return $currentLevel;
    }
    return $currentLevel;
}

function registerGlobalEventExceptionHandler(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;
    set_exception_handler(static function (Throwable $e): void {
        emitEvent('UNCAUGHT_EXCEPTION', 'critical', $e->getMessage(), [
            'source' => 'global_exception_handler',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'cli'),
        ]);
        http_response_code(500);
        echo 'Internal Server Error';
    });
}

/**
 * @param array<int, array<string, mixed>> $data
 * @param array<string, mixed> $metaExtra
 */
function eventApiResponse(bool $success, array $data = [], array $metaExtra = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    $meta = array_merge([
        'request_id' => getRequestId(),
        'event_count' => count($data),
    ], $metaExtra);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'meta' => $meta,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
