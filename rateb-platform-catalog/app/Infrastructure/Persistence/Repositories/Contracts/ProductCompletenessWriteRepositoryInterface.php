<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductCompletenessWriteRepositoryInterface
{
    /**
     * @param list<string> $failedRules
     */
    public function upsert(
        int $productId,
        string $locale,
        float $score,
        bool $blockingFailed,
        array $failedRules
    ): void;
}
