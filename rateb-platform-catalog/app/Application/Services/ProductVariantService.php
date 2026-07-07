<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\VariantCreated;
use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductVariantPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantWriteRepositoryInterface;

final class ProductVariantService
{
    public function __construct(
        private readonly ProductVariantReadRepositoryInterface $readRepository,
        private readonly ProductVariantWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductVariantPolicy $policy,
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
        $variantIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $barcodes = $this->readRepository->listBarcodesGroupedByVariantId($variantIds);
        $options = $this->readRepository->listOptionValuesGroupedByVariantId($variantIds, $locale);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $items[] = ProductRelationshipMapper::toVariantDto(
                $row,
                $barcodes[$id] ?? [],
                $options[$id] ?? []
            )->toArray();
        }

        return [
            'items' => $items,
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->create();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $variantUuid = $this->writeRepository->createForProduct(
            $productUuid,
            $payload,
            $payload['translations'] ?? [],
            $payload['barcodes'] ?? [],
            $payload['option_values'] ?? [],
            $payload['actor_id'] ?? null
        );

        $this->events->dispatch(new VariantCreated($productUuid, $variantUuid, $locale->locale));

        $list = $this->list($productUuid, $locale);
        $item = null;
        foreach ($list['items'] as $variant) {
            if ($variant['uuid'] === $variantUuid) {
                $item = $variant;
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $variantUuid],
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
