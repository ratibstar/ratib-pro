<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductBarcodeWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function addForProduct(string $productUuid, array $data, ?int $actorId = null): string;

    public function removeForProduct(string $productUuid, string $barcodeUuid, ?int $actorId = null): bool;
}
