<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domains\Search;

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
    public function search(string $keyword, array $tlds): array
    {
        $cacheKey = sha1(strtolower(trim($keyword)) . ':' . implode(',', $tlds));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->metrics->externalDependencyStatus('domain_search_cache', 'hit');
            return $cached;
        }
        $result = $this->aggregator->searchAsyncPrepared($keyword, $tlds);
        $this->cache->put($cacheKey, $result);
        $this->metrics->externalDependencyStatus('domain_search_cache', 'miss');
        return $result;
    }
}

