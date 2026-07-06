<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Repositories\V2\Adapters\ErpCashierAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpLocaleAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpPosPermissionsAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1PosContextAdapter;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagContextResolver;

/** Wires PosV2Application and its dependency graph (T04). */
final class PosV2ApplicationFactory
{
    public function create(): PosV2Application
    {
        $root = PosV2RequestScope::ensure();

        $resolver = new PosV2ContextResolver(
            new V1PosContextAdapter(new PosContextService()),
            new ErpCashierAdapter(),
            new ErpLocaleAdapter(),
            new ErpPosPermissionsAdapter(),
            new PosV2FeatureFlagContextResolver(),
            $root->services->featureFlags,
        );

        $contextFactory = new PosV2ContextFactory($resolver);

        return new PosV2Application(
            new PosV2Bootstrap($contextFactory),
            new PosV2ResponseFactory(),
        );
    }
}
