<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductFileUploaded;
use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Application\Policies\FilePolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaStorageKeyBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaUploadHelper;
use Rateb\PlatformCatalog\Application\Validators\UploadValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class FileService
{
    public function __construct(
        private readonly ProductFileReadRepositoryInterface $readRepository,
        private readonly ProductFileWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly StorageAdapterInterface $storage,
        private readonly FilePolicy $policy,
        private readonly LocaleResolverService $localeResolver,
        private readonly EventDispatcher $events,
        private readonly ?UploadValidator $uploadValidator = null
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
            $items[] = MediaMapper::toProductFileDto($row, $this->storage, $translations[$id] ?? [])->toArray();
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
    public function upload(
        string $productUuid,
        array $payload,
        ?array $uploadedFile = null,
        ?LocaleContext $locale = null
    ): array {
        $this->policy->upload();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $assetTypeCode = (string) ($payload['asset_type_code'] ?? 'pdf');
        $binary = $this->uploadValidator !== null
            ? $this->uploadValidator->resolveAndValidate($payload, $uploadedFile, $assetTypeCode, $locale, false)
            : MediaUploadHelper::resolveBinary($payload, $uploadedFile);
        $checksum = MediaUploadHelper::sha256($binary['content']);
        $fileUuid = Uuid::v4();
        $storageKey = MediaStorageKeyBuilder::productFile($productUuid, $fileUuid, $binary['extension']);

        try {
            $this->storage->put($storageKey, $binary['content'], [
                'mime_type' => $binary['mime_type'],
                'checksum_sha256' => $checksum,
            ]);
            $this->writeRepository->createForProduct(
                $productUuid,
                $fileUuid,
                $storageKey,
                [
                    'asset_type_code' => $assetTypeCode,
                    'mime_type' => $binary['mime_type'],
                    'file_size_bytes' => $binary['size'],
                    'checksum_sha256' => $checksum,
                    'sort_order' => (int) ($payload['sort_order'] ?? 0),
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

        $this->events->dispatch(new ProductFileUploaded($productUuid, $fileUuid, $storageKey, $checksum));

        $list = $this->list($productUuid, $locale);
        $item = null;
        foreach ($list['items'] as $file) {
            if ($file['uuid'] === $fileUuid) {
                $item = $file;
                break;
            }
        }

        return [
            'item' => $item ?? ['uuid' => $fileUuid, 'storage_key' => $storageKey],
            'meta' => $list['meta'],
        ];
    }

    public function delete(string $productUuid, string $fileUuid, ?LocaleContext $locale = null): bool
    {
        $this->policy->upload();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $row = $this->readRepository->findByUuid($fileUuid, $locale);
        $deleted = $this->writeRepository->removeForProduct($productUuid, $fileUuid);
        if ($deleted && $row !== null && $this->storage->exists((string) $row['storage_key'])) {
            $this->storage->delete((string) $row['storage_key']);
        }

        return $deleted;
    }

    /**
     * @return array{storage_key: string, mime_type: string, stream: resource}
     */
    public function resolveFileStream(string $fileUuid): array
    {
        $this->policy->viewList();
        $locale = $this->localeResolver->resolveFromRequest();
        $row = $this->readRepository->findByUuid($fileUuid, $locale);
        if ($row === null) {
            throw new \RuntimeException('File not found', 404);
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
