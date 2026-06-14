<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;

final class AssetDeviceWorkflowService
{
    /** @param array<string, mixed> $data */
    public function createMaintenance(array $data, string $table = 'rateb_asset_maintenance'): int
    {
        $cid = TenantGuard::requireCompanyId();
        $db = \Rateb\App\Core\Database::connection();
        $wf = new WorkflowTableService();
        if ($table === 'rateb_asset_maintenance') {
            $assetId = (int) ($data['asset_id'] ?? 0);
            TenantGuard::assertAsset($assetId, $cid);
            $no = (string) ($data['maintenance_no'] ?? '');
            if ($no === '') {
                $no = $wf->generateRecordNo('asset-maintenance');
            }
            $db->prepare(
                'INSERT INTO rateb_asset_maintenance (company_id, maintenance_no, asset_id, maintenance_type, scheduled_date, completed_date, cost, status, notes)
                 VALUES (:cid, :no, :aid, :mt, :sd, :cd, :cost, :st, :notes)'
            )->execute([
                'cid' => $cid,
                'no' => $no,
                'aid' => $assetId,
                'mt' => $data['maintenance_type'] ?? 'general',
                'sd' => $data['scheduled_date'] ?? null,
                'cd' => $data['completed_date'] ?? null,
                'cost' => (float) ($data['cost'] ?? 0),
                'st' => $data['status'] ?? 'scheduled',
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $deviceId = (int) ($data['device_id'] ?? 0);
            TenantGuard::assertDevice($deviceId, $cid);
            $no = (string) ($data['service_no'] ?? '');
            if ($no === '') {
                $no = $wf->generateRecordNo('device-maintenance');
            }
            $db->prepare(
                'INSERT INTO rateb_device_service_history (company_id, service_no, device_id, service_date, service_type, provider, cost, notes)
                 VALUES (:cid, :no, :did, :sd, :st, :pr, :cost, :notes)'
            )->execute([
                'cid' => $cid,
                'no' => $no,
                'did' => $deviceId,
                'sd' => $data['service_date'] ?? date('Y-m-d'),
                'st' => $data['service_type'] ?? 'maintenance',
                'pr' => $data['provider'] ?? null,
                'cost' => (float) ($data['cost'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);
        }
        return (int) $db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createAssignment(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $assetId = (int) ($data['asset_id'] ?? 0);
        TenantGuard::assertAsset($assetId, $cid);
        $db = \Rateb\App\Core\Database::connection();
        $no = (new WorkflowTableService())->generateRecordNo('asset-assignments');
        $db->prepare(
            'INSERT INTO rateb_asset_assignments (company_id, assignment_no, asset_id, assigned_to, department, assigned_at, returned_at, notes)
             VALUES (:cid, :no, :aid, :to, :dept, :at, :ret, :notes)'
        )->execute([
            'cid' => $cid,
            'no' => $no,
            'aid' => $assetId,
            'to' => $data['assigned_to'] ?? '',
            'dept' => $data['department'] ?? null,
            'at' => $data['assigned_at'] ?? date('Y-m-d'),
            'ret' => ($data['returned_at'] ?? '') !== '' ? $data['returned_at'] : null,
            'notes' => $data['notes'] ?? null,
        ]);
        $id = (int) $db->lastInsertId();
        if ($assetId > 0 && !empty($data['assigned_to'])) {
            $db->prepare('UPDATE rateb_assets SET assigned_to = :t WHERE id = :id AND company_id = :cid')
                ->execute(['t' => $data['assigned_to'], 'id' => $assetId, 'cid' => $cid]);
        }
        return $id;
    }

    /** @param array<string, mixed> $data */
    public function recordDepreciation(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $assetId = (int) ($data['asset_id'] ?? 0);
        TenantGuard::assertAsset($assetId, $cid);
        $amount = (float) ($data['amount'] ?? 0);
        $book = (float) ($data['book_value'] ?? 0);
        $db = \Rateb\App\Core\Database::connection();
        $no = (new WorkflowTableService())->generateRecordNo('asset-depreciation');
        $db->prepare(
            'INSERT INTO rateb_asset_depreciation (company_id, depreciation_no, asset_id, period_date, amount, book_value)
             VALUES (:cid, :no, :aid, :pd, :amt, :bv)'
        )->execute([
            'cid' => $cid,
            'no' => $no,
            'aid' => $assetId,
            'pd' => $data['period_date'] ?? date('Y-m-d'),
            'amt' => $amount,
            'bv' => $book,
        ]);
        if ($assetId > 0 && $book >= 0) {
            $db->prepare('UPDATE rateb_assets SET current_value = :v WHERE id = :id AND company_id = :cid')
                ->execute(['v' => $book, 'id' => $assetId, 'cid' => $cid]);
        }
        return (int) $db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createSparePart(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $deviceId = (int) ($data['device_id'] ?? 0);
        TenantGuard::assertDevice($deviceId, $cid);
        $db = \Rateb\App\Core\Database::connection();
        $partNo = trim((string) ($data['part_no'] ?? ''));
        if ($partNo === '') {
            $partNo = (new WorkflowTableService())->generateRecordNo('device-spare-parts');
        }
        $db->prepare(
            'INSERT INTO rateb_device_spare_parts (company_id, device_id, part_name, part_no, quantity, reorder_level)
             VALUES (:cid, :did, :name, :pn, :qty, :rl)'
        )->execute([
            'cid' => $cid,
            'did' => $deviceId,
            'name' => $data['part_name'] ?? '',
            'pn' => $partNo,
            'qty' => (float) ($data['quantity'] ?? 0),
            'rl' => (float) ($data['reorder_level'] ?? 0),
        ]);
        return (int) $db->lastInsertId();
    }

    public function updateDeviceWarranty(int $deviceId, string $warrantyExpiry): bool
    {
        $cid = TenantGuard::requireCompanyId();
        TenantGuard::assertDevice($deviceId, $cid);
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('UPDATE rateb_medical_devices SET warranty_expiry = :we WHERE id = :id AND company_id = :cid');
        return $stmt->execute(['we' => $warrantyExpiry, 'id' => $deviceId, 'cid' => $cid]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listAssetMaintenance(): array
    {
        return $this->tenantList('rateb_asset_maintenance m LEFT JOIN rateb_assets a ON a.id = m.asset_id', 'm.*, a.name AS asset_name', 'm.id DESC');
    }

    /** @return array<int, array<string, mixed>> */
    public function listAssignments(): array
    {
        return $this->tenantList('rateb_asset_assignments m LEFT JOIN rateb_assets a ON a.id = m.asset_id', 'm.*, a.name AS asset_name', 'm.id DESC');
    }

    /** @return array<int, array<string, mixed>> */
    public function listDepreciation(): array
    {
        return $this->tenantList('rateb_asset_depreciation m LEFT JOIN rateb_assets a ON a.id = m.asset_id', 'm.*, a.name AS asset_name', 'm.period_date DESC');
    }

    /** @return array<int, array<string, mixed>> */
    public function listDeviceService(): array
    {
        return $this->tenantList('rateb_device_service_history m LEFT JOIN rateb_medical_devices d ON d.id = m.device_id', 'm.*, d.device_name', 'm.service_date DESC');
    }

    /** @return array<int, array<string, mixed>> */
    public function listSpareParts(): array
    {
        return $this->tenantList('rateb_device_spare_parts m LEFT JOIN rateb_medical_devices d ON d.id = m.device_id', 'm.*, d.device_name', 'm.id DESC');
    }

    /** @return array<int, array<string, mixed>> */
    public function devicesWarrantyDue(int $days = 60): array
    {
        $cid = TenantContext::companyId();
        $sql = 'SELECT * FROM rateb_medical_devices WHERE warranty_expiry IS NOT NULL AND warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL :d DAY)';
        $params = ['d' => $days];
        if ($cid !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $cid;
        }
        $sql .= ' ORDER BY warranty_expiry ASC LIMIT 100';
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private function tenantList(string $from, string $select, string $order): array
    {
        $cid = TenantContext::companyId();
        $sql = "SELECT {$select} FROM {$from} WHERE 1=1";
        $params = [];
        if ($cid !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND m.company_id = :cid';
            $params['cid'] = $cid;
        }
        $sql .= ' ORDER BY ' . $order . ' LIMIT 200';
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
