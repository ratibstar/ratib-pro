<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductImageUploaded;
use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Application\Policies\MediaPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaStorageKeyBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaUploadHelper;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class MediaService
{
    public function __construct(
        private readonly ProductImageReadRepositoryInterface $readRepository,
        private readonly ProductImageWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly StorageAdapterInterface $storage,
        private readonly MediaPolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listImages(string $productUuid, ?LocaleContext $locale = null): array
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
            $items[] = MediaMapper::toProductImageDto($row, $this->storage, $translations[$id] ?? [])->toArray();
        }

        return [
            'items' => $items,
            'meta' => LocaleMetaBuilder::build($locale, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $uploadedFile
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function uploadImage(
        string $productUuid,
        array $payload,
        ?array $uploadedFile = null,
        ?LocaleContext $locale = null
    ): array {
        $this->policy->upload();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $binary = MediaUploadHelper::resolveBinary($payload, $uploadedFile);
        $checksum = MediaUploadHelper::sha256($binary['content']);
        $dimensions = MediaUploadHelper::imageDimensions($binary['content']);
        $imageUuid = Uuid::v4();
        $storageKey = MediaStorageKeyBuilder::productImage($productUuid, $imageUuid, 'original', $binary['extension']);

        try {
            $this->storage->put($storageKey, $binary['content'], ['mime_type' => $binary['mime_type']]);
            $this->writeRepository->createForProduct(
                $productUuid,
                $imageUuid,
                $storageKey,
                [
                    'asset_type_code' => $payload['asset_type_code'] ?? 'image_original',
                    'mime_type' => $binary['mime_type'],
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'file_size_bytes' => $binary['size'],
                    'variant' => 'original',
                    'sort_order' => (int) ($payload['sort_order'] ?? 0),
                    'is_primary' => (int) ($payload['is_primary'] ?? 0),
                    'checksum_sha256' => $checksum,
                ],
                $payload['translations'] ?? [],
                isset($payload['actor_id']) ? (int) $payload['actor_id'] : null
            );
        } catch (\Throwable $e) {
            if ($this->storage->exists($storageKey)) {
                $this->storage->delete($storageKey);
            }
            throw $e;
        }

        $this->events->dispatch(new ProductImageUploaded($productUuid, $imageUuid, $storageKey, $checksum));

        $list = $this->listImages($productUuid, $locale);
        $item = null;
        foreach ($list['items'] as $image) {
            if ($image['uuid'] === $imageUuid && $image['variant'] === 'original') {
                $item = $image;
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $imageUuid, 'storage_key' => $storageKey],
            'meta' => $list['meta'],
        ];
    }

    public function deleteImage(string $productUuid, string $imageUuid, ?LocaleContext $locale = null): bool
    {
        $this->policy->upload();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $rows = $this->readRepository->listByProductUuid($productUuid, $locale);
        $keys = [];
        foreach ($rows as $row) {
            if ($row['uuid'] === $imageUuid) {
                $keys[] = (string) $row['storage_key'];
            }
        }

        $deleted = $this->writeRepository->removeForProduct($productUuid, $imageUuid);
        if ($deleted) {
            foreach ($keys as $key) {
                if ($this->storage->exists($key)) {
                    $this->storage->delete($key);
                }
            }
        }

        return $deleted;
    }

    /**
     * @return array{storage_key: string, mime_type: string, stream: resource}
     */
    public function resolveImageStream(string $imageUuid, string $variant): array
    {
        $this->policy->viewList();
        $row = $this->readRepository->findByUuidAndVariant($imageUuid, $variant);
        if ($row === null) {
            throw new \RuntimeException('Image not found', 404);
        }

        $storageKey = (string) $row['storage_key'];

        return [
            'storage_key' => $storageKey,
            'mime_type' => (string) $row['mime_type'],
            'stream' => $this->storage->get($storageKey),
        ];
    }

    private function assertProductExists(string $productUuid, LocaleContext $locale): void
    {
        if ($this->productReadRepository->findByUuid($productUuid, $locale) === null) {
            throw new \RuntimeException('Product not found', 404);
        }
    }
}
