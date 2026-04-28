<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/tenant-test.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/tenant-test.php`.
 */
/**
 * Multi-Tenant Test Page
 * Visit this page to verify tenant detection is working.
 * DELETE this file in production for security.
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Tenant Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 1rem; }
        .ok { color: #0a0; }
        .info { background: #f0f8ff; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Multi-Tenant Test</h1>
    
    <div class="info">
        <strong>Host:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? '-'); ?>
    </div>

    <table>
        <tr><th>Constant</th><th>Value</th></tr>
        <tr>
            <td>COUNTRY_ID</td>
            <td><?php echo defined('COUNTRY_ID') ? (int)COUNTRY_ID : '<span class="ok">0 (multi-tenant off or control panel)</span>'; ?></td>
        </tr>
        <tr>
            <td>COUNTRY_CODE</td>
            <td><?php echo defined('COUNTRY_CODE') ? htmlspecialchars(COUNTRY_CODE) : '-'; ?></td>
        </tr>
        <tr>
            <td>COUNTRY_NAME</td>
            <td><?php echo defined('COUNTRY_NAME') ? htmlspecialchars(COUNTRY_NAME) : '-'; ?></td>
        </tr>
        <tr>
            <td>MULTI_TENANT_ENABLED</td>
            <td><?php echo defined('MULTI_TENANT_ENABLED') && MULTI_TENANT_ENABLED ? '<span class="ok">Yes</span>' : 'No'; ?></td>
        </tr>
        <tr>
            <td>Session country_id</td>
            <td><?php echo isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : '-'; ?></td>
        </tr>
    </table>

    <?php if (defined('COUNTRY_ID') && COUNTRY_ID > 0): ?>
    <p class="ok"><strong>✓ Multi-tenant is active.</strong> Current country: <?php echo htmlspecialchars(COUNTRY_NAME ?? ''); ?> (<?php echo htmlspecialchars(COUNTRY_CODE ?? ''); ?>)</p>
    <?php else: ?>
    <p>Multi-tenant not active or country not detected for this host.</p>
    <?php endif; ?>

    <p><small>Delete this file (pages/tenant-test.php) in production.</small></p>
</body>
</html>
