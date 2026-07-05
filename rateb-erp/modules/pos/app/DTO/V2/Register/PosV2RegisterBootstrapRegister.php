<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Register operational state for bootstrap. */
final readonly class PosV2RegisterBootstrapRegister
{
    public function __construct(
        public int $sessionId,
        public int $warehouseId,
        public bool $registerReady,
        public bool $rtl,
    ) {
    }

    /** @return array<string, bool|int> */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'warehouse_id' => $this->warehouseId,
            'register_ready' => $this->registerReady,
            'rtl' => $this->rtl,
        ];
    }
}
