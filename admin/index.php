<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/index.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/index.php`.
 */
/**
 * Central Super Admin Panel
 * Only super_admin can access. Manage all tenants.
 */
require_once __DIR__ . '/../includes/config.php';

// Require super_admin (works with or without multi-tenant)
if (!Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pdo = \Database::getInstance()->getConnection();
$tenants = $pdo->query("SELECT id, name, code, domain, status, subscription_plan, subscription_expiry FROM countries ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Tenants</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1>Central Admin - Tenants</h1>
    <p class="text-muted">Super Admin: <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
    <table class="table table-striped">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Code</th><th>Domain</th><th>Status</th><th>Subscription</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($tenants as $t): ?>
            <tr>
                <td><?php echo (int)$t['id']; ?></td>
                <td><?php echo htmlspecialchars($t['name']); ?></td>
                <td><?php echo htmlspecialchars($t['code']); ?></td>
                <td><?php echo htmlspecialchars($t['domain']); ?></td>
                <td><span class="badge bg-<?php echo $t['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($t['status']); ?></span></td>
                <td><?php echo htmlspecialchars($t['subscription_expiry'] ?? '-'); ?></td>
                <td>
                    <a href="https://<?php echo htmlspecialchars($t['domain']); ?>/pages/dashboard.php" target="_blank" class="btn btn-sm btn-primary">Open</a>
                    <a href="switch-tenant.php?tenant_id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-secondary">Switch</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="<?php echo pageUrl('dashboard.php'); ?>">Back to Dashboard</a></p>
</div>
</body>
</html>
