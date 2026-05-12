<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Config\RuntimeOverrideStore;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

$ratibInfraControlPanelConfig = dirname(__DIR__, 4) . '/control-panel/includes/config.php';
if (is_file($ratibInfraControlPanelConfig)) {
    require_once $ratibInfraControlPanelConfig;
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ratib_control');
    @session_start();
}

ControlSecurityGuard::ensureInfraCsrfSessionToken();
$infraControlCsrfToken = (string) ($_SESSION['infra_control_csrf_token'] ?? '');

$bindings = ModuleConfig::providerBindings();
$allowlist = ModuleConfig::rolloutTenantAllowlist();

$ratibRt = RuntimeOverrideStore::read();
$ratibPf = is_array($ratibRt['provider_flags'] ?? null) ? $ratibRt['provider_flags'] : [];
$ratibPfSel = static function (array $pf, string $provider, string $mode): string {
    if (!isset($pf[$provider][$mode])) {
        return '';
    }
    return !empty($pf[$provider][$mode]) ? '1' : '0';
};
$ratibNcRt = is_array($ratibRt['registrar_secrets']['namecheap'] ?? null) ? $ratibRt['registrar_secrets']['namecheap'] : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($infraControlCsrfToken !== ''): ?>
    <meta name="infra-control-csrf" content="<?php echo htmlspecialchars($infraControlCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title>Infrastructure Control Center</title>
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace.css">
    <link rel="stylesheet" href="/modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view">
<main class="infra-market-wrap">
    <header>
        <h1>Infrastructure Control Center</h1>
        <p>Operational flags, rollout scope, provider configuration readiness, and operator shortcuts.</p>
    </header>

    <section class="infra-market-grid">
        <article class="infra-market-card">
            <h3>Apply Runtime Controls</h3>
            <p>Updates are saved in module file: <code>/modules/infrastructure-marketplace/Config/runtime-overrides.json</code></p>
            <form id="infra-runtime-controls-form" method="post" action="/api/infrastructure-marketplace/control-update.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($infraControlCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="source" value="ui">
                <input type="hidden" name="runtime_controls_submit" value="1">
                <p><label><input type="checkbox" name="enabled" value="1" <?php echo ModuleConfig::isModuleEnabled() ? 'checked' : ''; ?>> Module enabled</label></p>
                <p><label><input type="checkbox" name="dry_run" value="1" <?php echo ModuleConfig::dryRunMode() ? 'checked' : ''; ?>> Dry-run mode</label></p>
                <p><label><input type="checkbox" name="execution_kill_switch" value="1" <?php echo ModuleConfig::executionKillSwitch() ? 'checked' : ''; ?>> Execution kill-switch</label></p>
                <p>
                    <label>Queue driver
                        <select name="queue_driver">
                            <?php $qd = ModuleConfig::defaultQueueDriver(); ?>
                            <option value="sync" <?php echo $qd === 'sync' ? 'selected' : ''; ?>>sync</option>
                            <option value="database" <?php echo $qd === 'database' ? 'selected' : ''; ?>>database</option>
                            <option value="redis" <?php echo $qd === 'redis' ? 'selected' : ''; ?>>redis</option>
                        </select>
                    </label>
                </p>
                <p><label>Queue max attempts <input type="number" min="1" step="1" name="queue_max_attempts" value="<?php echo ModuleConfig::queueMaxAttempts(); ?>"></label></p>
                <p><label>Queue pressure threshold <input type="number" min="100" step="1" name="queue_pressure_threshold" value="<?php echo ModuleConfig::queuePressureThreshold(); ?>"></label></p>
                <p><label>Worker max loop jobs <input type="number" min="1" step="1" name="worker_max_loop_jobs" value="<?php echo ModuleConfig::workerMaxLoopJobs(); ?>"></label></p>
                <p><label>Default currency <input type="text" maxlength="3" name="default_currency" value="<?php echo htmlspecialchars(ModuleConfig::defaultMarketplaceCurrency(), ENT_QUOTES, 'UTF-8'); ?>"></label></p>
                <p><label>Tenant allowlist (comma-separated IDs) <input type="text" name="tenant_allowlist" value="<?php echo htmlspecialchars(implode(',', $allowlist), ENT_QUOTES, 'UTF-8'); ?>"></label></p>

                <h4 class="infra-market-card__subhead">Provider execution flags</h4>
                <p class="infra-domain-lead">Overrides <code>RATIB_INFRA_PROVIDER_*</code> env for each adapter. <strong>Inherit</strong> removes the panel override so environment variables apply.</p>
                <?php
                $pfProviders = [
                    'namecheap' => 'Namecheap (registrar API)',
                    'cloudflare_dns' => 'Cloudflare DNS',
                    'letsencrypt_ssl' => "Let's Encrypt (SSL)",
                ];
                foreach ($pfProviders as $pkey => $plabel) {
                    $sl = $ratibPfSel($ratibPf, $pkey, 'live');
                    $ss = $ratibPfSel($ratibPf, $pkey, 'sandbox');
                    ?>
                <p><strong><?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <label>Live <select name="pf_<?php echo htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8'); ?>_live">
                        <option value="" <?php echo $sl === '' ? 'selected' : ''; ?>>Inherit (env)</option>
                        <option value="1" <?php echo $sl === '1' ? 'selected' : ''; ?>>On</option>
                        <option value="0" <?php echo $sl === '0' ? 'selected' : ''; ?>>Off</option>
                    </select></label>
                    <label style="margin-left:1rem;">Sandbox <select name="pf_<?php echo htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8'); ?>_sandbox">
                        <option value="" <?php echo $ss === '' ? 'selected' : ''; ?>>Inherit (env)</option>
                        <option value="1" <?php echo $ss === '1' ? 'selected' : ''; ?>>On</option>
                        <option value="0" <?php echo $ss === '0' ? 'selected' : ''; ?>>Off</option>
                    </select></label></p>
                <?php } ?>

                <h4 class="infra-market-card__subhead">Namecheap API (stored in runtime file)</h4>
                <p class="infra-domain-lead">Panel values override env / secret manager for domain checks. Whitelist this server’s IP in Namecheap; set Client IP to the same address.</p>
                <p><label>API user <input type="text" name="nc_api_user" value="<?php echo htmlspecialchars((string) ($ratibNcRt['api_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" size="40"></label></p>
                <p><label>API key <input type="password" name="nc_api_key" value="" placeholder="Leave blank to keep existing" autocomplete="new-password" size="40"></label></p>
                <p><label>Username <input type="text" name="nc_username" value="<?php echo htmlspecialchars((string) ($ratibNcRt['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" size="40"></label></p>
                <p><label>Client IP <input type="text" name="nc_client_ip" value="<?php echo htmlspecialchars((string) ($ratibNcRt['client_ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" size="24"></label></p>

                <p><button class="infra-btn infra-btn--primary" type="submit">Save Runtime Controls</button></p>
            </form>
        </article>

        <article class="infra-market-card">
            <h3>Runtime Controls</h3>
            <p><strong>Module enabled:</strong> <?php echo ModuleConfig::isModuleEnabled() ? 'yes' : 'no'; ?></p>
            <p><strong>Dry-run mode:</strong> <?php echo ModuleConfig::dryRunMode() ? 'on' : 'off'; ?></p>
            <p><strong>Execution kill-switch:</strong> <?php echo ModuleConfig::executionKillSwitch() ? 'on' : 'off'; ?></p>
            <p><strong>Queue driver:</strong> <?php echo htmlspecialchars(ModuleConfig::defaultQueueDriver(), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Queue max attempts:</strong> <?php echo ModuleConfig::queueMaxAttempts(); ?></p>
            <p><strong>Dead-letter state:</strong> <?php echo htmlspecialchars(ModuleConfig::queueDeadLetterState(), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Worker max loop jobs:</strong> <?php echo ModuleConfig::workerMaxLoopJobs(); ?></p>
            <p><strong>Queue pressure threshold:</strong> <?php echo ModuleConfig::queuePressureThreshold(); ?></p>
        </article>

        <article class="infra-market-card">
            <h3>Rollout Controls</h3>
            <p><strong>Tenant allowlist:</strong> <?php echo $allowlist === [] ? 'all tenants (no allowlist)' : htmlspecialchars(implode(', ', $allowlist), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Default marketplace currency:</strong> <?php echo htmlspecialchars(ModuleConfig::defaultMarketplaceCurrency(), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Lock TTL seconds:</strong> <?php echo ModuleConfig::workerLockTtlSeconds(); ?></p>
            <p><strong>Provider bindings configured:</strong> <?php echo count($bindings); ?></p>
            <p><strong>cPanel base URL:</strong> <?php echo ModuleConfig::cpanelWhmBaseUrl() !== null ? 'configured' : 'missing'; ?></p>
            <p><strong>cPanel username:</strong> <?php echo htmlspecialchars((string) SecretManager::masked(ModuleConfig::cpanelWhmUsername()), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>cPanel token:</strong> <?php echo ModuleConfig::cpanelWhmToken() !== null ? 'configured (masked)' : 'missing'; ?></p>
        </article>

        <article class="infra-market-card">
            <h3>Provider execution (effective)</h3>
            <p><strong>Namecheap · live:</strong> <?php echo ModuleConfig::providerLiveEnabled('namecheap') ? 'on' : 'off'; ?> · <strong>sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('namecheap') ? 'on' : 'off'; ?></p>
            <p><strong>Cloudflare DNS · live:</strong> <?php echo ModuleConfig::providerLiveEnabled('cloudflare_dns') ? 'on' : 'off'; ?> · <strong>sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('cloudflare_dns') ? 'on' : 'off'; ?></p>
            <p><strong>Let’s Encrypt · live:</strong> <?php echo ModuleConfig::providerLiveEnabled('letsencrypt_ssl') ? 'on' : 'off'; ?> · <strong>sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('letsencrypt_ssl') ? 'on' : 'off'; ?></p>
            <p><strong>Namecheap API (panel file):</strong> <?php echo ModuleConfig::namecheapSecretFromRuntime('api_user') ? 'API user set' : 'not in panel file'; ?> · <?php echo ModuleConfig::namecheapSecretFromRuntime('client_ip') ? 'client IP set' : 'not in panel file'; ?></p>
        </article>

        <article class="infra-market-card">
            <h3>Operator Shortcuts</h3>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/health.php" target="_blank" rel="noopener">Health API</a></p>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/dashboard.php" target="_blank" rel="noopener">Dashboard API</a></p>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/prelaunch-health.php" target="_blank" rel="noopener">Prelaunch Health</a></p>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/providers.php" target="_blank" rel="noopener">Provider Diagnostics</a></p>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/ops-queue.php" target="_blank" rel="noopener">Queue Operations</a></p>
            <p><a class="infra-btn" href="/api/infrastructure-marketplace/deployment-audit.php" target="_blank" rel="noopener">Deployment Audit</a></p>
            <p><a class="infra-btn" href="/modules/infrastructure-marketplace/Views/admin/dashboard.php" target="_blank" rel="noopener">Admin Dashboard</a></p>
            <p><a class="infra-btn" href="/modules/infrastructure-marketplace/Views/admin/providers.php" target="_blank" rel="noopener">Provider Management</a></p>
        </article>
    </section>
</main>
<script>
(function () {
  var form = document.getElementById('infra-runtime-controls-form');
  if (!form) return;
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(form);
    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().then(function (j) {
          return { ok: r.ok, json: j };
        });
      })
      .then(function (x) {
        var j = x.json || {};
        if (j.ok) {
          alert('Saved. Reload this page to refresh read-only summaries.');
        } else {
          alert(j.message || 'Save failed');
        }
      })
      .catch(function () {
        alert('Network error');
      });
  });
})();
</script>
</body>
</html>
