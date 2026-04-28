<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/tenant.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/tenant.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/core/EventRepository.php';

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

function adminTenantControlPdo(): PDO
{
    if (!defined('DB_HOST') || !defined('DB_USER')) {
        throw new RuntimeException('Database is not configured.');
    }

    $host = DB_HOST;
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = DB_USER;
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('DB_NAME') ? DB_NAME : '';

    if (
        defined('CONTROL_PANEL_DB_NAME') &&
        CONTROL_PANEL_DB_NAME !== '' &&
        CONTROL_PANEL_DB_NAME !== $dbName
    ) {
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

function statusClass(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'active') {
        return 'badge-active';
    }
    if ($s === 'provisioning') {
        return 'badge-provisioning';
    }
    if ($s === 'failed') {
        return 'badge-failed';
    }
    if ($s === 'suspended') {
        return 'badge-suspended';
    }
    return 'badge-default';
}

$tenantId = (int) ($_GET['id'] ?? 0);
$tenant = null;
$error = null;
$dbHealth = ['ok' => false, 'message' => 'Not tested'];
$domainHealth = ['ok' => false, 'message' => 'Not tested', 'ip' => null];
$logs = [];
$logsSource = 'system_events';

if ($tenantId <= 0) {
    $error = 'Invalid tenant id.';
} else {
    try {
        $controlPdo = adminTenantControlPdo();

        $stmt = $controlPdo->prepare(
            "SELECT id, name, domain, status, database_name, db_host, db_user, db_password, created_at
             FROM tenants
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $error = 'Tenant not found.';
        } else {
            // Health check 1: tenant DB connectivity
            try {
                $dbHost = trim((string) ($tenant['db_host'] ?? ''));
                if ($dbHost === '') {
                    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
                }
                $dbName = trim((string) ($tenant['database_name'] ?? ''));
                $dbUser = trim((string) ($tenant['db_user'] ?? ''));
                $dbPass = (string) ($tenant['db_password'] ?? '');
                $dbPort = defined('DB_PORT') ? (int) DB_PORT : 3306;

                if ($dbName === '' || $dbUser === '') {
                    throw new RuntimeException('Tenant DB credentials are incomplete.');
                }

                $tenantDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
                $tenantPdo = new PDO($tenantDsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $probe = $tenantPdo->query('SELECT 1')->fetchColumn();
                if ((string) $probe !== '1') {
                    throw new RuntimeException('SELECT 1 returned unexpected result.');
                }
                $dbHealth = ['ok' => true, 'message' => 'Connection OK (SELECT 1 passed)'];
            } catch (Throwable $e) {
                $dbHealth = ['ok' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
            }

            // Health check 2: basic domain resolution
            $domain = strtolower(trim((string) ($tenant['domain'] ?? '')));
            if ($domain === '') {
                $domainHealth = ['ok' => false, 'message' => 'Domain is empty', 'ip' => null];
            } else {
                $resolvedIp = gethostbyname($domain);
                if ($resolvedIp === $domain) {
                    $domainHealth = ['ok' => false, 'message' => 'Domain does not resolve', 'ip' => null];
                } else {
                    $domainHealth = ['ok' => true, 'message' => 'Domain resolves', 'ip' => $resolvedIp];
                }
            }

            $logs = EventRepository::latest(['tenant_id' => $tenantId], 20);
        }
    } catch (Throwable $e) {
        $error = 'Failed to load tenant details.';
        error_log('admin/tenant.php: ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Details</title>
    <link rel="stylesheet" href="assets/css/tenant.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Tenant Details</h1>
            <div class="actions">
                <a class="btn" href="/admin/tenants">Back to Tenants</a>
                <button class="btn primary" type="button" data-refresh-page>Refresh</button>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif ($tenant !== null): ?>
            <div class="card">
                <div class="grid">
                    <div class="row"><strong>ID:</strong> <?php echo (int) ($tenant['id'] ?? 0); ?></div>
                    <div class="row"><strong>Name:</strong> <?php echo htmlspecialchars((string) ($tenant['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="row"><strong>Domain:</strong> <?php echo htmlspecialchars((string) ($tenant['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="row">
                        <strong>Status:</strong>
                        <?php $st = (string) ($tenant['status'] ?? 'unknown'); ?>
                        <span class="badge <?php echo statusClass($st); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="row"><strong>Database:</strong> <?php echo htmlspecialchars((string) ($tenant['database_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="row"><strong>Created At:</strong> <?php echo htmlspecialchars((string) ($tenant['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <div class="card">
                <h3>Health Checks</h3>
                <p>
                    <strong>DB Connection:</strong>
                    <span class="<?php echo $dbHealth['ok'] ? 'health-ok' : 'health-fail'; ?>">
                        <?php echo $dbHealth['ok'] ? 'OK' : 'FAILED'; ?>
                    </span>
                    <span class="muted">- <?php echo htmlspecialchars((string) $dbHealth['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                </p>
                <p>
                    <strong>Domain Resolution:</strong>
                    <span class="<?php echo $domainHealth['ok'] ? 'health-ok' : 'health-fail'; ?>">
                        <?php echo $domainHealth['ok'] ? 'OK' : 'FAILED'; ?>
                    </span>
                    <span class="muted">
                        - <?php echo htmlspecialchars((string) $domainHealth['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($domainHealth['ip'])): ?>
                            (<?php echo htmlspecialchars((string) $domainHealth['ip'], ENT_QUOTES, 'UTF-8'); ?>)
                        <?php endif; ?>
                    </span>
                </p>
            </div>

            <div class="card">
                <h3>Recent Events (<?php echo htmlspecialchars($logsSource, ENT_QUOTES, 'UTF-8'); ?>)</h3>
                <?php if (empty($logs)): ?>
                    <p class="muted">No recent events matched this tenant.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach (array_keys($logs[0]) as $col): ?>
                                    <th><?php echo htmlspecialchars((string) $col, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<script src="assets/js/admin-refresh.js?v=<?php echo time(); ?>"></script>
</body>
</html>

