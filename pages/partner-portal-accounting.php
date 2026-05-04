<?php
/**
 * Partner portal — account statement linked to Ratib Pro chart of accounts (read-only, English).
 */
require_once __DIR__ . '/../includes/config.php';

if (!ratib_partner_portal_session_is_valid()) {
    header('Location: ' . pageUrl('partner-portal-login.php'));
    exit;
}

$pageTitle = 'Account statement';
$v = time();
$pageCss = [
    asset('css/partnerships.css') . '?v=' . $v,
    asset('css/partnerships-agency-detail.css') . '?v=' . $v,
    asset('css/partner-portal.css') . '?v=' . $v,
];
$pageJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    asset('js/partnerships/partner-portal-accounting.js') . '?v=' . $v,
];
$partnerPortalMinimal = true;
$partnerPortalNavActive = 'accounting';
$ppPortalHome = htmlspecialchars(pageUrl('partner-portal.php'), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../includes/partner-portal-header.php';
?>

<div class="partner-portal-wrap partner-portal-accounting-page agency-detail-page" dir="ltr" lang="en">
    <header class="partner-portal-header-mega glass-card">
        <?php include __DIR__ . '/../includes/partner-portal-marketing-strip.php'; ?>
        <div class="partner-portal-docs-page-intro">
            <a class="muted-btn partner-portal-docs-back" href="<?php echo $ppPortalHome; ?>">← Back to partner portal</a>
            <div class="partner-portal-docs-page-intro-text">
                <p class="partner-portal-kicker">Partner portal · Ratib Pro accounting</p>
                <h1 class="partner-portal-title partner-portal-docs-page-h1">Account statement</h1>
                <p id="ppAcctSub" class="partner-portal-docs-subline">Loading…</p>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/../includes/partner-portal-nav.php'; ?>

    <div id="ppAcctError" class="partner-portal-error glass-card is-hidden" hidden></div>

    <div class="agency-accounting-toolbar glass-card">
        <div class="agency-accounting-toolbar-text">
            <p class="agency-accounting-title">Ledger link</p>
            <p id="ppAcctLinkSummary" class="agency-detail-note agency-accounting-summary">Loading…</p>
            <p class="agency-detail-note agency-accounting-summary partner-portal-acct-posted-note">Only <strong>posted</strong> amounts appear. Payment vouchers and journal entries still in <strong>Draft</strong> are excluded until your office posts them.</p>
        </div>
    </div>

    <div class="agency-accounting-filters glass-card" id="ppAcctFilters" hidden>
        <label class="agency-accounting-date-label">From <input type="date" id="ppAcctStart" class="agency-accounting-date-input"></label>
        <label class="agency-accounting-date-label">To <input type="date" id="ppAcctEnd" class="agency-accounting-date-input"></label>
        <button type="button" class="neon-btn agency-accounting-refresh" id="ppAcctRefreshBtn">Refresh</button>
    </div>

    <div id="ppAcctBalances" class="agency-accounting-balances glass-card is-hidden" hidden></div>

    <div id="ppAcctChartWrap" class="agency-accounting-chart-wrap glass-card is-hidden" hidden lang="en">
        <h3 class="agency-accounting-chart-heading">Monthly debit and credit (SAR)</h3>
        <p class="agency-accounting-chart-note">Summary of posted journal lines on your linked chart account.</p>
        <p id="ppAcctChartEmpty" class="agency-accounting-chart-empty" hidden></p>
        <div class="agency-accounting-chart-canvas">
            <canvas id="ppAcctChart" aria-label="Monthly debit and credit"></canvas>
        </div>
    </div>

    <div id="ppAcctTableWrap" class="agency-accounting-table-wrap glass-card is-hidden" hidden>
        <table class="agency-accounting-table" id="ppAcctTable">
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
            <tbody id="ppAcctTbody"></tbody>
        </table>
    </div>

    <p id="ppAcctHint" class="agency-detail-note agency-accounting-hint glass-card is-hidden" hidden></p>
</div>

<?php include __DIR__ . '/../includes/partner-portal-footer.php'; ?>
