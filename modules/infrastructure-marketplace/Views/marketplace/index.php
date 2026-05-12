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
$pageTitle = $focusDomains ? 'Domains — Infrastructure Marketplace' : 'Infrastructure Marketplace';
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
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <header>
        <h1>Infrastructure Marketplace</h1>
        <p>Hosting, domains, SSL, DNS, and provisioning — tenant-safe catalog and domain availability search.</p>
    </header>

    <section id="infra-domain-search" class="infra-market-card infra-domain-hub" aria-labelledby="infra-domain-heading">
        <h2 id="infra-domain-heading">Domains</h2>
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

