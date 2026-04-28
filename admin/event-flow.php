<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/event-flow.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/event-flow.php`.
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

$requestId = substr(trim((string) ($_GET['request_id'] ?? '')), 0, 32);
$trace = $requestId !== '' ? EventRepository::trace($requestId) : [];

/** @var array<string, string> */
$flowLabels = [
    'REQUEST_START' => 'Request start',
    'AUTH_LOGIN_SUCCESS' => 'Auth · success',
    'AUTH_LOGIN_FAILED' => 'Auth · failed',
    'AUTH_LOGIN_BLOCKED' => 'Auth · blocked',
    'AUTH_LOGOUT' => 'Auth · logout',
    'QUERY_EXECUTED' => 'Query executed',
    'QUERY_EXECUTION_FAILED' => 'Query failed',
    'QUERY_GATEWAY_POLICY' => 'Gateway policy',
    'ADMIN_AUDIT' => 'Admin audit',
    'CONTROL_LOG' => 'Control log',
    'CONTROL_CENTER_ERROR' => 'Control error',
    'TENANT_CREATED' => 'Tenant created',
    'UNCAUGHT_EXCEPTION' => 'Uncaught exception',
    'ANOMALY_DETECTED' => 'Anomaly',
    'SYSTEM_HEALTH_ERROR' => 'Health check error',
    'RESPONSE_SENT' => 'Response sent',
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Flow<?php echo $requestId !== '' ? ' · ' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') : ''; ?></title>
    <link rel="stylesheet" href="assets/css/event-flow.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1 class="ef-title">Request event flow</h1>
        <p class="ef-subtitle">Step-by-step execution for one <code>request_id</code> (canonical path + all captured events).</p>
        <form method="get" class="ef-form">
            <input type="text" name="request_id" placeholder="request_id" value="<?php echo htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8'); ?>" class="ef-input-request">
            <button type="submit">Load flow</button>
            <a href="event-timeline.php">Timeline</a>
        </form>
    </div>

    <?php if ($requestId === ''): ?>
        <div class="card"><p class="ef-empty-note">Enter a request id from the timeline or stream.</p></div>
    <?php elseif ($trace === []): ?>
        <div class="card"><p class="ef-empty-no-events">No events for <code><?php echo htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8'); ?></code>.</p></div>
    <?php else: ?>
        <?php
        $seenTypes = [];
        foreach ($trace as $t) {
            $seenTypes[(string) ($t['event_type'] ?? '')] = true;
        }
        $canonical = ['REQUEST_START', 'AUTH_LOGIN_SUCCESS', 'AUTH_LOGIN_FAILED', 'AUTH_LOGIN_BLOCKED', 'QUERY_EXECUTED', 'QUERY_GATEWAY_POLICY', 'QUERY_EXECUTION_FAILED', 'RESPONSE_SENT', 'CONTROL_CENTER_ERROR'];
        $steps = [];
        foreach ($canonical as $ct) {
            $hit = !empty($seenTypes[$ct]);
            $matchRow = null;
            if ($hit) {
                foreach ($trace as $t) {
                    if ((string) ($t['event_type'] ?? '') === $ct) {
                        $matchRow = $t;
                        break;
                    }
                }
            }
            $steps[] = ['type' => $ct, 'hit' => $hit, 'row' => $matchRow];
        }
        ?>
        <div class="card">
            <h2 class="ef-canonical-title">Canonical path</h2>
            <p class="meta">Green = observed · dim = not seen for this request</p>
            <div class="flow">
                <?php foreach ($steps as $i => $st): ?>
                    <?php if ($i > 0): ?><span class="arrow">→</span><?php endif; ?>
                    <?php
                    $lvl = $st['row'] ? strtolower((string) ($st['row']['level'] ?? 'info')) : '';
                    $cls = 'step miss';
                    if ($st['hit']) {
                        $cls = 'step hit';
                        if ($lvl === 'warn') {
                            $cls = 'step hit warn';
                        }
                        if ($lvl === 'error') {
                            $cls = 'step hit err';
                        }
                        if ($lvl === 'critical') {
                            $cls = 'step hit crit';
                        }
                    }
                    $dur = '';
                    if ($st['row'] && !empty($st['row']['metadata'])) {
                        $mj = json_decode((string) $st['row']['metadata'], true);
                        if (is_array($mj) && isset($mj['duration_ms']) && $mj['duration_ms'] !== null) {
                            $dur = (string) $mj['duration_ms'] . ' ms';
                        }
                    }
                    ?>
                    <div class="<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="etype"><?php echo htmlspecialchars($st['type'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="label"><?php echo htmlspecialchars($flowLabels[$st['type']] ?? $st['type'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($dur !== ''): ?><div class="meta"><?php echo htmlspecialchars($dur, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card extra">
            <h3>Full chain (<?php echo count($trace); ?> events)</h3>
            <?php foreach ($trace as $idx => $t): ?>
                <div class="row">
                    <strong><?php echo (int) ($idx + 1); ?>.</strong>
                    <?php echo htmlspecialchars((string) ($t['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <span class="ef-level"><?php echo htmlspecialchars((string) ($t['level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                    $dms = '';
                    if (!empty($t['metadata'])) {
                        $mj = json_decode((string) $t['metadata'], true);
                        if (is_array($mj) && isset($mj['duration_ms'])) {
                            $dms = ' · ' . (string) $mj['duration_ms'] . ' ms';
                        }
                    }
                    echo htmlspecialchars($dms, ENT_QUOTES, 'UTF-8');
                    ?>
                    <br>
                    <span class="ef-message"><?php echo htmlspecialchars(substr((string) ($t['message'] ?? ''), 0, 200), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
