<?php
/**
 * Partner portal — full Documents & CVs table (session scoped, same-site navigation).
 */
require_once __DIR__ . '/../includes/config.php';

if (!ratib_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$pageTitle = 'Documents & CVs';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-portal-documents.js') . '?v=' . $v];
$partnerPortalMinimal = true;
$partnerPortalNavActive = 'documents';
$ppPortalHome = htmlspecialchars(pageUrl('partner-portal.php'), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap partner-portal-docs-page agency-detail-page" dir="ltr" lang="en">
    <header class="partner-portal-header-mega glass-card">
        <?php include __DIR__ . '/../includes/partner-portal-marketing-strip.php'; ?>
        <div class="partner-portal-docs-page-intro">
            <a class="muted-btn partner-portal-docs-back" href="<?php echo $ppPortalHome; ?>">← Back to partner portal</a>
            <div class="partner-portal-docs-page-intro-text">
                <p class="partner-portal-kicker">Partner portal</p>
                <h1 class="partner-portal-title partner-portal-docs-page-h1">Documents &amp; CVs</h1>
                <p id="ppDocsAgencySub" class="partner-portal-docs-subline">Loading…</p>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/../includes/partner-portal-nav.php'; ?>

    <div id="ppDocsError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <div id="ppDocsStatusCards" class="partner-portal-docs-status-cards glass-card" hidden aria-live="polite">
        <div id="ppDocsStatusCardsInner" class="partner-portal-docs-status-cards-row"></div>
    </div>

    <div class="glass-card partner-portal-docs-toolbar">
        <input type="search" id="ppDocsSearch" class="partner-portal-input partner-portal-docs-search" placeholder="Search title, worker, CV ref, worker type, or status…" aria-label="Search documents">
        <div class="partner-portal-docs-toolbar-right">
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

    <div class="glass-card partner-portal-docs-table-shell">
        <div class="partner-portal-docs-table-scroll">
            <table class="partnerships-table partner-portal-docs-table" aria-label="Partner documents and CVs">
                <thead>
                    <tr>
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

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
