<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/deployments.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/deployments.php`.
 */
/**
 * Deployments are managed per agency: Partner Agencies → row "View" opens the deployment table.
 * This URL remains valid for bookmarks and redirects to Partner Agencies.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
if (!hasPermission('view_partner_agencies') && !hasPermission('view_workers')) {
    header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
    exit;
}

header('Location: ' . ratib_nav_url('partner-agencies.php'), true, 302);
exit;
