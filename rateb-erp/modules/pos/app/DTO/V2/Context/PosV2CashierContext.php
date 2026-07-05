<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/** Cashier (authenticated operator) slice. */
final readonly class PosV2CashierContext
{
    public function __construct(
        public int $userId,
        public string $displayName,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'display_name' => $this->displayName,
        ];
    }
}
