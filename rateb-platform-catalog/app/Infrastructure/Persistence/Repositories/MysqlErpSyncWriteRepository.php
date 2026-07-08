<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ErpSyncWriteRepositoryInterface;

final class MysqlErpSyncWriteRepository extends BaseRepository implements ErpSyncWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'erp_product_sync';
    }

    public function upsertSyncRecord(int $companyId, int $productId, ?int $variantId, int $version, string $status): void
    {
        $existing = $this->fetchOne(
            'SELECT id FROM erp_product_sync
             WHERE erp_company_id = :company_id
               AND product_id = :product_id
               AND ' . ($variantId === null ? 'product_variant_id IS NULL' : 'product_variant_id = :variant_id') . '
             LIMIT 1',
            $variantId === null
                ? ['company_id' => $companyId, 'product_id' => $productId]
                : ['company_id' => $companyId, 'product_id' => $productId, 'variant_id' => $variantId],
            false
        );

        if ($existing !== null) {
            $this->writePdo->prepare(
                'UPDATE erp_product_sync
                 SET platform_source_version = :version,
                     sync_status = :sync_status,
                     deleted_at = NULL,
                     deleted_by = NULL,
                     updated_at = CURRENT_TIMESTAMP(6)
                 WHERE id = :id'
            )->execute([
                'id' => (int) $existing['id'],
                'version' => $version,
                'sync_status' => $status,
            ]);

            return;
        }

        $this->writePdo->prepare(
            'INSERT INTO erp_product_sync
             (uuid, erp_company_id, product_id, product_variant_id, platform_source_version, sync_status)
             VALUES (:uuid, :erp_company_id, :product_id, :product_variant_id, :platform_source_version, :sync_status)'
        )->execute([
            'uuid' => $this->newUuid(),
            'erp_company_id' => $companyId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'platform_source_version' => $version,
            'sync_status' => $status,
        ]);
    }

    public function writeSyncLog(
        int $companyId,
        string $productUuid,
        int $fromVersion,
        int $toVersion,
        string $action,
        ?array $payload
    ): void {
        $this->writePdo->prepare(
            'INSERT INTO sync_logs
             (uuid, erp_company_id, product_uuid, from_version, to_version, sync_action, sync_payload)
             VALUES (:uuid, :erp_company_id, :product_uuid, :from_version, :to_version, :sync_action, :sync_payload)'
        )->execute([
            'uuid' => $this->newUuid(),
            'erp_company_id' => $companyId,
            'product_uuid' => $productUuid,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'sync_action' => $action,
            'sync_payload' => $payload === null ? null : (json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}'),
        ]);
    }
}
