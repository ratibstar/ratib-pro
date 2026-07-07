<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class SearchResult
{
    /**
     * @param list<array<string, mixed>> $hits
     * @param array<string, array<string, int>> $facets
     */
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
        public readonly array $facets = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'total' => $this->total,
            'facets' => $this->facets,
        ];
    }
}
