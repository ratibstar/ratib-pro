<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class BarcodeResolveResult
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        public readonly string $matchType,
        public readonly array $document
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'match_type' => $this->matchType,
            'document' => $this->document,
        ];
    }
}
