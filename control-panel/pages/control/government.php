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
    die(cp_t('common.db_unavailable'));
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
    <p class="text-muted gov-intro"><?php echo htmlspecialchars(cp_t('gov.intro'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($canManageGov): ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-outline-warning" id="govSeedDemoBtn">
            <i class="fas fa-flask me-1"></i><?php echo htmlspecialchars(cp_t('gov.seed_demo'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <a class="btn btn-sm btn-outline-info" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-map-location-dot me-1"></i><?php echo htmlspecialchars(cp_t('gov.open_tracking_map'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <a class="btn btn-sm btn-outline-light gov-readonly-link" href="<?php echo htmlspecialchars((defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/admin/government-tracking.php', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-landmark me-1"></i><?php echo htmlspecialchars(cp_t('gov.readonly_view'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="stats-grid gov-summary-cards">
        <div class="stat-card">
            <div class="stat-icon stat-icon-agencies"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-content">
                <h3 id="govStatViolations">—</h3>
                <p><?php echo htmlspecialchars(cp_t('gov.total_violations'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon stat-icon-pending"><i class="fas fa-ban"></i></div>
            <div class="stat-content">
                <h3 id="govStatBlacklist">—</h3>
                <p><?php echo htmlspecialchars(cp_t('gov.active_blacklist'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-countries"><i class="fas fa-bell"></i></div>
            <div class="stat-content">
                <h3 id="govStatAlerts">—</h3>
                <p><?php echo htmlspecialchars(cp_t('gov.workers_in_alert'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs gov-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-insp" data-bs-toggle="tab" data-bs-target="#pane-insp" type="button" role="tab"><?php echo htmlspecialchars(cp_t('gov.tab_inspection'), ENT_QUOTES, 'UTF-8'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-viol" data-bs-toggle="tab" data-bs-target="#pane-viol" type="button" role="tab"><?php echo htmlspecialchars(cp_t('gov.tab_violations'), ENT_QUOTES, 'UTF-8'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-bl" data-bs-toggle="tab" data-bs-target="#pane-bl" type="button" role="tab"><?php echo htmlspecialchars(cp_t('gov.tab_blacklist'), ENT_QUOTES, 'UTF-8'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-track" data-bs-toggle="tab" data-bs-target="#pane-track" type="button" role="tab"><?php echo htmlspecialchars(cp_t('gov.tab_worker_monitoring'), ENT_QUOTES, 'UTF-8'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php'), ENT_QUOTES, 'UTF-8'); ?>" role="tab">
                <?php echo htmlspecialchars(cp_t('gov.tab_tracking_system'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </li>
    </ul>

    <div class="tab-content gov-tab-panes">
        <div class="tab-pane fade show active" id="pane-insp" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars(cp_t('gov.inspections'), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <div class="row g-2 mb-3 gov-form align-items-end">
                        <div class="col-md-3">
                            <label class="form-label visually-hidden" for="inspFilterCountry"><?php echo htmlspecialchars(cp_t('gov.country_filter'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="text" class="form-control form-control-sm" id="inspFilterCountry" name="insp_filter_country" autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.country_filter'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label visually-hidden" for="inspFilterAgency"><?php echo htmlspecialchars(cp_t('gov.agency_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="number" class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" id="inspFilterAgency" name="insp_filter_agency" placeholder="<?php echo htmlspecialchars(cp_t('gov.agency_id'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label visually-hidden" for="inspFilterSearch"><?php echo htmlspecialchars(cp_t('gov.search_insp'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="text" class="form-control form-control-sm" id="inspFilterSearch" name="insp_filter_search" autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.search_insp'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" id="inspApplyFilter"><?php echo htmlspecialchars(cp_t('gov.apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formInspection" class="mb-3 gov-form">
                        <div class="small text-uppercase text-muted mb-1"><?php echo htmlspecialchars(cp_t('gov.insp_target'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="inspWorkerId"><?php echo htmlspecialchars(cp_t('gov.worker_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspWorkerId" class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="worker_id" type="number" required autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.worker_id'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="inspAgencyId"><?php echo htmlspecialchars(cp_t('gov.agency_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspAgencyId" class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="agency_id" type="number" placeholder="<?php echo htmlspecialchars(cp_t('gov.optional'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="inspInspectorName"><?php echo htmlspecialchars(cp_t('gov.inspector'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspInspectorName" class="form-control form-control-sm" name="inspector_name" required autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.inspector_name'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="inspDate"><?php echo htmlspecialchars(cp_t('gov.inspection_date'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspDate" class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="inspection_date" type="date" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="inspStatus"><?php echo htmlspecialchars(cp_t('gov.status'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select id="inspStatus" class="form-select form-select-sm" name="status">
                                    <option value="pending"><?php echo htmlspecialchars(cp_t('gov.pending'), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="passed"><?php echo htmlspecialchars(cp_t('gov.passed'), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="failed"><?php echo htmlspecialchars(cp_t('gov.failed'), ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="small text-uppercase text-muted mb-1"><?php echo htmlspecialchars(cp_t('gov.insp_identity_section'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <p class="small text-muted mb-2"><?php echo htmlspecialchars(cp_t('gov.identity_hint'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="inspIdentity"><?php echo htmlspecialchars(cp_t('gov.identity'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspIdentity" class="form-control form-control-sm" name="identity" type="text" autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.identity_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0" for="inspPassword"><?php echo htmlspecialchars(cp_t('gov.password'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspPassword" class="form-control form-control-sm" name="password" type="password" autocomplete="new-password" placeholder="<?php echo htmlspecialchars(cp_t('gov.password_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-12">
                                <label class="form-label small mb-0" for="inspNotes"><?php echo htmlspecialchars(cp_t('gov.notes'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input id="inspNotes" class="form-control form-control-sm" name="notes" autocomplete="off" placeholder="<?php echo htmlspecialchars(cp_t('gov.notes'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 mt-1"><button type="submit" class="btn btn-primary btn-sm"><?php echo htmlspecialchars(cp_t('gov.create_inspection'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                        </div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableInspections">
                            <thead><tr>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_worker'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_date'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_inspector'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_identity'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_password_saved'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.status'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_agency'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.notes'), ENT_QUOTES, 'UTF-8'); ?></th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-viol" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars(cp_t('gov.violations'), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="number" class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" id="violFilterWorker" placeholder="<?php echo htmlspecialchars(cp_t('gov.worker_id'), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="violFilterBtn"><?php echo htmlspecialchars(cp_t('gov.filter_by_worker'), ENT_QUOTES, 'UTF-8'); ?></button>
                        </div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formViolation" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="worker_id" type="number" required placeholder="<?php echo htmlspecialchars(cp_t('gov.worker_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="agency_id" type="number" placeholder="<?php echo htmlspecialchars(cp_t('gov.agency_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="inspection_id" type="number" placeholder="<?php echo htmlspecialchars(cp_t('gov.inspection_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" name="type" required placeholder="<?php echo htmlspecialchars(cp_t('gov.type'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="severity">
                                <option value="low"><?php echo htmlspecialchars(cp_t('gov.low'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="medium" selected><?php echo htmlspecialchars(cp_t('gov.medium'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="high"><?php echo htmlspecialchars(cp_t('gov.high'), ENT_QUOTES, 'UTF-8'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-12"><input class="form-control form-control-sm" name="description" required placeholder="<?php echo htmlspecialchars(cp_t('gov.description'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-12"><input class="form-control form-control-sm" name="action_taken" placeholder="<?php echo htmlspecialchars(cp_t('gov.action_taken'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><?php echo htmlspecialchars(cp_t('gov.add_violation'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableViolations">
                            <thead><tr>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_worker'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.type'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.severity'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_insp'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_created'), ENT_QUOTES, 'UTF-8'); ?></th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-bl" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars(cp_t('gov.blacklist'), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <?php if ($canManageGov): ?>
                    <form id="formBlacklist" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="entity_type">
                                <option value="worker"><?php echo htmlspecialchars(cp_t('gov.worker'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="agency"><?php echo htmlspecialchars(cp_t('gov.agency'), ENT_QUOTES, 'UTF-8'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="entity_id" type="number" required placeholder="<?php echo htmlspecialchars(cp_t('gov.entity_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-6"><input class="form-control form-control-sm" name="reason" required placeholder="<?php echo htmlspecialchars(cp_t('gov.reason'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-12"><button type="submit" class="btn btn-danger btn-sm"><?php echo htmlspecialchars(cp_t('gov.add_blacklist'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableBlacklist">
                            <thead><tr>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.type'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.status'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.reason'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_name'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php if ($canManageGov): ?><th></th><?php endif; ?>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-track" role="tabpanel">
            <div class="card gov-card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars(cp_t('gov.tab_worker_monitoring'), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3"><input class="form-control form-control-sm" id="trackFilterCountry" placeholder="<?php echo htmlspecialchars(cp_t('gov.country'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="trackFilterStatus">
                                <option value=""><?php echo htmlspecialchars(cp_t('gov.all_statuses'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="safe"><?php echo htmlspecialchars(cp_t('gov.safe'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="warning"><?php echo htmlspecialchars(cp_t('gov.warning'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="alert"><?php echo htmlspecialchars(cp_t('gov.alert'), ENT_QUOTES, 'UTF-8'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-primary" id="trackApply"><?php echo htmlspecialchars(cp_t('gov.apply'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                    </div>
                    <?php if ($canManageGov): ?>
                    <form id="formTracking" class="row g-2 mb-3 gov-form">
                        <div class="col-md-2"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="worker_id" type="number" required placeholder="<?php echo htmlspecialchars(cp_t('gov.worker_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-3"><input class="form-control form-control-sm cp-ltr-field" lang="en" dir="ltr" name="last_checkin" type="datetime-local"></div>
                        <div class="col-md-3"><input class="form-control form-control-sm" name="location_text" placeholder="<?php echo htmlspecialchars(cp_t('gov.city_country'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="status">
                                <option value="safe"><?php echo htmlspecialchars(cp_t('gov.safe'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="warning"><?php echo htmlspecialchars(cp_t('gov.warning'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="alert"><?php echo htmlspecialchars(cp_t('gov.alert'), ENT_QUOTES, 'UTF-8'); ?></option>
                            </select>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><?php echo htmlspecialchars(cp_t('gov.save_checkin'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                    </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped gov-table" id="tableTracking">
                            <thead><tr>
                                <th><?php echo htmlspecialchars(cp_t('gov.th_worker'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.country'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.last_seen'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.location'), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th><?php echo htmlspecialchars(cp_t('gov.status'), ENT_QUOTES, 'UTF-8'); ?></th>
                            </tr></thead>
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
