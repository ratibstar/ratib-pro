<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelWriteRepositoryInterface;

final class MysqlChannelWriteRepository extends BaseRepository implements ChannelWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_channels';
    }

    public function replaceProductChannels(string $productUuid, array $assignments): void
    {
        $this->transaction(function () use ($productUuid, $assignments): void {
            $productId = $this->resolveProductIdByUuid($productUuid);

            $this->writePdo->prepare(
                'UPDATE product_channels
                 SET deleted_at = CURRENT_TIMESTAMP(6)
                 WHERE product_id = :product_id AND deleted_at IS NULL'
            )->execute(['product_id' => $productId]);

            foreach ($assignments as $assignment) {
                $channelId = $this->resolveChannelId($assignment);
                $this->writePdo->prepare(
                    'INSERT INTO product_channels
                     (uuid, product_id, channel_id, is_enabled, channel_config, publish_at, archive_at)
                     VALUES (:uuid, :product_id, :channel_id, :is_enabled, :channel_config, :publish_at, :archive_at)
                     ON DUPLICATE KEY UPDATE
                        is_enabled = VALUES(is_enabled),
                        channel_config = VALUES(channel_config),
                        publish_at = VALUES(publish_at),
                        archive_at = VALUES(archive_at),
                        deleted_at = NULL,
                        deleted_by = NULL,
                        updated_at = CURRENT_TIMESTAMP(6)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_id' => $productId,
                    'channel_id' => $channelId,
                    'is_enabled' => (int) ($assignment['is_enabled'] ?? 1),
                    'channel_config' => isset($assignment['channel_config'])
                        ? (json_encode($assignment['channel_config'], JSON_UNESCAPED_UNICODE) ?: '{}')
                        : null,
                    'publish_at' => $assignment['publish_at'] ?? null,
                    'archive_at' => $assignment['archive_at'] ?? null,
                ]);
            }
        });
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function resolveChannelId(array $assignment): int
    {
        if (isset($assignment['channel_uuid']) && $assignment['channel_uuid'] !== '') {
            $row = $this->fetchOne(
                'SELECT id FROM channels WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
                ['uuid' => (string) $assignment['channel_uuid']],
                false
            );
        } elseif (isset($assignment['channel_code']) && $assignment['channel_code'] !== '') {
            $row = $this->fetchOne(
                'SELECT id FROM channels WHERE code = :code AND deleted_at IS NULL LIMIT 1',
                ['code' => (string) $assignment['channel_code']],
                false
            );
        } else {
            throw new \InvalidArgumentException('channel_uuid or channel_code is required');
        }

        if ($row === null) {
            throw new \InvalidArgumentException('Channel not found');
        }

        return (int) $row['id'];
    }
}
