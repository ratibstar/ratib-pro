<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

$ratebInfraControlPanelConfig = dirname(__DIR__, 4) . '/control-panel/includes/config.php';
if (is_file($ratebInfraControlPanelConfig)) {
    require_once $ratebInfraControlPanelConfig;
}
ControlSecurityGuard::ensureInfraCsrfSessionToken();
$ratebInfraAdminCsrf = (string) ($_SESSION['infra_control_csrf_token'] ?? '');

$ratebAdminControlCss = '/modules/infrastructure-marketplace/Assets/css/infrastructure-admin-control.css';
$ratebAdminControlCssPath = dirname(__DIR__, 2) . '/Assets/css/infrastructure-admin-control.css';
$ratebAdminControlV = is_file($ratebAdminControlCssPath) ? (string) @filemtime($ratebAdminControlCssPath) : '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($ratebInfraAdminCsrf !== ''): ?>
    <meta name="infra-control-csrf" content="<?php echo htmlspecialchars($ratebInfraAdminCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title>Infrastructure Provider Management</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($ratebAdminControlCss, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo htmlspecialchars($ratebAdminControlV, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="rateb-infra-marketplace-scope rateb-infra-marketplace-view rateb-infra-admin-embed">
<main class="infra-market-wrap infra-control-page">
    <div class="infra-control-hero infra-providers-hero">
        <h1>Provider management</h1>
        <p>Health payloads, capability discovery, and activation rows for registrar/DNS/SSL adapters.</p>
    </div>

    <p id="infra-provider-notice" class="infra-provider-notice" hidden></p>

    <div class="infra-control-layout">
        <article class="infra-market-card">
            <h3>Provider health</h3>
            <pre id="infra-provider-health" class="infra-code-block">Loading...</pre>
        </article>
        <article class="infra-market-card">
            <h3>Capability discovery</h3>
            <pre id="infra-provider-capability" class="infra-code-block">Loading...</pre>
        </article>
    </div>

    <article class="infra-market-card infra-control-full infra-providers-form" style="margin-top: 1rem;">
        <h3>Database activations (<code>rateb_infra_provider_activations</code>)</h3>
        <p class="infra-domain-lead">Enable/disable rows and fix <strong>provider_class</strong> (concrete PHP class, e.g. Namecheap adapter).</p>
        <pre id="infra-provider-activations" class="infra-code-block">Loading…</pre>
        <h4 class="infra-market-card__subhead">Upsert activation</h4>
        <form id="infra-provider-upsert-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($ratebInfraAdminCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="infra-field-grid infra-field-grid--2">
                <div class="infra-field">
                    <label for="provider_type">Type</label>
                    <select id="provider_type" name="provider_type" required>
                        <option value="hosting">hosting</option>
                        <option value="registrar" selected>registrar</option>
                        <option value="dns">dns</option>
                        <option value="ssl">ssl</option>
                    </select>
                </div>
                <div class="infra-field">
                    <label for="provider_code">Code</label>
                    <input id="provider_code" name="provider_code" type="text" value="namecheap" required maxlength="64">
                </div>
                <div class="infra-field" style="grid-column: 1 / -1;">
                    <label for="provider_class">Class</label>
                    <input id="provider_class" name="provider_class" type="text" required value="RATEB\InfrastructureMarketplace\Registrars\Adapters\NamecheapRegistrarAdapter">
                </div>
                <div class="infra-field">
                    <label for="priority_weight">Priority</label>
                    <input id="priority_weight" name="priority_weight" type="number" value="100" min="1" step="1">
                </div>
                <div class="infra-check" style="align-self: end;">
                    <label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>
                </div>
                <div class="infra-field">
                    <label for="tenant_id">Tenant ID (optional)</label>
                    <input id="tenant_id" name="tenant_id" type="text" placeholder="empty = global">
                </div>
                <div class="infra-field">
                    <label for="agency_id">Agency ID (optional)</label>
                    <input id="agency_id" name="agency_id" type="text" placeholder="empty = global">
                </div>
            </div>
            <div class="infra-form-actions">
                <button type="submit" class="infra-btn infra-btn--primary">Save activation</button>
            </div>
        </form>
    </article>
</main>
<script src="/modules/infrastructure-marketplace/Assets/js/admin-providers.js"></script>
</body>
</html>
