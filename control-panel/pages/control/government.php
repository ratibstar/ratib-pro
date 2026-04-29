<?php
/**
 * Control Panel — Government Labor Monitoring (demo / integration-ready).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_GOVERNMENT, 'view_control_government', 'gov_admin');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

$canManageGov = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('manage_control_government')
    || hasControlPermission('gov_admin');

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
$additionalCSS = ['css/control/government.css'];
$additionalJS = ['js/control/government.js'];
startControlLayout('Government Control', $additionalCSS, []);
?>
<div id="gov-labor-page"
     data-can-manage="<?php echo $canManageGov ? '1' : '0'; ?>"
     data-page-url="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php'), ENT_QUOTES, 'UTF-8'); ?>">
    <p class="text-muted gov-intro">Labor monitoring simulation for government demonstration. Data is stored in the active agency database (same as workers).</p>
    <?php if ($canManageGov): ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-outline-warning" id="govSeedDemoBtn">
            <i class="fas fa-flask me-1"></i>Seed Indonesia demo data
        </button>
        <a class="btn btn-sm btn-outline-info" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-map-location-dot me-1"></i>Open Live Tracking Map
        </a>
        <a class="btn btn-sm btn-outline-light gov-readonly-link" href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/admin/government-tracking.php', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-landmark me-1"></i>Government Read-only View
        </a>
    </div>
    <?php endif; ?>

    <div class="stats-grid gov-summary-cards">
        <div class="stat-card">
            <div class="stat-icon stat-icon-agencies"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-content">
                <h3 id="govStatViolations">—</h3>
                <p>Total violations</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon stat-icon-pending"><i class="fas fa-ban"></i></div>
            <div class="stat-content">
                <h3 id="govStatBlacklist">—</h3>
                <p>Active blacklist</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-countries"><i class="fas fa-bell"></i></div>
            <div class="stat-content">
                <h3 id="govStatAlerts">—</h3>
                <p>Workers in alert</p>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs gov-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-insp" data-bs-toggle="tab" data-bs-target="#pane-insp" type="button" role="tab">Inspection</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-viol" data-bs-toggle="tab" data-bs-target="#pane-viol" type="button" role="tab">Violations</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-bl" data-bs-toggle="tab" data-bs-target="#pane-bl" type="button" role="tab">Blacklist</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-track" data-bs-toggle="tab" data-bs-target="#pane-track" type="button" role="tab">Worker monitoring</button>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>" role="tab">
                Tracking System
            </a>
        </li>
    </ul>

    <div class="tab-content gov-tab-panes">
        <div class="tab-pane fade show active" id="pane-insp" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Inspections</h5>
                    <div class="row g-2 mb-3 gov-form align-items-end">
                        <div class="col-md-3">
                            <label class="form-label visually-hidden" for="inspFilterCountry">Country filter</label>
                            <input type="text" class="form-control form-control-sm" id="inspFilterCountry" name="insp_filter_country" autocomplete="off" placeholder="Country filter">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label visually-hidden" for="inspFilterAgency">Agency ID</label>
                            <input type="number" class="form-control form-control-sm" id="inspFilterAgency" name="insp_filter_agency" placeholder="Agency ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label visually-hidden" for="inspFilterSearch">Search inspections</label>
                            <input type="text" class="form-control form-control-sm" id="inspFilterSearch" name="insp_filter_search" autocomplete="off" placeholder="Search worker/identity/inspector">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" id="inspApplyFilter">Apply</button>
                        </div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formInspection" class="row g-2 mb-3 gov-form align-items-end">
                        <div class="col-md-2"><input class="form-control form-control-sm" name="worker_id" type="number" required autocomplete="off" placeholder="Worker ID"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="agency_id" type="number" placeholder="Agency ID"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="inspector_name" required placeholder="Inspector"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="identity" placeholder="Identity"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="password" type="password" placeholder="Password"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="inspection_date" type="date" required></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="status">
                                <option value="pending">pending</option>
                                <option value="passed">passed</option>
                                <option value="failed">failed</option>
                            </select>
                        </div>
                        <div class="col-md-12"><input class="form-control form-control-sm" name="notes" placeholder="Notes"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Create inspection</button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableInspections">
                            <thead><tr><th>ID</th><th>Worker</th><th>Date</th><th>Inspector</th><th>Identity</th><th>Status</th><th>Agency</th><th>Notes</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-viol" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Violations</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="number" class="form-control form-control-sm" id="violFilterWorker" placeholder="Worker ID">
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="violFilterBtn">Filter by worker</button>
                        </div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formViolation" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2"><input class="form-control form-control-sm" name="worker_id" type="number" required placeholder="Worker ID"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="agency_id" type="number" placeholder="Agency ID"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="inspection_id" type="number" placeholder="Inspection ID"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="type" required placeholder="Type"></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="severity">
                                <option value="low">low</option>
                                <option value="medium" selected>medium</option>
                                <option value="high">high</option>
                            </select>
                        </div>
                        <div class="col-md-12"><input class="form-control form-control-sm" name="description" required placeholder="Description"></div>
                        <div class="col-md-12"><input class="form-control form-control-sm" name="action_taken" placeholder="Action taken"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Add violation</button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableViolations">
                            <thead><tr><th>ID</th><th>Worker</th><th>Type</th><th>Severity</th><th>Insp.</th><th>Created</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-bl" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Blacklist</h5>
                    <?php if ($canManageGov): ?>
                    <form id="formBlacklist" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="entity_type">
                                <option value="worker">worker</option>
                                <option value="agency">agency</option>
                            </select>
                        </div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="entity_id" type="number" required placeholder="Entity ID"></div>
                        <div class="col-md-6"><input class="form-control form-control-sm" name="reason" required placeholder="Reason"></div>
                        <div class="col-12"><button type="submit" class="btn btn-danger btn-sm">Add / refresh active blacklist</button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableBlacklist">
                            <thead><tr><th>ID</th><th>Type</th><th>ID</th><th>Status</th><th>Reason</th><th>Name</th><?php if ($canManageGov): ?><th></th><?php endif; ?></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-track" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Worker monitoring</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3"><input class="form-control form-control-sm" id="trackFilterCountry" placeholder="Country"></div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="trackFilterStatus">
                                <option value="">All statuses</option>
                                <option value="safe">safe</option>
                                <option value="warning">warning</option>
                                <option value="alert">alert</option>
                            </select>
                        </div>
                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-primary" id="trackApply">Apply</button></div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formTracking" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2"><input class="form-control form-control-sm" name="worker_id" type="number" required placeholder="Worker ID"></div>
                        <div class="col-md-3"><input class="form-control form-control-sm" name="last_checkin" type="datetime-local"></div>
                        <div class="col-md-3"><input class="form-control form-control-sm" name="location_text" placeholder="City / country"></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="status">
                                <option value="safe">safe</option>
                                <option value="warning">warning</option>
                                <option value="alert">alert</option>
                            </select>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Save check-in</button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableTracking">
                            <thead><tr><th>Worker</th><th>Country</th><th>Last seen</th><th>Location</th><th>Status</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="govFlash" class="alert d-none mt-2" role="alert"></div>
</div>
<?php
endControlLayout($additionalJS);
