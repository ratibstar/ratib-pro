<?php
/**
 * Legacy URL: CV sharing is now done from Workers (bulk) → Partner Agencies.
 */
require_once __DIR__ . '/../includes/config.php';
if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('partner-agencies.php'));
exit;
