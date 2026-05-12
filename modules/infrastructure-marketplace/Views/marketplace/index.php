<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(__DIR__, 2) . '/bootstrap.php';
$baseModule = '/modules/infrastructure-marketplace';
$marketplaceJsPath = dirname(__DIR__, 2) . '/Assets/js/marketplace-catalog.js';
$domainsJsPath = dirname(__DIR__, 2) . '/Assets/js/marketplace-domains.js';
clearstatcache(true, $marketplaceJsPath);
clearstatcache(true, $domainsJsPath);
$marketplaceJsV = (int) (@filemtime($marketplaceJsPath) ?: time());
$domainsJsV = (int) (@filemtime($domainsJsPath) ?: time());
$focusDomains = isset($_GET['focus']) && strtolower((string) $_GET['focus']) === 'domains';
$embedMode = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
/* Public-facing: avoid "Infrastructure Marketplace" in title — same page as header "Find a domain". */
$pageTitle = $focusDomains
    ? 'Find a domain — RATIB'
    : 'Domains & services — RATIB';
$h1Text = $focusDomains ? 'Find a domain' : 'Domains & services';
$headerLead = $focusDomains
    ? 'Search domain availability, then use the catalog below for infrastructure offers when enabled.'
    : 'Domain search, provisioning catalog, and status — one place for RATIB infrastructure services.';
$domainSectionH2 = $focusDomains ? 'Availability search' : 'Domains';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseModule, ENT_QUOTES, 'UTF-8'); ?>/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseModule, ENT_QUOTES, 'UTF-8'); ?>/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view<?php echo $embedMode ? ' ratib-infra-marketplace-embed' : ''; ?>">
<main class="infra-market-wrap">
    <?php if (!$embedMode): ?>
    <header>
        <h1><?php echo htmlspecialchars($h1Text, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars($headerLead, ENT_QUOTES, 'UTF-8'); ?></p>
    </header>
    <?php endif; ?>

    <section id="infra-domain-search" class="infra-market-card infra-domain-hub" aria-labelledby="infra-domain-heading">
        <h2 id="infra-domain-heading"><?php echo htmlspecialchars($domainSectionH2, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="infra-domain-lead">Search availability across registrar providers activated in Control Panel. Successful checkout flows still require catalog SKUs, payments, and fulfillment wiring.</p>
        <form id="infra-domain-search-form" class="infra-domain-search-form" autocomplete="off">
            <label class="visually-hidden" for="infra-domain-q">Domain keyword</label>
            <input id="infra-domain-q" name="q" type="text" placeholder="yourbrand" maxlength="253" required />
            <button type="submit" class="infra-btn infra-btn--primary">Search availability</button>
        </form>
        <p id="infra-domain-search-hint" class="infra-domain-hint" role="status"></p>
        <div id="infra-domain-results" class="infra-domain-results" aria-live="polite"></div>
    </section>

    <section class="infra-market-card">
        <h3>Provisioning Status</h3>
        <p id="infra-market-notice">No recent provisioning events.</p>
    </section>
    <section id="infra-market-catalog" class="infra-market-grid">
        <article class="infra-market-card"><p>Loading catalog...</p></article>
    </section>
</main>
<script>window.RATIB_INFRA_API_ROOT = '';</script>
<script src="<?php echo htmlspecialchars($baseModule, ENT_QUOTES, 'UTF-8'); ?>/Assets/js/marketplace-domains.js?v=<?php echo (int) $domainsJsV; ?>"></script>
<script src="<?php echo htmlspecialchars($baseModule, ENT_QUOTES, 'UTF-8'); ?>/Assets/js/marketplace-catalog.js?v=<?php echo (int) $marketplaceJsV; ?>"></script>
</body>
</html>

