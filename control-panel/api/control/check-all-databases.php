<?php
/**
 * HTTP wrapper — check all Rateb databases (ERP, CP, RCC, Pro).
 * Requires Control Panel admin session.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Login to Control Panel first.');
}

header('Content-Type: text/plain; charset=utf-8');
require dirname(__DIR__, 3) . '/scripts/check-all-databases.php';
