<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Models\PosSetting;
use Rateb\App\Pos\Models\PosTerminal;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpCashierAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogCategoryAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogProductAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CartAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CustomerAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1DiscountAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CheckoutAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1PaymentAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1PosContextAdapter;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosContextPortInterface;
use Rateb\App\Pos\Repositories\V2\FeatureFlagRepository;
use Rateb\App\Pos\Repositories\V2\InMemoryCatalogCategoryCache;
use Rateb\App\Pos\Repositories\V2\InMemoryFeatureFlagCache;
use Rateb\App\Pos\Repositories\V2\InMemoryPosSettingsCache;
use Rateb\App\Pos\Repositories\V2\PosSettingsRepository;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\V2\PosV2EnvironmentFlagReader;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagResolver;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagService;
use Rateb\App\Pos\Services\V2\PosV2UnifiedFeatureFlagContextResolver;

/**
 * Single request-scoped object graph for POS V2 infrastructure (T07.5).
 */
final class PosV2RequestCompositionRoot
{
    private ?PosV2FeatureFlagContext $featureFlagContext = null;

    public function __construct(
        public readonly PosV2SharedRepositories $repositories,
        public readonly PosV2SharedServices $services,
        public readonly PosV2PosContextPortInterface $posContext,
        public readonly PosV2CashierPortInterface $cashier,
        private readonly PosV2UnifiedFeatureFlagContextResolver $featureFlagContextResolver,
    ) {
    }

    public function resolveFeatureFlagContext(): ?PosV2FeatureFlagContext
    {
        if ($this->featureFlagContext === null) {
            $this->featureFlagContext = $this->featureFlagContextResolver->resolve();
        }

        return $this->featureFlagContext;
    }

    public static function create(): self
    {
        $featureFlagCache = new InMemoryFeatureFlagCache();
        $posSettingsCache = new InMemoryPosSettingsCache();
        $posSetting = new PosSetting();
        $posTerminal = new PosTerminal();
        $posContext = new V1PosContextAdapter(new PosContextService());
        $cashier = new ErpCashierAdapter();

        $featureFlagRepository = new FeatureFlagRepository(
            $posSetting,
            $posTerminal,
            $featureFlagCache,
        );

        $posSettingsRepository = new PosSettingsRepository(
            $posSetting,
            $posSettingsCache,
        );

        $catalogCategoryCache = new InMemoryCatalogCategoryCache();
        $catalogCategories = new V1CatalogCategoryAdapter($catalogCategoryCache);
        $catalogProducts = new V1CatalogProductAdapter();
        $cart = new V1CartAdapter();
        $customers = new V1CustomerAdapter();
        $discounts = new V1DiscountAdapter(cartPort: $cart);
        $payments = new V1PaymentAdapter(cartPort: $cart);
        $checkout = new V1CheckoutAdapter(cartPort: $cart);

        $featureFlagService = new PosV2FeatureFlagService(
            $featureFlagRepository,
            new PosV2FeatureFlagResolver(
                new PosV2EnvironmentFlagReader(),
            ),
            $featureFlagCache,
        );

        return new self(
            new PosV2SharedRepositories(
                $featureFlagCache,
                $featureFlagRepository,
                $posSettingsCache,
                $posSettingsRepository,
                $catalogCategoryCache,
                $catalogCategories,
                $catalogProducts,
                $cart,
                $customers,
                $discounts,
                $payments,
                $checkout,
            ),
            new PosV2SharedServices($featureFlagService),
            $posContext,
            $cashier,
            new PosV2UnifiedFeatureFlagContextResolver($posContext, $cashier),
        );
    }
}
