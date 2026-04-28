<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/switch-tenant.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/switch-tenant.php`.
 */
/**
 * Super Admin: Switch tenant context (allow_cross_tenant)
 */
require_once __DIR__ . '/../includes/config.php';

if (!Auth::isSuperAdmin()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$tenantId = (int)($_GET['tenant_id'] ?? 0);
if ($tenantId > 0) {
    $_SESSION['tenant_override_id'] = $tenantId;
}
$defaultReturn = ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0));
header('Location: ' . ($_GET['return'] ?? $defaultReturn));
exit;
