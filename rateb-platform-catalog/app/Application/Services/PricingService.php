<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\PricingPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class PricingService
{
    public function __construct(
        private readonly ProductPriceReadRepositoryInterface $readRepository,
        private readonly ProductPriceWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly PricingPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listForProduct(string $productUuid): array
    {
        $this->policy->view();
        $this->assertProductExists($productUuid);
        $items = $this->readRepository->listForProduct($productUuid);

        return ['items' => $items, 'meta' => ['count' => count($items)]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function replaceForProduct(string $productUuid, array $payload): array
    {
        $this->policy->manage();
        $this->assertProductExists($productUuid);
        $prices = $payload['prices'] ?? [];
        if (!is_array($prices)) {
            throw new \InvalidArgumentException('prices array is required');
        }

        $this->writeRepository->replaceForProduct($productUuid, $prices);

        return $this->listForProduct($productUuid);
    }

    private function assertProductExists(string $productUuid): void
    {
        $locale = $this->localeResolver->resolveFromRequest();
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
