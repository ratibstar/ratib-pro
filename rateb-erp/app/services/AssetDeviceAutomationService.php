<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

final class AssetDeviceAutomationService
{
    public function processAssetMaintenanceReminders(): int
    {
        $count = 0;
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $stmt = Database::connection()->prepare(
                "SELECT m.*, a.name AS asset_name FROM rateb_asset_maintenance m
                 JOIN rateb_assets a ON a.id = m.asset_id
                 WHERE m.company_id = :cid AND m.status IN ('scheduled','pending')
                   AND m.scheduled_date IS NOT NULL AND m.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
            );
            $stmt->execute(['cid' => $cid]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if ($this->exists($cid, 'maintenance_due', 'asset_maintenance', $id)) {
                    continue;
                }
                (new NotificationService())->triggerMaintenanceDue(
                    $cid,
                    (string) ($row['asset_name'] ?? ''),
                    (string) ($row['scheduled_date'] ?? '')
                );
                (new EmailAlertService())->sendMaintenanceDue(
                    $cid,
                    (string) ($row['asset_name'] ?? ''),
                    (string) ($row['scheduled_date'] ?? '')
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    public function processDeviceMaintenanceReminders(): int
    {
        $count = 0;
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT * FROM rateb_medical_devices
                 WHERE company_id = :cid AND maintenance_due IS NOT NULL
                   AND maintenance_due <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            );
            $stmt->execute(['cid' => $cid]);
            foreach ($stmt->fetchAll() as $device) {
                $id = (int) $device['id'];
                if ($this->exists($cid, 'maintenance_due', 'medical_device', $id)) {
                    continue;
                }
                (new NotificationService())->triggerMaintenanceDue(
                    $cid,
                    (string) ($device['device_name'] ?? ''),
                    (string) ($device['maintenance_due'] ?? '')
                );
                (new EmailAlertService())->sendMaintenanceDue(
                    $cid,
                    (string) ($device['device_name'] ?? ''),
                    (string) ($device['maintenance_due'] ?? '')
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    public function processWarrantyExpiryAlerts(): int
    {
        $count = 0;
        $svc = new AssetDeviceWorkflowService();
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            foreach ($svc->devicesWarrantyDue(60) as $device) {
                $id = (int) $device['id'];
                if ($this->exists($cid, 'warranty_expiry', 'medical_device', $id)) {
                    continue;
                }
                (new NotificationService())->notifyCompany(
                    $cid,
                    __('warranty_expiry_alert'),
                    __('warranty_expiry_message', [
                        'device' => (string) ($device['device_name'] ?? ''),
                        'date' => (string) ($device['warranty_expiry'] ?? ''),
                    ]),
                    'warning',
                    'warranty_expiry',
                    'medical_device',
                    $id
                );
                (new EmailAlertService())->sendWarrantyExpiry(
                    $cid,
                    (string) ($device['device_name'] ?? ''),
                    (string) ($device['warranty_expiry'] ?? '')
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    public function processSparePartsLowStock(): int
    {
        $count = 0;
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $rows = Database::connection()->prepare(
                'SELECT * FROM rateb_device_spare_parts
                 WHERE company_id = :cid AND reorder_level > 0 AND quantity <= reorder_level'
            );
            $rows->execute(['cid' => $cid]);
            foreach ($rows->fetchAll() as $part) {
                $id = (int) $part['id'];
                if ($this->exists($cid, 'spare_part_low', 'device_spare_part', $id)) {
                    continue;
                }
                (new NotificationService())->triggerLowStock(
                    $cid,
                    (string) ($part['part_name'] ?? ''),
                    (float) ($part['quantity'] ?? 0)
                );
                (new EmailAlertService())->sendLowStock(
                    $cid,
                    'Spare: ' . (string) ($part['part_name'] ?? ''),
                    (float) ($part['quantity'] ?? 0)
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    private function exists(int $companyId, string $trigger, string $entityType, int $entityId): bool
    {
        $row = (new \Rateb\App\Models\Notification())->queryOne(
            'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
             AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
            ['cid' => $companyId, 'tt' => $trigger, 'et' => $entityType, 'eid' => $entityId]
        );
        return $row !== null;
    }
}
