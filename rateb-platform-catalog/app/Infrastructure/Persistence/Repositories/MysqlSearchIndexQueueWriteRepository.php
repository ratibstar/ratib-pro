<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueWriteRepositoryInterface;

final class MysqlSearchIndexQueueWriteRepository extends BaseRepository implements SearchIndexQueueWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'search_index_queue';
    }

    public function enqueue(string $entityType, string $entityUuid, string $locale, string $action = 'upsert'): string
    {
        return $this->transaction(function () use ($entityType, $entityUuid, $locale, $action): string {
            $existing = $this->fetchOne(
                'SELECT uuid FROM search_index_queue
                 WHERE entity_type = :entity_type AND entity_uuid = :entity_uuid
                   AND locale = :locale AND action = :action
                   AND status IN ("pending", "processing")
                 LIMIT 1',
                [
                    'entity_type' => $entityType,
                    'entity_uuid' => $entityUuid,
                    'locale' => $locale,
                    'action' => $action,
                ],
                false
            );
            if ($existing !== null) {
                return (string) $existing['uuid'];
            }

            $uuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO search_index_queue (uuid, entity_type, entity_uuid, locale, action)
                 VALUES (:uuid, :entity_type, :entity_uuid, :locale, :action)'
            )->execute([
                'uuid' => $uuid,
                'entity_type' => $entityType,
                'entity_uuid' => $entityUuid,
                'locale' => $locale,
                'action' => $action,
            ]);

            return $uuid;
        });
    }

    public function markCompleted(string $uuid): void
    {
        $this->writePdo->prepare(
            'UPDATE search_index_queue SET status = "completed", processed_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid'
        )->execute(['uuid' => $uuid]);
    }

    public function markFailed(string $uuid, string $error): void
    {
        $this->writePdo->prepare(
            'UPDATE search_index_queue SET status = "failed", last_error = :error, attempts = attempts + 1
             WHERE uuid = :uuid'
        )->execute(['uuid' => $uuid, 'error' => $error]);
    }
}
