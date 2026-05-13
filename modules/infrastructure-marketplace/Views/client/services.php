<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(__DIR__, 4) . '/includes/config.php';
require_once dirname(__DIR__, 4) . '/modules/client-dashboard/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$embedMode = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
$compatMode = isset($_GET['compatibility']) && (string) $_GET['compatibility'] === '1';
$controlMode = isset($_GET['control']) && (string) $_GET['control'] === '1';
if (!$embedMode && !$compatMode && !$controlMode && ratib_client_dashboard_can_access()) {
    $canonicalQuery = $_GET;
    unset($canonicalQuery['embed'], $canonicalQuery['compatibility']);
    $canonicalQuery['source'] = 'legacy_infra_services';
    header('Location: ' . ratib_nav_url('client/services.php', http_build_query($canonicalQuery)), true, 302);
    exit;
}
$clientServicesJsPath = dirname(__DIR__, 2) . '/Assets/js/client-services.js';
clearstatcache(true, $clientServicesJsPath);
$clientServicesJsV = (int) (@filemtime($clientServicesJsPath) ?: time());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Services</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view<?php echo $embedMode ? ' ratib-infra-marketplace-embed' : ''; ?>">
<main class="infra-market-wrap">
    <?php if (!$embedMode): ?>
    <header>
        <h1>My Services</h1>
        <p>Provisioning progress, renewals, and lifecycle status inside the unified client experience.</p>
    </header>
    <?php endif; ?>
    <section class="infra-market-grid">
        <article class="infra-market-card">
            <h3>Active Services</h3>
            <div id="infra-client-services">Loading...</div>
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

