<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/events-stream.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/events-stream.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}
require_once __DIR__ . '/core/ControlCenterAccess.php';
require_once __DIR__ . '/core/EventBus.php';

set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-store');
    }
    echo 'data: ' . json_encode([
        'error' => 'stream_failed',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    flush();
    exit;
});

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403 Forbidden';
    exit;
}

@set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$lastId = (int) ($_GET['last_id'] ?? 0);
$maxIterations = 900;
$iter = 0;
$typesFilter = trim((string) ($_GET['event_types'] ?? ''));
$allowedTypeTokens = [];
if ($typesFilter !== '') {
    $parts = explode(',', $typesFilter);
    foreach ($parts as $p) {
        $p = strtoupper(trim((string) $p));
        if ($p !== '' && preg_match('/^[A-Z0-9_]+$/', $p)) {
            $allowedTypeTokens[] = $p;
        }
    }
}

/**
 * @return PDO|null
 */
function resolveStreamPdo()
{
    try {
        return getControlDB();
    } catch (Throwable $e) {
        // Fallback for environments where getControlDB is bound to an
        // incompatible DB name/credential pair.
    }

    $dbCandidates = [];
    $addDb = static function (?string $v) use (&$dbCandidates): void {
        $v = trim((string) $v);
        if ($v !== '' && !in_array($v, $dbCandidates, true)) {
            $dbCandidates[] = $v;
        }
    };
    $addDb(defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : null);
    $addDb(defined('CONTROL_DB_NAME') ? (string) CONTROL_DB_NAME : null);
    $addDb(getenv('CONTROL_PANEL_DB_NAME') !== false ? (string) getenv('CONTROL_PANEL_DB_NAME') : null);
    $addDb(getenv('CONTROL_DB_NAME') !== false ? (string) getenv('CONTROL_DB_NAME') : null);
    $addDb(defined('DB_NAME') ? (string) DB_NAME : null);

    $host = (string) (defined('DB_HOST') ? DB_HOST : 'localhost');
    $port = (int) (defined('DB_PORT') ? DB_PORT : 3306);
    $user = (string) (defined('DB_USER') ? DB_USER : '');
    $pass = (string) (defined('DB_PASS') ? DB_PASS : '');

    foreach ($dbCandidates as $dbName) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $test = $pdo->query('SELECT 1 FROM system_events LIMIT 1');
            if ($test !== false) {
                return $pdo;
            }
        } catch (Throwable $e) {
            // try next
        }
    }
    return null;
}

/**
 * @param array<string,mixed> $payload
 */
function sseData(array $payload): void
{
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
}

function stream_event_priority_rank(array $event): int
{
    $et = strtoupper(trim((string) ($event['event_type'] ?? '')));
    $lvl = strtolower(trim((string) ($event['level'] ?? 'info')));
    if ($lvl === 'critical') {
        return 5;
    }
    if (in_array($et, ['WORKER_THREAT_CRITICAL', 'WORKER_SOS'], true)) {
        return 4;
    }
    if (in_array($et, ['WORKER_THREAT_HIGH', 'WORKER_GPS_SPOOF_CONFIRMED', 'WORKER_GEOFENCE_BREACH_PATTERN', 'WORKER_RESPONSE_ACTION'], true)) {
        return 3;
    }
    if (in_array($et, ['WORKER_THREAT_ELEVATED', 'WORKER_ESCAPE_RISK'], true)) {
        return 2;
    }
    return 1;
}

$pdo = resolveStreamPdo();
if (!$pdo instanceof PDO) {
    sseData(['error' => 'db_unavailable']);
    flush();
    exit;
}

while ($iter < $maxIterations && connection_aborted() === 0) {
    try {
        $sql = 'SELECT * FROM system_events WHERE id > :after';
        if ($allowedTypeTokens !== []) {
            $inParts = [];
            foreach ($allowedTypeTokens as $i => $tok) {
                $inParts[] = ':ev' . $i;
            }
            $sql .= ' AND event_type IN (' . implode(',', $inParts) . ')';
        }
        $sql .= ' ORDER BY id ASC LIMIT 50';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':after', max(0, $lastId), PDO::PARAM_INT);
        if ($allowedTypeTokens !== []) {
            foreach ($allowedTypeTokens as $i => $tok) {
                $stmt->bindValue(':ev' . $i, $tok);
            }
        }
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($events === []) {
            echo ": ping " . time() . "\n\n";
        } else {
            usort($events, static function (array $a, array $b): int {
                $pa = stream_event_priority_rank($a);
                $pb = stream_event_priority_rank($b);
                if ($pa === $pb) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                return $pb <=> $pa;
            });
            foreach ($events as $event) {
                $lastId = max($lastId, (int) ($event['id'] ?? 0));
                sseData($event);
            }
        }
    } catch (Throwable $e) {
        sseData(['error' => 'stream_failed', 'message' => $e->getMessage()]);
    }
    flush();
    sleep(2);
    $iter++;
}
