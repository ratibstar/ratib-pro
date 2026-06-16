<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Hosting\Adapters;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Hosting\Contracts\HostingProvisioningInterface;
use RATEB\InfrastructureMarketplace\Hosting\DTOs\HostingOperationResult;
use RATEB\InfrastructureMarketplace\Hosting\DTOs\HostingUsageSnapshot;
use RATEB\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use RATEB\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use RATEB\InfrastructureMarketplace\Observability\ProviderEventBus;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class CpanelWhmAdapter implements HostingProvisioningInterface
{
    private HttpClientInterface $http;
    private SecretManager $secrets;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?SecretManager $secrets = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->secrets = $secrets ?? SecretManager::withDefaultProvidersLazy();
    }

    public function createAccount(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $query = $payload->attributes();
        $username = (string) ($query['username'] ?? '');
        $package = (string) ($query['plan'] ?? $query['pkgname'] ?? '');
        if ($username === '' || $package === '') {
            $result = $this->errorResult('create_account', $username, 'validation', false, 'username and package are required');
            $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
            return $result;
        }
        if (!$this->packageExists($tenant, $package)) {
            $result = $this->errorResult('create_account', $username, 'validation', false, 'Package not found: ' . $package);
            $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
            return $result;
        }
        $existing = $this->accountSummary($tenant, $username);
        if ($existing !== null) {
            $result = (new HostingOperationResult(true, 'create_account', $username, [
                'idempotent' => true,
                'account' => $existing,
            ]))->toArray();
            $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = $this->callWhmApi('createacct', $tenant, $query, 'create_account');
        $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
        return $result;
    }

    public function suspendAccount(TenantContext $tenant, string $externalReference): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $result = $this->callWhmApi('suspendacct', $tenant, ['user' => $externalReference], 'suspend_account');
        $verify = $this->accountSummary($tenant, $externalReference);
        $result['data']['suspended_verified'] = is_array($verify) && ((string) ($verify['suspended'] ?? '') === '1');
        $this->logProviderEvent('suspend', $tenant, $started, $requestId, $result);
        return $result;
    }

    public function unsuspendAccount(TenantContext $tenant, string $externalReference): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $result = $this->callWhmApi('unsuspendacct', $tenant, ['user' => $externalReference], 'unsuspend_account');
        $this->logProviderEvent('unsuspend', $tenant, $started, $requestId, $result);

        return $result;
    }

    public function terminateAccount(TenantContext $tenant, string $externalReference): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $before = $this->accountSummary($tenant, $externalReference);
        if ($before === null) {
            $result = (new HostingOperationResult(true, 'terminate_account', $externalReference, ['idempotent' => true]))->toArray();
            $this->logProviderEvent('terminate', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = $this->callWhmApi('removeacct', $tenant, ['user' => $externalReference], 'terminate_account');
        $this->logProviderEvent('terminate', $tenant, $started, $requestId, $result);
        return $result;
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
        $acct = $this->accountSummary($tenant, $externalReference);
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

    /**
     * @return array<string, mixed>
     */
    public function accountDiagnostics(TenantContext $tenant, string $externalReference): array
    {
        $acct = $this->accountSummary($tenant, $externalReference);
        if ($acct === null) {
            return ['exists' => false];
        }
        return [
            'exists' => true,
            'metadata' => [
                'domain' => (string) ($acct['domain'] ?? ''),
                'ip' => (string) ($acct['ip'] ?? ''),
                'owner' => (string) ($acct['owner'] ?? ''),
                'suspended' => ((string) ($acct['suspended'] ?? '') === '1'),
                'ssl_status' => (string) ($acct['ssl'] ?? 'unknown'),
            ],
            'quota_sync' => [
                'quota_mb' => (float) ($acct['quota'] ?? 0),
                'disk_used_mb' => (float) ($acct['diskused'] ?? 0),
            ],
            'bandwidth_sync' => [
                'bandwidth_mb' => (float) ($acct['bwusage'] ?? 0),
            ],
        ];
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
            $classified = $this->classifyError($e);
            return $this->errorResult($operation, $reference, $classified['class'], $classified['transient'], $classified['message']);
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
        $token = $this->secrets->getSecret('RATEB_INFRA_CPANEL', 'API_TOKEN') ?? ModuleConfig::cpanelWhmToken();
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

    private function packageExists(TenantContext $tenant, string $packageName): bool
    {
        $packages = $this->listPackages($tenant);
        foreach ($packages as $pkg) {
            if ((string) ($pkg['name'] ?? '') === $packageName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function accountSummary(TenantContext $tenant, string $username): ?array
    {
        $raw = $this->callWhmApiRaw('accountsummary', $tenant, ['user' => $username]);
        $acct = $raw['data']['acct'][0] ?? null;
        return is_array($acct) ? $acct : null;
    }

    /**
     * @return array{class:string,transient:bool,message:string}
     */
    private function classifyError(\Throwable $e): array
    {
        $msg = strtolower($e->getMessage());
        $isTransient = str_contains($msg, 'timed out')
            || str_contains($msg, 'temporar')
            || str_contains($msg, '429')
            || preg_match('/status 5\\d\\d/', $msg) === 1;

        $class = 'unknown';
        if (str_contains($msg, 'missing') || str_contains($msg, 'required')) {
            $class = 'validation';
        } elseif (str_contains($msg, '401') || str_contains($msg, '403') || str_contains($msg, 'unauthor')) {
            $class = 'auth';
        } elseif (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
            $class = 'network_timeout';
        } elseif (str_contains($msg, 'status 5')) {
            $class = 'provider_unavailable';
        }

        return [
            'class' => $class,
            'transient' => $isTransient,
            'message' => 'WHM operation failed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResult(string $operation, string $reference, string $class, bool $transient, string $message): array
    {
        $base = (new HostingOperationResult(false, $operation, $reference !== '' ? $reference : null, [], $message))->toArray();
        $base['error_class'] = $class;
        $base['transient'] = $transient;
        return $base;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logProviderEvent(string $eventName, TenantContext $tenant, float $startedAt, string $requestId, array $result): void
    {
        $status = !empty($result['success']) ? 'success' : (!empty($result['retryable']) ? 'retry' : 'failed');
        ProviderEventBus::log('hosting', 'cpanel_whm', $eventName, [
            'request_id' => $requestId,
            'operation_name' => (string) ($result['operation'] ?? $eventName),
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'tenant_id' => $tenant->tenantId(),
            'agency_id' => $tenant->agencyId(),
            'payload' => $result,
            'error_message' => isset($result['error']) ? (string) $result['error'] : null,
        ]);
    }
}

