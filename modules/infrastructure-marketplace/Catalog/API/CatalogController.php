<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Catalog\API;

use Ratib\InfrastructureMarketplace\Catalog\CatalogRepository;
use Ratib\InfrastructureMarketplace\Catalog\Presentation\CatalogPresenter;
use Ratib\InfrastructureMarketplace\Catalog\Pricing\PricingEngine;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Reseller\AgencyResellerPolicy;

final class CatalogController
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?int $tenantId, ?int $agencyId, string $currency = 'USD'): array
    {
        $repo = new CatalogRepository($this->pdo);
        $tenant = new TenantContext($tenantId, $agencyId);
        $policy = new AgencyResellerPolicy();
        $presenter = new CatalogPresenter(new PricingEngine($policy));
        $rows = $repo->listVisibleForTenant($tenantId);

        return [
            'ok' => true,
            'currency' => strtoupper($currency),
            'items' => $presenter->present($rows, $tenant, $currency),
        ];
    }
}

