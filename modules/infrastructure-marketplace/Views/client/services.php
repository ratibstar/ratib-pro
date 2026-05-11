<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
$clientServicesJsPath = dirname(__DIR__, 2) . '/Assets/js/client-services.js';
clearstatcache(true, $clientServicesJsPath);
$clientServicesJsV = (int) (@filemtime($clientServicesJsPath) ?: time());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Infrastructure Services</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <header>
        <h1>My Infrastructure Services</h1>
        <p>Provisioning progress, renewal indicators, and lifecycle status timeline.</p>
    </header>
    <section class="infra-market-grid">
        <article class="infra-market-card">
            <h3>Active Services</h3>
            <pre id="infra-client-services">Loading...</pre>
        </article>
        <article class="infra-market-card">
            <h3>Domain Search (placeholder)</h3>
            <p>Domain availability checks are prepared for async provider aggregation.</p>
            <?php $status = 'queued'; $label = 'PREPARED'; include dirname(__DIR__) . '/components/status-badge.php'; ?>
        </article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/client-services.js?v=<?php echo (int) $clientServicesJsV; ?>"></script>
</body>
</html>

