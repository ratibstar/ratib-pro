<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Warehouse slice for register bootstrap. */
final readonly class PosV2WarehouseContext
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /** @return array{id: int, name: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
