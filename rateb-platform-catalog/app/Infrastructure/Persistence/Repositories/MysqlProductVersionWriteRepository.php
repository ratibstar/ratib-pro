<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionWriteRepositoryInterface;

final class MysqlProductVersionWriteRepository extends BaseRepository implements ProductVersionWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_versions';
    }

    public function create(
        int $productId,
        int $versionNumber,
        string $changeType,
        array $snapshot,
        int $entityVersion,
        ?string $changeSummary,
        ?int $actorId
    ): string {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO product_versions
             (uuid, product_id, version_number, change_type, change_summary, snapshot_json, entity_version, created_by)
             VALUES (:uuid, :product_id, :version_number, :change_type, :change_summary, :snapshot_json, :entity_version, :created_by)'
        )->execute([
            'uuid' => $uuid,
            'product_id' => $productId,
            'version_number' => $versionNumber,
            'change_type' => $changeType,
            'change_summary' => $changeSummary,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '{}',
            'entity_version' => $entityVersion,
            'created_by' => $actorId,
        ]);

        return $uuid;
    }
}
