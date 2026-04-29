<?php
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
requireControlPermission(CONTROL_PERM_GOVERNMENT, 'view_control_government', 'gov_admin', CONTROL_PERM_ADMINS);

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Tracking Health', ['css/control/tracking-health.css'], []);
?>
<div id="tracking-health-page">
    <div class="card gov-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-1">Tracking Health</h5>
                    <p class="text-muted mb-0">Live operational status for onboarding, sessions, devices, and alerts.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="trackingHealthRefreshBtn">Refresh</button>
            </div>
        </div>
    </div>

    <div class="tracking-health-grid mb-3" id="trackingHealthStats"></div>

    <div class="card gov-card">
        <div class="card-body">
            <h6 class="card-title">Latest worker session status</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped gov-table" id="trackingHealthTable">
                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Identity</th>
                            <th>Device</th>
                            <th>Tenant</th>
                            <th>Agency</th>
                            <th>Last seen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="trackingHealthFlash" class="alert d-none mt-2" role="alert"></div>
</div>
<?php
endControlLayout(['js/control/tracking-health.js']);
