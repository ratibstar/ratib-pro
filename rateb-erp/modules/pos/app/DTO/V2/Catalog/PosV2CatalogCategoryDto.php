<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** POS catalog category slice. */
final readonly class PosV2CatalogCategoryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public int $sortOrder,
    ) {
    }

    /** @return array{id: int, name: string, sort_order: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sortOrder,
        ];
    }
}
