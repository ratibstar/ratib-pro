<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/** Terminal slice attached to register context. */
final readonly class PosV2TerminalContext
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public int $warehouseId,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'warehouse_id' => $this->warehouseId,
        ];
    }
}
