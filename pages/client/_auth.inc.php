<?php
/**
 * Session + permission gate for all /pages/client/* routes.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/bootstrap.php';

ratib_client_dashboard_require_access();
