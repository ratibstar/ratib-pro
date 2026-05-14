<?php
declare(strict_types=1);

if (function_exists('ratib_client_dashboard_is_control_wrapper_active') && ratib_client_dashboard_is_control_wrapper_active()) {
    require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-end.inc.php';
    echo '</div>';
    require_once dirname(__DIR__, 2) . '/control-panel/includes/control/layout-wrapper.php';
    endControlLayout($pageJs ?? []);
    return;
}

require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-end.inc.php';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
