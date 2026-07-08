<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\CollectionPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionWriteRepositoryInterface;

final class CollectionService
{
    public function __construct(
        private readonly CollectionReadRepositoryInterface $readRepository,
        private readonly CollectionWriteRepositoryInterface $writeRepository,
        private readonly CollectionPolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly PlatformIdentityResolver $identityResolver
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(int $limit = 50, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $rows = $this->readRepository->list($locale, $limit, $offset);

        return [
            'items' => $rows,
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function getByUuid(string $uuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewDetail();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $row = $this->readRepository->findByUuid($uuid, $locale);

        return [
            'item' => $row,
            'meta' => LocaleMetaBuilder::build($locale, $row !== null ? [$row] : []),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listProducts(string $uuid, int $limit = 50, int $offset = 0, ?LocaleContext $locale = null): array
    {
        $this->policy->viewDetail();
        $locale ??= $this->localeResolver->resolveFromRequest();
        if ($this->readRepository->findByUuid($uuid, $locale) === null) {
            throw new \RuntimeException('Collection not found', 404);
        }

        $rows = $this->readRepository->listProducts($uuid, $locale, $limit, $offset);

        return [
            'items' => $rows,
            'meta' => LocaleMetaBuilder::build($locale, $rows, $limit, $offset),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->manage();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $actorId = $this->identityResolver->resolveActorId();
        $payload['created_by'] = $actorId;

        $uuid = $this->writeRepository->create($payload, $payload['translations'] ?? []);
        if (isset($payload['product_uuids']) && is_array($payload['product_uuids'])) {
            $this->writeRepository->replaceProducts($uuid, $payload['product_uuids']);
        }

        $result = $this->getByUuid($uuid, $locale);
        if ($result['item'] === null) {
            throw new \RuntimeException('Collection not found after create', 500);
        }

        return ['item' => $result['item'], 'meta' => $result['meta']];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function update(string $uuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->manage();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $payload['updated_by'] = $this->identityResolver->resolveActorId();

        $updated = $this->writeRepository->update($uuid, $payload, $payload['translations'] ?? []);
        if (!$updated && $this->readRepository->findByUuid($uuid, $locale) === null) {
            throw new \RuntimeException('Collection not found', 404);
        }

        if (isset($payload['product_uuids']) && is_array($payload['product_uuids'])) {
            $this->writeRepository->replaceProducts($uuid, $payload['product_uuids']);
        }

        return $this->getByUuid($uuid, $locale);
    }
}
