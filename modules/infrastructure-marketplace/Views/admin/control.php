<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (empty($_SESSION['infra_control_csrf_token']) || !is_string($_SESSION['infra_control_csrf_token'])) {
    try {
        $_SESSION['infra_control_csrf_token'] = bin2hex(random_bytes(32));
    } catch (\Throwable $e) {
        $_SESSION['infra_control_csrf_token'] = sha1((string) microtime(true) . (string) mt_rand());
    }
}
$infraControlCsrfToken = (string) $_SESSION['infra_control_csrf_token'];

$bindings = ModuleConfig::providerBindings();
$allowlist = ModuleConfig::rolloutTenantAllowlist();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            <form method="post" action="/api/infrastructure-marketplace/control-update.php" target="_blank" rel="noopener">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($infraControlCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="source" value="ui">
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
                <p><button class="infra-btn" type="submit">Save Runtime Controls</button></p>
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
            <h3>Provider Toggle Snapshot</h3>
            <p><strong>hosting sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('hosting') ? 'on' : 'off'; ?></p>
            <p><strong>hosting live:</strong> <?php echo ModuleConfig::providerLiveEnabled('hosting') ? 'on' : 'off'; ?></p>
            <p><strong>dns sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('dns') ? 'on' : 'off'; ?></p>
            <p><strong>dns live:</strong> <?php echo ModuleConfig::providerLiveEnabled('dns') ? 'on' : 'off'; ?></p>
            <p><strong>registrar sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('registrar') ? 'on' : 'off'; ?></p>
            <p><strong>registrar live:</strong> <?php echo ModuleConfig::providerLiveEnabled('registrar') ? 'on' : 'off'; ?></p>
            <p><strong>ssl sandbox:</strong> <?php echo ModuleConfig::providerSandboxEnabled('ssl') ? 'on' : 'off'; ?></p>
            <p><strong>ssl live:</strong> <?php echo ModuleConfig::providerLiveEnabled('ssl') ? 'on' : 'off'; ?></p>
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
</body>
</html>
