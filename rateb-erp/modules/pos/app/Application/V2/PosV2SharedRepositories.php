<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagRepositoryInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2DiscountPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CheckoutPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

/** Request-scoped repository and cache instances shared across POS V2 (T07.5–T12). */
final class PosV2SharedRepositories
{
    public function __construct(
        public readonly FeatureFlagCacheInterface $featureFlagCache,
        public readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        public readonly PosV2PosSettingsCacheInterface $posSettingsCache,
        public readonly PosV2PosSettingsPortInterface $posSettingsRepository,
        public readonly PosV2CatalogCategoryCacheInterface $catalogCategoryCache,
        public readonly PosV2CatalogCategoryPortInterface $catalogCategories,
        public readonly PosV2CatalogProductPortInterface $catalogProducts,
        public readonly PosV2CartPortInterface $cart,
        public readonly PosV2CustomerPortInterface $customers,
        public readonly PosV2DiscountPortInterface $discounts,
        public readonly PosV2PaymentPortInterface $payments,
        public readonly PosV2CheckoutPortInterface $checkout,
    ) {
    }
}
