<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\DTO\ProductListFilter;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductCreated;
use Rateb\PlatformCatalog\Application\Events\ProductDeleted;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Mappers\ProductMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface;

final class ProductService
{
    public function __construct(
        private readonly ProductReadRepositoryInterface $readRepository,
        private readonly ProductWriteRepositoryInterface $writeRepository,
        private readonly ProductPolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly ConcurrencyService $concurrencyService,
        private readonly EventDispatcher $events,
        private readonly ?CompletenessService $completenessService = null,
        private readonly ?AuditEventService $auditEventService = null
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(ProductListFilter $filter, int $limit = 100, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->listFiltered($locale, $filter, $limit, $offset);

        return [
            'items' => array_map(static fn (array $row): array => ProductMapper::toProductDto($row)->toArray(), $rows),
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>, lock_version: int|null}
     */
    public function getByUuid(string $uuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewDetail();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $row = $this->readRepository->findByUuid($uuid, $locale);

        return [
            'item' => $row !== null ? ProductMapper::toProductDto($row)->toArray() : null,
            'meta' => LocaleMetaBuilder::build($locale, $row !== null ? [$row] : []),
            'lock_version' => $row !== null ? (int) $row['lock_version'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->create();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $uuid = $this->writeRepository->createWithTranslations(
            $payload,
            $payload['translations'] ?? [],
            $payload['actor_id'] ?? null
        );

        $this->events->dispatch(new ProductCreated($uuid, $locale->locale));

        $result = $this->getByUuid($uuid, $locale);

        $this->auditEventService?->record(
            'product',
            $uuid,
            'create',
            isset($result['item']['version_number']) ? (int) $result['item']['version_number'] : null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            null,
            (array) $result['item']
        );

        return [
            'item' => (array) $result['item'],
            'meta' => $result['meta'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function update(string $uuid, array $payload, ?int $lockVersion = null, ?LocaleContext $locale = null): array
    {
        $this->policy->update();
        $locale ??= $this->localeResolver->resolveFromRequest();

        $expected = $this->concurrencyService->resolveExpectedLockVersion(
            $lockVersion ?? (isset($payload['lock_version']) ? (int) $payload['lock_version'] : null)
        );

        if ($expected === null) {
            throw new \InvalidArgumentException('lock_version or If-Match header is required');
        }

        $current = $this->readRepository->findByUuid($uuid, $locale);
        $before = $current !== null ? ProductMapper::toProductDto($current)->toArray() : null;

        $lockRow = $this->readRepository->findLockVersion($uuid);
        if ($lockRow === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        try {
            $this->concurrencyService->assertLockVersion($expected, $lockRow);
        } catch (ProductVersionConflictException $e) {
            throw $e;
        }

        try {
            $this->writeRepository->updateWithTranslations(
                $uuid,
                $payload,
                $payload['translations'] ?? [],
                $expected,
                $payload['actor_id'] ?? null
            );
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 409) {
                $fresh = $this->readRepository->findLockVersion($uuid) ?? $lockRow;
                throw new ProductVersionConflictException($fresh);
            }
            throw $e;
        }

        $this->events->dispatch(new ProductUpdated($uuid, $locale->locale));
        $this->completenessService?->recalculateForProductUuid($uuid);

        $result = $this->getByUuid($uuid, $locale);

        $this->auditEventService?->record(
            'product',
            $uuid,
            'update',
            isset($result['item']['version_number']) ? (int) $result['item']['version_number'] : null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            $before,
            (array) $result['item']
        );

        return [
            'item' => (array) $result['item'],
            'meta' => $result['meta'],
        ];
    }

    public function delete(string $uuid, ?int $actorId = null): bool
    {
        $this->policy->delete();
        $locale = $this->localeResolver->resolveFromRequest();
        $beforeRow = $this->readRepository->findByUuid($uuid, $locale);
        $before = $beforeRow !== null ? ProductMapper::toProductDto($beforeRow)->toArray() : null;

        $deleted = $this->writeRepository->softDelete($uuid, $actorId);
        if ($deleted) {
            $this->events->dispatch(new ProductDeleted($uuid));
            $this->auditEventService?->record(
                'product',
                $uuid,
                'delete',
                isset($before['version_number']) ? (int) $before['version_number'] : null,
                $actorId,
                $before,
                null
            );
        }

        return $deleted;
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listByFamilyUuid(string $familyUuid, int $limit = 100, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->listByFamilyUuid($familyUuid, $locale, $limit, $offset);

        return [
            'items' => array_map(static fn (array $row): array => ProductMapper::toProductDto($row)->toArray(), $rows),
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    public function buildListFilterFromQuery(): ProductListFilter
    {
        return new ProductListFilter(
            status: isset($_GET['status']) ? (string) $_GET['status'] : null,
            categoryUuid: isset($_GET['category_uuid']) ? (string) $_GET['category_uuid'] : null,
            brandUuid: isset($_GET['brand_uuid']) ? (string) $_GET['brand_uuid'] : null,
            familyUuid: isset($_GET['family_uuid']) ? (string) $_GET['family_uuid'] : null,
            sku: isset($_GET['sku']) ? (string) $_GET['sku'] : null
        );
    }
}
