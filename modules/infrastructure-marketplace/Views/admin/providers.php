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
    <title>Infrastructure Provider Management</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <h1>Provider Management</h1>
    <p id="infra-provider-notice" class="infra-provider-notice" hidden></p>
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

    <section class="infra-market-card" style="margin-top: 1rem;">
        <h3>Database activations (<code>ratib_infra_provider_activations</code>)</h3>
        <p class="infra-domain-lead">Enable/disable rows and fix <strong>provider_class</strong> (must be a concrete PHP class, e.g. Namecheap adapter below).</p>
        <pre id="infra-provider-activations">Loading…</pre>
        <h4 class="infra-market-card__subhead">Upsert activation</h4>
        <form id="infra-provider-upsert-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($ratibInfraAdminCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <p><label>Type <select name="provider_type" required>
                <option value="hosting">hosting</option>
                <option value="registrar" selected>registrar</option>
                <option value="dns">dns</option>
                <option value="ssl">ssl</option>
            </select></label></p>
            <p><label>Code <input name="provider_code" type="text" value="namecheap" required size="20"></label></p>
            <p><label>Class <input name="provider_class" type="text" size="80" required value="Ratib\InfrastructureMarketplace\Registrars\Adapters\NamecheapRegistrarAdapter"></label></p>
            <p><label>Priority <input name="priority_weight" type="number" value="100" min="1" step="1"></label></p>
            <p><label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label></p>
            <p><label>Tenant ID (optional) <input name="tenant_id" type="text" placeholder="empty = global"></label></p>
            <p><label>Agency ID (optional) <input name="agency_id" type="text" placeholder="empty = global"></label></p>
            <p><button type="submit" class="infra-btn infra-btn--primary">Save activation</button></p>
        </form>
    </section>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/admin-providers.js"></script>
</body>
</html>

