<?php
/**
 * Partner portal — Agency & contracts (profile, deployments, embedded account statement).
 */
require_once __DIR__ . '/../includes/config.php';

if (!ratib_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$pageTitle = 'Agency & contracts';
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
$partnerPortalMinimal = true;
$partnerPortalNavActive = 'agency';
$ppPortalHome = htmlspecialchars(pageUrl('partner-portal.php'), ENT_QUOTES, 'UTF-8');
$ppAccountingPage = htmlspecialchars(pageUrl('partner-portal-accounting.php'), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap partner-portal-docs-page agency-detail-page" dir="ltr" lang="en">
    <header class="partner-portal-header-mega glass-card">
        <?php include __DIR__ . '/../includes/partner-portal-marketing-strip.php'; ?>
        <div class="partner-portal-docs-page-intro">
            <div class="partner-portal-agency-intro-top">
                <a class="muted-btn partner-portal-docs-back" href="<?php echo $ppPortalHome; ?>#partner-portal-dashboard">← Back to dashboard</a>
                <a class="muted-btn partner-portal-agency-logout" href="<?php echo htmlspecialchars(pageUrl('partner-portal-logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Log out</a>
            </div>
            <div class="partner-portal-docs-page-intro-text">
                <p class="partner-portal-kicker">Partner portal</p>
                <h1 class="partner-portal-title partner-portal-docs-page-h1">Agency &amp; contracts</h1>
                <p id="ppAgencyContractsSub" class="partner-portal-docs-subline">Loading…</p>
            </div>
        </div>
    </header>

    <div id="ppError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <?php include __DIR__ . '/../includes/partner-portal-nav.php'; ?>

    <div id="partner-portal-section-overview" class="partner-portal-overview-stack partner-portal-anchor-target">
        <div class="agency-detail-grid">
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

        <section class="agency-detail-card glass-card partner-portal-ledger-in-overview" aria-labelledby="ppOvAcctHeading">
            <div class="agency-detail-card-head partner-portal-ledger-in-overview-head">
                <h2 id="ppOvAcctHeading" class="agency-detail-card-title">
                    <span class="agency-detail-card-icon" aria-hidden="true">📊</span> Account statement (Ratib Pro)
                </h2>
                <a class="muted-btn partner-portal-ledger-full-link" href="<?php echo $ppAccountingPage; ?>">Full screen →</a>
            </div>
            <p class="agency-detail-note">Posted journal lines on the chart account your office linked to this partnership. Read-only; same data your office sees in accounting.</p>
            <p id="ppOvAcctSummary" class="agency-detail-note partner-portal-ledger-summary">Loading…</p>
            <div class="agency-accounting-filters glass-card partner-portal-ledger-filters" id="ppOvAcctFilters" hidden>
                <label class="agency-accounting-date-label">From <input type="date" id="ppOvAcctStart" class="agency-accounting-date-input" autocomplete="off"></label>
                <label class="agency-accounting-date-label">To <input type="date" id="ppOvAcctEnd" class="agency-accounting-date-input" autocomplete="off"></label>
                <button type="button" class="neon-btn agency-accounting-refresh" id="ppOvAcctRefreshBtn">Refresh</button>
            </div>
            <div id="ppOvAcctBalances" class="agency-accounting-balances glass-card partner-portal-ledger-balances is-hidden" hidden></div>
            <div id="ppOvAcctChartWrap" class="agency-accounting-chart-wrap glass-card partner-portal-ledger-chart is-hidden" hidden lang="en">
                <h3 class="agency-accounting-chart-heading">Monthly debit and credit (SAR)</h3>
                <p class="agency-accounting-chart-note">English summary for the selected range.</p>
                <p id="ppOvAcctChartEmpty" class="agency-accounting-chart-empty" hidden></p>
                <div class="agency-accounting-chart-canvas partner-portal-ledger-chart-canvas">
                    <canvas id="ppOvAcctChart" aria-label="Monthly debit and credit"></canvas>
                </div>
            </div>
            <div id="ppOvAcctTableWrap" class="agency-accounting-table-wrap glass-card partner-portal-ledger-table is-hidden" hidden>
                <table class="agency-accounting-table" id="ppOvAcctTable">
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
                    <tbody id="ppOvAcctTbody"></tbody>
                </table>
            </div>
            <p id="ppOvAcctHint" class="agency-detail-note agency-accounting-hint glass-card partner-portal-ledger-hint is-hidden" hidden></p>
        </section>
    </div>
</div>

<?php include __DIR__ . '/../includes/partner-portal-modals.php'; ?>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
