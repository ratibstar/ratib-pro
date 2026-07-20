<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Persistence port for rateb_mobile_devices (ERP-owned phone registry).
 */
interface MobileDeviceStoreInterface
{
    /** @return array<string,mixed>|null */
    public function findByIdentity(int $companyId, string $clientApp, string $deviceId): ?array;

    /** @return array<string,mixed>|null */
    public function findByIdForUser(int $companyId, int $userId, int $id): ?array;

    /**
     * @return list<array<string,mixed>>
     */
    public function listActiveWithPush(int $companyId, int $userId, string $clientApp): array;

    /** @param array<string,mixed> $data */
    public function insert(array $data): int;

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void;
}
