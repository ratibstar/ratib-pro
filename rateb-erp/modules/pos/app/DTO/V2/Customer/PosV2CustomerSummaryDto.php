<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Customer;

/** OpenAPI-aligned customer summary (T10). */
final readonly class PosV2CustomerSummaryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $phone,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
        ];
    }
}
