<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/dashboard/index.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/dashboard/index.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ControlCenterAccess.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Control center access required.</p>';
    exit;
}

$target = (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/admin/control-center.php';
if ($target === '/admin/control-center.php') {
    $target = 'control-center.php';
}
header('Location: ' . $target, true, 302);
exit;

