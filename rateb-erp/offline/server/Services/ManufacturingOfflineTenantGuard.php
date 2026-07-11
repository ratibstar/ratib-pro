<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\MfgBom;
use Rateb\App\Models\MfgProductionOrder;
use Rateb\App\Models\MfgRouting;
use Rateb\App\Models\MfgWorkOrder;

/**
 * Tenant + branch isolation for Manufacturing offline replay (Phase 22B).
 * Additive — does not alter Phase 22A MFG domain services or EAM work orders.
 */
final class ManufacturingOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, bom?: array<string, mixed>}
     */
    public function assertBom(int $bomId, array $scope): array
    {
        return $this->assertRow(
            $bomId,
            $scope,
            'rateb_mfg_boms',
            new MfgBom(),
            'bom_not_found',
            'invalid_bom_id',
            'bom'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, routing?: array<string, mixed>}
     */
    public function assertRouting(int $routingId, array $scope): array
    {
        return $this->assertRow(
            $routingId,
            $scope,
            'rateb_mfg_routings',
            new MfgRouting(),
            'routing_not_found',
            'invalid_routing_id',
            'routing'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, production_order?: array<string, mixed>}
     */
    public function assertProductionOrder(int $productionOrderId, array $scope): array
    {
        return $this->assertRow(
            $productionOrderId,
            $scope,
            'rateb_mfg_production_orders',
            new MfgProductionOrder(),
            'production_order_not_found',
            'invalid_production_order_id',
            'production_order'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, work_order?: array<string, mixed>}
     */
    public function assertWorkOrder(int $workOrderId, array $scope): array
    {
        return $this->assertRow(
            $workOrderId,
            $scope,
            'rateb_mfg_work_orders',
            new MfgWorkOrder(),
            'work_order_not_found',
            'invalid_work_order_id',
            'work_order'
        );
    }

    public function bomExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_mfg_boms', new MfgBom(), $idempotencyKey);
    }

    public function productionOrderExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_mfg_production_orders', new MfgProductionOrder(), $idempotencyKey);
    }

    public function workOrderExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_mfg_work_orders', new MfgWorkOrder(), $idempotencyKey);
    }

    private function existsForNotesKey(int $companyId, string $table, object $model, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = $model->queryOne(
            'SELECT id FROM ' . $table . '
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, bom?: array<string, mixed>, routing?: array<string, mixed>, production_order?: array<string, mixed>, work_order?: array<string, mixed>}
     */
    private function assertRow(
        int $id,
        array $scope,
        string $table,
        object $model,
        string $notFound,
        string $invalidId,
        string $key
    ): array {
        if ($id < 1) {
            return ['ok' => false, 'error' => $invalidId];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        /** @var array<string, mixed>|null $row */
        $row = $model->queryOne(
            'SELECT * FROM ' . $table . ' WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => $notFound];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, $key => $row];
    }
}
