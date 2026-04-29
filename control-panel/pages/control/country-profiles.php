<?php
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings', 'edit_control_system_settings', 'manage_control_roles');

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Country Profiles', [], []);
?>
<div id="country-profiles-page">
    <div class="card gov-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="card-title mb-1">Country Profiles</h5>
                    <p class="text-muted mb-0">Edit per-country worker labels and required fields without code changes.</p>
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

