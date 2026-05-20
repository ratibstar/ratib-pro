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

$ratibAdminControlCss = '/modules/infrastructure-marketplace/Assets/css/infrastructure-admin-control.css';
$ratibAdminControlCssPath = dirname(__DIR__, 2) . '/Assets/css/infrastructure-admin-control.css';
$ratibAdminControlV = is_file($ratibAdminControlCssPath) ? (string) @filemtime($ratibAdminControlCssPath) : '1';
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($ratibAdminControlCss, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo htmlspecialchars($ratibAdminControlV, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ratib-infra-marketplace-scope ratib-infra-marketplace-view ratib-infra-admin-embed">
<main class="infra-market-wrap infra-control-page">
    <div class="infra-control-hero">
        <h1>Infrastructure Control Center</h1>
        <p>Operational flags, rollout, provider execution, Namecheap credentials (runtime file), and operator shortcuts.</p>
    </div>

    <div class="infra-control-layout">
        <article class="infra-market-card infra-control-form-card">
            <h3>Apply runtime controls</h3>
            <p class="infra-form-hint">Persists to <code>/storage/infrastructure-marketplace/runtime-overrides.json</code> (override via <code>RATIB_INFRA_RUNTIME_OVERRIDES_PATH</code>)</p>
            <form id="infra-runtime-controls-form" method="post" action="/api/infrastructure-marketplace/control-update.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($infraControlCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="source" value="ui">
                <input type="hidden" name="runtime_controls_submit" value="1">

                <div class="infra-form-section">
                    <h4 class="infra-market-card__subhead">Module &amp; queue</h4>
                    <div class="infra-field-grid infra-field-grid--2">
                        <div class="infra-check"><label><input type="checkbox" name="enabled" value="1" <?php echo ModuleConfig::isModuleEnabled() ? 'checked' : ''; ?>> Module enabled</label></div>
                        <div class="infra-check"><label><input type="checkbox" name="dry_run" value="1" <?php echo ModuleConfig::dryRunMode() ? 'checked' : ''; ?>> Dry-run mode</label></div>
                        <div class="infra-check"><label><input type="checkbox" name="execution_kill_switch" value="1" <?php echo ModuleConfig::executionKillSwitch() ? 'checked' : ''; ?>> Execution kill-switch</label></div>
                    </div>
                    <div class="infra-field-grid infra-field-grid--2" style="margin-top:0.75rem;">
                        <div class="infra-field">
                            <label for="queue_driver">Queue driver</label>
                            <select id="queue_driver" name="queue_driver">
                                <?php $qd = ModuleConfig::defaultQueueDriver(); ?>
                                <option value="sync" <?php echo $qd === 'sync' ? 'selected' : ''; ?>>sync</option>
                                <option value="database" <?php echo $qd === 'database' ? 'selected' : ''; ?>>database</option>
                                <option value="redis" <?php echo $qd === 'redis' ? 'selected' : ''; ?>>redis</option>
                            </select>
                        </div>
                        <div class="infra-field">
                            <label for="queue_max_attempts">Queue max attempts</label>
                            <input id="queue_max_attempts" type="number" min="1" step="1" name="queue_max_attempts" value="<?php echo ModuleConfig::queueMaxAttempts(); ?>">
                        </div>
                        <div class="infra-field">
                            <label for="queue_pressure_threshold">Queue pressure threshold</label>
                            <input id="queue_pressure_threshold" type="number" min="100" step="1" name="queue_pressure_threshold" value="<?php echo ModuleConfig::queuePressureThreshold(); ?>">
                        </div>
                        <div class="infra-field">
                            <label for="worker_max_loop_jobs">Worker max loop jobs</label>
                            <input id="worker_max_loop_jobs" type="number" min="1" step="1" name="worker_max_loop_jobs" value="<?php echo ModuleConfig::workerMaxLoopJobs(); ?>">
                        </div>
                        <div class="infra-field">
                            <label for="default_currency">Default currency</label>
                            <input id="default_currency" type="text" maxlength="3" name="default_currency" value="<?php echo htmlspecialchars(ModuleConfig::defaultMarketplaceCurrency(), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="infra-field">
                            <label for="cpanel_base_url">cPanel WHM base URL</label>
                            <input id="cpanel_base_url" type="url" name="cpanel_base_url" value="<?php echo htmlspecialchars((string) (ModuleConfig::cpanelWhmBaseUrl() ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://whm.example.com:2087">
                        </div>
                        <div class="infra-field">
                            <label for="cpanel_username">cPanel WHM username</label>
                            <input id="cpanel_username" type="text" name="cpanel_username" value="<?php echo htmlspecialchars((string) (ModuleConfig::cpanelWhmUsername() ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="infra-field">
                            <label for="cpanel_api_token">cPanel WHM API token</label>
                            <input id="cpanel_api_token" type="password" name="cpanel_api_token" value="" placeholder="Leave blank to keep existing" autocomplete="new-password">
                        </div>
                        <div class="infra-field" style="grid-column: 1 / -1;">
                            <label for="tenant_allowlist">Tenant allowlist (comma-separated IDs)</label>
                            <input id="tenant_allowlist" type="text" name="tenant_allowlist" value="<?php echo htmlspecialchars(implode(',', $allowlist), ENT_QUOTES, 'UTF-8'); ?>" placeholder="empty = all tenants">
                        </div>
                    </div>
                </div>

                <div class="infra-form-section">
                    <h4 class="infra-market-card__subhead">Provider execution (panel overrides)</h4>
                    <p class="infra-domain-lead">Optional overrides for env-based <code>RATIB_INFRA_PROVIDER_*</code> flags. Choose <strong>Inherit</strong> to use server environment only.</p>
                    <table class="infra-flags-table">
                        <thead>
                            <tr>
                                <th scope="col">Provider</th>
                                <th scope="col">Live API</th>
                                <th scope="col">Sandbox</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pfProviders = [
                                'namecheap' => 'Namecheap (registrar)',
                                'cloudflare_dns' => 'Cloudflare DNS',
                                'letsencrypt_ssl' => "Let’s Encrypt (SSL)",
                            ];
                            foreach ($pfProviders as $pkey => $plabel) {
                                $sl = $ratibPfSel($ratibPf, $pkey, 'live');
                                $ss = $ratibPfSel($ratibPf, $pkey, 'sandbox');
                                ?>
                            <tr>
                                <td><?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <select name="pf_<?php echo htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8'); ?>_live" aria-label="<?php echo htmlspecialchars($plabel . ' live', ENT_QUOTES, 'UTF-8'); ?>">
                                        <option value="" <?php echo $sl === '' ? 'selected' : ''; ?>>Inherit</option>
                                        <option value="1" <?php echo $sl === '1' ? 'selected' : ''; ?>>On</option>
                                        <option value="0" <?php echo $sl === '0' ? 'selected' : ''; ?>>Off</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="pf_<?php echo htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8'); ?>_sandbox" aria-label="<?php echo htmlspecialchars($plabel . ' sandbox', ENT_QUOTES, 'UTF-8'); ?>">
                                        <option value="" <?php echo $ss === '' ? 'selected' : ''; ?>>Inherit</option>
                                        <option value="1" <?php echo $ss === '1' ? 'selected' : ''; ?>>On</option>
                                        <option value="0" <?php echo $ss === '0' ? 'selected' : ''; ?>>Off</option>
                                    </select>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="infra-form-section">
                    <h4 class="infra-market-card__subhead">Namecheap API (runtime file)</h4>
                    <p class="infra-domain-lead">Overrides env / secret manager for availability checks. API key: leave blank to keep current.</p>
                    <div class="infra-field-grid infra-field-grid--2">
                        <div class="infra-field">
                            <label for="nc_api_user">API user</label>
                            <input id="nc_api_user" type="text" name="nc_api_user" value="<?php echo htmlspecialchars((string) ($ratibNcRt['api_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="infra-field">
                            <label for="nc_api_key">API key</label>
                            <input id="nc_api_key" type="password" name="nc_api_key" value="" placeholder="Leave blank to keep existing" autocomplete="new-password">
                        </div>
                        <div class="infra-field">
                            <label for="nc_username">Username</label>
                            <input id="nc_username" type="text" name="nc_username" value="<?php echo htmlspecialchars((string) ($ratibNcRt['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                        <div class="infra-field">
                            <label for="nc_client_ip">Client IP (allowlisted)</label>
                            <input id="nc_client_ip" type="text" name="nc_client_ip" value="<?php echo htmlspecialchars((string) ($ratibNcRt['client_ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="infra-form-actions">
                    <button class="infra-btn infra-btn--primary" type="submit">Save runtime controls</button>
                </div>
            </form>
        </article>

        <div class="infra-status-stack">
            <article class="infra-market-card">
                <h3>Effective · runtime</h3>
                <dl class="infra-kv-list">
                    <dt>Module enabled</dt><dd><?php echo ModuleConfig::isModuleEnabled() ? 'yes' : 'no'; ?></dd>
                    <dt>Dry-run</dt><dd><?php echo ModuleConfig::dryRunMode() ? 'on' : 'off'; ?></dd>
                    <dt>Kill-switch</dt><dd><?php echo ModuleConfig::executionKillSwitch() ? 'on' : 'off'; ?></dd>
                    <dt>Queue driver</dt><dd><?php echo htmlspecialchars(ModuleConfig::defaultQueueDriver(), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>Queue max attempts</dt><dd><?php echo ModuleConfig::queueMaxAttempts(); ?></dd>
                    <dt>Dead-letter state</dt><dd><?php echo htmlspecialchars(ModuleConfig::queueDeadLetterState(), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>Worker max jobs</dt><dd><?php echo ModuleConfig::workerMaxLoopJobs(); ?></dd>
                    <dt>Pressure threshold</dt><dd><?php echo ModuleConfig::queuePressureThreshold(); ?></dd>
                </dl>
            </article>

            <article class="infra-market-card">
                <h3>Rollout &amp; hosting hooks</h3>
                <dl class="infra-kv-list">
                    <dt>Tenant allowlist</dt><dd><?php echo $allowlist === [] ? 'All tenants' : htmlspecialchars(implode(', ', $allowlist), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>Default currency</dt><dd><?php echo htmlspecialchars(ModuleConfig::defaultMarketplaceCurrency(), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>Lock TTL (s)</dt><dd><?php echo ModuleConfig::workerLockTtlSeconds(); ?></dd>
                    <dt>Provider bindings</dt><dd><?php echo count($bindings); ?> configured</dd>
                    <dt>cPanel URL</dt><dd><?php echo ModuleConfig::cpanelWhmBaseUrl() !== null ? 'set' : 'missing'; ?></dd>
                    <dt>cPanel user</dt><dd><?php echo htmlspecialchars((string) SecretManager::masked(ModuleConfig::cpanelWhmUsername()), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt>cPanel token</dt><dd><?php echo ModuleConfig::cpanelWhmToken() !== null ? 'set (masked)' : 'missing'; ?></dd>
                </dl>
            </article>

            <article class="infra-market-card">
                <h3>Provider execution (effective)</h3>
                <dl class="infra-kv-list">
                    <dt>Namecheap</dt><dd>live <?php echo ModuleConfig::providerLiveEnabled('namecheap') ? 'on' : 'off'; ?> · sandbox <?php echo ModuleConfig::providerSandboxEnabled('namecheap') ? 'on' : 'off'; ?></dd>
                    <dt>Cloudflare DNS</dt><dd>live <?php echo ModuleConfig::providerLiveEnabled('cloudflare_dns') ? 'on' : 'off'; ?> · sandbox <?php echo ModuleConfig::providerSandboxEnabled('cloudflare_dns') ? 'on' : 'off'; ?></dd>
                    <dt>Let’s Encrypt</dt><dd>live <?php echo ModuleConfig::providerLiveEnabled('letsencrypt_ssl') ? 'on' : 'off'; ?> · sandbox <?php echo ModuleConfig::providerSandboxEnabled('letsencrypt_ssl') ? 'on' : 'off'; ?></dd>
                    <dt>Namecheap in panel file</dt><dd><?php echo ModuleConfig::namecheapSecretFromRuntime('api_user') ? 'API user set' : '—'; ?> · <?php echo ModuleConfig::namecheapSecretFromRuntime('client_ip') ? 'client IP set' : '—'; ?></dd>
                </dl>
            </article>
        </div>

        <article class="infra-market-card infra-control-full">
            <h3>Operator shortcuts</h3>
            <p class="infra-form-hint">Opens in a new tab · JSON or HTML diagnostics</p>
            <div class="infra-shortcuts">
                <a class="infra-btn" href="/api/infrastructure-marketplace/health.php" target="_blank" rel="noopener">Health</a>
                <a class="infra-btn" href="/api/infrastructure-marketplace/dashboard.php" target="_blank" rel="noopener">Dashboard API</a>
                <a class="infra-btn" href="/api/infrastructure-marketplace/prelaunch-health.php" target="_blank" rel="noopener">Prelaunch</a>
                <a class="infra-btn" href="/api/infrastructure-marketplace/providers.php" target="_blank" rel="noopener">Providers JSON</a>
                <a class="infra-btn" href="/api/infrastructure-marketplace/ops-queue.php" target="_blank" rel="noopener">Queue ops</a>
                <a class="infra-btn" href="/api/infrastructure-marketplace/deployment-audit.php" target="_blank" rel="noopener">Deploy audit</a>
                <a class="infra-btn" href="/modules/infrastructure-marketplace/Views/admin/dashboard.php" target="_blank" rel="noopener">Admin dashboard</a>
                <a class="infra-btn" href="/modules/infrastructure-marketplace/Views/admin/providers.php" target="_blank" rel="noopener">Provider management</a>
            </div>
        </article>
    </div>
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
          alert('Saved. Reload this page to refresh summaries.');
        } else {
          var msg = j.message || 'Save failed';
          if (j.detail) {
            msg += '\n\n' + j.detail;
          }
          if (j.hint) {
            msg += '\n\n' + j.hint;
          }
          alert(msg);
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
