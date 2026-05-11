<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../modules/client-dashboard/bootstrap.php';
if (ratib_client_dashboard_can_access()) {
    header('Location: ' . pageUrl('client/dashboard.php'));
    exit;
}
if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('profile.php'));
exit;
