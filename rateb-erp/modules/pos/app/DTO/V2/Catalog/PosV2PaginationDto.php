<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** Pagination metadata for catalog list endpoints. */
final readonly class PosV2PaginationDto
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
    }

    /** @return array{page: int, per_page: int, total: int, last_page: int} */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
        ];
    }
}
