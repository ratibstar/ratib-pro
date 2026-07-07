<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;

final class MysqlProductWorkflowWriteRepository extends BaseRepository implements ProductWorkflowWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_workflow_history';
    }

    public function transitionStatus(
        string $productUuid,
        string $fromStatus,
        string $toStatus,
        string $action,
        int $lockVersion,
        ?int $actorId,
        ?string $comment,
        ?array $versionSnapshot = null,
        ?string $versionChangeType = null,
        ?string $versionChangeSummary = null
    ): array {
        return $this->transaction(function () use (
            $productUuid,
            $fromStatus,
            $toStatus,
            $action,
            $lockVersion,
            $actorId,
            $comment,
            $versionSnapshot,
            $versionChangeType,
            $versionChangeSummary
        ): array {
            $product = $this->fetchOne(
                'SELECT id, status, version_number, lock_version
                 FROM products
                 WHERE uuid = :uuid AND deleted_at IS NULL
                 LIMIT 1 FOR UPDATE',
                ['uuid' => $productUuid],
                false
            );
            if ($product === null) {
                throw new \RuntimeException('Product not found', 404);
            }
            if ((string) $product['status'] !== $fromStatus) {
                throw new \RuntimeException('Invalid workflow transition: status changed', 409);
            }
            if ((int) $product['lock_version'] !== $lockVersion) {
                throw new \RuntimeException('version_conflict', 409);
            }

            $sets = ['status = :status', 'lock_version = lock_version + 1', 'updated_by = :actor_id'];
            $params = [
                'uuid' => $productUuid,
                'status' => $toStatus,
                'actor_id' => $actorId,
                'from_status' => $fromStatus,
                'expected_lock' => $lockVersion,
            ];

            if ($toStatus === 'approved') {
                $sets[] = 'approved_by = :approved_by';
                $sets[] = 'approved_at = CURRENT_TIMESTAMP(6)';
                $params['approved_by'] = $actorId;
            }
            if ($toStatus === 'published') {
                $sets[] = 'published_at = CURRENT_TIMESTAMP(6)';
                $sets[] = 'version_number = version_number + 1';
            }
            if ($toStatus === 'archived' && $versionSnapshot !== null) {
                $sets[] = 'version_number = version_number + 1';
            }

            $sql = 'UPDATE products SET ' . implode(', ', $sets) . '
                    WHERE uuid = :uuid AND lock_version = :expected_lock AND status = :from_status AND deleted_at IS NULL';
            $stmt = $this->writePdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('version_conflict', 409);
            }

            $refreshed = $this->fetchOne(
                'SELECT id, version_number, lock_version FROM products WHERE uuid = :uuid LIMIT 1',
                ['uuid' => $productUuid],
                false
            );

            $versionUuid = null;
            if ($versionSnapshot !== null && $versionChangeType !== null) {
                $versionNumber = (int) ($refreshed['version_number'] ?? $product['version_number']);
                $versionSnapshot['entity_version'] = $versionNumber;
                $versionUuid = $this->newUuid();
                $this->writePdo->prepare(
                    'INSERT INTO product_versions
                     (uuid, product_id, version_number, change_type, change_summary, snapshot_json, entity_version, created_by)
                     VALUES (:uuid, :product_id, :version_number, :change_type, :change_summary, :snapshot_json, :entity_version, :created_by)'
                )->execute([
                    'uuid' => $versionUuid,
                    'product_id' => (int) $product['id'],
                    'version_number' => $versionNumber,
                    'change_type' => $versionChangeType,
                    'change_summary' => $versionChangeSummary,
                    'snapshot_json' => json_encode($versionSnapshot, JSON_UNESCAPED_UNICODE) ?: '{}',
                    'entity_version' => $versionNumber,
                    'created_by' => $actorId,
                ]);
            }

            $historyUuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_workflow_history
                 (uuid, product_id, product_uuid, from_status, to_status, action, actor_id, comment, entity_version, created_by)
                 VALUES (:uuid, :product_id, :product_uuid, :from_status, :to_status, :action, :actor_id, :comment, :entity_version, :created_by)'
            )->execute([
                'uuid' => $historyUuid,
                'product_id' => (int) $product['id'],
                'product_uuid' => $productUuid,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'action' => $action,
                'actor_id' => $actorId,
                'comment' => $comment,
                'entity_version' => (int) ($refreshed['version_number'] ?? $product['version_number']),
                'created_by' => $actorId,
            ]);

            return [
                'history_uuid' => $historyUuid,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'version_number' => (int) ($refreshed['version_number'] ?? $product['version_number']),
                'lock_version' => (int) ($refreshed['lock_version'] ?? $lockVersion + 1),
                'product_id' => (int) $product['id'],
                'version_uuid' => $versionUuid,
            ];
        });
    }
}
