<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\TaxonomyMapper;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Policies\BrandPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BrandRepositoryInterface;

final class BrandService
{
    public function __construct(
        private readonly BrandRepositoryInterface $repository,
        private readonly BrandPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(int $limit = 100, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->repository->list($locale, $limit, $offset);

        return [
            'items' => array_map(static fn (array $row): array => TaxonomyMapper::toBrandDto($row)->toArray(), $rows),
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function getByUuid(string $uuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewDetail();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $row = $this->repository->findByUuid($uuid, $locale);

        return [
            'item' => $row !== null ? TaxonomyMapper::toBrandDto($row)->toArray() : null,
            'meta' => LocaleMetaBuilder::build($locale, $row !== null ? [$row] : []),
        ];
    }
}
