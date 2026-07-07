<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\BarcodeChanged;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductBarcodePolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class ProductBarcodeService
{
    public function __construct(
        private readonly ProductBarcodeReadRepositoryInterface $readRepository,
        private readonly ProductBarcodeWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductBarcodePolicy $policy,
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
        $this->assertProductExists($productUuid);
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->listByProductUuid($productUuid);

        return [
            'items' => array_map(
                static fn (array $row): array => ProductRelationshipMapper::toBarcodeDto($row)->toArray(),
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
        $this->policy->manage();
        $this->assertProductExists($productUuid);
        $locale ??= $this->localeResolver->resolveFromRequest();
        $uuid = $this->writeRepository->addForProduct($productUuid, $payload, $payload['actor_id'] ?? null);
        $this->events->dispatch(new BarcodeChanged($productUuid, $locale->locale));
        $rows = $this->readRepository->listByProductUuid($productUuid);
        $item = null;
        foreach ($rows as $row) {
            if ($row['uuid'] === $uuid) {
                $item = ProductRelationshipMapper::toBarcodeDto($row)->toArray();
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $uuid],
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    public function delete(string $productUuid, string $barcodeUuid): bool
    {
        $this->policy->manage();
        $this->assertProductExists($productUuid);

        $deleted = $this->writeRepository->removeForProduct($productUuid, $barcodeUuid);
        if ($deleted) {
            $this->events->dispatch(new BarcodeChanged($productUuid));
        }

        return $deleted;
    }

    private function assertProductExists(string $productUuid): void
    {
        if ($this->productReadRepository->findLockVersion($productUuid) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
