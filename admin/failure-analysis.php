<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/failure-analysis.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/failure-analysis.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

function failureAnalysisPdo(): PDO
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

function faClassifyStep(string $text): string
{
    $t = strtolower($text);
    if (strpos($t, 'domain_validation') !== false || strpos($t, 'domain') !== false) {
        return 'domain_validation';
    }
    if (strpos($t, 'db_created') !== false || strpos($t, 'rollback_db') !== false || strpos($t, 'database') !== false || strpos($t, 'connect failed') !== false) {
        return 'db_creation';
    }
    if (strpos($t, 'tenant_activated') !== false || strpos($t, 'activation') !== false || strpos($t, 'status = \'active\'') !== false) {
        return 'activation';
    }
    return 'other';
}

function faClassifyReason(string $text): string
{
    $t = strtolower($text);
    $rules = [
        'Domain does not resolve' => ['domain does not resolve', 'invalid domain', 'domain validation failed'],
        'Domain resolves to wrong target' => ['different target', 'domain resolves to different'],
        'Database creation failed' => ['create database', 'db_created', 'database connection failed', 'connect failed'],
        'Tenant rollback triggered' => ['rollback', 'rollback_db', 'rollback_tenant'],
        'Domain already exists' => ['domain already exists', 'duplicate', 'already exists'],
        'Tenant DB credentials incomplete' => ['credentials are incomplete', 'db_user', 'db_password'],
        'Unknown runtime error' => ['failed', 'error', 'exception'],
    ];

    foreach ($rules as $label => $needles) {
        foreach ($needles as $needle) {
            if (strpos($t, $needle) !== false) {
                return $label;
            }
        }
    }
    return 'Unclassified';
}

function faExtractTenantId(string $text): ?int
{
    if (preg_match('/tenant_id=(\d+)/i', $text, $m)) {
        return (int)$m[1];
    }
    return null;
}

$error = null;
$logsSource = 'system_events';
$totalFailures = 0;
$stepStats = [];
$reasonStats = [];
$topReasons = [];

try {
    $pdo = failureAnalysisPdo();

    // Read a reasonable window and analyze in PHP.
    $sql = "SELECT id AS log_id,
                   created_at AS log_time,
                   event_type,
                   message,
                   metadata AS details,
                   tenant_id
            FROM system_events
            ORDER BY id DESC
            LIMIT 1200";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $row) {
        $msg = (string)($row['message'] ?? '');
        $det = (string)($row['details'] ?? '');
        $blob = trim($msg . ' ' . $det);
        $blobLower = strtolower($blob);

        // Only analyze failure-like entries to avoid noisy stats.
        $isFailure = (
            strpos($blobLower, 'failed') !== false ||
            strpos($blobLower, 'error') !== false ||
            strpos($blobLower, 'exception') !== false ||
            strpos($blobLower, 'rollback') !== false
        );
        if (!$isFailure) {
            continue;
        }

        $totalFailures++;
        $step = faClassifyStep($blob);
        $reason = faClassifyReason($blob);
        $tenantId = faExtractTenantId($blob);

        if (!isset($stepStats[$step])) {
            $stepStats[$step] = 0;
        }
        $stepStats[$step]++;

        if (!isset($reasonStats[$reason])) {
            $reasonStats[$reason] = [
                'count' => 0,
                'tenants' => [],
            ];
        }
        $reasonStats[$reason]['count']++;
        if ($tenantId !== null && $tenantId > 0) {
            $reasonStats[$reason]['tenants'][$tenantId] = true;
        }
    }

    // Build top-5 reasons
    uasort($reasonStats, static function (array $a, array $b): int {
        return ($b['count'] <=> $a['count']);
    });

    $i = 0;
    foreach ($reasonStats as $reason => $stat) {
        if ($i >= 5) {
            break;
        }
        $cnt = (int)$stat['count'];
        $affectedTenantCount = count($stat['tenants']);
        $pct = $totalFailures > 0 ? round(($cnt / $totalFailures) * 100, 2) : 0.0;
        $topReasons[] = [
            'reason' => $reason,
            'count' => $cnt,
            'tenant_count' => $affectedTenantCount,
            'percentage' => $pct,
        ];
        $i++;
    }
} catch (Throwable $e) {
    $error = 'Unable to analyze failures.';
    error_log('admin/failure-analysis.php: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Failure Analysis</title>
    <link rel="stylesheet" href="assets/css/failure-analysis.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Failure Analysis</h1>
        <div class="actions">
            <a class="btn" href="/admin/ops.php">Ops</a>
            <button class="btn primary" type="button" data-refresh-page>Refresh</button>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <div class="summary">
            Source: <strong><?php echo htmlspecialchars((string)$logsSource, ENT_QUOTES, 'UTF-8'); ?></strong>
            | Total analyzed failures: <strong><?php echo (int)$totalFailures; ?></strong>
        </div>

        <div class="section">
            <div class="section-head">Top 5 Failure Reasons</div>
            <div class="section-body">
                <?php if (empty($topReasons)): ?>
                    <p class="muted">No failure patterns detected in recent logs.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Reason</th>
                            <th class="num">Failures</th>
                            <th class="num">Affected Tenants</th>
                            <th class="num">Percentage</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topReasons as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo (int)$row['count']; ?></td>
                                <td class="num"><?php echo (int)$row['tenant_count']; ?></td>
                                <td class="num"><?php echo number_format((float)$row['percentage'], 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-head">Failure Distribution by Step</div>
            <div class="section-body">
                <?php if (empty($stepStats)): ?>
                    <p class="muted">No step-level failure data found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Step</th>
                            <th class="num">Failures</th>
                            <th class="num">Percentage</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stepStats as $step => $count): ?>
                            <?php $pct = $totalFailures > 0 ? round(($count / $totalFailures) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$step, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo (int)$count; ?></td>
                                <td class="num"><?php echo number_format((float)$pct, 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="assets/js/admin-refresh.js?v=<?php echo time(); ?>"></script>
</body>
</html>

