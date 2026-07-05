<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Profile- and permission-derived UI capabilities (no external queries). */
final readonly class PosV2RegisterCapabilities
{
    public function __construct(
        public bool $registerAccess,
        public bool $shiftOpen,
        public bool $shiftClose,
        public bool $scanMode,
        public bool $offlineMode,
        public bool $cardTerminal,
        public bool $manageSettings,
        public bool $manageTerminals,
        public bool $returns,
        public bool $discounts,
    ) {
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'register_access' => $this->registerAccess,
            'shift_open' => $this->shiftOpen,
            'shift_close' => $this->shiftClose,
            'scan_mode' => $this->scanMode,
            'offline_mode' => $this->offlineMode,
            'card_terminal' => $this->cardTerminal,
            'manage_settings' => $this->manageSettings,
            'manage_terminals' => $this->manageTerminals,
            'returns' => $this->returns,
            'discounts' => $this->discounts,
        ];
    }
}
