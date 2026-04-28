<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/dev/event-retention.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/dev/event-retention.php`.
 */
declare(strict_types=1);

/**
 * Purge old system_events rows. Run via CLI or browser with admin session + token.
 *
 * CLI: php admin/dev/event-retention.php --days=30 --confirm=1
 * HTTP: .../admin/dev/event-retention.php?days=30&confirm=1&token=...
 */

$root = dirname(__DIR__, 2);
require_once $root . '/includes/config.php';
require_once $root . '/admin/core/ControlCenterAccess.php';
require_once $root . '/admin/core/EventBus.php';
require_once $root . '/admin/core/EventRepository.php';

$isCli = PHP_SAPI === 'cli';
$days = (int) ($_GET['days'] ?? 30);
$confirm = (string) ($_GET['confirm'] ?? '') === '1';

if ($isCli) {
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--days=')) {
            $days = (int) substr($arg, 7);
        }
        if ($arg === '--confirm=1') {
            $confirm = true;
        }
    }
}

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!defined('ADMIN_CONTROL_MODE')) {
        define('ADMIN_CONTROL_MODE', true);
    }
    if (!ControlCenterAccess::canAccessControlCenter()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'data' => [], 'meta' => ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'Forbidden']]);
        exit;
    }
}
if (!$isCli) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!$confirm) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'meta' => [
            'request_id' => function_exists('getRequestId') ? getRequestId() : 'cli',
            'event_count' => 0,
            'hint' => 'Add confirm=1 and optional days=30 (HTTP) or --confirm=1 --days=30 (CLI)',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = getControlDB();
    $deleted = EventRepository::purgeOlderThanDays($pdo, $days);
    echo json_encode([
        'success' => true,
        'data' => [['deleted_rows' => $deleted, 'retention_days' => max(1, min(3650, $days))]],
        'meta' => [
            'request_id' => function_exists('getRequestId') ? getRequestId() : 'cli',
            'event_count' => 1,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [],
        'meta' => [
            'request_id' => function_exists('getRequestId') ? getRequestId() : 'cli',
            'event_count' => 0,
            'error' => $e->getMessage(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
