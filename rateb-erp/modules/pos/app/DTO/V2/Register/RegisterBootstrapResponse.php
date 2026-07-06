<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogBootstrapDto;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2BranchContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2ShiftContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2TerminalContext;

/** Fully typed register bootstrap payload (T06 + T07 enrichment). */
final readonly class RegisterBootstrapResponse
{
    public function __construct(
        public PosV2RegisterBootstrapRegister $register,
        public ?PosV2TerminalContext $terminal,
        public ?PosV2BranchContext $branch,
        public ?PosV2WarehouseContext $warehouse,
        public PosV2CompanyContext $company,
        public ?PosV2ShiftContext $shift,
        public PosV2CashierContext $cashier,
        public PosV2PermissionsContext $permissions,
        public PosV2FeatureFlagsContext $featureFlags,
        public PosV2LocaleContext $locale,
        public string $timezone,
        public PosV2CurrencyContext $currency,
        public string $profile,
        public ?PosV2PosSettingsContext $posSettings,
        public ?PosV2ReceiptSettingsContext $receiptSettings,
        public ?PosV2TaxSettingsContext $taxSettings,
        public PosV2ProfilesContext $profiles,
        public PosV2RegisterCapabilities $capabilities,
        public PosV2CatalogBootstrapDto $catalog,
        public CartResponse $cart,
        public PosV2RegisterBootstrapMetadata $metadata,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'register' => $this->register->toArray(),
            'terminal' => $this->terminal?->toArray(),
            'branch' => $this->branch?->toArray(),
            'warehouse' => $this->warehouse?->toArray(),
            'company' => $this->company->toArray(),
            'shift' => $this->shift?->toArray(),
            'cashier' => $this->cashier->toArray(),
            'permissions' => $this->permissions->toArray(),
            'feature_flags' => $this->featureFlags->toArray(),
            'locale' => $this->locale->toArray(),
            'timezone' => $this->timezone,
            'currency' => $this->currency->toArray(),
            'profile' => $this->profile,
            'pos_settings' => $this->posSettings?->toArray(),
            'receipt_settings' => $this->receiptSettings?->toArray(),
            'tax_settings' => $this->taxSettings?->toArray(),
            'profiles' => $this->profiles->toArray(),
            'capabilities' => $this->capabilities->toArray(),
            'catalog' => $this->catalog->toArray(),
            'cart' => $this->cart->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
