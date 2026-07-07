<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Application\Policies\AssetTypePolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;

final class AssetTypeService
{
    public function __construct(
        private readonly AssetTypeReadRepositoryInterface $readRepository,
        private readonly AssetTypePolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(?LocaleContext $locale = null, int $limit = 100, int $offset = 0): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->list($locale, $limit, $offset);

        return [
            'items' => array_map(static fn (array $row): array => MediaMapper::toAssetTypeDto($row)->toArray(), $rows),
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }
}
