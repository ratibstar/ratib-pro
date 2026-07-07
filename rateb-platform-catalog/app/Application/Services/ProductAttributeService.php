<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductAttributePolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class ProductAttributeService
{
    public function __construct(
        private readonly ProductAttributeReadRepositoryInterface $readRepository,
        private readonly ProductAttributeWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductAttributePolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly EventDispatcher $events
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
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $translations = $this->readRepository->listTranslationsGrouped($ids);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $items[] = ProductRelationshipMapper::toAttributeDto($row, $translations[$id] ?? [])->toArray();
        }

        return [
            'items' => $items,
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function replace(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->manage();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $attributes = $payload['attributes'] ?? [];
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException('attributes must be an array');
        }

        $this->writeRepository->replaceForProduct($productUuid, $attributes, $payload['actor_id'] ?? null);
        $this->events->dispatch(new ProductUpdated($productUuid, $locale->locale));

        return $this->list($productUuid, $locale);
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
