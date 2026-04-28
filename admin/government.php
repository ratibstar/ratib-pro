<?php
/**
 * Pretty URL entry for Government Control → control panel module.
 */
require_once __DIR__ . '/../control-panel/includes/config.php';

if (!function_exists('control_panel_page_with_control')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Configuration error.';
    exit;
}

header('Location: ' . control_panel_page_with_control('control/government.php'), true, 302);
exit;
