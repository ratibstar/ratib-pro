<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/create-tenant.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/create-tenant.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!class_exists('Auth') || !Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Tenant</title>
    <link rel="stylesheet" href="assets/css/create-tenant.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <h1>Create Tenant</h1>
        <div class="card">
            <form id="tenantForm">
                <div class="field">
                    <label for="agency_name">Agency Name</label>
                    <input id="agency_name" name="agency_name" type="text" required placeholder="Example Agency Ltd">
                </div>
                <div class="field">
                    <label for="domain">Domain</label>
                    <input id="domain" name="domain" type="text" required placeholder="example.yourdomain.com">
                </div>
                <button id="submitBtn" type="submit">Create Tenant</button>
                <div class="hint">This will call <code>/api/tenants/create-full.php</code>.</div>
            </form>

            <div id="message" class="msg"></div>
            <div id="result" class="result">
                <div><strong>Tenant ID:</strong> <span id="outTenantId">-</span></div>
                <div><strong>Domain:</strong> <span id="outDomain">-</span></div>
                <div><strong>Status:</strong> <span id="outStatus">-</span></div>
            </div>
        </div>
    </div>

    <script src="assets/js/create-tenant.js?v=<?php echo time(); ?>"></script>
</body>
</html>

