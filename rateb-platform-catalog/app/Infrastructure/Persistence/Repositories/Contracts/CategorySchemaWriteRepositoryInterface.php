<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CategorySchemaWriteRepositoryInterface
{
    /**
     * @param list<array{attribute_uuid: string, is_required?: bool, sort_order?: int, inheritance?: string}> $items
     */
    public function replaceForCategory(int $categoryId, array $items, ?int $actorId = null): void;
}
