<?php
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../includes/control/country-program-scope.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
if (!control_country_profiles_can_edit($GLOBALS['control_conn'] ?? null)) {
    http_response_code(403);
    die('Access denied.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Country Profiles', ['css/control/government.css', 'css/control/country-profiles.css'], []);
?>
<div id="country-profiles-page">
    <div class="card gov-card mb-3 country-profiles-intro">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <p class="mb-0 country-profiles-lead">Each active country under Manage Countries gets its own profile card (slug = URL slug). Set labels and required fields per country; the generic “Default (fallback)” row applies when a worker’s country does not match.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light" id="countryProfilesExportBtn">Export JSON</button>
                    <button type="button" class="btn btn-sm btn-outline-light" id="countryProfilesImportBtn">Import JSON</button>
                    <input type="file" id="countryProfilesImportInput" class="d-none" accept="application/json,.json">
                </div>
            </div>
        </div>
    </div>
    <div id="countryProfilesWrap" class="d-grid gap-3"></div>
    <div id="countryProfilesFlash" class="alert d-none mt-3" role="alert"></div>
</div>
<?php endControlLayout(['js/control/country-profiles.js']); ?>

