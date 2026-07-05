<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapProviderBundle;
use Rateb\App\Pos\Services\V2\Register\Providers\CapabilitiesProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\CurrencyProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\LocaleProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\ProfilesProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\ReceiptSettingsProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterCompanyProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterSettingsProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterWarehouseProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\TaxSettingsProvider;

/** Orchestrates register bootstrap data providers (single settings load per scope). */
final class RegisterBootstrapProvidersOrchestrator
{
    public function __construct(
        private readonly RegisterCompanyProvider $company,
        private readonly RegisterWarehouseProvider $warehouse,
        private readonly LocaleProvider $locale,
        private readonly CurrencyProvider $currency,
        private readonly RegisterSettingsProvider $settings,
        private readonly ReceiptSettingsProvider $receiptSettings,
        private readonly TaxSettingsProvider $taxSettings,
        private readonly ProfilesProvider $profiles,
        private readonly CapabilitiesProvider $capabilities,
    ) {
    }

    public function provide(PosV2RequestContext $context): RegisterBootstrapProviderBundle
    {
        return new RegisterBootstrapProviderBundle(
            company: $this->company->provide($context),
            warehouse: $this->warehouse->provide($context),
            locale: $this->locale->provide($context),
            currency: $this->currency->provide($context),
            posSettings: $this->settings->provide($context),
            receiptSettings: $this->receiptSettings->provide($context),
            taxSettings: $this->taxSettings->provide($context),
            profiles: $this->profiles->provide($context),
            capabilities: $this->capabilities->provide($context),
        );
    }
}
