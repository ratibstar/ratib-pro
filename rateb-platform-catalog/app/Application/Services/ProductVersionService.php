<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Events\VersionCreated;
use Rateb\PlatformCatalog\Application\Policies\ProductPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotRestoreRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionWriteRepositoryInterface;

final class ProductVersionService
{
    public function __construct(
        private readonly ProductVersionReadRepositoryInterface $readRepository,
        private readonly ProductVersionWriteRepositoryInterface $writeRepository,
        private readonly ProductSnapshotRestoreRepositoryInterface $restoreRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductSnapshotBuilder $snapshotBuilder,
        private readonly ProductPolicy $policy,
        private readonly ConcurrencyService $concurrencyService,
        private readonly AuditEventService $auditEventService,
        private readonly LocaleResolverService $localeResolver,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $productUuid, int $limit = 50): array
    {
        $this->policy->viewDetail();
        $meta = $this->productReadRepository->findWorkflowMeta($productUuid);
        if ($meta === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        return $this->readRepository->listByProductUuid($productUuid, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $productUuid, int $versionNumber): array
    {
        $this->policy->viewDetail();
        $version = $this->readRepository->findByProductAndVersion($productUuid, $versionNumber);
        if ($version === null) {
            throw new \RuntimeException('Version not found', 404);
        }

        return $version;
    }

    /**
     * @return array{from_version: int, to_version: int, differences: list<array<string, mixed>>}
     */
    public function compare(string $productUuid, int $fromVersion, int $toVersion): array
    {
        $this->policy->viewDetail();
        $from = $this->readRepository->findByProductAndVersion($productUuid, $fromVersion);
        $to = $this->readRepository->findByProductAndVersion($productUuid, $toVersion);
        if ($from === null || $to === null) {
            throw new \RuntimeException('Version not found', 404);
        }

        return [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'differences' => $this->diffSnapshots(
                is_array($from['snapshot'] ?? null) ? $from['snapshot'] : [],
                is_array($to['snapshot'] ?? null) ? $to['snapshot'] : []
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function restore(string $productUuid, int $versionNumber, array $payload): array
    {
        $this->policy->update();
        $version = $this->readRepository->findByProductAndVersion($productUuid, $versionNumber);
        if ($version === null) {
            throw new \RuntimeException('Version not found', 404);
        }

        $snapshot = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [];
        $lockVersion = $this->concurrencyService->requireLockVersion(
            isset($payload['lock_version']) ? (int) $payload['lock_version'] : null
        );
        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;

        try {
            $result = $this->restoreRepository->restore(
                $productUuid,
                $snapshot,
                $lockVersion,
                $actorId,
                'Restored from version ' . $versionNumber
            );
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 409) {
                throw new ProductVersionConflictException(
                    (int) ($this->productReadRepository->findLockVersion($productUuid) ?? $lockVersion)
                );
            }
            throw $e;
        }

        $this->auditEventService->record(
            'product',
            $productUuid,
            'version_restore',
            (int) $result['version_number'],
            $actorId,
            ['restored_from_version' => $versionNumber],
            ['version_number' => (int) $result['version_number']]
        );

        $locale = $this->localeResolver->resolveFromRequest();
        $this->events->dispatch(new VersionCreated($productUuid, (int) $result['version_number'], 'restore'));
        $this->events->dispatch(new ProductUpdated($productUuid, $locale->locale));

        return [
            'product_uuid' => $productUuid,
            'restored_from_version' => $versionNumber,
            'version_number' => (int) $result['version_number'],
            'version_uuid' => $result['version_uuid'],
            'lock_version' => (int) $result['lock_version'],
        ];
    }

    public function createSnapshot(
        string $productUuid,
        int $productId,
        int $versionNumber,
        string $changeType,
        ?int $actorId,
        ?string $changeSummary = null
    ): string {
        unset($productId);
        $snapshot = $this->snapshotBuilder->build($productUuid, $versionNumber);

        $uuid = $this->writeRepository->create(
            (int) ($this->productReadRepository->findWorkflowMeta($productUuid)['id'] ?? 0),
            $versionNumber,
            $changeType,
            $snapshot,
            $versionNumber,
            $changeSummary,
            $actorId
        );

        $this->events->dispatch(new VersionCreated($productUuid, $versionNumber, $changeType));

        return $uuid;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return list<array<string, mixed>>
     */
    private function diffSnapshots(array $left, array $right): array
    {
        $differences = [];
        $this->collectDiff('', $left, $right, $differences);

        return $differences;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @param list<array<string, mixed>> $differences
     */
    private function collectDiff(string $prefix, array $left, array $right, array &$differences): void
    {
        $keys = array_unique(array_merge(array_keys($left), array_keys($right)));
        foreach ($keys as $key) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $leftValue = $left[$key] ?? null;
            $rightValue = $right[$key] ?? null;
            if (is_array($leftValue) && is_array($rightValue)) {
                $this->collectDiff($path, $leftValue, $rightValue, $differences);
                continue;
            }
            if ($leftValue !== $rightValue) {
                $differences[] = [
                    'field_path' => $path,
                    'old_value' => $leftValue,
                    'new_value' => $rightValue,
                ];
            }
        }
    }
}
