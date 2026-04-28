<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/event-timeline.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/event-timeline.php`.
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
require_once __DIR__ . '/core/EventRepository.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    exit('403 Forbidden');
}

/**
 * @param array<string, mixed> $row
 */
function etl_is_anomaly(array $row): bool
{
    return ($row['event_type'] ?? '') === 'ANOMALY_DETECTED'
        || ($row['level'] ?? '') === 'critical';
}

function etl_duration_ms(?string $metadata): ?int
{
    if ($metadata === null || $metadata === '') {
        return null;
    }
    $j = json_decode($metadata, true);
    if (!is_array($j) || !isset($j['duration_ms'])) {
        return null;
    }
    $v = $j['duration_ms'];
    return is_numeric($v) ? (int) $v : null;
}

function timelinePdo(): PDO
{
    return getControlDB();
}

$pdo = timelinePdo();
$filters = [
    'tenant_id' => (int) ($_GET['tenant_id'] ?? 0),
    'event_type' => (string) ($_GET['event_type'] ?? ''),
    'level' => (string) ($_GET['level'] ?? ''),
    'request_id' => (string) ($_GET['request_id'] ?? ''),
    'from' => (string) ($_GET['from'] ?? ''),
    'to' => (string) ($_GET['to'] ?? ''),
];
$traceRequestId = (string) ($_GET['trace_request_id'] ?? '');
$isApi = ((string) ($_GET['api'] ?? '') === '1');
$sinceId = (int) ($_GET['since_id'] ?? 0);

if ($isApi) {
    try {
        $timelineRows = EventRepository::latest($filters, 300);
        $total = 0;
        $countStmt = $pdo->query('SELECT COUNT(*) AS c FROM system_events');
        if ($countStmt) {
            $total = (int) (($countStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
        }
        $timeline = ['rows' => $timelineRows, 'total' => $total];
        if ($sinceId > 0) {
            $timeline['rows'] = array_values(array_filter($timeline['rows'], static function ($row) use ($sinceId) {
                return (int) ($row['id'] ?? 0) > $sinceId;
            }));
        }
        $trace = $traceRequestId !== '' ? EventRepository::trace($traceRequestId) : [];
        eventApiResponse(true, $timeline['rows'], [
            'request_id' => getRequestId(),
            'event_count' => count($timeline['rows']),
            'total' => $timeline['total'],
            'trace' => $trace,
        ]);
    } catch (Throwable $e) {
        emitEvent('TIMELINE_API_ERROR', 'error', 'Event timeline API failed', [
            'source' => 'event_timeline',
            'error' => $e->getMessage(),
        ], $pdo);
        eventApiResponse(false, [], [
            'request_id' => getRequestId(),
            'event_count' => 0,
            'error' => 'Failed to load events',
        ], 500);
    }
}

$timelineRows = [];
$total = 0;
$traceRows = [];
try {
    $timelineRows = EventRepository::latest($filters, 200);
    $countStmt = $pdo->query('SELECT COUNT(*) AS c FROM system_events');
    if ($countStmt) {
        $total = (int) (($countStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
    }
    if ($traceRequestId !== '') {
        $traceRows = EventRepository::trace($traceRequestId);
    }
} catch (Throwable $e) {
    emitEvent('TIMELINE_PAGE_ERROR', 'error', 'Event timeline render failed', [
        'source' => 'event_timeline',
        'error' => $e->getMessage(),
    ], $pdo);
}

$groups = [];
$maxTimelineId = 0;
foreach ($timelineRows as $r) {
    $maxTimelineId = max($maxTimelineId, (int) ($r['id'] ?? 0));
    $rid = trim((string) ($r['request_id'] ?? ''));
    if ($rid === '') {
        $rid = '—';
    }
    if (!isset($groups[$rid])) {
        $groups[$rid] = ['request_id' => $rid, 'events' => [], 'max_id' => 0];
    }
    $groups[$rid]['events'][] = $r;
    $groups[$rid]['max_id'] = max($groups[$rid]['max_id'], (int) ($r['id'] ?? 0));
}
uasort($groups, static function ($a, $b) {
    return $b['max_id'] <=> $a['max_id'];
});

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Timeline</title>
    <link rel="stylesheet" href="assets/css/event-timeline.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="row etl-header-row">
            <h2 class="etl-title">Central Event Timeline</h2>
            <div class="row">
                <a href="control-center.php" class="etl-link">Control Center</a>
                <a href="event-flow.php" class="etl-link">Event Flow</a>
            </div>
        </div>
        <p class="muted">Real-time SSE stream (no polling) · grouped by <code>request_id</code> · anomalies highlighted.</p>
        <form method="get" class="row" id="filterForm">
            <input type="number" name="tenant_id" placeholder="Tenant ID" value="<?php echo (int) $filters['tenant_id'] > 0 ? (int) $filters['tenant_id'] : ''; ?>">
            <input type="text" name="event_type" placeholder="Event type" value="<?php echo htmlspecialchars((string) $filters['event_type'], ENT_QUOTES, 'UTF-8'); ?>">
            <select name="level">
                <option value="">Any level</option>
                <?php foreach (['info', 'warn', 'error', 'critical'] as $lv): ?>
                    <option value="<?php echo $lv; ?>" <?php echo $filters['level'] === $lv ? 'selected' : ''; ?>><?php echo $lv; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="request_id" placeholder="Request ID" value="<?php echo htmlspecialchars((string) $filters['request_id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="datetime-local" name="from" value="<?php echo htmlspecialchars((string) $filters['from'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="datetime-local" name="to" value="<?php echo htmlspecialchars((string) $filters['to'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Apply</button>
            <label><input type="checkbox" id="liveMode"> Live stream (SSE)</label>
            <span id="streamStatus" class="stream-status off">stream off</span>
        </form>
        <p class="muted">Showing <?php echo count($timelineRows); ?> / <?php echo $total; ?> events · max id <?php echo (int) $maxTimelineId; ?> · request <?php echo htmlspecialchars(getRequestId(), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <div class="card" id="timelineHost" data-last-id="<?php echo (int) $maxTimelineId; ?>">
        <?php if ($groups === []): ?>
            <div class="muted">No events matched filters.</div>
        <?php else: ?>
            <?php foreach ($groups as $bundle): ?>
                <?php
                $rid = $bundle['request_id'];
                $evs = $bundle['events'];
                usort($evs, static function ($a, $b) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                });
                $chain = implode(' → ', array_values(array_unique(array_map(static function ($x) {
                    return (string) ($x['event_type'] ?? '');
                }, $evs))));
                ?>
                <details class="req-bundle" open data-req="<?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?>">
                    <summary>
                        <span>req <code><?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?></code></span>
                        <span class="pill"><?php echo count($evs); ?> events</span>
                        <?php if ($rid !== '—'): ?>
                            <a href="event-flow.php?request_id=<?php echo urlencode($rid); ?>" class="etl-link js-stop-propagation">flow</a>
                            <a href="?trace_request_id=<?php echo urlencode($rid); ?>" class="etl-link js-stop-propagation">trace</a>
                        <?php endif; ?>
                    </summary>
                    <div class="bundle-inner">
                        <div class="muted etl-chain"><?php echo htmlspecialchars($chain, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php foreach ($evs as $r): ?>
                            <?php
                            $dms = etl_duration_ms(isset($r['metadata']) ? (string) $r['metadata'] : null);
                            $anom = etl_is_anomaly($r);
                            $lvlSafe = preg_replace('/[^a-z]/', '', strtolower((string) ($r['level'] ?? 'info'))) ?: 'info';
                            $cls = 'timeline-item lvl-' . $lvlSafe;
                            if ($anom) {
                                $cls .= ' anomaly';
                            }
                            ?>
                            <div class="<?php echo $cls; ?>" data-event-id="<?php echo (int) ($r['id'] ?? 0); ?>">
                                <div class="meta">
                                    <span><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars((string) $r['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>level=<?php echo htmlspecialchars((string) $r['level'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($dms !== null): ?><span><?php echo (int) $dms; ?> ms</span><?php endif; ?>
                                    <span>tenant=<?php echo (int) ($r['tenant_id'] ?? 0); ?></span>
                                </div>
                                <div><?php echo htmlspecialchars((string) $r['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="muted"><?php echo htmlspecialchars(substr((string) ($r['metadata'] ?? ''), 0, 320), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card trace" id="traceHost">
        <h3 class="etl-trace-title">Request Trace</h3>
        <?php if ($traceRequestId === ''): ?>
            <p class="muted">Open a trace link or use filters to load a request.</p>
        <?php elseif ($traceRows === []): ?>
            <p class="muted">No events for request <code><?php echo htmlspecialchars($traceRequestId, ENT_QUOTES, 'UTF-8'); ?></code>.</p>
        <?php else: ?>
            <?php foreach ($traceRows as $t): ?>
                <?php $tlvl = preg_replace('/[^a-z]/', '', strtolower((string) ($t['level'] ?? 'info'))) ?: 'info'; ?>
                <div class="timeline-item lvl-<?php echo $tlvl; ?><?php echo etl_is_anomaly($t) ? ' anomaly' : ''; ?>">
                    <div class="meta">
                        <span><?php echo htmlspecialchars((string) $t['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) $t['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>tenant=<?php echo (int) ($t['tenant_id'] ?? 0); ?></span>
                        <?php $td = etl_duration_ms(isset($t['metadata']) ? (string) $t['metadata'] : null); ?>
                        <?php if ($td !== null): ?><span><?php echo (int) $td; ?> ms</span><?php endif; ?>
                    </div>
                    <div><?php echo htmlspecialchars((string) $t['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="assets/js/event-timeline.js?v=<?php echo time(); ?>"></script>
</body>
</html>
