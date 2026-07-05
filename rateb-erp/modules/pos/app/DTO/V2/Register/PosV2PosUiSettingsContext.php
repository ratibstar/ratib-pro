<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** POS UI settings slice (only keys present in stored settings). */
final readonly class PosV2PosUiSettingsContext
{
    public function __construct(
        public ?string $localeDefault,
        public ?bool $rtl,
        public ?int $catalogColumns,
        public ?bool $showProductImages,
        public ?string $chargeButtonLabel,
        public ?int $idleTimeoutMinutes,
    ) {
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'locale_default' => $this->localeDefault,
            'rtl' => $this->rtl,
            'catalog_columns' => $this->catalogColumns,
            'show_product_images' => $this->showProductImages,
            'charge_button_label' => $this->chargeButtonLabel,
            'idle_timeout_minutes' => $this->idleTimeoutMinutes,
        ];
    }
}
