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

$ratibCpSectionKey = $RCP_SECTION;
$ratibCpPageHeading = $RCP_HEADING;
$ratibCpPageSubheading = $RCP_SUBHEADING;

$pageTitle = 'Client Hub · ' . $RCP_HEADING;
$extraBodyClasses = ['ratib-client-dashboard'];
$pageCss = [ratib_client_dashboard_asset_url('css/client-dashboard.css')];

$pageJs = array_merge([
    ratib_client_dashboard_asset_url('js/client-dashboard-shell.js'),
    ratib_client_dashboard_asset_url('js/client-dashboard-data.js'),
    ratib_client_dashboard_asset_url('js/client-dashboard-actions.js'),
], $RCP_EXTRA_JS);

if (function_exists('ratib_client_dashboard_is_control_wrapper_active') && ratib_client_dashboard_is_control_wrapper_active()) {
    require_once dirname(__DIR__, 2) . '/control-panel/includes/control/layout-wrapper.php';
    require_once dirname(__DIR__, 2) . '/control-panel/includes/control/client-platform-nav.php';
    startControlLayout($pageTitle, $pageCss);
    echo '<div class="ratib-client-dashboard-surface">';
    require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-start.inc.php';
    $clientPlatformTabKey = function_exists('control_client_platform_section_to_key')
        ? control_client_platform_section_to_key((string) $RCP_SECTION)
        : null;
    echo '<div class="client-platform-tabs-bar">';
    echo control_render_client_platform_tabs($clientPlatformTabKey);
    echo '</div>';
    return;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-start.inc.php';
