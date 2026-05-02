<?php
/**
 * Staff page: bulk control table for worker CV/document sharing to partner portals.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
if (!hasPermission('view_partner_agencies') && !hasPermission('view_workers')) {
    header('Location: ' . ratib_country_dashboard_url((int) ($_SESSION['agency_id'] ?? 0)));
    exit;
}

$pageTitle = 'CVs Control';
$v = time();
$pageCss = [asset('css/partnerships.css') . '?v=' . $v];
$pageJs = [asset('js/partnerships/partner-cvs-control.js') . '?v=' . $v];
include '../includes/header.php';
?>

<div class="main-content partnerships-page partner-cvs-control-page" lang="en" dir="ltr">
    <div class="partnerships-toolbar glass-card">
        <h2>📎 CVs Control (Bulk)</h2>
        <div class="toolbar-actions">
            <a class="muted-btn" href="<?php echo htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to Partner Agencies</a>
        </div>
    </div>

    <section class="glass-card cvs-send-wizard" aria-label="Send full CVs to partner">
        <h3 class="cvs-wizard-title">Send worker CV files to a partner</h3>
        <p class="cvs-wizard-lead">Pick workers, pick partner, then send. The system shares every uploaded document file on each selected worker profile. Only workers deployed to that partner can be sent.</p>
        <ol class="cvs-wizard-steps">
            <li class="cvs-wizard-step">
                <span class="cvs-wizard-step-label">Select workers</span>
                <input id="cvsReadySearch" type="search" class="cvs-ready-search" placeholder="Search name or passport…" aria-label="Search ready workers">
                <div id="cvsReadyWorkerList" class="cvs-ready-worker-list" role="group" aria-label="Ready workers"></div>
                <div class="toolbar-actions cvs-ready-toolbar">
                    <button type="button" id="cvsSelectAllReadyBtn" class="muted-btn">Select all shown</button>
                    <button type="button" id="cvsClearReadyBtn" class="muted-btn">Clear selection</button>
                    <span id="cvsReadySelectionLabel" class="cvs-ready-selection-label">0 workers selected</span>
                </div>
            </li>
            <li class="cvs-wizard-step">
                <span class="cvs-wizard-step-label">Choose partner</span>
                <div class="toolbar-actions">
                    <select id="cvsPartnerSelect" aria-label="Partner agency">
                        <option value="">Select partner agency…</option>
                    </select>
                </div>
                <p id="cvsPartnerWizardHint" class="cvs-wizard-hint" hidden></p>
            </li>
            <li class="cvs-wizard-step">
                <span class="cvs-wizard-step-label">Send to partner portal</span>
                <div class="toolbar-actions cvs-wizard-send-row">
                    <button type="button" id="cvsSendToPartnerBtn" class="neon-btn" disabled>Send selected CVs to this partner</button>
                </div>
            </li>
        </ol>
        <p id="cvsControlNotice" class="partner-cvs-control-notice cvs-page-notice" role="status" aria-live="polite" hidden></p>
    </section>

    <div class="cvs-advanced-bar glass-card">
        <div class="cvs-advanced-bar-text">
            <h3 class="cvs-advanced-title">Advanced: per-document editing</h3>
            <p class="cvs-advanced-lead">Optional row-by-row filters and bulk actions. Keep closed for a cleaner screen.</p>
        </div>
        <button type="button" id="cvsToggleAdvancedBtn" class="muted-btn cvs-advanced-toggle" aria-expanded="false" aria-controls="cvsAdvancedPanel">Show advanced</button>
    </div>

    <div id="cvsAdvancedPanel" class="cvs-advanced-panel" hidden>
    <div class="glass-card partner-cvs-control-filters">
        <div class="toolbar-actions">
            <select id="cvsWorkerQuickSelect" aria-label="Select all document rows for one worker" title="Pick a worker to check every CV/document row for bulk share with this partner">
                <option value="">Select worker (all their CV rows)…</option>
            </select>
            <input id="cvsSearch" type="text" placeholder="Search worker, passport, document">
            <select id="cvsSharedFilter" aria-label="Shared status">
                <option value="">All shares</option>
                <option value="shared">Shared on portal</option>
                <option value="not_shared">Not shared</option>
            </select>
            <select id="cvsFileFilter" aria-label="File status">
                <option value="">All file statuses</option>
                <option value="has_file">Has worker file</option>
                <option value="missing_file">Missing worker file</option>
            </select>
            <select id="cvsDocFilter" aria-label="Document type">
                <option value="">All document types</option>
            </select>
            <select id="cvsPageSize" aria-label="Rows per page">
                <option value="10">10 / page</option>
                <option value="25" selected>25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>
        <div class="toolbar-actions partner-cvs-control-bulk-row">
            <span id="cvsFilteredInfo" class="partner-cvs-filtered-info" aria-live="polite">0 filtered</span>
            <button type="button" id="cvsSelectFilteredBtn" class="muted-btn" disabled title="Check every row in the current filter (all pages)">Select all filtered</button>
            <button type="button" id="cvsExportCsvBtn" class="muted-btn" disabled title="Download current filtered rows as CSV">Export CSV</button>
        </div>
        <div class="toolbar-actions partner-cvs-control-bulk-row">
            <span id="cvsSelectionLabel" class="bulk-agency-selection-label">0 selected</span>
            <button type="button" id="cvsBulkShareBtn" class="bulk-agency-btn bulk-agency-btn--act" disabled>Bulk Add</button>
            <button type="button" id="cvsBulkRemoveBtn" class="bulk-agency-btn bulk-agency-btn--inact" disabled>Bulk Remove</button>
            <select id="cvsBulkEditType" aria-label="Bulk edit document type">
                <option value="">Edit type to…</option>
            </select>
            <button type="button" id="cvsBulkEditBtn" class="bulk-agency-btn bulk-agency-btn--clear" disabled>Bulk Edit Type</button>
            <button type="button" id="cvsClearSelectionBtn" class="muted-btn" disabled>Clear</button>
            <button type="button" id="cvsReloadBtn" class="muted-btn">Reload</button>
        </div>
        <p class="cvs-bulk-hint">Use <strong>Select all filtered</strong> with <strong>Bulk Add / Remove / Edit</strong> to apply to every row matching your filters (all pages).</p>
    </div>

    <div class="glass-card table-shell">
        <table class="partnerships-table partnerships-table--cvs-control">
            <thead>
                <tr>
                    <th class="col-select"><input type="checkbox" id="cvsSelectAll"></th>
                    <th>Worker</th>
                    <th>Passport</th>
                    <th>Document Type</th>
                    <th>Worker File</th>
                    <th>Portal Share</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="cvsControlBody"></tbody>
        </table>
        <div class="table-pagination">
            <button id="cvsPrevPage" class="muted-btn" type="button">Prev</button>
            <span id="cvsPageInfo">Page 1 / 1</span>
            <button id="cvsNextPage" class="muted-btn" type="button">Next</button>
        </div>
    </div>
    </div>
</div>

<div id="cvsWorkerModal" class="modal-wrap modal-wrap--cvs-worker" aria-hidden="true">
    <div class="modal-card modal-card--cvs-worker glass-card">
        <div class="modal-header-row">
            <h3 id="cvsWorkerModalTitle">Worker CV</h3>
            <button type="button" id="cvsWorkerModalClose" class="icon-btn" aria-label="Close">×</button>
        </div>
        <p class="cvs-worker-modal-hint">Same CV/worker form as Workers → opens in view mode. Close when done — your CVs Control table is still open behind this.</p>
        <iframe id="cvsWorkerIframe" class="cvs-worker-iframe" title="Worker CV form"></iframe>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
