<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\DuplicatePolicy;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateWriteRepositoryInterface;

final class DuplicateService
{
    public function __construct(
        private readonly DuplicateReadRepositoryInterface $readRepository,
        private readonly DuplicateWriteRepositoryInterface $writeRepository,
        private readonly DuplicatePolicy $policy,
        private readonly QueueService $queueService,
        private readonly PlatformIdentityResolver $identityResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listGroups(?string $status, int $limit = 50, int $offset = 0): array
    {
        $this->policy->viewList();
        $items = $this->readRepository->listGroups($status, $limit, $offset);

        return ['items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit, 'offset' => $offset]];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function getGroup(string $uuid): array
    {
        $this->policy->viewDetail();
        $item = $this->readRepository->findGroupByUuid($uuid);

        return ['item' => $item, 'meta' => []];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listRules(): array
    {
        $this->policy->viewList();
        $items = $this->readRepository->listRules();

        return ['items' => $items, 'meta' => ['count' => count($items)]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function resolve(string $uuid, array $payload): array
    {
        $this->policy->resolve();
        if ($this->readRepository->findGroupByUuid($uuid) === null) {
            throw new \RuntimeException('Duplicate group not found', 404);
        }

        $status = (string) ($payload['status'] ?? 'resolved');
        $actorId = $this->identityResolver->resolveActorId() ?? 0;
        $note = isset($payload['note']) ? (string) $payload['note'] : null;

        $this->writeRepository->resolveGroup($uuid, $actorId, $status, $note);

        return $this->getGroup($uuid);
    }

    public function enqueueScan(?string $ruleCode = null): string
    {
        $this->policy->scan();

        return $this->queueService->enqueueSystem('maintenance', 'duplicate_scan', [
            'rule_code' => $ruleCode,
        ], 'duplicate_scan:' . ($ruleCode ?? 'all'));
    }
}
