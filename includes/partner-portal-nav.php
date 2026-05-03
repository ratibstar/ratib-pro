<?php
/**
 * Shared partner portal navigation (home, agency & contracts, documents, accounting).
 * Set $partnerPortalNavActive to 'home', 'agency', 'documents', or 'accounting' before including.
 */
$ppNavActive = isset($partnerPortalNavActive) ? (string) $partnerPortalNavActive : 'home';
$ppNavHome = pageUrl('partner-portal.php');
$ppNavAgencyContracts = pageUrl('partner-portal-agency-contracts.php');
$ppNavDocs = pageUrl('partner-portal-documents.php');
$ppNavAccounting = pageUrl('partner-portal-accounting.php');
?>
<nav class="partner-portal-main-nav glass-card" aria-label="Partner portal navigation">
    <div class="partner-portal-main-nav__inner">
        <span class="partner-portal-main-nav__title"><span class="partner-portal-main-nav__title-icon" aria-hidden="true">🧭</span> Menu</span>
        <ul class="partner-portal-main-nav__list">
            <li>
                <a class="partner-portal-main-nav__link"
                   data-pp-nav-spy="dashboard"
                   href="<?php echo htmlspecialchars($ppNavHome, ENT_QUOTES, 'UTF-8'); ?>#partner-portal-dashboard">Dashboard</a>
            </li>
            <li>
                <a class="partner-portal-main-nav__link<?php echo $ppNavActive === 'agency' ? ' is-active' : ''; ?>"
                   href="<?php echo htmlspecialchars($ppNavAgencyContracts, ENT_QUOTES, 'UTF-8'); ?>">Agency &amp; contracts</a>
            </li>
            <li>
                <a class="partner-portal-main-nav__link<?php echo $ppNavActive === 'documents' ? ' is-active' : ''; ?>"
                   data-pp-nav-spy="documents"
                   href="<?php echo htmlspecialchars($ppNavDocs, ENT_QUOTES, 'UTF-8'); ?>">Documents &amp; CVs</a>
            </li>
            <li>
                <a class="partner-portal-main-nav__link"
                   data-pp-nav-spy="worker-docs"
                   href="<?php echo htmlspecialchars($ppNavHome, ENT_QUOTES, 'UTF-8'); ?>#partner-portal-section-worker-docs">Worker documents</a>
            </li>
            <li>
                <a class="partner-portal-main-nav__link<?php echo $ppNavActive === 'accounting' ? ' is-active' : ''; ?>"
                   href="<?php echo htmlspecialchars($ppNavAccounting, ENT_QUOTES, 'UTF-8'); ?>">Account statement</a>
            </li>
        </ul>
    </div>
</nav>
