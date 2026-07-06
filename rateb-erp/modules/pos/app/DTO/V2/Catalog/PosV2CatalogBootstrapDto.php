<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** Catalog bootstrap slice embedded in register bootstrap (T08). */
final readonly class PosV2CatalogBootstrapDto
{
    /**
     * @param list<PosV2CatalogCategoryDto> $categories
     */
    public function __construct(
        public array $categories,
    ) {
    }

    /** @return array{categories: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'categories' => array_map(
                static fn (PosV2CatalogCategoryDto $category): array => $category->toArray(),
                $this->categories,
            ),
        ];
    }
}
