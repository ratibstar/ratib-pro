<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoWriteRepositoryInterface;

final class MysqlProductVideoWriteRepository extends BaseRepository implements ProductVideoWriteRepositoryInterface
{
    private const VIDEO_TYPES = ['youtube', 'vimeo', 'self_hosted'];

    protected function table(): string
    {
        return 'product_videos';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use createForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Video updates are not supported in Phase 2.6');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Video delete is not supported in Phase 2.6');
    }

    public function createForProduct(
        string $productUuid,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string {
        return $this->transaction(function () use ($productUuid, $metadata, $translations, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $videoType = (string) ($metadata['video_type'] ?? '');
            if (!in_array($videoType, self::VIDEO_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid video_type');
            }

            $assetTypeCode = (string) ($metadata['asset_type_code'] ?? match ($videoType) {
                'youtube' => 'video_youtube',
                'vimeo' => 'video_vimeo',
                default => 'video_self_hosted',
            });
            $assetTypeId = $this->resolveAssetTypeId($assetTypeCode);

            if ($videoType === 'self_hosted' && empty($metadata['storage_key'])) {
                throw new \InvalidArgumentException('storage_key is required for self_hosted videos');
            }
            if (in_array($videoType, ['youtube', 'vimeo'], true) && empty($metadata['external_url'])) {
                throw new \InvalidArgumentException('external_url is required for external videos');
            }

            $uuid = isset($metadata['video_uuid']) && is_string($metadata['video_uuid']) && $metadata['video_uuid'] !== ''
                ? (string) $metadata['video_uuid']
                : $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_videos (
                    uuid, product_id, asset_type_id, video_type, external_id, external_url,
                    storage_key, thumbnail_storage_key, duration_seconds, sort_order, created_by
                 ) VALUES (
                    :uuid, :product_id, :asset_type_id, :video_type, :external_id, :external_url,
                    :storage_key, :thumbnail_storage_key, :duration_seconds, :sort_order, :created_by
                 )'
            )->execute([
                'uuid' => $uuid,
                'product_id' => $productId,
                'asset_type_id' => $assetTypeId,
                'video_type' => $videoType,
                'external_id' => $metadata['external_id'] ?? null,
                'external_url' => $metadata['external_url'] ?? null,
                'storage_key' => $metadata['storage_key'] ?? null,
                'thumbnail_storage_key' => $metadata['thumbnail_storage_key'] ?? null,
                'duration_seconds' => $metadata['duration_seconds'] ?? null,
                'sort_order' => (int) ($metadata['sort_order'] ?? 0),
                'created_by' => $actorId,
            ]);

            $videoId = (int) $this->writePdo->lastInsertId();
            foreach ($translations as $translation) {
                $this->writePdo->prepare(
                    'INSERT INTO product_video_translations (uuid, product_video_id, language_code, title, description, created_by)
                     VALUES (:uuid, :product_video_id, :language_code, :title, :description, :created_by)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_video_id' => $videoId,
                    'language_code' => (string) $translation['language_code'],
                    'title' => (string) $translation['title'],
                    'description' => $translation['description'] ?? null,
                    'created_by' => $actorId,
                ]);
            }

            return $uuid;
        });
    }

    private function resolveAssetTypeId(string $code): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM asset_types WHERE code = :code AND deleted_at IS NULL LIMIT 1',
            ['code' => $code],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Asset type not found: ' . $code);
        }

        return (int) $row['id'];
    }
}
