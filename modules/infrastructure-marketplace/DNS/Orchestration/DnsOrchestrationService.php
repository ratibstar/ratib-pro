<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\DNS\Orchestration;

use RATEB\InfrastructureMarketplace\Domain\Contracts\DnsProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

final class DnsOrchestrationService
{
    private DnsProviderInterface $provider;

    public function __construct(DnsProviderInterface $provider) {
        $this->provider = $provider;
    }


    /**
     * @param list<array{name:string,type:string,target:string,ttl?:int}> $records
     * @return array<string, mixed>
     */
    public function applyIdempotentRecords(TenantContext $tenant, string $zoneFqdn, array $records): array
    {
        $normalized = [];
        $seen = [];
        $conflicts = [];
        foreach ($records as $record) {
            $name = strtolower(trim((string) ($record['name'] ?? '')));
            $type = strtoupper(trim((string) ($record['type'] ?? 'A')));
            $target = trim((string) ($record['target'] ?? ''));
            if ($name === '' || $target === '') {
                continue;
            }
            $key = $name . '|' . $type;
            if (isset($seen[$key]) && $seen[$key] !== $target) {
                $conflicts[] = ['name' => $name, 'type' => $type];
            }
            $seen[$key] = $target;
            $normalized[] = [
                'name' => $name,
                'type' => $type,
                'target' => $target,
                'ttl' => isset($record['ttl']) ? max(60, (int) $record['ttl']) : 300,
            ];
        }
        if ($conflicts !== []) {
            return [
                'provider' => 'dns_orchestration',
                'state' => 'conflict_detected',
                'conflicts' => $conflicts,
                'retryable' => false,
            ];
        }

        return $this->provider->applyRecords($tenant, strtolower($zoneFqdn), $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyZone(string $zoneFqdn): array
    {
        $zone = strtolower(trim($zoneFqdn));
        $valid = filter_var('https://' . $zone, FILTER_VALIDATE_URL) !== false;
        return [
            'zone' => $zone,
            'verified' => $valid,
            'source' => 'orchestration_precheck',
            'retryable' => !$valid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function propagationCheck(string $zoneFqdn): array
    {
        $zone = strtolower(trim($zoneFqdn));
        $records = @dns_get_record($zone, DNS_A + DNS_AAAA + DNS_CNAME);
        $count = is_array($records) ? count($records) : 0;
        return [
            'zone' => $zone,
            'state' => $count > 0 ? 'propagated' : 'pending_external_dns',
            'records_seen' => $count,
            'retryable' => $count === 0,
        ];
    }
}

