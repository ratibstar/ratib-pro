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
    header('Location: ' . pageUrl('partner-portal-login.php') . '?err=1', true, 302);
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
$pageJs = [asset('js/partnerships/partner-portal.js') . '?v=' . $v];
$partnerPortalMinimal = true;
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap" dir="ltr" lang="en">
    <header class="partner-portal-top glass-card">
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
            <a class="muted-btn" href="<?php echo htmlspecialchars(pageUrl('partner-portal-logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Log out</a>
        </div>
    </header>

    <div id="ppError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <div class="agency-detail-grid">
        <div class="agency-detail-main-col">
            <section class="agency-detail-card glass-card">
                <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">🏢</span> Agency data</h2>
                <dl class="agency-detail-dl" id="ppAgencyData"></dl>
            </section>
            <section class="agency-detail-card glass-card">
                <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📞</span> Contact information</h2>
                <dl class="agency-detail-dl" id="ppContactData"></dl>
            </section>
            <section class="agency-detail-card glass-card">
                <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📋</span> Administrative &amp; financial</h2>
                <dl class="agency-detail-dl" id="ppAdminData"></dl>
                <p class="agency-detail-note">Extended license and banking fields can be added when available in your profile.</p>
            </section>
        </div>
        <aside class="agency-detail-side-col">
            <section class="agency-detail-card glass-card agency-detail-contracts-card">
                <div class="agency-detail-card-head">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📄</span> Recruitment contracts</h2>
                    <span class="agency-detail-count" id="ppContractCount">0</span>
                </div>
                <div id="ppContracts" class="agency-contracts-list"></div>
                <p id="ppContractsEmpty" class="agency-detail-empty" hidden>No deployments recorded for this agency yet.</p>
            </section>
        </aside>
    </div>

    <section class="agency-detail-card glass-card partner-portal-cvs-block">
        <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; CVs</h2>
        <p class="agency-detail-note">Your office uploads files here in Ratib Pro. You can download them below; uploads are not available on this page.</p>
        <ul id="ppCvList" class="partner-portal-cv-list"></ul>
        <p id="ppCvEmpty" class="agency-detail-empty" hidden>No documents uploaded yet.</p>
    </section>

    <section class="agency-detail-card glass-card partner-portal-worker-shares-block">
        <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">👤</span> Worker documents from your office</h2>
        <p class="agency-detail-note">Only workers and document types your office selected for this agency appear here. Download only.</p>
        <ul id="ppWorkerShareList" class="partner-portal-worker-share-list"></ul>
        <p id="ppWorkerShareEmpty" class="agency-detail-empty" hidden>No worker documents shared with your portal yet.</p>
    </section>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
