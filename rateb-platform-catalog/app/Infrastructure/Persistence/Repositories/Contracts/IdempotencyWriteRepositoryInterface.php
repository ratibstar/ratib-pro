<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\Support\IdempotencyAcquireResult;

interface IdempotencyWriteRepositoryInterface
{
    public function acquire(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        \DateTimeImmutable $expiresAt
    ): IdempotencyAcquireResult;

    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function finalize(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        int $responseStatus,
        ?array $responseBody,
        \DateTimeImmutable $expiresAt
    ): void;

    public function abandon(string $idempotencyKey, string $scope): void;

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
