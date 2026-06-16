<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Catalog\API;

use RATEB\InfrastructureMarketplace\Catalog\CatalogRepository;
use RATEB\InfrastructureMarketplace\Catalog\Presentation\CatalogPresenter;
use RATEB\InfrastructureMarketplace\Catalog\Pricing\PricingEngine;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Reseller\AgencyResellerPolicy;

final class CatalogController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


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

