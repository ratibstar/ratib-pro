<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/tenants.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/tenants.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

function adminTenantsPdo(): PDO
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
        throw new RuntimeException('Database name is missing.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$tenants = [];
$error = null;
try {
    $pdo = adminTenantsPdo();
    $stmt = $pdo->query(
        "SELECT id, name, domain, status, created_at
         FROM tenants
         ORDER BY id DESC"
    );
    $tenants = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Unable to load tenants list.';
    error_log('admin/tenants.php: ' . $e->getMessage());
}

function tenantStatusClass(string $status): string
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
    return 'badge-default';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Tenants</title>
    <link rel="stylesheet" href="assets/css/tenants.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Tenants</h1>
            <div class="actions">
                <button class="btn" type="button" data-refresh-page>Refresh</button>
                <a class="btn primary" href="/admin/create-tenant">Create Tenant</a>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($tenants)): ?>
                <div class="empty">No tenants found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td><?php echo (int) ($tenant['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($tenant['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($tenant['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php $status = (string) ($tenant['status'] ?? 'unknown'); ?>
                                <span class="badge <?php echo tenantStatusClass($status); ?>">
                                    <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($tenant['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<script src="assets/js/admin-refresh.js?v=<?php echo time(); ?>"></script>
</body>
</html>

