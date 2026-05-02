<?php
/**
 * Staff: partner-style CVs view only (same blocks as the partner portal).
 * Opened from Partner Agencies → CVs.
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

$pageTitle = 'Partner CVs (portal view)';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/partner-agency-portal-cvs.js') . '?v=' . $v];
include '../includes/header.php';

$listHref = htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8');
?>

<div class="main-content partnerships-page partner-agency-portal-cvs-page" dir="ltr" lang="en">
    <nav class="agency-detail-breadcrumb glass-card" aria-label="Breadcrumb">
        <a href="<?php echo $listHref; ?>">Partner Agencies</a>
        <span class="agency-detail-bc-sep" aria-hidden="true">/</span>
        <a id="pacvBreadcrumbAgencyLink" class="agency-detail-breadcrumb-agency" href="<?php echo htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8'); ?>"><span id="pacvBreadcrumbAgencyName">Agency</span></a>
        <span class="agency-detail-bc-sep" aria-hidden="true">/</span>
        <span class="agency-detail-bc-current">CVs (partner view)</span>
    </nav>

    <header class="agency-detail-hero glass-card">
        <div class="agency-detail-hero-top">
            <a href="<?php echo $listHref; ?>" class="muted-btn agency-detail-back">← Back to list</a>
        </div>
        <div class="partner-agency-portal-cvs-hero">
            <p class="partner-agency-portal-cvs-kicker">Same as partner portal</p>
            <h1 id="pacvPageTitle" class="agency-detail-title">Documents &amp; CVs</h1>
            <p class="partner-agency-portal-cvs-lead">This is what the partner sees for downloads. To upload files or change which worker documents are shared, use the link below.</p>
            <p class="partner-agency-portal-cvs-actions">
                <a id="pacvManageLink" class="neon-btn" href="#">Manage uploads &amp; worker sharing</a>
                <a id="pacvDetailLink" class="muted-btn" href="#">Open full agency details</a>
            </p>
        </div>
    </header>

    <div id="pacvError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <section class="agency-detail-card glass-card partner-portal-cvs-block">
        <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; CVs</h2>
        <p class="agency-detail-note">Your office uploads files in Ratib Pro. Partners download only — same list as on their portal.</p>
        <ul id="pacvCvList" class="partner-portal-cv-list"></ul>
        <p id="pacvCvEmpty" class="agency-detail-empty" hidden>No documents uploaded yet.</p>
    </section>

    <section class="agency-detail-card glass-card partner-portal-worker-shares-block">
        <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">👤</span> Worker documents from your office</h2>
        <p class="agency-detail-note">Only workers and document types your office enabled for this agency appear here — same as the partner portal.</p>
        <ul id="pacvWorkerShareList" class="partner-portal-worker-share-list"></ul>
        <p id="pacvWorkerShareEmpty" class="agency-detail-empty" hidden>No worker documents shared with this portal yet.</p>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
