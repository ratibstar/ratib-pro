<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CollectionWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $translations
     */
    public function create(array $data, array $translations): string;

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $translations
     */
    public function update(string $uuid, array $data, array $translations): bool;

    /**
     * @param list<string> $productUuids
     */
    public function replaceProducts(string $collectionUuid, array $productUuids): void;
}
