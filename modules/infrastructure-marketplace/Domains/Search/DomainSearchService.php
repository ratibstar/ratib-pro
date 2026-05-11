<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domains\Search;

use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Registrars\Search\RegistrarSearchAggregator;

final class DomainSearchService
{
    public function __construct(
        private readonly DomainSearchCache $cache,
        private readonly RegistrarSearchAggregator $aggregator,
        private readonly InfrastructureMetrics $metrics
    ) {}

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

