<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL\Validation;

final class DnsValidationPreparation
{
    /**
     * @return array<string, mixed>
     */
    public function challengeRecord(string $fqdn, string $token): array
    {
        return [
            'name' => '_acme-challenge.' . strtolower($fqdn),
            'type' => 'TXT',
            'target' => $token,
            'ttl' => 120,
        ];
    }
}

