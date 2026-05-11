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

require_once dirname(__DIR__, 2) . '/includes/header.php';
require dirname(__DIR__, 2) . '/modules/client-dashboard/Layout/shell-start.inc.php';
