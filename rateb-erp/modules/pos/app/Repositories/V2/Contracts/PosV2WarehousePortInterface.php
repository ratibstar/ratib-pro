<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\DTO\V2\Register\PosV2WarehouseContext;

/** Warehouse label lookup for bootstrap (read-only). */
interface PosV2WarehousePortInterface
{
    public function resolve(int $warehouseId): ?PosV2WarehouseContext;
}
