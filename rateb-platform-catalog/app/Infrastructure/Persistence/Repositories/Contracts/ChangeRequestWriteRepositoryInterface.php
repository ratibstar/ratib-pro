<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ChangeRequestWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $proposedChanges
     * @param list<array{field_path: string, old_value: mixed, new_value: mixed}> $items
     */
    public function create(
        int $productId,
        string $requestType,
        array $proposedChanges,
        int $currentVersion,
        ?int $submittedBy,
        array $items
    ): string;

    public function assignReviewer(string $uuid, int $reviewerId): bool;

    public function approve(string $uuid, ?int $reviewedBy, ?string $note): bool;

    public function reject(string $uuid, ?int $reviewedBy, ?string $note): bool;

    public function markApplied(string $uuid): bool;

    /**
     * @param array<string, mixed> $productData
     * @param list<array<string, mixed>> $translations
     * @param array<string, mixed>|null $seoData
     * @param array<string, mixed> $versionSnapshot
     * @return array{version_number: int, lock_version: int, version_uuid: string}
     */
    public function applyApproved(
        string $uuid,
        string $productUuid,
        int $expectedLockVersion,
        int $expectedCurrentVersion,
        array $productData,
        array $translations,
        ?array $seoData,
        array $versionSnapshot,
        ?int $actorId
    ): array;
}
