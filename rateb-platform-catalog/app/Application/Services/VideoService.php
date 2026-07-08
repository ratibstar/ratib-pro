<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductVideoAdded;
use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Application\Policies\VideoPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaStorageKeyBuilder;
use Rateb\PlatformCatalog\Application\Support\MediaUploadHelper;
use Rateb\PlatformCatalog\Application\Validators\UploadValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class VideoService
{
    public function __construct(
        private readonly ProductVideoReadRepositoryInterface $readRepository,
        private readonly ProductVideoWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly StorageAdapterInterface $storage,
        private readonly VideoPolicy $policy,
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
            $items[] = MediaMapper::toProductVideoDto($row, $this->storage, $translations[$id] ?? [])->toArray();
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
    public function create(
        string $productUuid,
        array $payload,
        ?array $uploadedFile = null,
        ?LocaleContext $locale = null
    ): array {
        $this->policy->create();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $this->assertProductExists($productUuid, $locale);

        $videoType = (string) ($payload['video_type'] ?? 'youtube');
        $storageKeyForRollback = null;

        try {
            if ($videoType === 'self_hosted') {
                $payload = $this->prepareSelfHostedPayload($productUuid, $payload, $uploadedFile, $locale, $storageKeyForRollback);
            }

            $videoUuid = $this->writeRepository->createForProduct(
                $productUuid,
                $payload,
                $payload['translations'] ?? [],
                isset($payload['actor_id']) ? (int) $payload['actor_id'] : null
            );
        } catch (\Throwable $e) {
            if ($storageKeyForRollback !== null && $this->storage->exists($storageKeyForRollback)) {
                $this->storage->delete($storageKeyForRollback);
            }
            throw $e;
        }

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

    /**
     * @param array<string, mixed> $payload
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $uploadedFile
     * @return array<string, mixed>
     */
    private function prepareSelfHostedPayload(
        string $productUuid,
        array $payload,
        ?array $uploadedFile,
        LocaleContext $locale,
        ?string &$storageKeyForRollback
    ): array {
        $hasBinary = $this->hasUploadBinary($payload, $uploadedFile);
        $clientKey = isset($payload['storage_key']) ? trim((string) $payload['storage_key']) : '';

        if ($hasBinary && $clientKey !== '') {
            throw new \InvalidArgumentException('storage_key must not be supplied when uploading binary content');
        }

        if ($hasBinary) {
            return $this->storeSelfHostedBinary($productUuid, $payload, $uploadedFile, $locale, $storageKeyForRollback);
        }

        if ($clientKey === '') {
            throw new \InvalidArgumentException('storage_key or upload binary is required for self_hosted videos');
        }

        $this->assertValidClientStorageKey($clientKey);
        if (!$this->storage->exists($clientKey)) {
            throw new \InvalidArgumentException('storage_key does not reference an existing stored object');
        }

        $payload['storage_key'] = $clientKey;

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $uploadedFile
     * @return array<string, mixed>
     */
    private function storeSelfHostedBinary(
        string $productUuid,
        array $payload,
        ?array $uploadedFile,
        LocaleContext $locale,
        ?string &$storageKeyForRollback
    ): array {
        $assetTypeCode = (string) ($payload['asset_type_code'] ?? 'video_self_hosted');
        $binary = $this->uploadValidator !== null
            ? $this->uploadValidator->resolveAndValidate($payload, $uploadedFile, $assetTypeCode, $locale, false)
            : MediaUploadHelper::resolveBinary($payload, $uploadedFile);
        $checksum = MediaUploadHelper::sha256($binary['content']);
        $videoUuid = Uuid::v4();
        $filename = $videoUuid . '.' . ltrim($binary['extension'], '.');
        $storageKey = MediaStorageKeyBuilder::productVideo($productUuid, $videoUuid, $filename);
        $storageKeyForRollback = $storageKey;

        $this->storage->put($storageKey, $binary['content'], [
            'mime_type' => $binary['mime_type'],
            'checksum_sha256' => $checksum,
        ]);

        $payload['storage_key'] = $storageKey;
        $payload['video_uuid'] = $videoUuid;

        return $payload;
    }

    private function assertValidClientStorageKey(string $storageKey): void
    {
        $normalized = str_replace('\\', '/', ltrim($storageKey, '/'));
        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new \InvalidArgumentException('storage_key is invalid');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $uploadedFile
     */
    private function hasUploadBinary(array $payload, ?array $uploadedFile): bool
    {
        if ($uploadedFile !== null && is_readable($uploadedFile['tmp_name'] ?? '')) {
            return true;
        }

        return isset($payload['content_base64'])
            && is_string($payload['content_base64'])
            && $payload['content_base64'] !== '';
    }
}
