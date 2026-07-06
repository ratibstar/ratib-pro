<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpCompanyAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1WarehouseAdapter;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;
use Rateb\App\Pos\Services\V2\Register\Providers\CapabilitiesProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\CartBootstrapProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\CatalogBootstrapProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\CurrencyProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\LocaleProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\ProfilesProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\ReceiptSettingsProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterCompanyProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterSettingsProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\RegisterWarehouseProvider;
use Rateb\App\Pos\Services\V2\Register\Providers\TaxSettingsProvider;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;

/** Wires register bootstrap data providers (T07). */
final class RegisterBootstrapProvidersFactory
{
    public function createOrchestrator(): RegisterBootstrapProvidersOrchestrator
    {
        $settingsPort = PosV2RequestScope::ensure()->repositories->posSettingsRepository;
        $catalogCategories = PosV2RequestScope::ensure()->repositories->catalogCategories;
        $cartPort = PosV2RequestScope::ensure()->repositories->cart;

        return new RegisterBootstrapProvidersOrchestrator(
            new RegisterCompanyProvider(new ErpCompanyAdapter()),
            new RegisterWarehouseProvider(new V1WarehouseAdapter()),
            new LocaleProvider(),
            new CurrencyProvider(),
            new RegisterSettingsProvider($settingsPort),
            new ReceiptSettingsProvider($settingsPort),
            new TaxSettingsProvider($settingsPort),
            new ProfilesProvider(),
            new CapabilitiesProvider(new PosV2RegisterCapabilitiesResolver()),
            new CatalogBootstrapProvider($catalogCategories),
            new CartBootstrapProvider(
                new GetCartUseCase(new PosV2CartAccessValidator(), $cartPort),
            ),
        );
    }
}
