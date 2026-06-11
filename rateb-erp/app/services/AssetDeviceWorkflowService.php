<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;

final class AssetDeviceWorkflowService
{
    /** @param array<string, mixed> $data */
    public function createMaintenance(array $data, string $table = 'rateb_asset_maintenance'): int
    {
        $db = \Rateb\App\Core\Database::connection();
        if ($table === 'rateb_asset_maintenance') {
            $db->prepare(
                'INSERT INTO rateb_asset_maintenance (company_id, asset_id, maintenance_type, scheduled_date, cost, status, notes)
                 VALUES (:cid, :aid, :mt, :sd, :cost, :st, :notes)'
            )->execute([
                'cid' => TenantContext::companyId(),
                'aid' => (int) ($data['asset_id'] ?? 0),
                'mt' => $data['maintenance_type'] ?? 'general',
                'sd' => $data['scheduled_date'] ?? null,
                'cost' => (float) ($data['cost'] ?? 0),
                'st' => $data['status'] ?? 'scheduled',
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO rateb_device_service_history (company_id, device_id, service_date, service_type, provider, cost, notes)
                 VALUES (:cid, :did, :sd, :st, :pr, :cost, :notes)'
            )->execute([
                'cid' => TenantContext::companyId(),
                'did' => (int) ($data['device_id'] ?? 0),
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
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_asset_assignments (company_id, asset_id, assigned_to, department, assigned_at, notes)
             VALUES (:cid, :aid, :to, :dept, :at, :notes)'
        )->execute([
            'cid' => TenantContext::companyId(),
            'aid' => (int) ($data['asset_id'] ?? 0),
            'to' => $data['assigned_to'] ?? '',
            'dept' => $data['department'] ?? null,
            'at' => $data['assigned_at'] ?? date('Y-m-d'),
            'notes' => $data['notes'] ?? null,
        ]);
        $id = (int) $db->lastInsertId();
        if (!empty($data['asset_id']) && !empty($data['assigned_to'])) {
            $db->prepare('UPDATE rateb_assets SET assigned_to = :t WHERE id = :id')
                ->execute(['t' => $data['assigned_to'], 'id' => (int) $data['asset_id']]);
        }
        return $id;
    }

    /** @param array<string, mixed> $data */
    public function recordDepreciation(array $data): int
    {
        $db = \Rateb\App\Core\Database::connection();
        $assetId = (int) ($data['asset_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $book = (float) ($data['book_value'] ?? 0);
        $db->prepare(
            'INSERT INTO rateb_asset_depreciation (company_id, asset_id, period_date, amount, book_value)
             VALUES (:cid, :aid, :pd, :amt, :bv)'
        )->execute([
            'cid' => TenantContext::companyId(),
            'aid' => $assetId,
            'pd' => $data['period_date'] ?? date('Y-m-d'),
            'amt' => $amount,
            'bv' => $book,
        ]);
        if ($assetId > 0 && $book >= 0) {
            $db->prepare('UPDATE rateb_assets SET current_value = :v WHERE id = :id')->execute(['v' => $book, 'id' => $assetId]);
        }
        return (int) $db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createSparePart(array $data): int
    {
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_device_spare_parts (company_id, device_id, part_name, part_no, quantity, reorder_level)
             VALUES (:cid, :did, :name, :pn, :qty, :rl)'
        )->execute([
            'cid' => TenantContext::companyId(),
            'did' => (int) ($data['device_id'] ?? 0),
            'name' => $data['part_name'] ?? '',
            'pn' => $data['part_no'] ?? null,
            'qty' => (float) ($data['quantity'] ?? 0),
            'rl' => (float) ($data['reorder_level'] ?? 0),
        ]);
        return (int) $db->lastInsertId();
    }

    public function updateDeviceWarranty(int $deviceId, string $warrantyExpiry): bool
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('UPDATE rateb_medical_devices SET warranty_expiry = :we WHERE id = :id');
        return $stmt->execute(['we' => $warrantyExpiry, 'id' => $deviceId]);
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
