<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

/**
 * Fully resolved POS V2 feature flags for a given context.
 */
final readonly class PosV2ResolvedFeatureFlags
{
    public function __construct(
        public bool $enabled,
        public string $profile,
        public bool $scanMode,
        public bool $offline,
        public bool $cardTerminal,
    ) {
    }

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'POS_V2_ENABLED' => $this->enabled,
            'POS_V2_PROFILE' => $this->profile,
            'POS_V2_SCAN_MODE' => $this->scanMode,
            'POS_V2_OFFLINE' => $this->offline,
            'POS_V2_CARD_TERMINAL' => $this->cardTerminal,
        ];
    }
}
