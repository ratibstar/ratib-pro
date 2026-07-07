<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class SearchQuery
{
    /**
     * @param array<string, list<string>> $facets
     */
    public function __construct(
        public readonly string $query,
        public readonly string $locale,
        public readonly string $indexType = 'product',
        public readonly array $facets = [],
        public readonly string $sort = 'relevance',
        public readonly int $limit = 50,
        public readonly int $offset = 0
    ) {
    }
}
