<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/register-pro.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/register-pro.php`.
 */
/**
 * Short link for Pro agency registration — redirects to standalone register-agency page.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';
header('Location: ' . rateb_public_agency_register_url('', 'pro', 1), true, 302);
exit;
