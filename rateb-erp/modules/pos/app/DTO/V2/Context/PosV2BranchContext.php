<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/** Branch slice attached to register context. */
final readonly class PosV2BranchContext
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
