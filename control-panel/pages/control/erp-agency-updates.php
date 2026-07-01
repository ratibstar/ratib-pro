<?php
declare(strict_types=1);
/**
 * Redirect — agency ERP push lives only on rateb.sa platform admin.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$target = function_exists('control_rateb_erp_public_url')
    ? control_rateb_erp_public_url('admin/agency-updates')
    : 'https://rateb.sa/rateb-erp/public/admin/agency-updates';
header('Location: ' . $target, true, 302);
exit;
