<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

final class RbacService
{
    public function __construct(
        private readonly RbacReadRepositoryInterface $readRepository
    ) {
    }

    public function userHasPermission(int $userId, string $permissionSlug): bool
    {
        return in_array($permissionSlug, $this->readRepository->listPermissionSlugsForUser($userId), true);
    }

    /**
     * @return list<string>
     */
    public function listPermissionsForUser(int $userId): array
    {
        return $this->readRepository->listPermissionSlugsForUser($userId);
    }

    public function resolveUserId(string $reference): ?int
    {
        if ($reference === '') {
            return null;
        }

        if (ctype_digit($reference)) {
            $userId = (int) $reference;

            return $this->readRepository->userIsActive($userId) ? $userId : null;
        }

        return $this->readRepository->findActiveUserIdByUuid($reference);
    }
}
