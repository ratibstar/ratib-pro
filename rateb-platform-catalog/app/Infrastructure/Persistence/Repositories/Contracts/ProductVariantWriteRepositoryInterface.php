<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductVariantWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $translations
     * @param list<array<string, mixed>> $barcodes
     * @param list<array<string, mixed>> $optionValues
     */
    public function createForProduct(
        string $productUuid,
        array $data,
        array $translations,
        array $barcodes,
        array $optionValues,
        ?int $actorId = null
    ): string;
}
