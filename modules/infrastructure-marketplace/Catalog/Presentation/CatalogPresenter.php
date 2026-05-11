<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Catalog\Presentation;

use Ratib\InfrastructureMarketplace\Catalog\Pricing\PricingEngine;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

final class CatalogPresenter
{
    private PricingEngine $pricing;

    public function __construct(PricingEngine $pricing) {
        $this->pricing = $pricing;
    }


    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function present(array $rows, TenantContext $tenant, string $currency = 'USD'): array
    {
        $out = [];
        foreach ($rows as $row) {
            $meta = is_array($row['metadata_json'] ?? null) ? $row['metadata_json'] : json_decode((string) ($row['metadata_json'] ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            $out[] = [
                'sku' => (string) ($row['sku'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'service_type' => (string) ($meta['service_type'] ?? 'hosting'),
                'description' => (string) ($meta['description'] ?? ''),
                'lifecycle' => (array) ($meta['lifecycle'] ?? []),
                'provisioning' => (array) ($meta['provisioning'] ?? []),
                'pricing' => $this->pricing->resolve(array_merge($row, $meta), $tenant, $currency),
            ];
        }
        return $out;
    }
}

