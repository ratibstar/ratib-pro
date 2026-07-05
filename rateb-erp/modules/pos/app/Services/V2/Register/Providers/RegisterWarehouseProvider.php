<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2WarehouseContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2WarehousePortInterface;

final class RegisterWarehouseProvider
{
    public function __construct(
        private readonly PosV2WarehousePortInterface $warehouses,
    ) {
    }

    public function provide(PosV2RequestContext $context): ?PosV2WarehouseContext
    {
        return $this->warehouses->resolve($context->register->warehouseId);
    }
}
