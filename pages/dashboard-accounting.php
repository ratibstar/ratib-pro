<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/dashboard-accounting.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/dashboard-accounting.php`.
 */
/**
 * Legacy route: embedded accounting shell was never completed (missing JS/API wiring).
 * Send users to the main Professional Accounting page with the same query flags (e.g. control=1, agency_id).
 */
require_once __DIR__ . '/../includes/config.php';

rateb_staff_page_require_session();

$params = $_GET;
unset($params['embedded']);

$dest = pageUrl('accounting.php');
$query = http_build_query($params);
if ($query !== '') {
    $dest .= '?' . $query;
}
header('Location: ' . $dest, true, 302);
exit;
