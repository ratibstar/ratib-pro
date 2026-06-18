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

rateb_staff_page_require_session();
rateb_staff_require_partner_access();

header('Location: ' . rateb_nav_url('partner-agencies.php'), true, 302);
exit;
