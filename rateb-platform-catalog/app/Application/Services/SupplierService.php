<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\TaxonomyMapper;
use Rateb\PlatformCatalog\Application\Policies\SupplierPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SupplierRepositoryInterface;

final class SupplierService
{
    public function __construct(
        private readonly SupplierRepositoryInterface $repository,
        private readonly SupplierPolicy $policy,
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
            'items' => array_map(static fn (array $row): array => TaxonomyMapper::toSupplierDto($row)->toArray(), $rows),
            'meta' => $this->meta($locale, $rows, $limit, $offset),
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
            'item' => $row !== null ? TaxonomyMapper::toSupplierDto($row)->toArray() : null,
            'meta' => $this->meta($locale, $row !== null ? [$row] : []),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function meta(LocaleContext $locale, array $rows, ?int $limit = null, ?int $offset = null): array
    {
        $fallbackUsed = false;
        foreach ($rows as $row) {
            if (isset($row['resolved_language_code']) && (string) $row['resolved_language_code'] !== $locale->locale) {
                $fallbackUsed = true;
                break;
            }
        }

        $meta = [
            'locale' => $locale->locale,
            'locale_fallback_used' => $fallbackUsed,
            'count' => count($rows),
        ];

        if ($limit !== null) {
            $meta['limit'] = $limit;
        }
        if ($offset !== null) {
            $meta['offset'] = $offset;
        }

        return $meta;
    }
}
