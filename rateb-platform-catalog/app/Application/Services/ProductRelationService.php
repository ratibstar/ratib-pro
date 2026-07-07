<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductRelationPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationWriteRepositoryInterface;

final class ProductRelationService
{
    public function __construct(
        private readonly ProductRelationReadRepositoryInterface $readRepository,
        private readonly ProductRelationWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductRelationPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(string $productUuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $rows = $this->readRepository->listByProductUuid($productUuid, $locale);

        return [
            'items' => array_map(
                static fn (array $row): array => ProductRelationshipMapper::toRelationDto($row)->toArray(),
                $rows
            ),
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function add(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->create();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $relationUuid = $this->writeRepository->addRelation($productUuid, $payload, $payload['actor_id'] ?? null);
        $list = $this->list($productUuid, $locale);
        $item = null;
        foreach ($list['items'] as $relation) {
            if ($relation['uuid'] === $relationUuid) {
                $item = $relation;
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $relationUuid],
            'meta' => $list['meta'],
        ];
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
