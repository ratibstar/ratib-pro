<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Repositories\V2\Adapters\ErpLocaleAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpPosPermissionsAdapter;

/** Wires PosV2Application and its dependency graph (T04). */
final class PosV2ApplicationFactory
{
    public function create(): PosV2Application
    {
        $root = PosV2RequestScope::ensure();

        $resolver = new PosV2ContextResolver(
            $root->posContext,
            $root->cashier,
            new ErpLocaleAdapter(),
            new ErpPosPermissionsAdapter(),
            $root->services->featureFlags,
        );

        $contextFactory = new PosV2ContextFactory($resolver);

        return new PosV2Application(
            new PosV2Bootstrap($contextFactory),
            new PosV2ResponseFactory(),
        );
    }
}
