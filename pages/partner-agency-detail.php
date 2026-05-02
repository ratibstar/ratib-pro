<?php
/**
 * EN: Partner agency profile / detail (English, dark theme).
 * AR: تفاصيل وكيل الشريك.
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

$pageTitle = 'Partner Agency Details';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
];
$pageJs = [asset('js/partnerships/agency-detail.js') . '?v=' . $v];
include '../includes/header.php';

$listHref = htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8');
?>

<div class="main-content partnerships-page agency-detail-page" dir="ltr" lang="en">
    <nav class="agency-detail-breadcrumb glass-card" aria-label="Breadcrumb">
        <a href="<?php echo $listHref; ?>">Home</a>
        <span class="agency-detail-bc-sep" aria-hidden="true">/</span>
        <a href="<?php echo $listHref; ?>">Partner Agencies</a>
        <span class="agency-detail-bc-sep" aria-hidden="true">/</span>
        <span id="breadcrumbAgencyName" class="agency-detail-bc-current">Agency</span>
    </nav>

    <header class="agency-detail-hero glass-card">
        <div class="agency-detail-hero-top">
            <a href="<?php echo $listHref; ?>" class="muted-btn agency-detail-back">← Back to list</a>
        </div>
        <div class="agency-detail-hero-main">
            <div class="agency-detail-avatar" id="agencyDetailAvatar" aria-hidden="true">PA</div>
            <div class="agency-detail-hero-text">
                <p class="agency-detail-kicker">🌍 Partner Agencies</p>
                <h1 id="detailPageTitle" class="agency-detail-title">Partner Agency Details</h1>
                <div class="agency-detail-meta">
                    <span id="detailStatus" class="status-pill status-inactive" hidden>—</span>
                    <span id="detailAgencyId" class="agency-detail-id-badge" hidden></span>
                </div>
            </div>
        </div>
    </header>

    <div class="agency-detail-tabs glass-card" role="tablist" aria-label="Agency sections">
        <button type="button" class="agency-detail-tab is-active" role="tab" aria-selected="true" data-tab="basic">Basic data</button>
        <button type="button" class="agency-detail-tab" role="tab" aria-selected="false" data-tab="attachments">Attachments &amp; updates</button>
        <button type="button" class="agency-detail-tab" role="tab" aria-selected="false" data-tab="account">Account statement</button>
    </div>

    <div id="panel-basic" class="agency-detail-panels" role="tabpanel">
        <div class="agency-detail-grid">
            <div class="agency-detail-main-col">
                <section class="agency-detail-card glass-card">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">🏢</span> Agency data</h2>
                    <dl class="agency-detail-dl" id="detailAgencyData"></dl>
                </section>
                <section class="agency-detail-card glass-card">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📞</span> Contact information</h2>
                    <dl class="agency-detail-dl" id="detailContactData"></dl>
                </section>
                <section class="agency-detail-card glass-card">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📋</span> Administrative &amp; financial</h2>
                    <dl class="agency-detail-dl" id="detailAdminData"></dl>
                    <p class="agency-detail-note">Extended license and banking fields can be added when available in your profile.</p>
                </section>
                <section class="agency-detail-card glass-card agency-portal-card" id="partnerPortalCard">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">🔗</span> Partner portal (baby link)</h2>
                    <p class="agency-detail-note">Give this agency a private link to view only their deployments and the documents you upload here. Treat the link like a password.</p>
                    <div class="agency-portal-row">
                        <label class="agency-portal-check"><input type="checkbox" id="portalEnabled"> Enable partner portal</label>
                    </div>
                    <p class="agency-detail-note" id="portalTokenStatus"></p>
                    <div class="agency-portal-actions">
                        <button type="button" class="muted-btn" id="portalRegenBtn">Generate new access link</button>
                        <button type="button" class="muted-btn" id="portalSaveBtn">Save portal settings</button>
                    </div>
                    <div id="portalMagicLinkWrap" class="agency-portal-magic is-hidden" hidden>
                        <label class="agency-portal-label" for="portalMagicLinkField">Copy this link for the partner (shown once after generate)</label>
                        <input type="text" readonly class="agency-portal-input" id="portalMagicLinkField" autocomplete="off">
                    </div>
                    <div class="agency-portal-pw">
                        <label class="agency-portal-label" for="portalPasswordInput">Optional portal password (agency ID + password on login page)</label>
                        <input type="password" class="agency-portal-input" id="portalPasswordInput" placeholder="Leave blank to keep unchanged" autocomplete="new-password">
                        <div class="agency-portal-actions">
                            <button type="button" class="muted-btn" id="portalPwClearBtn">Clear password</button>
                        </div>
                    </div>
                    <p class="agency-detail-note">Partner sign-in page: <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-login.php'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">partner-portal-login.php</a></p>
                </section>
            </div>
            <aside class="agency-detail-side-col">
                <section class="agency-detail-card glass-card agency-detail-contracts-card">
                    <div class="agency-detail-card-head">
                        <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📄</span> Recruitment contracts</h2>
                        <span class="agency-detail-count" id="contractsCount">0</span>
                    </div>
                    <div id="contractsList" class="agency-contracts-list"></div>
                    <p id="contractsEmpty" class="agency-detail-empty" hidden>No deployments recorded for this agency yet.</p>
                </section>
            </aside>
        </div>
    </div>

    <div id="panel-attachments" class="agency-detail-panels is-hidden" role="tabpanel" hidden>
        <section class="agency-detail-card glass-card">
            <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; CVs (partner portal)</h2>
            <p class="agency-detail-note">Files listed here are visible to this agency when they use their partner portal link.</p>
            <form id="cvUploadForm" class="agency-cv-upload-form">
                <input type="text" id="cvTitle" placeholder="Title (e.g. Company profile 2026)" required maxlength="255">
                <input type="file" id="cvFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required>
                <button type="submit" class="neon-btn" id="cvUploadBtn">Upload</button>
            </form>
            <ul id="cvAdminList" class="agency-cv-admin-list"></ul>
            <p id="cvAdminEmpty" class="agency-detail-empty" hidden>No documents uploaded yet.</p>
        </section>
    </div>

    <div id="panel-account" class="agency-detail-panels is-hidden" role="tabpanel" hidden>
        <div class="agency-detail-placeholder glass-card">
            <p>Account statement and billing history will appear here when linked to accounting.</p>
        </div>
    </div>

    <div id="agencyDetailError" class="agency-detail-error glass-card is-hidden" hidden></div>
</div>

<?php include '../includes/footer.php'; ?>
