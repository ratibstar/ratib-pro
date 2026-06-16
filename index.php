<?php
/**
 * EN: Handles application behavior in `index.php`.
 * AR: يدير سلوك جزء من التطبيق في `index.php`.
 */
/**
 * Main Entry Point - RATEB
 * Redirects to login page or dashboard if already logged in
 */
require_once 'includes/config.php';

if (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user()) {
    header('Location: ' . rateb_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
    exit();
}
require_once __DIR__ . '/includes/rateb-public-base-url.php';
header('Location: ' . rateb_public_marketing_home_url());
exit(); 