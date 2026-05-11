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

        $checks[] = $this->check('module_enabled', ModuleConfig::isModuleEnabled(), 'Module is disabled.');
        $checks[] = $this->check('queue_driver_database', ModuleConfig::defaultQueueDriver() === 'database', 'Queue driver is not database.');
        $checks[] = $this->check('kill_switch_off', !ModuleConfig::executionKillSwitch(), 'Execution kill-switch is ON.');
        $checks[] = $this->check('dry_run_status', true, ModuleConfig::dryRunMode() ? 'Dry-run mode enabled (safe prelaunch).' : 'Dry-run mode disabled.');
        $checks[] = $this->check('cpanel_base_url', ModuleConfig::cpanelWhmBaseUrl() !== null, 'cPanel base URL missing.');
        $checks[] = $this->check(
            'secrets_present',
            $secret->getSecret('RATIB_INFRA_CLOUDFLARE', 'API_TOKEN') !== null
                || $secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') !== null
                || ModuleConfig::cpanelWhmToken() !== null,
            'Provider secrets missing from environment scope.'
        );

        $checks[] = $this->check(
            'sandbox_live_consistency',
            !ModuleConfig::providerLiveEnabled('cloudflare_dns') || ModuleConfig::providerSandboxEnabled('cloudflare_dns'),
            'Cloudflare live enabled while sandbox disabled.'
        );
        $checks[] = $this->check(
            'tenant_allowlist_configured',
            ModuleConfig::rolloutTenantAllowlist() !== [],
            'Tenant rollout allowlist is empty.'
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
}

