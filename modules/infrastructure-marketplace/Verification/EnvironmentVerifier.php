<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Verification;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class EnvironmentVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $secret = SecretManager::withEnvProvider();
        $checks = [];
        $moduleEnabled = ModuleConfig::isModuleEnabled();
        $dryRun = ModuleConfig::dryRunMode();
        $queueDriver = ModuleConfig::defaultQueueDriver();
        $queueReady = in_array($queueDriver, ['database', 'redis'], true)
            || (!$moduleEnabled && $queueDriver === 'sync')
            || ($dryRun && $queueDriver === 'sync');
        $allowlistReady = ModuleConfig::rolloutTenantAllowlist() !== [] || !$moduleEnabled || $dryRun;
        $hasNamecheap = $this->hasNamecheapCredentials($secret);
        $hasCloudflare = $secret->getSecret('RATIB_INFRA_CLOUDFLARE', 'API_TOKEN') !== null
            || (is_string(getenv('RATIB_INFRA_CLOUDFLARE_API_TOKEN')) && trim((string) getenv('RATIB_INFRA_CLOUDFLARE_API_TOKEN')) !== '');
        $hasCpanel = ModuleConfig::cpanelWhmUsername() !== null && ModuleConfig::cpanelWhmToken() !== null;

        $checks[] = $this->check('module_enabled', $moduleEnabled, 'Module is disabled.');
        $checks[] = $this->check('queue_driver_database', $queueReady, 'Queue driver is not database/redis, and sync is only acceptable while disabled or dry-run.');
        $checks[] = $this->check('kill_switch_off', !ModuleConfig::executionKillSwitch(), 'Execution kill-switch is ON.');
        $checks[] = $this->check('dry_run_status', true, $dryRun ? 'Dry-run mode enabled (safe prelaunch).' : 'Dry-run mode disabled.');
        $checks[] = $this->check('cpanel_base_url', ModuleConfig::cpanelWhmBaseUrl() !== null, 'cPanel base URL missing.');
        $checks[] = $this->check(
            'secrets_present',
            $hasCloudflare || $hasNamecheap || $hasCpanel,
            'Provider secrets missing from environment/runtime scope.'
        );

        $checks[] = $this->check(
            'sandbox_live_consistency',
            !ModuleConfig::providerLiveEnabled('cloudflare_dns') || ModuleConfig::providerSandboxEnabled('cloudflare_dns'),
            'Cloudflare live enabled while sandbox disabled.'
        );
        $checks[] = $this->check(
            'tenant_allowlist_configured',
            $allowlistReady,
            'Tenant rollout allowlist is empty while execution is enabled.'
        );

        return [
            'checks' => $checks,
            'summary' => $this->summary($checks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function check(string $name, bool $pass, string $message): array
    {
        return [
            'name' => $name,
            'status' => $pass ? 'PASS' : 'WARN',
            'message' => $pass ? 'ok' : $message,
        ];
    }

    /**
     * @param list<array<string, mixed>> $checks
     * @return array<string, int>
     */
    private function summary(array $checks): array
    {
        $out = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0];
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'WARN');
            if (!isset($out[$status])) {
                continue;
            }
            $out[$status]++;
        }
        return $out;
    }

    private function hasNamecheapCredentials(SecretManager $secret): bool
    {
        $apiUser = ModuleConfig::namecheapSecretFromRuntime('api_user')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_USER') ?? getenv('RATIB_INFRA_NAMECHEAP_API_USER'));
        $apiKey = ModuleConfig::namecheapSecretFromRuntime('api_key')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') ?? getenv('RATIB_INFRA_NAMECHEAP_API_KEY'));
        $username = ModuleConfig::namecheapSecretFromRuntime('username')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'USERNAME') ?? getenv('RATIB_INFRA_NAMECHEAP_USERNAME'));
        $clientIp = ModuleConfig::namecheapSecretFromRuntime('client_ip') ?: getenv('RATIB_INFRA_NAMECHEAP_CLIENT_IP');

        return is_string($apiUser) && trim($apiUser) !== ''
            && is_string($apiKey) && trim($apiKey) !== ''
            && is_string($username) && trim($username) !== ''
            && is_string($clientIp) && trim($clientIp) !== '';
    }
}

