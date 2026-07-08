<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface IdempotencyWriteRepositoryInterface
{
    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function store(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        int $responseStatus,
        ?array $responseBody,
        \DateTimeImmutable $expiresAt
    ): void;
}
