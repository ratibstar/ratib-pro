<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domains\Search;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use RATEB\InfrastructureMarketplace\Registrars\Search\RegistrarSearchAggregator;

final class DomainSearchService
{
    private DomainSearchCache $cache;
    private RegistrarSearchAggregator $aggregator;
    private InfrastructureMetrics $metrics;

    public function __construct(DomainSearchCache $cache, RegistrarSearchAggregator $aggregator, InfrastructureMetrics $metrics) {
        $this->cache = $cache;
        $this->aggregator = $aggregator;
        $this->metrics = $metrics;
    }


    /**
     * @param list<string> $tlds
     * @return list<array<string, mixed>>
     */
    public function search(string $keyword, array $tlds, ?TenantContext $tenant = null): array
    {
        $tenantKey = $tenant?->tenantId() ?? 0;
        $agencyKey = $tenant?->agencyId() ?? 0;
        $cacheKey = sha1(strtolower(trim($keyword)) . ':' . implode(',', $tlds) . ':' . $tenantKey . ':' . $agencyKey);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->metrics->externalDependencyStatus('domain_search_cache', 'hit');
            return $cached;
        }
        $result = $this->aggregator->searchAsyncPrepared($keyword, $tlds, $tenant);
        $this->cache->put($cacheKey, $result);
        $this->metrics->externalDependencyStatus('domain_search_cache', 'miss');
        return $result;
    }
}

