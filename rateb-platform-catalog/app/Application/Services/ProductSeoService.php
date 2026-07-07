<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Mappers\ProductSeoMapper;
use Rateb\PlatformCatalog\Application\Policies\ProductSeoPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Validators\ProductSeoValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoWriteRepositoryInterface;

final class ProductSeoService
{
    public function __construct(
        private readonly ProductSeoReadRepositoryInterface $readRepository,
        private readonly ProductSeoWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly ProductSeoPolicy $policy,
        private readonly ProductSeoValidator $validator,
        private readonly LocaleResolverService $localeResolver,
        private readonly AuditEventService $auditEventService,
        private readonly EventDispatcher $events,
        private readonly ?CompletenessService $completenessService = null
    ) {
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function get(string $productUuid, ?LocaleContext $locale = null): array
    {
        $this->policy->view();
        $locale ??= $this->localeResolver->resolveFromRequest();

        if ($this->productReadRepository->findWorkflowMeta($productUuid) === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        $row = $this->readRepository->findByProductUuid($productUuid, $locale);

        return [
            'item' => $row !== null ? ProductSeoMapper::toDto($row)->toArray() : null,
            'meta' => LocaleMetaBuilder::build($locale, $row !== null ? [$row] : []),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function upsert(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->update();
        $locale ??= $this->localeResolver->resolveFromRequest();

        if ($this->productReadRepository->findWorkflowMeta($productUuid) === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        $before = $this->readRepository->findByProductUuid($productUuid);

        $canonicalUrl = array_key_exists('canonical_url', $payload) ? $payload['canonical_url'] : null;
        if ($canonicalUrl !== null && $canonicalUrl !== '') {
            $canonicalUrl = (string) $canonicalUrl;
        } else {
            $canonicalUrl = null;
        }

        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        $this->validator->validate($canonicalUrl, $translations, $productUuid);

        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;
        $seoUuid = $this->writeRepository->upsertForProduct($productUuid, $canonicalUrl, $translations, $actorId);

        $afterRow = $this->readRepository->findByProductUuid($productUuid, $locale);
        $after = $afterRow !== null ? ProductSeoMapper::toDto($afterRow)->toArray() : null;

        $this->auditEventService->record(
            'product_seo',
            $seoUuid,
            $before === null ? 'create' : 'update',
            null,
            $actorId,
            $before !== null ? ProductSeoMapper::toDto($before)->toArray() : null,
            $after
        );

        $this->events->dispatch(new ProductUpdated($productUuid, $locale->locale));
        $this->completenessService?->recalculateForProductUuid($productUuid);

        return [
            'item' => (array) $after,
            'meta' => LocaleMetaBuilder::build($locale, $afterRow !== null ? [$afterRow] : []),
        ];
    }
}
