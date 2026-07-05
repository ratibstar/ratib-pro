<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/** Shift slice attached to register context. */
final readonly class PosV2ShiftContext
{
    public function __construct(
        public int $id,
        public string $shiftNo,
        public string $status,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'shift_no' => $this->shiftNo,
            'status' => $this->status,
        ];
    }
}
