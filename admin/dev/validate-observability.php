<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/dev/validate-observability.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/dev/validate-observability.php`.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$requestId = bin2hex(random_bytes(8));

$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    echo json_encode(['success' => false, 'data' => [], 'meta' => ['request_id' => $requestId, 'event_count' => 0, 'error' => 'Root not found']]);
    exit;
}

$legacyNeedles = ['system_logs', 'audit_logs', 'admin_audit_logs', 'system_alerts'];
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$legacyRefs = [];
$invalidEmitCalls = [];
$missingRequestIdCalls = [];
$invalidEventTypes = [];
$typesPath = __DIR__ . '/../config/event-types.php';
$allowedTypes = is_file($typesPath) ? (array) require $typesPath : ['UNKNOWN_EVENT'];
$allowedTypes = array_values(array_filter(array_map(static function ($v): string {
    return strtoupper(trim((string) $v));
}, $allowedTypes)));
$allowPrefixes = ['ALERT_', 'CONTROL_', 'QUERY_', 'AUTH_', 'TENANT_'];

foreach ($phpFiles as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (strpos(str_replace('\\', '/', $path), '/Designed/') !== false) {
        continue;
    }
    $content = @file_get_contents($path);
    if (!is_string($content) || $content === '') {
        continue;
    }
    foreach ($legacyNeedles as $needle) {
        if (stripos($content, $needle) !== false) {
            if (str_replace('\\', '/', $path) !== str_replace('\\', '/', __FILE__)) {
                $legacyRefs[] = ['file' => $path, 'token' => $needle];
            }
        }
    }
    if (preg_match_all("/emitEvent\\(\\s*'([A-Z0-9_]+)'\\s*,(.*?)\\);/s", $content, $calls, PREG_SET_ORDER)) {
        foreach ($calls as $call) {
            $eventType = strtoupper(trim((string) ($call[1] ?? '')));
            $callText = (string) ($call[2] ?? '');
            if (!preg_match("/^\\s*('([a-zA-Z]+)'|\\$[a-zA-Z_][a-zA-Z0-9_]*)/s", $callText, $parts)) {
                $invalidEmitCalls[] = ['file' => $path, 'call' => trim(substr("emitEvent('{$eventType}', {$callText}", 0, 180))];
                continue;
            }
            $okType = in_array($eventType, $allowedTypes, true);
            if (!$okType) {
                foreach ($allowPrefixes as $prefix) {
                    if (strpos($eventType, $prefix) === 0) {
                        $okType = true;
                        break;
                    }
                }
            }
            if (!$okType) {
                $invalidEventTypes[] = ['file' => $path, 'event_type' => $eventType];
            }
            if (stripos($callText, 'request_id') === false) {
                $missingRequestIdCalls[] = ['file' => $path, 'event_type' => $eventType];
            }
        }
    }
}

$data = [
    'legacy_reference_count' => count($legacyRefs),
    'legacy_references' => $legacyRefs,
    'invalid_emit_calls_count' => count($invalidEmitCalls),
    'invalid_emit_calls' => $invalidEmitCalls,
    'missing_request_id_emit_calls_count' => count($missingRequestIdCalls),
    'missing_request_id_emit_calls' => $missingRequestIdCalls,
    'invalid_event_types_count' => count($invalidEventTypes),
    'invalid_event_types' => $invalidEventTypes,
    'allowed_event_types_count' => count($allowedTypes),
];

echo json_encode([
    'success' => count($legacyRefs) === 0 && count($invalidEmitCalls) === 0 && count($invalidEventTypes) === 0,
    'data' => $data,
    'meta' => [
        'request_id' => $requestId,
        'event_count' => count($legacyRefs) + count($invalidEmitCalls) + count($invalidEventTypes),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
