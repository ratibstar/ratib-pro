<?php
/**
 * Opens a client platform page: requires $RCP_SECTION, $RCP_HEADING, optional $RCP_SUBHEADING, $RCP_EXTRA_JS.
 */
declare(strict_types=1);

if (!isset($RCP_SECTION)) {
    throw new RuntimeException('client page bootstrap: missing $RCP_SECTION');
}
if (!isset($RCP_HEADING)) {
    throw new RuntimeException('client page bootstrap: missing $RCP_HEADING');
}

if (!isset($RCP_SUBHEADING)) {
    $RCP_SUBHEADING = '';
}
if (!isset($RCP_EXTRA_JS) || !is_array($RCP_EXTRA_JS)) {
    $RCP_EXTRA_JS = [];
}

$ratebCpSectionKey = $RCP_SECTION;
$ratebCpPageHeading = $RCP_HEADING;
$ratebCpPageSubheading = $RCP_SUBHEADING;

$pageTitle = 'Client Hub · ' . $RCP_HEADING;
$extraBodyClasses = ['rateb-client-dashboard'];
$pageCss = [rateb_client_dashboard_asset_url('css/client-dashboard.css')];

$pageJs = array_merge([
    rateb_client_dashboard_asset_url('js/client-dashboard-shell.js'),
    rateb_client_dashboard_asset_url('js/client-dashboard-data.js'),
    rateb_client_dashboard_asset_url('js/client-dashboard-actions.js'),
], $RCP_EXTRA_JS);

if (function_exists('rateb_client_dashboard_is_control_wrapper_active') && rateb_client_dashboard_is_control_wrapper_active()) {
    require_once dirname(__DIR__, 2) . '/control-panel/includes/control/layout-wrapper.php';
    startControlLayout($pageTitle, $pageCss);
    echo '<div class="rateb-client-dashboard-surface">';
    require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-start.inc.php';
    return;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-start.inc.php';
