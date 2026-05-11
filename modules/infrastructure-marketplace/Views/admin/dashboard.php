<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Infrastructure Marketplace Dashboard</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-admin-dashboard.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-admin-page">
<main class="infra-admin-wrap">
    <header class="infra-admin-header">
        <h1>Infrastructure Marketplace</h1>
        <p>Operational status, provisioning queue, provider readiness, and catalog visibility.</p>
    </header>

    <section class="infra-grid">
        <article class="infra-card" data-card="health">
            <h2>Infrastructure Health</h2>
            <div class="infra-kv" id="infra-health">Loading...</div>
        </article>
        <article class="infra-card" data-card="queue">
            <h2>Queue Status</h2>
            <div class="infra-kv" id="infra-queue">Loading...</div>
        </article>
        <article class="infra-card" data-card="providers">
            <h2>Provider Status</h2>
            <div class="infra-kv" id="infra-providers">Loading...</div>
        </article>
        <article class="infra-card" data-card="catalog">
            <h2>Catalog Overview</h2>
            <div class="infra-kv" id="infra-catalog">Loading...</div>
        </article>
        <article class="infra-card" data-card="jobs">
            <h2>Provisioning Jobs</h2>
            <div class="infra-kv" id="infra-jobs">Loading...</div>
        </article>
        <article class="infra-card" data-card="workers">
            <h2>Worker Health</h2>
            <div class="infra-kv" id="infra-workers">Loading...</div>
        </article>
        <article class="infra-card" data-card="failed">
            <h2>Failed + Dead-Letter</h2>
            <div class="infra-kv" id="infra-failed">Loading...</div>
        </article>
        <article class="infra-card" data-card="reconcile">
            <h2>Reconciliation</h2>
            <div class="infra-kv" id="infra-reconcile">Loading...</div>
        </article>
        <article class="infra-card" data-card="diagnostics">
            <h2>Provider Diagnostics</h2>
            <div class="infra-kv" id="infra-diagnostics">Loading...</div>
        </article>
        <article class="infra-card" data-card="traces">
            <h2>Provisioning Traces</h2>
            <div class="infra-kv" id="infra-traces">Loading...</div>
        </article>
        <article class="infra-card" data-card="audit">
            <h2>Audit Timeline</h2>
            <div class="infra-kv" id="infra-audit">Loading...</div>
        </article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/infrastructure-marketplace.js"></script>
<script src="/modules/infrastructure-marketplace/Assets/js/infrastructure-admin-dashboard.js"></script>
</body>
</html>

