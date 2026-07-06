<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Customer;

use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerValidationException;

/** Customer search query (T10). */
final readonly class CustomerSearchRequest
{
    public function __construct(
        public string $query,
        public int $limit,
    ) {
    }

    /** @param array<string, mixed> $params */
    public static function fromQueryParams(array $params): self
    {
        $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
        if (strlen($query) < 2) {
            throw new PosV2CustomerValidationException(
                'QUERY_TOO_SHORT',
                'Customer search query must be at least 2 characters.',
            );
        }

        if (strlen($query) > 100) {
            throw new PosV2CustomerValidationException(
                'QUERY_TOO_LONG',
                'Customer search query must be at most 100 characters.',
            );
        }

        $limit = (int) ($params['limit'] ?? 10);
        if ($limit < 1 || $limit > 20) {
            throw new PosV2CustomerValidationException(
                'LIMIT_INVALID',
                'limit must be between 1 and 20.',
            );
        }

        return new self($query, $limit);
    }
}
