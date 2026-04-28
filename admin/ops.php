<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/ops.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/ops.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/core/EventRepository.php';

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

function adminOpsPdo(): PDO
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

function badgeClass(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'active') return 'badge-active';
    if ($s === 'provisioning') return 'badge-provisioning';
    if ($s === 'failed') return 'badge-failed';
    if ($s === 'suspended') return 'badge-suspended';
    return 'badge-default';
}

$error = null;
$overview = [
    'total' => 0,
    'active' => 0,
    'failed' => 0,
    'provisioning' => 0,
];
$activityRows = [];
$activitySource = 'system_events';
$failedTenants = [];

try {
    $pdo = adminOpsPdo();

    $stmtCheck = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmtCheck->execute([':table' => 'tenants']);
    if (!(bool) $stmtCheck->fetchColumn()) {
        throw new RuntimeException('tenants table not found.');
    }

    $stmtOverview = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status = 'provisioning' THEN 1 ELSE 0 END) AS provisioning
         FROM tenants"
    );
    $o = $stmtOverview->fetch() ?: [];
    $overview['total'] = (int) ($o['total'] ?? 0);
    $overview['active'] = (int) ($o['active'] ?? 0);
    $overview['failed'] = (int) ($o['failed'] ?? 0);
    $overview['provisioning'] = (int) ($o['provisioning'] ?? 0);

    // Failed tenants base list
    $stmtFailed = $pdo->query(
        "SELECT id, name, domain, status, created_at
         FROM tenants
         WHERE status = 'failed'
         ORDER BY id DESC"
    );
    $failedTenants = $stmtFailed->fetchAll();

    $activityRows = EventRepository::latest([], 20);

    // Attach last error for failed tenants from activity logs when available.
    if (!empty($failedTenants) && $activitySource !== null && !empty($activityRows)) {
        foreach ($failedTenants as &$tenant) {
            $tid = (int) ($tenant['id'] ?? 0);
            $tenant['last_error'] = '';
            if ($tid <= 0) {
                continue;
            }
            foreach ($activityRows as $row) {
                $msg = strtolower((string) ($row['message'] ?? ''));
                $det = strtolower((string) ($row['metadata'] ?? ''));
                if ((int) ($row['tenant_id'] ?? 0) === $tid || strpos($msg, 'tenant_id=' . $tid) !== false || strpos($det, 'tenant_id=' . $tid) !== false) {
                    $tenant['last_error'] = (string) ($row['message'] ?? '');
                    if ($tenant['last_error'] === '') {
                        $tenant['last_error'] = (string) ($row['metadata'] ?? '');
                    }
                    break;
                }
            }
        }
        unset($tenant);
    }
} catch (Throwable $e) {
    $error = 'Unable to load operations dashboard.';
    error_log('admin/ops.php: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Ops</title>
    <link rel="stylesheet" href="assets/css/ops.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Ops Dashboard</h1>
        <div class="actions">
            <a class="btn" href="/admin/tenants">Tenants</a>
            <button class="btn primary" type="button" data-refresh-page>Refresh</button>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <div class="cards">
            <div class="card"><div class="label">Total Tenants</div><div class="value"><?php echo (int) $overview['total']; ?></div></div>
            <div class="card"><div class="label">Active Tenants</div><div class="value"><?php echo (int) $overview['active']; ?></div></div>
            <div class="card"><div class="label">Failed Tenants</div><div class="value"><?php echo (int) $overview['failed']; ?></div></div>
            <div class="card"><div class="label">Provisioning Tenants</div><div class="value"><?php echo (int) $overview['provisioning']; ?></div></div>
        </div>

        <div class="section">
            <div class="section-head">Recent Activity (<?php echo htmlspecialchars($activitySource, ENT_QUOTES, 'UTF-8'); ?>)</div>
            <div class="section-body">
                <?php if (empty($activityRows)): ?>
                    <p class="muted">No recent activity found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Message</th>
                            <th>Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activityRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['metadata'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-head">Failed Tenants</div>
            <div class="section-body">
                <?php if (empty($failedTenants)): ?>
                    <p class="muted">No failed tenants found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Last Error</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($failedTenants as $t): ?>
                            <tr>
                                <td><?php echo (int) ($t['id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($t['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php $st = (string) ($t['status'] ?? 'failed'); ?>
                                    <span class="badge <?php echo badgeClass($st); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($t['last_error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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

