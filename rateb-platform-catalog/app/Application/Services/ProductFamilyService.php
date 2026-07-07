<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\FamilyAttributeMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductFamilyPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFamilyReadRepositoryInterface;

final class ProductFamilyService
{
    public function __construct(
        private readonly ProductFamilyReadRepositoryInterface $readRepository,
        private readonly ProductFamilyPolicy $policy,
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
        $rows = $this->readRepository->list($locale, $limit, $offset);

        return [
            'items' => array_map(
                static fn (array $row): array => FamilyAttributeMapper::toProductFamilyDto($row)->toArray(),
                $rows
            ),
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
        $row = $this->readRepository->findByUuid($uuid, $locale);

        return [
            'item' => $row !== null ? FamilyAttributeMapper::toProductFamilyDto($row)->toArray() : null,
            'meta' => LocaleMetaBuilder::build($locale, $row !== null ? [$row] : []),
        ];
    }
}
