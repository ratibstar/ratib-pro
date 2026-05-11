<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\DNS\Orchestration;

use Ratib\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

final class DnsOrchestrationService
{
    public function __construct(
        private readonly DnsProviderInterface $provider
    ) {}

    /**
     * @param list<array{name:string,type:string,target:string,ttl?:int}> $records
     * @return array<string, mixed>
     */
    public function applyIdempotentRecords(TenantContext $tenant, string $zoneFqdn, array $records): array
    {
        $normalized = [];
        foreach ($records as $record) {
            $normalized[] = [
                'name' => strtolower(trim((string) ($record['name'] ?? ''))),
                'type' => strtoupper(trim((string) ($record['type'] ?? 'A'))),
                'target' => trim((string) ($record['target'] ?? '')),
                'ttl' => isset($record['ttl']) ? max(60, (int) $record['ttl']) : 300,
            ];
        }

        return $this->provider->applyRecords($tenant, strtolower($zoneFqdn), $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyZone(string $zoneFqdn): array
    {
        return [
            'zone' => strtolower($zoneFqdn),
            'verified' => true,
            'source' => 'orchestration_precheck',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function propagationCheck(string $zoneFqdn): array
    {
        return [
            'zone' => strtolower($zoneFqdn),
            'state' => 'pending_external_dns',
            'retryable' => true,
        ];
    }
}

