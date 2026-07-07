<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\CategoryDto;
use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\TaxonomyMapper;
use Rateb\PlatformCatalog\Application\Policies\CategoryPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategoryRepositoryInterface;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getTree(?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->repository->listFlat($locale);
        $tree = TaxonomyMapper::buildCategoryTree($rows);

        return [
            'items' => array_map(static fn (CategoryDto $dto): array => $dto->toArray(), $tree),
            'meta' => $this->meta($locale, $rows),
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

        if ($row === null) {
            return ['item' => null, 'meta' => $this->meta($locale, [])];
        }

        $parentUuid = null;
        if ($row['parent_id'] !== null) {
            $flat = $this->repository->listFlat($locale);
            foreach ($flat as $candidate) {
                if ((int) $candidate['id'] === (int) $row['parent_id']) {
                    $parentUuid = (string) $candidate['uuid'];
                    break;
                }
            }
        }

        return [
            'item' => TaxonomyMapper::toCategoryDto($row, $parentUuid)->toArray(),
            'meta' => $this->meta($locale, [$row]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function meta(LocaleContext $locale, array $rows): array
    {
        $fallbackUsed = false;
        foreach ($rows as $row) {
            if (isset($row['resolved_language_code']) && (string) $row['resolved_language_code'] !== $locale->locale) {
                $fallbackUsed = true;
                break;
            }
        }

        return [
            'locale' => $locale->locale,
            'locale_fallback_used' => $fallbackUsed,
            'count' => count($rows),
        ];
    }
}
