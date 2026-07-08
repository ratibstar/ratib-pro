<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface DuplicateWriteRepositoryInterface
{
    public function createGroup(string $groupKey, ?int $ruleId): string;

    public function attachProduct(string $groupUuid, int $productId, ?float $matchScore, bool $isPrimary): void;

    public function resolveGroup(string $groupUuid, int $resolvedBy, string $status, ?string $note): bool;
}
