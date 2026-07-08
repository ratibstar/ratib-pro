<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface DuplicateReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listGroups(?string $status, int $limit, int $offset): array;

    public function findGroupByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRules(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findSkuMatches(string $sku): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findBarcodeMatches(string $barcode): array;

    /**
     * @return list<array{sku: string, product_ids: list<int>}>
     */
    public function findSkuCollisionGroups(int $limit = 200): array;

    /**
     * @return list<array{barcode: string, product_ids: list<int>}>
     */
    public function findBarcodeCollisionGroups(int $limit = 200): array;
}
