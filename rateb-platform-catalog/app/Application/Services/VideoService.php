<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductVideoAdded;
use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Application\Policies\VideoPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class VideoService
{
    public function __construct(
        private readonly ProductVideoReadRepositoryInterface $readRepository,
        private readonly ProductVideoWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly StorageAdapterInterface $storage,
        private readonly VideoPolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(string $productUuid, ?LocaleContext $locale = null): array
    {
        $this->policy->viewList();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $rows = $this->readRepository->listByProductUuid($productUuid, $locale);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $translations = $this->readRepository->listTranslationsGrouped($ids);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $items[] = MediaMapper::toProductVideoDto($row, $this->storage, $translations[$id] ?? [])->toArray();
        }

        return [
            'items' => $items,
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(string $productUuid, array $payload, ?LocaleContext $locale = null): array
    {
        $this->policy->create();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $videoUuid = $this->writeRepository->createForProduct(
            $productUuid,
            $payload,
            $payload['translations'] ?? [],
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null
        );

        $this->events->dispatch(new ProductVideoAdded(
            $productUuid,
            $videoUuid,
            (string) ($payload['video_type'] ?? 'youtube')
        ));

        $list = $this->list($productUuid, $locale);
        $item = null;
        foreach ($list['items'] as $video) {
            if ($video['uuid'] === $videoUuid) {
                $item = $video;
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $videoUuid],
            'meta' => $list['meta'],
        ];
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
