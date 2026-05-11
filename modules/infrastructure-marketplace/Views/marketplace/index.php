<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
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
    <section id="infra-market-catalog" class="infra-market-grid">
        <article class="infra-market-card"><p>Loading catalog...</p></article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/marketplace-catalog.js"></script>
</body>
</html>

