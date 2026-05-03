<?php
/**
 * Staff mirror of partner Documents & CVs (same table as the partner portal, staff session + partnerships view).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
if (!hasPermission('view_partner_agencies') && !hasPermission('view_workers')) {
    header('Location: ' . ratib_country_dashboard_url((int) ($_SESSION['agency_id'] ?? 0)));
    exit;
}

$partnerAgencyId = (int) ($_GET['partner_agency_id'] ?? 0);
if ($partnerAgencyId <= 0) {
    header('Location: ' . pageUrl('partner-agencies.php'));
    exit;
}

$highlightWorkerIds = array_values(
    array_filter(
        array_map('intval', preg_split('/[,]+/', (string) ($_GET['worker_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)),
        static function ($id) {
            return $id > 0;
        }
    )
);

$backQs = [];
if (isset($_GET['control']) && (string) $_GET['control'] !== '') {
    $backQs['control'] = (string) $_GET['control'];
}
if (isset($_GET['agency_id']) && (string) $_GET['agency_id'] !== '') {
    $backQs['agency_id'] = (string) $_GET['agency_id'];
}
$backHref = pageUrl('partner-agencies.php');
if ($backQs) {
    $backHref .= '?' . http_build_query($backQs, '', '&', PHP_QUERY_RFC3986);
}

$extraWorkerQuery = [];
if (isset($_GET['control']) && (string) $_GET['control'] !== '') {
    $extraWorkerQuery['control'] = (string) $_GET['control'];
}
if (isset($_GET['agency_id']) && (string) $_GET['agency_id'] !== '') {
    $extraWorkerQuery['agency_id'] = (string) $_GET['agency_id'];
}
$extraWorkerQueryStr = $extraWorkerQuery ? http_build_query($extraWorkerQuery, '', '&', PHP_QUERY_RFC3986) : '';

$v = time();
$pageTitle = 'Documents & CVs (partner preview)';
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-portal-documents.js') . '?v=' . $v];
include __DIR__ . '/../includes/header.php';
?>
<script>
window.RATIB_PARTNER_DOCS_STAFF = {
    partner_agency_id: <?php echo (int) $partnerAgencyId; ?>,
    highlight_worker_ids: <?php echo json_encode($highlightWorkerIds, JSON_UNESCAPED_UNICODE); ?>,
    worker_profile_extra_query: <?php echo json_encode($extraWorkerQueryStr, JSON_UNESCAPED_UNICODE); ?>,
    back_href: <?php echo json_encode($backHref, JSON_UNESCAPED_UNICODE); ?>
};
</script>

<div class="main-content partnerships-page partner-portal-docs-page partner-docs-staff-page" lang="en" dir="ltr">
    <header class="partner-portal-header-mega glass-card">
        <div class="partner-portal-docs-page-intro">
            <a class="muted-btn partner-portal-docs-back" href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>">← Back to Partner Agencies</a>
            <div class="partner-portal-docs-page-intro-text">
                <p class="partner-portal-kicker">Staff — partner preview</p>
                <h1 class="partner-portal-title partner-portal-docs-page-h1">Documents &amp; CVs</h1>
                <p id="ppDocsAgencySub" class="partner-portal-docs-subline">Loading…</p>
            </div>
        </div>
    </header>

    <div id="ppDocsError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <div id="ppDocsStatusCards" class="partner-portal-docs-status-cards glass-card" hidden aria-live="polite">
        <div id="ppDocsStatusCardsInner" class="partner-portal-docs-status-cards-row"></div>
    </div>

    <div class="glass-card partner-portal-docs-toolbar">
        <input type="search" id="ppDocsSearch" class="partner-portal-input partner-portal-docs-search" placeholder="Search title, worker, CV ref, worker type, or status…" aria-label="Search documents">
        <div class="partner-portal-docs-toolbar-right">
            <span id="ppDocsSelectedCount" class="partner-portal-docs-selected-count" hidden></span>
            <div class="partner-portal-docs-bulk-status-wrap" id="ppDocsBulkStatusWrap">
                <label class="partner-portal-docs-bulk-status-label" for="ppDocsBulkStatusSelect">Status for selected</label>
                <select id="ppDocsBulkStatusSelect" class="partner-portal-input partner-portal-docs-select" aria-label="Portal status to apply to selected rows" disabled></select>
                <button type="button" class="neon-btn partner-portal-docs-bulk-status-btn" id="ppDocsApplyStatusSelected" disabled>Apply status</button>
            </div>
            <button type="button" class="muted-btn partner-portal-docs-bulk-delete" id="ppDocsDeleteSelected" hidden>Delete selected</button>
            <label class="partner-portal-docs-rows-label">
                Rows
                <select id="ppDocsPageSize" class="partner-portal-input partner-portal-docs-select" aria-label="Rows per page">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <button type="button" class="muted-btn" id="ppDocsRefresh">Refresh</button>
        </div>
    </div>

    <input type="file" id="ppStaffWorkerDocUploadInput" name="pp_staff_worker_document" class="partner-portal-docs-hidden-file" accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png" tabindex="-1" aria-hidden="true" title="Worker document file">

    <p class="partner-portal-docs-staff-status-help">
        <strong>Preview</strong> on the table opens the worker documents window: only <strong>File</strong> and <strong>Status</strong> columns — click a row with a file to open the side preview (Download / Upload there), or an empty row to upload (PDF, JPG, or PNG).
        Use <strong>Status</strong> or bulk <strong>Apply status</strong> for what the partner portal shows; <strong>Auto</strong> clears a manual label.
    </p>

    <div class="glass-card partner-portal-docs-table-shell">
        <div class="partner-portal-docs-table-scroll">
            <table class="partnerships-table partner-portal-docs-table" aria-label="Partner documents and CVs">
                <thead>
                    <tr>
                        <th scope="col" class="pp-docs-select-col">
                            <input type="checkbox" id="ppDocsSelectAll" class="pp-docs-select-all" title="Select all on this page" aria-label="Select all on this page">
                        </th>
                        <th scope="col" class="col-num"><button type="button" class="partner-portal-sort-btn" data-sort="idx">CV ref</button></th>
                        <th scope="col" class="col-status"><button type="button" class="partner-portal-sort-btn" data-sort="portal_status">Status</button></th>
                        <th scope="col"><button type="button" class="partner-portal-sort-btn" data-sort="title">Title</button></th>
                        <th scope="col" class="col-file"><button type="button" class="partner-portal-sort-btn" data-sort="original_filename">File</button></th>
                        <th scope="col" class="col-worker-type"><button type="button" class="partner-portal-sort-btn" data-sort="worker_type">Worker type</button></th>
                        <th scope="col"><button type="button" class="partner-portal-sort-btn" data-sort="created_at">Uploaded</button></th>
                        <th scope="col" class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="ppDocsBody"></tbody>
            </table>
        </div>
        <p id="ppDocsEmpty" class="agency-detail-empty partner-portal-docs-empty" hidden>No documents yet.</p>
    </div>

    <div class="partner-portal-docs-pagination glass-card">
        <button type="button" class="muted-btn" id="ppDocsPrev">Prev</button>
        <span id="ppDocsPageInfo" class="partner-portal-docs-page-info">Page 1 / 1</span>
        <button type="button" class="muted-btn" id="ppDocsNext">Next</button>
    </div>
</div>

<div id="ppDocModal" class="modal-wrap partner-portal-modal" aria-hidden="true">
    <div class="modal-card glass-card partner-portal-modal-card partner-portal-modal-card--compact" role="dialog" aria-modal="true" aria-labelledby="ppDocModalTitle">
        <div class="partner-portal-modal-head">
            <h3 id="ppDocModalTitle" class="partner-portal-modal-title">Document</h3>
            <button type="button" class="icon-btn" id="ppDocModalCloseX" aria-label="Close">×</button>
        </div>
        <p id="ppDocModalLead" class="partner-portal-modal-lead"></p>
        <dl class="agency-detail-dl partner-portal-contract-dl" id="ppDocModalDl"></dl>
        <div class="partner-portal-modal-footer partner-portal-docs-modal-footer">
            <div id="ppDocModalFileLinks" class="partner-portal-docs-modal-links"></div>
            <button type="button" class="muted-btn" id="ppDocModalCloseBtn">Close</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
