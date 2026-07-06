<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogBootstrapDto;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;

/** Aggregated provider output for register bootstrap assembly (T07). */
final readonly class RegisterBootstrapProviderBundle
{
    public function __construct(
        public PosV2CompanyContext $company,
        public ?PosV2WarehouseContext $warehouse,
        public PosV2LocaleContext $locale,
        public PosV2CurrencyContext $currency,
        public ?PosV2PosSettingsContext $posSettings,
        public ?PosV2ReceiptSettingsContext $receiptSettings,
        public ?PosV2TaxSettingsContext $taxSettings,
        public PosV2ProfilesContext $profiles,
        public PosV2RegisterCapabilities $capabilities,
        public PosV2CatalogBootstrapDto $catalog,
        public CartResponse $cart,
    ) {
    }
}
