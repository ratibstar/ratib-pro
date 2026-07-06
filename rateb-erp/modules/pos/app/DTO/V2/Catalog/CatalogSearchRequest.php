<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogValidationException;

/** Catalog search/list query (T08). */
final readonly class CatalogSearchRequest
{
    public function __construct(
        public string $query,
        public ?int $categoryId,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromQueryParams(array $params): self
    {
        $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
        if (strlen($query) > 100) {
            throw new PosV2CatalogValidationException(
                'QUERY_TOO_LONG',
                'Catalog query must be at most 100 characters.',
            );
        }

        $categoryRaw = $params['category_id'] ?? null;
        $categoryId = null;
        if ($categoryRaw !== null && $categoryRaw !== '') {
            if (!ctype_digit((string) $categoryRaw)) {
                throw new PosV2CatalogValidationException(
                    'CATEGORY_INVALID',
                    'category_id must be a positive integer.',
                );
            }
            $categoryId = (int) $categoryRaw;
            if ($categoryId < 1) {
                throw new PosV2CatalogValidationException(
                    'CATEGORY_INVALID',
                    'category_id must be a positive integer.',
                );
            }
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['per_page'] ?? 24);
        if ($perPage < 1 || $perPage > 48) {
            throw new PosV2CatalogValidationException(
                'PER_PAGE_INVALID',
                'per_page must be between 1 and 48.',
            );
        }

        return new self($query, $categoryId, $page, $perPage);
    }
}
