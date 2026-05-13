<?php
/**
 * Partner agency portal — magic-link login (?token=) then scoped dashboard (English, dark).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../api/core/Database.php';
require_once __DIR__ . '/../api/core/ensure-global-partnerships-schema.php';

$db = Database::getInstance();
$conn = $db->getConnection();
ratibEnsureGlobalPartnershipsSchema($conn);

if (!empty($_GET['token'])) {
    $tok = trim((string) $_GET['token']);
    if ($tok !== '') {
        $stmt = $conn->prepare(
            'SELECT id FROM partner_agencies WHERE portal_enabled = 1 AND portal_access_token = ? LIMIT 1'
        );
        $stmt->execute([$tok]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['partner_portal_logged_in'] = true;
            $_SESSION['partner_portal_agency_id'] = (int) $row['id'];
            header('Location: ' . pageUrl('partner-portal.php'), true, 302);
            exit;
        }
    }
    // One-time notice on login page (avoid ?err= in URL so refresh stays clean).
    $_SESSION['partner_portal_flash_magic_link_failed'] = true;
    header('Location: ' . pageUrl('partner-portal-login.php'), true, 302);
    exit;
}

if (!ratib_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$pageTitle = 'Partner portal';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    asset('js/partnerships/partner-portal.js') . '?v=' . $v,
];
$ppAccountingPage = htmlspecialchars(pageUrl('partner-portal-accounting.php'), ENT_QUOTES, 'UTF-8');
$partnerPortalMinimal = true;
$partnerPortalNavActive = 'home';
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap agency-detail-page" dir="ltr" lang="en">
    <header class="partner-portal-header-mega glass-card">
        <?php include __DIR__ . '/../includes/partner-portal-marketing-strip.php'; ?>
        <div class="partner-portal-top partner-portal-top--identity">
            <div class="partner-portal-brand">
                <span class="partner-portal-globe" aria-hidden="true">🌍</span>
                <div>
                    <p class="partner-portal-kicker">Partner portal</p>
                    <h1 id="ppAgencyName" class="partner-portal-title">Loading…</h1>
                </div>
            </div>
            <div class="partner-portal-actions">
                <span id="ppStatus" class="status-pill status-inactive" hidden></span>
                <span id="ppAgencyIdBadge" class="agency-detail-id-badge" hidden></span>
                <a class="partner-portal-btn-portal" href="<?php echo htmlspecialchars(pageUrl('partner-portal-logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="partner-portal-btn-portal-icon" aria-hidden="true">👤</span>
                    Log out
                </a>
            </div>
        </div>
    </header>

    <div id="ppError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <?php include __DIR__ . '/../includes/partner-portal-nav.php'; ?>

    <section id="partner-portal-dashboard" class="partner-portal-dashboard partner-portal-anchor-target" aria-labelledby="ppDashboardHeading">
        <h2 id="ppDashboardHeading" class="partner-portal-dashboard-heading">Dashboard</h2>
        <p class="partner-portal-dashboard-lead">Quick view of your activity with this office.</p>
        <div class="partner-portal-dashboard-grid">
            <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-agency-contracts.php'), ENT_QUOTES, 'UTF-8'); ?>" class="partner-portal-dash-card glass-card partner-portal-dash-card--link">
                <span class="partner-portal-dash-card__icon" aria-hidden="true">📄</span>
                <span class="partner-portal-dash-card__value" id="ppDashDeployments">—</span>
                <span class="partner-portal-dash-card__label">Deployments</span>
                <span class="partner-portal-dash-card__hint" id="ppDashDeploymentsHint">Workers on record for your agency</span>
                <span class="partner-portal-dash-card__cta">View placements →</span>
            </a>
            <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-documents.php'), ENT_QUOTES, 'UTF-8'); ?>" class="partner-portal-dash-card glass-card partner-portal-dash-card--link">
                <span class="partner-portal-dash-card__icon" aria-hidden="true">📎</span>
                <span class="partner-portal-dash-card__value" id="ppDashDocTotal">—</span>
                <span class="partner-portal-dash-card__label">Documents &amp; CVs</span>
                <span class="partner-portal-dash-card__hint" id="ppDashDocHint">Agency files + shared worker files</span>
                <span class="partner-portal-dash-card__cta">Open full table →</span>
            </a>
            <a href="#partner-portal-section-worker-docs" class="partner-portal-dash-card glass-card partner-portal-dash-card--link">
                <span class="partner-portal-dash-card__icon" aria-hidden="true">👤</span>
                <span class="partner-portal-dash-card__value" id="ppDashWorkerShares">—</span>
                <span class="partner-portal-dash-card__label">Worker document rows</span>
                <span class="partner-portal-dash-card__hint" id="ppDashWorkerHint">Shared slots visible on this portal</span>
                <span class="partner-portal-dash-card__cta">Jump to list →</span>
            </a>
            <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-accounting.php'), ENT_QUOTES, 'UTF-8'); ?>" class="partner-portal-dash-card glass-card partner-portal-dash-card--link">
                <span class="partner-portal-dash-card__icon" aria-hidden="true">📊</span>
                <span class="partner-portal-dash-card__value" id="ppDashAccounting">GL</span>
                <span class="partner-portal-dash-card__label">Account statement</span>
                <span class="partner-portal-dash-card__hint" id="ppDashAccountingHint">Same ledger your office uses in Accounting</span>
                <span class="partner-portal-dash-card__cta">Open statement →</span>
            </a>
            <div class="partner-portal-dash-card glass-card partner-portal-dash-card--static">
                <span class="partner-portal-dash-card__icon" aria-hidden="true">🏢</span>
                <span class="partner-portal-dash-card__value" id="ppDashAgencyStatus">—</span>
                <span class="partner-portal-dash-card__label">Partnership</span>
                <span class="partner-portal-dash-card__hint" id="ppDashAgencyHint">Your listing status with Ratib</span>
                <button type="button" class="muted-btn partner-portal-dash-card__btn" id="ppDashOpenProfile">View profile</button>
            </div>
        </div>

        <div id="ppDashLedgerPreview" class="partner-portal-dash-ledger-preview glass-card" aria-labelledby="ppDashLedgerPreviewTitle">
            <div class="partner-portal-dash-ledger-preview-head">
                <h3 id="ppDashLedgerPreviewTitle" class="partner-portal-dash-ledger-preview-title">
                    <span aria-hidden="true">📊</span> Account statement (platform ledger)
                </h3>
                <a class="muted-btn partner-portal-dash-ledger-preview-link" href="<?php echo $ppAccountingPage; ?>">Full statement →</a>
            </div>
            <p id="ppDashLedgerPreviewLead" class="partner-portal-dash-ledger-preview-lead">Loading ledger…</p>
            <p class="agency-detail-note partner-portal-dash-ledger-footnote">Figures include <strong>posted</strong> activity only — not vouchers or journals left in <strong>Draft</strong>.</p>
            <div id="ppDashLedgerPreviewKpis" class="partner-portal-dash-ledger-kpis is-hidden" hidden></div>
        </div>
    </section>

    <section id="partner-portal-section-documents" class="agency-detail-card glass-card partner-portal-cvs-block partner-portal-anchor-target">
        <div class="agency-detail-card-head">
            <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; CVs</h2>
            <div class="partner-portal-card-actions">
                <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewDocs" title="View full profile">View</button>
                <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditDocs" title="Edit contact details">Edit</button>
            </div>
        </div>
        <p class="agency-detail-note">Your office uploads files in the main platform. Use the table page to search, sort, download, or open files.</p>
        <div class="partner-portal-docs-teaser">
            <p id="ppCvTeaserLine" class="partner-portal-docs-teaser-line">Loading document count…</p>
            <a class="neon-btn partner-portal-docs-teaser-cta" href="<?php echo htmlspecialchars(pageUrl('partner-portal-documents.php'), ENT_QUOTES, 'UTF-8'); ?>">Open documents table</a>
        </div>
    </section>

    <section id="partner-portal-section-worker-docs" class="agency-detail-card glass-card partner-portal-worker-shares-block partner-portal-anchor-target">
        <div class="agency-detail-card-head">
            <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">👤</span> Worker documents from your office</h2>
            <div class="partner-portal-card-actions">
                <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewWorkerDocs" title="View full profile">View</button>
                <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditWorkerDocs" title="Edit contact details">Edit</button>
            </div>
        </div>
        <p class="agency-detail-note">Only workers and document types your office selected for this agency appear here. Download only.</p>
        <ul id="ppWorkerShareList" class="partner-portal-worker-share-list"></ul>
        <p id="ppWorkerShareEmpty" class="agency-detail-empty" hidden>No worker documents shared with your portal yet.</p>
    </section>
</div>

<?php include __DIR__ . '/../includes/partner-portal-modals.php'; ?>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
