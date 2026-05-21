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

if (function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()) {
    header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
    exit();
}
require_once __DIR__ . '/includes/ratib-public-base-url.php';
header('Location: ' . ratib_public_marketing_home_url());
exit(); 