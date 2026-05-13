<?php
/**
 * Staff page: single partner agency profile, portal, and CVs.
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
$pageJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    asset('js/partnerships/agency-detail.js') . '?v=' . $v,
];
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
                    <p class="agency-detail-note">Give this agency a private link to view deployments and the documents your office exposes on the partner <strong>Documents &amp; CVs</strong> table. Treat the link like a password.</p>
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
                <section class="agency-detail-card glass-card" id="agencyPartnerTablesCard">
                    <h2 class="agency-detail-card-title"><span class="agency-detail-card-icon" aria-hidden="true">📎</span> Documents &amp; placements</h2>
                    <p class="agency-detail-note">Use the staff <strong>Documents &amp; CVs</strong> table to upload agency files, adjust partner-visible status, and manage shared worker rows. Use <strong>Partner Agencies</strong> → <strong>Placements</strong> for deployment status and contracts.</p>
                    <p class="agency-detail-note">Bulk-share worker files to this partner from <strong>Workers</strong> → select workers → <strong>Send CVs bulk</strong>, then pick this agency.</p>
                    <div class="agency-portal-actions">
                        <a class="neon-btn" id="agencyOpenDocsTable" href="#">Documents &amp; CVs (table)</a>
                        <a class="muted-btn" id="agencyOpenPlacementsTable" href="#">Placements &amp; deployments</a>
                    </div>
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

    <div id="panel-account" class="agency-detail-panels is-hidden" role="tabpanel" hidden
        data-can-view-chart="<?php echo hasPermission('view_chart_accounts') ? '1' : '0'; ?>"
        data-can-ensure="<?php echo ((hasPermission('edit_partner_agency') || hasPermission('edit_worker')) && hasPermission('add_account')) ? '1' : '0'; ?>">
        <div class="agency-accounting-toolbar glass-card">
            <div class="agency-accounting-toolbar-text">
                <p class="agency-accounting-title">Ledger link</p>
                <p id="agencyAccountingLinkSummary" class="agency-detail-note agency-accounting-summary">Loading…</p>
            </div>
            <div class="agency-accounting-actions">
                <button type="button" class="muted-btn" id="agencyAccountingEnsureBtn" hidden>Create ledger account</button>
                <a class="muted-btn agency-accounting-ac-link" id="agencyAccountingOpenCoa" href="#" hidden>Chart of accounts</a>
            </div>
        </div>
        <p class="agency-detail-note agency-accounting-portal-bridge glass-card">
            Partners signed into the
            <a href="<?php echo htmlspecialchars(pageUrl('partner-portal-login.php'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">partner portal</a>
            see the same linked ledger under <strong>Account statement</strong> (read-only, English).
        </p>
        <div class="agency-accounting-filters glass-card" id="agencyAccountingFilters" hidden>
            <label class="agency-accounting-date-label">From <input type="date" id="agencyAccountingStart" class="agency-accounting-date-input"></label>
            <label class="agency-accounting-date-label">To <input type="date" id="agencyAccountingEnd" class="agency-accounting-date-input"></label>
            <button type="button" class="neon-btn agency-accounting-refresh" id="agencyAccountingRefreshBtn">Refresh</button>
        </div>
        <div id="agencyAccountingBalances" class="agency-accounting-balances glass-card is-hidden" hidden></div>
        <div id="agencyAccountingChartWrap" class="agency-accounting-chart-wrap glass-card is-hidden" hidden lang="en">
            <h3 class="agency-accounting-chart-heading">Monthly debit and credit (SAR)</h3>
            <p class="agency-accounting-chart-note">English summary of journal activity in the selected date range.</p>
            <p id="agencyAccountingChartEmpty" class="agency-accounting-chart-empty" hidden></p>
            <div class="agency-accounting-chart-canvas">
                <canvas id="agencyAccountingChart" aria-label="Partner ledger debit and credit by month"></canvas>
            </div>
        </div>
        <div id="agencyAccountingTableWrap" class="agency-accounting-table-wrap glass-card is-hidden" hidden>
            <table class="agency-accounting-table" id="agencyAccountingTable">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="num">Debit</th>
                        <th scope="col" class="num">Credit</th>
                        <th scope="col" class="num">Balance</th>
                    </tr>
                </thead>
                <tbody id="agencyAccountingTbody"></tbody>
            </table>
        </div>
        <p id="agencyAccountingTableFootnote" class="agency-detail-note agency-accounting-table-foot glass-card is-hidden" hidden>
            The first row is <strong>opening balance</strong> at the start of the dates you picked. Extra lines only appear after journal entries are posted to this chart account in <strong>Accounting</strong> (same ledger as your office).
        </p>
        <div id="agencyAccountingHint" class="agency-detail-note agency-accounting-hint glass-card is-hidden" hidden></div>
        <div id="agencyAccountingError" class="agency-detail-error glass-card is-hidden" hidden></div>
    </div>

    <div id="agencyDetailError" class="agency-detail-error glass-card is-hidden" hidden></div>
</div>

<?php include '../includes/footer.php'; ?>
