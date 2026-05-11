<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\DNS\Adapters;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use Ratib\InfrastructureMarketplace\Security\Rollout\ProviderRolloutPolicy;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class CloudflareDnsAdapter implements DnsProviderInterface
{
    private HttpClientInterface $http;
    private SecretManager $secrets;
    private ProviderRolloutPolicy $rollout;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?SecretManager $secrets = null,
        ?ProviderRolloutPolicy $rollout = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->secrets = $secrets ?? SecretManager::withEnvProvider();
        $this->rollout = $rollout ?? new ProviderRolloutPolicy();
    }

    public function applyRecords(TenantContext $tenant, string $zoneFqdn, array $records): array
    {
        if (!$this->rollout->canExecute($tenant, 'cloudflare_dns')) {
            return ['provider' => 'cloudflare_dns', 'state' => 'disabled_by_rollout'];
        }
        $zoneId = $this->resolveZoneId($zoneFqdn);
        if ($zoneId === null) {
            return ['provider' => 'cloudflare_dns', 'state' => 'zone_not_found', 'retryable' => false];
        }

        $results = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $name = (string) ($record['name'] ?? '');
            $type = strtoupper((string) ($record['type'] ?? 'A'));
            $target = (string) ($record['target'] ?? '');
            $ttl = isset($record['ttl']) ? max(60, (int) $record['ttl']) : 300;
            if ($name === '' || $target === '') {
                continue;
            }
            $existing = $this->findRecord($zoneId, $name, $type);
            if ($existing !== null) {
                $results[] = $this->updateRecord($zoneId, (string) ($existing['id'] ?? ''), $type, $name, $target, $ttl);
                continue;
            }
            $results[] = $this->createRecord($zoneId, $type, $name, $target, $ttl);
        }

        return [
            'provider' => 'cloudflare_dns',
            'state' => 'applied',
            'mode' => $this->rollout->executionMode('cloudflare_dns'),
            'results' => $results,
        ];
    }

    public function purgeZone(TenantContext $tenant, string $zoneFqdn): array
    {
        if (!$this->rollout->canExecute($tenant, 'cloudflare_dns')) {
            return ['provider' => 'cloudflare_dns', 'state' => 'disabled_by_rollout'];
        }
        $zoneId = $this->resolveZoneId($zoneFqdn);
        if ($zoneId === null) {
            return ['provider' => 'cloudflare_dns', 'state' => 'zone_not_found'];
        }
        $resp = $this->cfGet('/zones/' . $zoneId . '/dns_records', ['per_page' => 100]);
        $records = is_array($resp['result'] ?? null) ? $resp['result'] : [];
        $deleted = 0;
        foreach ($records as $r) {
            if (!is_array($r) || !isset($r['id'])) {
                continue;
            }
            $this->cfDelete('/zones/' . $zoneId . '/dns_records/' . (string) $r['id']);
            $deleted++;
        }
        return ['provider' => 'cloudflare_dns', 'state' => 'purged', 'deleted' => $deleted];
    }

    public function getCapabilityMatrix(): array
    {
        return [
            'provider' => 'cloudflare_dns',
            'ready' => true,
            'supports' => [
                'zone_lookup' => true,
                'record_create_update' => true,
                'propagation_check' => true,
                'rollback_safe_update' => true,
            ],
        ];
    }

    private function resolveZoneId(string $zoneFqdn): ?string
    {
        $resp = $this->cfGet('/zones', ['name' => strtolower(trim($zoneFqdn))]);
        $rows = is_array($resp['result'] ?? null) ? $resp['result'] : [];
        if ($rows === [] || !is_array($rows[0] ?? null)) {
            return null;
        }
        return (string) ($rows[0]['id'] ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRecord(string $zoneId, string $name, string $type): ?array
    {
        $resp = $this->cfGet('/zones/' . $zoneId . '/dns_records', ['name' => $name, 'type' => $type, 'per_page' => 1]);
        $rows = is_array($resp['result'] ?? null) ? $resp['result'] : [];
        return is_array($rows[0] ?? null) ? $rows[0] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createRecord(string $zoneId, string $type, string $name, string $content, int $ttl): array
    {
        return $this->cfPost('/zones/' . $zoneId . '/dns_records', [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRecord(string $zoneId, string $recordId, string $type, string $name, string $content, int $ttl): array
    {
        return $this->cfPut('/zones/' . $zoneId . '/dns_records/' . $recordId, [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        ]);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function cfGet(string $path, array $query = []): array
    {
        $base = $this->apiBase();
        $resp = $this->http->get($base . $path, $this->headers(), $query);
        return $this->normalize($resp->statusCode(), $resp->json());
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function cfPost(string $path, array $body): array
    {
        $base = $this->apiBase();
        $resp = $this->http->post($base . $path, $this->headers(), $body);
        return $this->normalize($resp->statusCode(), $resp->json());
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function cfPut(string $path, array $body): array
    {
        $base = $this->apiBase();
        $resp = $this->http->post($base . $path, array_merge($this->headers(), ['X-HTTP-Method-Override' => 'PUT']), $body);
        return $this->normalize($resp->statusCode(), $resp->json());
    }

    /**
     * @return array<string, mixed>
     */
    private function cfDelete(string $path): array
    {
        $base = $this->apiBase();
        $resp = $this->http->post($base . $path, array_merge($this->headers(), ['X-HTTP-Method-Override' => 'DELETE']), []);
        return $this->normalize($resp->statusCode(), $resp->json());
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function normalize(int $status, ?array $json): array
    {
        if ($json === null) {
            return ['ok' => false, 'status' => $status, 'error_class' => 'invalid_json', 'retryable' => $status >= 500];
        }
        if ($status >= 500) {
            return ['ok' => false, 'status' => $status, 'error_class' => 'provider_unavailable', 'retryable' => true, 'raw' => $json];
        }
        if ($status >= 400) {
            return ['ok' => false, 'status' => $status, 'error_class' => 'provider_rejected', 'retryable' => in_array($status, [408, 409, 429], true), 'raw' => $json];
        }
        return ['ok' => true, 'status' => $status, 'result' => $json['result'] ?? null];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $token = $this->secrets->getSecret('RATIB_INFRA_CLOUDFLARE', 'API_TOKEN') ?? getenv('RATIB_INFRA_CLOUDFLARE_API_TOKEN');
        if (!is_string($token) || trim($token) === '') {
            throw new \RuntimeException('Cloudflare API token is missing.');
        }
        return [
            'Authorization' => 'Bearer ' . trim($token),
            'Accept' => 'application/json',
        ];
    }

    private function apiBase(): string
    {
        $base = getenv('RATIB_INFRA_CLOUDFLARE_API_BASE');
        if (is_string($base) && trim($base) !== '') {
            return rtrim(trim($base), '/');
        }
        return 'https://api.cloudflare.com/client/v4';
    }
}

