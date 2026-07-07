<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductBundlePolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class ProductBundleService
{
    public function __construct(
        private readonly ProductBundleReadRepositoryInterface $readRepository,
        private readonly ProductBundleWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductBundlePolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function get(string $bundleProductUuid, ?LocaleContext $locale = null): array
    {
        $this->policy->view();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($bundleProductUuid, $locale);

        $rows = $this->readRepository->listComponents($bundleProductUuid, $locale);

        return [
            'items' => array_map(
                static fn (array $row): array => ProductRelationshipMapper::toBundleComponentDto($row)->toArray(),
                $rows
            ),
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function replace(string $bundleProductUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->manage();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($bundleProductUuid, $locale);

        $components = $payload['components'] ?? [];
        if (!is_array($components)) {
            throw new \InvalidArgumentException('components must be an array');
        }

        $this->writeRepository->replaceBundle($bundleProductUuid, $components, $payload['actor_id'] ?? null);

        return $this->get($bundleProductUuid, $locale);
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
