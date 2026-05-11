<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

$ratibInfraControlPanelConfig = dirname(__DIR__, 4) . '/control-panel/includes/config.php';
if (is_file($ratibInfraControlPanelConfig)) {
    require_once $ratibInfraControlPanelConfig;
}
ControlSecurityGuard::ensureInfraCsrfSessionToken();
$ratibInfraAdminCsrf = (string) ($_SESSION['infra_control_csrf_token'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($ratibInfraAdminCsrf !== ''): ?>
    <meta name="infra-control-csrf" content="<?php echo htmlspecialchars($ratibInfraAdminCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
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
        <article class="infra-card" data-card="launch-readiness">
            <h2>Launch Readiness</h2>
            <div class="infra-kv" id="infra-launch-readiness">Loading...</div>
        </article>
        <article class="infra-card" data-card="deployment">
            <h2>Deployment Verification</h2>
            <div class="infra-kv" id="infra-deployment">Loading...</div>
        </article>
        <article class="infra-card" data-card="warnings">
            <h2>Configuration Warnings</h2>
            <div class="infra-kv" id="infra-warnings">Loading...</div>
        </article>
        <article class="infra-card" data-card="drills">
            <h2>Recovery Drill Status</h2>
            <div class="infra-kv" id="infra-drills">Loading...</div>
        </article>
        <article class="infra-card" data-card="release-history">
            <h2>Deployment Audit History</h2>
            <div class="infra-kv" id="infra-release-history">Loading...</div>
        </article>
        <article class="infra-card" data-card="rollout-scope">
            <h2>Rollout Scope Visibility</h2>
            <div class="infra-kv" id="infra-rollout-scope">Loading...</div>
        </article>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/infrastructure-marketplace.js"></script>
<script src="/modules/infrastructure-marketplace/Assets/js/infrastructure-admin-dashboard.js"></script>
</body>
</html>

