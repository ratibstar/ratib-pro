<?php
/**
 * Session + permission gate for all /pages/client/* routes.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/bootstrap.php';

// Control-panel wrapper routes (client-hub.php, client-services.php, …) already require
// control login + dashboard permission in client-platform-bootstrap.php.
if (!empty($_SESSION['control_logged_in'])) {
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $isControlClientWrapper = (function_exists('ratib_client_dashboard_is_control_wrapper_active')
            && ratib_client_dashboard_is_control_wrapper_active())
        || preg_match('#/control-panel/pages/control/client-[^/]+\.php$#i', $script) === 1;
    if ($isControlClientWrapper) {
        return;
    }
}

ratib_client_dashboard_require_access();
