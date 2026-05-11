<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Infrastructure Provider Management</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <h1>Provider Management</h1>
    <section class="infra-market-grid">
        <article class="infra-market-card">
            <h3>Provider Health</h3>
            <pre id="infra-provider-health">Loading...</pre>
        </article>
        <article class="infra-market-card">
            <h3>Capability Discovery</h3>
            <pre id="infra-provider-capability">Loading...</pre>
        </article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/admin-providers.js"></script>
</body>
</html>

