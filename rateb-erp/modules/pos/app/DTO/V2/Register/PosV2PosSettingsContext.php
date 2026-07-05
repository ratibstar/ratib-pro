<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** POS operational settings from rateb_pos_settings (stored keys only). */
final readonly class PosV2PosSettingsContext
{
    public function __construct(
        public ?int $schemaVersion,
        public ?bool $enabled,
        public ?string $profile,
        public ?PosV2PosUiSettingsContext $ui,
        public ?PosV2PosDiscountSettingsContext $discounts,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'enabled' => $this->enabled,
            'profile' => $this->profile,
            'ui' => $this->ui?->toArray(),
            'discounts' => $this->discounts?->toArray(),
        ];
    }
}
