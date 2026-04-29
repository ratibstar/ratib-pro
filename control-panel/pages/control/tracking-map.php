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

$canManageGov = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('manage_control_government')
    || hasControlPermission('gov_admin');

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Tracking Map', [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'css/control/tracking-map.css',
], []);
?>
<div id="tracking-map-page"
     data-can-manage="<?php echo $canManageGov ? '1' : '0'; ?>">
    <div class="tracking-toolbar">
        <div class="row g-2">
            <div class="col-md-2">
                <input class="form-control form-control-sm" id="trackingFilterTenant" type="number" placeholder="Tenant ID">
            </div>
            <div class="col-md-2">
                <input class="form-control form-control-sm" id="trackingFilterAgency" type="number" placeholder="Agency ID">
            </div>
            <div class="col-md-2">
                <input class="form-control form-control-sm" id="trackingFilterCountry" type="number" placeholder="Country ID">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="trackingFilterStatus">
                    <option value="">All session statuses</option>
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                    <option value="lost">lost</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-primary" id="trackingApplyFilters">Apply</button>
            </div>
            <div class="col-md-2">
                <input class="form-control form-control-sm" id="trackingFilterSearch" type="text" placeholder="Search worker/identity/device">
            </div>
            <div class="col-md-2">
                <label class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="trackingCriticalOnly">
                    <span class="form-check-label">Critical only</span>
                </label>
            </div>
            <div class="col-md-2">
                <label class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="trackingShowGeofences" checked>
                    <span class="form-check-label">Show Geofences</span>
                </label>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-2"><input class="form-control form-control-sm" id="playWorkerId" type="number" placeholder="Playback worker ID"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" id="playFrom" type="datetime-local"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" id="playTo" type="datetime-local"></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-light" id="playHistoryBtn">Playback route</button></div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-2"><input class="form-control form-control-sm" id="geoName" type="text" placeholder="Geofence name"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" id="geoCenterLat" type="number" step="0.000001" placeholder="Center lat"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" id="geoCenterLng" type="number" step="0.000001" placeholder="Center lng"></div>
            <div class="col-md-2"><input class="form-control form-control-sm" id="geoRadiusM" type="number" placeholder="Radius (m)"></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-success" id="createGeofenceBtn">Create geofence</button></div>
        </div>
    </div>

    <div id="trackingMapCanvas" class="tracking-map-canvas"></div>

    <div class="tracking-grid mt-3">
        <div class="card gov-card">
            <div class="card-body">
                <h5 class="card-title">Latest worker locations</h5>
                <div class="mb-2">
                    <span class="badge" id="trackingThreatBadge" style="background:#16a34a;color:#fff;">🔥 Threat Level: NORMAL</span>
                    <span class="badge ms-1" id="trackingResponseBadge" style="background:#6b7280;color:#fff;">⚡ Response State: NONE</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped gov-table" id="trackingLatestTable">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Identity</th>
                                <th>Tenant</th>
                                <th>Agency</th>
                                <th>Last seen</th>
                                <th>Battery</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card gov-card mt-3">
            <div class="card-body">
                <h5 class="card-title">Critical alerts (real-time)</h5>
                <ul id="trackingAlertsList" class="tracking-alert-list"></ul>
            </div>
        </div>
    </div>
    <div id="trackingFlash" class="alert d-none mt-2" role="alert"></div>
</div>
<?php
endControlLayout([
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'js/control/tracking-map.js',
]);
