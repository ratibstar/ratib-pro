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

    <nav class="partner-portal-section-nav glass-card" aria-label="Page sections">
        <span class="partner-portal-section-nav-label">Jump to</span>
        <a href="#partner-portal-section-overview">Agency &amp; contracts</a>
        <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-documents.php'), ENT_QUOTES, 'UTF-8'); ?>">Documents &amp; CVs</a>
        <a href="#partner-portal-section-worker-docs">Worker documents shared</a>
    </nav>

    <div id="partner-portal-section-overview" class="agency-detail-grid partner-portal-anchor-target">
        <div class="agency-detail-main-col">
            <section class="agency-detail-card glass-card">
                <div class="agency-detail-card-head">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">🏢</span> Agency data</h2>
                    <div class="partner-portal-card-actions">
                        <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewProfile" title="View full profile">View</button>
                        <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditAgency" title="Edit contact and address (office-managed fields stay with your office)">Edit</button>
                    </div>
                </div>
                <dl class="agency-detail-dl" id="ppAgencyData"></dl>
            </section>
            <section class="agency-detail-card glass-card">
                <div class="agency-detail-card-head">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📞</span> Contact information</h2>
                    <div class="partner-portal-card-actions">
                        <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewContact" title="View contact details">View</button>
                        <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditContact" title="Edit contact details">Edit</button>
                    </div>
                </div>
                <dl class="agency-detail-dl" id="ppContactData"></dl>
            </section>
            <section class="agency-detail-card glass-card">
                <div class="agency-detail-card-head">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📋</span> Administrative &amp; financial</h2>
                    <div class="partner-portal-card-actions">
                        <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewAdmin" title="View administrative details">View</button>
                        <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditAdmin" title="Edit contact and address">Edit</button>
                    </div>
                </div>
                <dl class="agency-detail-dl" id="ppAdminData"></dl>
                <p class="agency-detail-note">Extended license and banking fields can be added when available in your profile.</p>
            </section>
        </div>
        <aside class="agency-detail-side-col">
            <section class="agency-detail-card glass-card agency-detail-contracts-card">
                <div class="agency-detail-card-head">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📄</span> Recruitment contracts</h2>
                    <div class="partner-portal-card-actions partner-portal-contracts-actions">
                        <span class="agency-detail-count" id="ppContractCount">0</span>
                        <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewContractsCard" title="View profile including deployments">View</button>
                        <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditContractsCard" title="Edit your contact details">Edit</button>
                    </div>
                </div>
                <div id="ppContracts" class="agency-contracts-list"></div>
                <p id="ppContractsEmpty" class="agency-detail-empty" hidden>No deployments recorded for this agency yet.</p>
            </section>
        </aside>
    </div>

    <section id="partner-portal-section-documents" class="agency-detail-card glass-card partner-portal-cvs-block partner-portal-anchor-target">
        <div class="agency-detail-card-head">
            <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; CVs</h2>
            <div class="partner-portal-card-actions">
                <button type="button" class="muted-btn partner-portal-card-btn" id="ppBtnViewDocs" title="View full profile">View</button>
                <button type="button" class="neon-btn partner-portal-card-btn partner-portal-card-btn--primary" id="ppBtnEditDocs" title="Edit contact details">Edit</button>
            </div>
        </div>
        <p class="agency-detail-note">Your office uploads files in Ratib Pro. Use the table page to search, sort, download, or open files.</p>
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

<div id="ppProfileModal" class="modal-wrap partner-portal-modal" aria-hidden="true">
    <div class="modal-card glass-card partner-portal-modal-card" role="dialog" aria-modal="true" aria-labelledby="ppProfileModalTitle">
        <div class="partner-portal-modal-head">
            <h3 id="ppProfileModalTitle" class="partner-portal-modal-title">Profile</h3>
            <button type="button" class="icon-btn" id="ppProfileModalClose" aria-label="Close">×</button>
        </div>
        <p id="ppProfileModalLead" class="partner-portal-modal-lead"></p>
        <div id="ppProfileViewPanel" class="partner-portal-modal-view"></div>
        <form id="ppProfileEditForm" class="partner-portal-edit-form" hidden>
            <label class="partner-portal-label">Contact person</label>
            <input type="text" name="contact_person" id="ppEditContactPerson" class="partner-portal-input" maxlength="255" autocomplete="name">
            <label class="partner-portal-label">Email</label>
            <input type="email" name="email" id="ppEditEmail" class="partner-portal-input" maxlength="255" autocomplete="email">
            <label class="partner-portal-label">Phone 1</label>
            <input type="text" name="phone" id="ppEditPhone" class="partner-portal-input" maxlength="80" autocomplete="tel">
            <label class="partner-portal-label">Phone 2</label>
            <input type="text" name="phone2" id="ppEditPhone2" class="partner-portal-input" maxlength="80">
            <label class="partner-portal-label">Fax</label>
            <input type="text" name="fax" id="ppEditFax" class="partner-portal-input" maxlength="80">
            <label class="partner-portal-label">Mobile</label>
            <input type="text" name="mobile" id="ppEditMobile" class="partner-portal-input" maxlength="80" autocomplete="tel">
            <label class="partner-portal-label">Address (English)</label>
            <textarea name="address_en" id="ppEditAddressEn" class="partner-portal-input partner-portal-textarea" rows="3" maxlength="2000"></textarea>
            <label class="partner-portal-label">Address (Arabic)</label>
            <textarea name="address_ar" id="ppEditAddressAr" class="partner-portal-input partner-portal-textarea" rows="2" maxlength="2000"></textarea>
            <p id="ppProfileFormMsg" class="partner-portal-modal-msg" hidden></p>
            <div class="partner-portal-modal-footer">
                <button type="button" class="muted-btn" id="ppProfileCancelBtn">Cancel</button>
                <button type="submit" class="neon-btn" id="ppProfileSaveBtn">Save</button>
            </div>
        </form>
        <div id="ppProfileViewFooter" class="partner-portal-modal-footer">
            <button type="button" class="muted-btn" id="ppProfileCloseBtn">Close</button>
        </div>
    </div>
</div>

<div id="ppContractModal" class="modal-wrap partner-portal-modal" aria-hidden="true">
    <div class="modal-card glass-card partner-portal-modal-card partner-portal-modal-card--compact" role="dialog" aria-modal="true" aria-labelledby="ppContractModalTitle">
        <div class="partner-portal-modal-head">
            <h3 id="ppContractModalTitle" class="partner-portal-modal-title">Deployment</h3>
            <button type="button" class="icon-btn" id="ppContractModalClose" aria-label="Close">×</button>
        </div>
        <dl class="agency-detail-dl partner-portal-contract-dl" id="ppContractModalBody"></dl>
        <div class="partner-portal-modal-footer">
            <button type="button" class="muted-btn" id="ppContractCloseBtn">Close</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
