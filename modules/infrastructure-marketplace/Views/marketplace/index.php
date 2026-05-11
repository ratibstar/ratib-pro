<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(__DIR__, 2) . '/bootstrap.php';
$marketplaceJsPath = dirname(__DIR__, 2) . '/Assets/js/marketplace-catalog.js';
clearstatcache(true, $marketplaceJsPath);
$marketplaceJsV = (int) (@filemtime($marketplaceJsPath) ?: time());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Infrastructure Marketplace</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <header>
        <h1>Infrastructure Marketplace</h1>
        <p>Hosting, domains, SSL, DNS, and future VPS catalog in tenant-safe mode.</p>
    </header>
    <section class="infra-market-card">
        <h3>Provisioning Status</h3>
        <p id="infra-market-notice">No recent provisioning events.</p>
    </section>
    <section id="infra-market-catalog" class="infra-market-grid">
        <article class="infra-market-card"><p>Loading catalog...</p></article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/marketplace-catalog.js?v=<?php echo (int) $marketplaceJsV; ?>"></script>
</body>
</html>

