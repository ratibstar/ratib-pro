<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Supplier;

final class SupplierAutomationService
{
    public function updateAllKpis(): int
    {
        $analytics = new ErpAnalyticsService();
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        $count = 0;
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $rows = $analytics->supplierPerformance($cid);
            $db = Database::connection();
            foreach ($rows as $row) {
                $supplierId = (int) ($row['id'] ?? 0);
                if ($supplierId < 1) {
                    continue;
                }
                $poCount = (int) ($row['po_count'] ?? 0);
                $avgEval = (float) ($row['avg_eval'] ?? 0);
                $rating = (float) ($row['rating'] ?? 0);
                $kpi = round(($avgEval * 0.5) + ($rating * 0.3) + (min($poCount, 20) * 1.0), 2);
                $db->prepare(
                    'UPDATE rateb_suppliers SET performance_kpi = :kpi WHERE id = :id AND company_id = :cid'
                )->execute(['kpi' => $kpi, 'id' => $supplierId, 'cid' => $cid]);
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    public function processAlerts(): int
    {
        $threshold = AutomationSettings::getInt('supplier_kpi_poor_threshold', 50);
        $inactiveDays = AutomationSettings::getInt('supplier_inactive_days', 180);
        $count = 0;
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        $notifier = new NotificationService();
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $poor = (new Supplier())->query(
                'SELECT id, name, performance_kpi FROM rateb_suppliers
                 WHERE company_id = :cid AND status = \'active\' AND performance_kpi > 0 AND performance_kpi < :th',
                ['cid' => $cid, 'th' => $threshold]
            );
            foreach ($poor as $s) {
                if ($this->alertExists($cid, 'supplier_poor', 'supplier', (int) $s['id'])) {
                    continue;
                }
                $notifier->notifyCompany(
                    $cid,
                    __('supplier_poor_performance'),
                    __('supplier_poor_message', ['name' => (string) $s['name'], 'kpi' => (string) $s['performance_kpi']]),
                    'warning',
                    'supplier_poor',
                    'supplier',
                    (int) $s['id']
                );
                (new EmailAlertService())->sendSupplierAlert($cid, (string) $s['name'], 'poor');
                $count++;
            }
            $inactive = (new Supplier())->query(
                "SELECT s.id, s.name FROM rateb_suppliers s
                 LEFT JOIN rateb_purchase_orders po ON po.supplier_id = s.id AND po.order_date >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
                 WHERE s.company_id = :cid AND s.status = 'active'
                 GROUP BY s.id HAVING COUNT(po.id) = 0",
                ['cid' => $cid, 'd' => $inactiveDays]
            );
            foreach ($inactive as $s) {
                if ($this->alertExists($cid, 'supplier_inactive', 'supplier', (int) $s['id'])) {
                    continue;
                }
                $notifier->notifyCompany(
                    $cid,
                    __('supplier_inactive'),
                    __('supplier_inactive_message', ['name' => (string) $s['name']]),
                    'info',
                    'supplier_inactive',
                    'supplier',
                    (int) $s['id']
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    private function alertExists(int $companyId, string $trigger, string $entityType, int $entityId): bool
    {
        $row = (new \Rateb\App\Models\Notification())->queryOne(
            'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
             AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) LIMIT 1',
            ['cid' => $companyId, 'tt' => $trigger, 'et' => $entityType, 'eid' => $entityId]
        );
        return $row !== null;
    }
}
