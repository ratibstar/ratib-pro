<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting\Adapters;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Hosting\Contracts\HostingProvisioningInterface;
use Ratib\InfrastructureMarketplace\Hosting\DTOs\HostingOperationResult;
use Ratib\InfrastructureMarketplace\Hosting\DTOs\HostingUsageSnapshot;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;

final class CpanelWhmAdapter implements HostingProvisioningInterface
{
    private readonly HttpClientInterface $http;

    public function __construct(
        ?HttpClientInterface $http = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
    }

    public function createAccount(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        $query = $payload->attributes();
        return $this->callWhmApi('createacct', $tenant, $query, 'create_account');
    }

    public function suspendAccount(TenantContext $tenant, string $externalReference): array
    {
        return $this->callWhmApi('suspendacct', $tenant, ['user' => $externalReference], 'suspend_account');
    }

    public function unsuspendAccount(TenantContext $tenant, string $externalReference): array
    {
        return $this->callWhmApi('unsuspendacct', $tenant, ['user' => $externalReference], 'unsuspend_account');
    }

    public function terminateAccount(TenantContext $tenant, string $externalReference): array
    {
        return $this->callWhmApi('removeacct', $tenant, ['user' => $externalReference], 'terminate_account');
    }

    public function listPackages(TenantContext $tenant): array
    {
        $resp = $this->callWhmApiRaw('listpkgs', $tenant, []);
        $pkg = [];
        $rows = $resp['data']['pkg'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = (string) ($row['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $pkg[] = [
                    'name' => $name,
                    'display_name' => (string) ($row['_PACKAGE_NAME'] ?? $name),
                ];
            }
        }
        return $pkg;
    }

    public function usageMetrics(TenantContext $tenant, string $externalReference): array
    {
        $raw = $this->callWhmApiRaw('accountsummary', $tenant, ['user' => $externalReference]);
        $acct = $raw['data']['acct'][0] ?? [];
        if (!is_array($acct)) {
            return (new HostingUsageSnapshot($externalReference, 0.0, 0.0, 0.0))->toArray();
        }

        $snapshot = new HostingUsageSnapshot(
            $externalReference,
            (float) ($acct['bwusage'] ?? 0),
            (float) ($acct['quota'] ?? 0),
            (float) ($acct['diskused'] ?? 0)
        );
        return $snapshot->toArray();
    }

    public function getCapabilityMatrix(): array
    {
        return [
            'provider' => 'cpanel_whm',
            'supports' => [
                'package_listing' => true,
                'create' => true,
                'suspend' => true,
                'unsuspend' => true,
                'terminate' => true,
                'usage_metrics' => true,
                'bandwidth' => true,
                'quota' => true,
            ],
        ];
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function callWhmApi(string $endpoint, TenantContext $tenant, array $query, string $operation): array
    {
        try {
            $raw = $this->callWhmApiRaw($endpoint, $tenant, $query);
            $reference = (string) ($query['user'] ?? $query['username'] ?? '');
            return (new HostingOperationResult(true, $operation, $reference !== '' ? $reference : null, $raw))->toArray();
        } catch (\Throwable $e) {
            $reference = (string) ($query['user'] ?? $query['username'] ?? '');
            return (new HostingOperationResult(false, $operation, $reference !== '' ? $reference : null, [], $e->getMessage()))->toArray();
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function callWhmApiRaw(string $endpoint, TenantContext $tenant, array $query): array
    {
        unset($tenant);
        $base = ModuleConfig::cpanelWhmBaseUrl();
        $user = ModuleConfig::cpanelWhmUsername();
        $token = ModuleConfig::cpanelWhmToken();
        if ($base === null || $user === null || $token === null) {
            throw new \RuntimeException('Missing cPanel/WHM credentials in environment.');
        }

        $url = $base . '/json-api/' . $endpoint;
        $headers = [
            'Authorization' => 'whm ' . $user . ':' . $token,
            'Accept' => 'application/json',
        ];
        $response = $this->http->get($url, $headers, array_merge(['api.version' => 1], $query));

        if (!$response->isSuccess() || $response->json() === null) {
            throw new \RuntimeException('WHM request failed with status ' . $response->statusCode());
        }

        return $response->json() ?? [];
    }
}

