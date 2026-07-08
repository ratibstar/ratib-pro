<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\ChannelPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

final class ChannelService
{
    public function __construct(
        private readonly ChannelReadRepositoryInterface $readRepository,
        private readonly ChannelWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ChannelPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(int $limit = 50, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->list($locale, $limit, $offset);

        return [
            'items' => $rows,
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listForProduct(string $productUuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);
        $rows = $this->readRepository->listForProduct($productUuid, $locale);

        return [
            'items' => $rows,
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function replaceProductChannels(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->manage();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $assignments = $payload['channels'] ?? $payload['assignments'] ?? [];
        if (!is_array($assignments)) {
            throw new \InvalidArgumentException('channels array is required');
        }

        $this->writeRepository->replaceProductChannels($productUuid, $assignments);

        return $this->listForProduct($productUuid, $locale);
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
