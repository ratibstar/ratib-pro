<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/debug-dashboard.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/debug-dashboard.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

function isDebugDashboardEnabled(): bool
{
    $asBool = static function ($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    };

    if (defined('OBSERVABILITY_DASHBOARD_ENABLED') && $asBool(OBSERVABILITY_DASHBOARD_ENABLED)) {
        return true;
    }
    $obsEnv = getenv('OBSERVABILITY_DASHBOARD_ENABLED');
    if ($obsEnv !== false && $asBool($obsEnv)) {
        return true;
    }

    if (defined('DEBUG_MODE') && $asBool(DEBUG_MODE)) {
        return true;
    }
    $debugEnv = getenv('DEBUG_MODE');
    if ($debugEnv !== false && $asBool($debugEnv)) {
        return true;
    }
    $appDebugEnv = getenv('APP_DEBUG');
    if ($appDebugEnv !== false && $asBool($appDebugEnv)) {
        return true;
    }

    return false;
}

if (!isDebugDashboardEnabled()) {
    http_response_code(403);
    echo 'Debug dashboard is disabled.';
    exit;
}

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

if (!class_exists('TenantExecutionContext', false)) {
    require_once __DIR__ . '/../core/TenantExecutionContext.php';
}

function debugDashboardPdo(): PDO
{
    if (!defined('DB_HOST') || !defined('DB_USER')) {
        throw new RuntimeException('Database is not configured.');
    }

    $host = DB_HOST;
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = DB_USER;
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('DB_NAME') ? DB_NAME : '';
    if (defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== '') {
        $dbName = CONTROL_PANEL_DB_NAME;
    }
    if ($dbName === '') {
        throw new RuntimeException('Control database name is missing.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function ddExtractField(array $row, array $candidates): string
{
    foreach ($candidates as $key) {
        if (array_key_exists($key, $row) && (string) $row[$key] !== '') {
            return (string) $row[$key];
        }
    }
    return '';
}

function ddTrimText(string $text, int $max = 260): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if (strlen($text) > $max) {
        return substr($text, 0, $max) . '...';
    }
    return $text;
}

function ddParseReasonCode(string $text): string
{
    $up = strtoupper($text);
    if (strpos($up, 'ALLOWLIST') !== false) return 'ALLOWLIST';
    if (strpos($up, 'SYSTEM') !== false) return 'SYSTEM_BYPASS';
    if (strpos($up, 'BLOCK') !== false || strpos($up, 'VIOLATION') !== false) return 'STRICT_MODE';
    if (strpos($up, 'WARNING') !== false || strpos($up, 'WARN') !== false) return 'WARNING';
    return 'GENERAL_POLICY';
}

function ddParseDecision(string $text): string
{
    $up = strtoupper($text);
    if (strpos($up, 'BLOCK') !== false || strpos($up, 'VIOLATION') !== false) return 'blocked';
    if (strpos($up, 'WARN') !== false) return 'warned';
    if (strpos($up, 'ALLOWLIST') !== false || strpos($up, 'SYSTEM') !== false || strpos($up, 'ALLOWED') !== false) return 'allowed';
    return 'allowed';
}

function ddBadgeClassForDecision(string $decision, string $reasonCode): string
{
    if ($reasonCode === 'SYSTEM_BYPASS') return 'badge-blue';
    if ($decision === 'blocked') return 'badge-red';
    if ($decision === 'warned') return 'badge-yellow';
    return 'badge-green';
}

function ddLogSourceQuery(PDO $pdo, string $table, int $limit): array
{
    $sql = "SELECT id AS log_id,
                   created_at AS log_time,
                   level AS log_level,
                   event_type,
                   message,
                   metadata AS details,
                   tenant_id
            FROM system_events
            ORDER BY id DESC
            LIMIT " . (int) $limit;
    return $pdo->query($sql)->fetchAll();
}

$error = null;
$contextPanel = [
    'tenant_id' => TenantExecutionContext::getTenantId(),
    'system_context' => TenantExecutionContext::isSystemContext(),
    'is_locked' => TenantExecutionContext::isLocked(),
    'was_resolved_from_legacy' => TenantExecutionContext::wasResolvedFromLegacy(),
];
$systemState = [
    'tenant_strict_mode' => defined('TENANT_STRICT_MODE') ? (bool) TENANT_STRICT_MODE : false,
    'tenant_enforce_context_on_api' => defined('TENANT_ENFORCE_CONTEXT_ON_API') ? (bool) TENANT_ENFORCE_CONTEXT_ON_API : false,
    'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
];
$activitySource = 'system_events';
$gatewayLast = null;
$safetyEvents = [];
$systemLogRows = [];

try {
    $pdo = debugDashboardPdo();

    {
        $rows = ddLogSourceQuery($pdo, $activitySource, 120);
        $systemLogRows = array_slice($rows, 0, 20);

        foreach ($rows as $row) {
            $msg = ddExtractField($row, ['message']);
            $details = ddExtractField($row, ['details']);
            $combined = trim($msg . ' ' . $details);
            if ($combined === '') {
                continue;
            }

            $eventType = strtoupper((string) ($row['event_type'] ?? ''));
            $isGatewayPolicy = $eventType === 'QUERY_GATEWAY_POLICY' || strpos($combined, 'QUERY_GATEWAY_POLICY') !== false;
            $isSafety = str_contains($eventType, 'SAFETY')
                || str_contains($eventType, 'SECURITY')
                || strpos($combined, 'TENANT_QUERY_SAFETY_WARNING') !== false
                || strpos($combined, 'TENANT_QUERY_ALLOWLIST_BYPASS') !== false;

            if ($gatewayLast === null && $isGatewayPolicy) {
                $reason = ddParseReasonCode($combined);
                $decision = ddParseDecision($combined);
                $queryType = (stripos($combined, 'tenant_query') !== false) ? 'TenantQuery' : 'raw SQL';
                $gatewayLast = [
                    'query' => ddTrimText($combined, 420),
                    'query_type' => $queryType,
                    'decision' => $decision,
                    'reason_code' => $reason,
                    'badge' => ddBadgeClassForDecision($decision, $reason),
                ];
            }

            if ($isSafety && count($safetyEvents) < 20) {
                preg_match('/tenant_id=([0-9]+)/i', $combined, $mTid);
                preg_match('/endpoint=([^\s]+)/i', $combined, $mEndpoint);
                $reason = ddParseReasonCode($combined);
                $decision = ddParseDecision($combined);
                $safetyEvents[] = [
                    'tenant_id' => isset($mTid[1]) ? (int) $mTid[1] : 0,
                    'endpoint' => $mEndpoint[1] ?? '',
                    'query' => ddTrimText($combined, 320),
                    'reason' => $reason,
                    'decision' => $decision,
                    'badge' => ddBadgeClassForDecision($decision, $reason),
                ];
            }
        }
    }
} catch (Throwable $e) {
    $error = 'Unable to load debug dashboard data.';
    error_log('admin/debug-dashboard.php: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug Dashboard</title>
    <link rel="stylesheet" href="assets/css/debug-dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Internal Debug Dashboard</h1>
        <div class="actions">
            <label for="liveMode"><input id="liveMode" type="checkbox"> Live Mode (auto refresh every 5s)</label>
            <button class="btn primary" type="button" data-refresh-page>Refresh</button>
            <a class="btn" href="/admin/ops">Ops</a>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="section">
        <div class="section-head">Current Request Context Panel</div>
        <div class="section-body">
            <div class="cards">
                <div class="card"><div class="label">tenant_id</div><div class="value"><?php echo (int) ($contextPanel['tenant_id'] ?? 0); ?></div></div>
                <div class="card"><div class="label">system_context</div><div class="value"><?php echo $contextPanel['system_context'] ? 'true' : 'false'; ?></div></div>
                <div class="card"><div class="label">is_locked</div><div class="value"><?php echo $contextPanel['is_locked'] ? 'true' : 'false'; ?></div></div>
                <div class="card"><div class="label">was_resolved_from_legacy</div><div class="value"><?php echo $contextPanel['was_resolved_from_legacy'] ? 'true' : 'false'; ?></div></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">Query Gateway Activity (Last Request Evidence)</div>
        <div class="section-body">
            <?php if ($gatewayLast === null): ?>
                <p class="muted">No `QUERY_GATEWAY_POLICY` log evidence found in <?php echo htmlspecialchars((string) ($activitySource ?? 'available logs'), ENT_QUOTES, 'UTF-8'); ?>.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Query Type</th>
                        <th>Decision</th>
                        <th>Reason Code</th>
                        <th>Last Executed Query/Event</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($gatewayLast['query_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge <?php echo htmlspecialchars($gatewayLast['badge'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($gatewayLast['decision'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars($gatewayLast['reason_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="mono"><?php echo htmlspecialchars($gatewayLast['query'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-head">Tenant Safety Events (Last 20)</div>
        <div class="section-body">
            <?php if (empty($safetyEvents)): ?>
                <p class="muted">No tenant safety warnings/bypass events found.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Tenant ID</th>
                        <th>Endpoint</th>
                        <th>Decision</th>
                        <th>Reason</th>
                        <th>Query/Event</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($safetyEvents as $event): ?>
                        <tr>
                            <td><?php echo (int) ($event['tenant_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($event['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge <?php echo htmlspecialchars((string) ($event['badge'] ?? 'badge-yellow'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($event['decision'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($event['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="mono"><?php echo htmlspecialchars((string) ($event['query'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-head">System Events Viewer (<?php echo htmlspecialchars($activitySource, ENT_QUOTES, 'UTF-8'); ?>)</div>
        <div class="section-body">
            <?php if (empty($systemLogRows)): ?>
                <p class="muted">No data available.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Level</th>
                        <th>Tenant ID</th>
                        <th>Message</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($systemLogRows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['log_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['log_level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="mono"><?php echo htmlspecialchars(ddTrimText((string) (($row['message'] ?? '') . ' ' . ($row['details'] ?? '')), 260), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-head">Quick System State</div>
        <div class="section-body">
            <table>
                <tbody>
                <tr><th>TENANT_STRICT_MODE</th><td><?php echo $systemState['tenant_strict_mode'] ? 'true' : 'false'; ?></td></tr>
                <tr><th>TENANT_ENFORCE_CONTEXT_ON_API</th><td><?php echo $systemState['tenant_enforce_context_on_api'] ? 'true' : 'false'; ?></td></tr>
                <tr><th>Current Endpoint</th><td class="mono"><?php echo htmlspecialchars($systemState['request_uri'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>HTTP Method</th><td><?php echo htmlspecialchars($systemState['method'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="assets/js/admin-refresh.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/debug-dashboard-live.js?v=<?php echo time(); ?>"></script>
</body>
</html>

