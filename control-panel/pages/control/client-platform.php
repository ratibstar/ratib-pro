<?php
/**
 * Backward-compatible redirect for old query-based Client Platform links.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/client-platform-nav.php';

$section = strtolower(trim((string) ($_GET['section'] ?? 'hub')));
$extra = [];
if (!empty($_GET['catalog'])) {
    $extra['catalog'] = (string) $_GET['catalog'];
}

$target = control_client_platform_wrapper_url($section, http_build_query($extra));
header('Location: ' . $target, true, 302);
exit;
