<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\SavedFilterPolicy;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterWriteRepositoryInterface;

final class SavedFilterService
{
    public function __construct(
        private readonly SavedFilterReadRepositoryInterface $readRepository,
        private readonly SavedFilterWriteRepositoryInterface $writeRepository,
        private readonly SavedFilterPolicy $policy,
        private readonly PlatformIdentityResolver $identityResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(?string $entityType = null): array
    {
        $this->policy->view();
        $userId = $this->requireUserId();
        $items = array_map([$this, 'formatFilter'], $this->readRepository->listForUser($userId, $entityType));

        return ['items' => $items, 'meta' => ['count' => count($items)]];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function getByUuid(string $uuid): array
    {
        $this->policy->view();
        $userId = $this->requireUserId();
        $item = $this->readRepository->findByUuid($uuid, $userId);

        return [
            'item' => $item !== null ? $this->formatFilter($item) : null,
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $this->policy->manage();
        $userId = $this->requireUserId();
        $name = trim((string) ($payload['name'] ?? ''));
        $entityType = trim((string) ($payload['entity_type'] ?? ''));
        if ($name === '' || $entityType === '') {
            throw new \InvalidArgumentException('name and entity_type are required');
        }

        $filter = $payload['filter'] ?? $payload['filter_json'] ?? [];
        if (!is_array($filter)) {
            throw new \InvalidArgumentException('filter must be an object');
        }

        $uuid = $this->writeRepository->create(
            $userId,
            $name,
            $entityType,
            $filter,
            is_array($payload['sort'] ?? $payload['sort_json'] ?? null) ? ($payload['sort'] ?? $payload['sort_json']) : null,
            (bool) ($payload['is_default'] ?? false),
            (bool) ($payload['is_shared'] ?? false)
        );

        $item = $this->getByUuid($uuid)['item'];
        if ($item === null) {
            throw new \RuntimeException('Saved filter not found after create', 500);
        }

        return ['item' => $item, 'meta' => []];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function update(string $uuid, array $payload): array
    {
        $this->policy->manage();
        $userId = $this->requireUserId();
        $existing = $this->readRepository->findByUuid($uuid, $userId);
        if ($existing === null) {
            throw new \RuntimeException('Saved filter not found', 404);
        }

        $filter = $payload['filter'] ?? $payload['filter_json'] ?? $existing['filter_json'] ?? [];
        if (!is_array($filter)) {
            throw new \InvalidArgumentException('filter must be an object');
        }

        $this->writeRepository->update(
            $uuid,
            $userId,
            trim((string) ($payload['name'] ?? $existing['name'] ?? '')),
            $filter,
            is_array($payload['sort'] ?? $payload['sort_json'] ?? null)
                ? ($payload['sort'] ?? $payload['sort_json'])
                : ($existing['sort_json'] ?? null),
            (bool) ($payload['is_default'] ?? ($existing['is_default'] ?? false)),
            (bool) ($payload['is_shared'] ?? ($existing['is_shared'] ?? false))
        );

        return $this->getByUuid($uuid);
    }

    public function delete(string $uuid): bool
    {
        $this->policy->manage();

        return $this->writeRepository->delete($uuid, $this->requireUserId());
    }

    private function requireUserId(): int
    {
        $userId = $this->identityResolver->resolveActorId();
        if ($userId === null) {
            throw new \RuntimeException('Authentication required', 401);
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatFilter(array $row): array
    {
        $row['filter'] = $row['filter_json'] ?? [];
        $row['sort'] = $row['sort_json'] ?? null;
        unset($row['filter_json'], $row['sort_json']);

        return $row;
    }
}
