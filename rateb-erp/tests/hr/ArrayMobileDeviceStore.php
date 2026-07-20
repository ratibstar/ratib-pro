<?php
declare(strict_types=1);

/**
 * In-memory store for Phase I.1 mobile device tests (no DB).
 */
final class ArrayMobileDeviceStore implements \Rateb\App\Services\MobileDeviceStoreInterface
{
    /** @var array<int, array<string,mixed>> */
    public array $rows = [];
    private int $seq = 1;

    public function findByIdentity(int $companyId, string $clientApp, string $deviceId): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) $row['company_id'] === $companyId
                && (string) $row['client_app'] === $clientApp
                && (string) $row['device_id'] === $deviceId) {
                return $row;
            }
        }

        return null;
    }

    public function findByIdForUser(int $companyId, int $userId, int $id): ?array
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null) {
            return null;
        }
        if ((int) $row['company_id'] !== $companyId || (int) $row['user_id'] !== $userId) {
            return null;
        }

        return $row;
    }

    public function listActiveWithPush(int $companyId, int $userId, string $clientApp): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ((int) $row['company_id'] !== $companyId) {
                continue;
            }
            if ((int) $row['user_id'] !== $userId) {
                continue;
            }
            if ((string) $row['client_app'] !== $clientApp) {
                continue;
            }
            if ((string) ($row['status'] ?? '') !== 'active') {
                continue;
            }
            $tok = (string) ($row['push_token'] ?? '');
            if ($tok === '') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function insert(array $data): int
    {
        $id = $this->seq++;
        $data['id'] = $id;
        $data['push_provider'] = $data['push_provider'] ?? 'none';
        $this->rows[$id] = $data;

        return $id;
    }

    public function update(int $id, array $data): void
    {
        if (!isset($this->rows[$id])) {
            return;
        }
        $this->rows[$id] = array_merge($this->rows[$id], $data);
    }
}
