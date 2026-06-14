<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Supplier;

final class ErpAnalyticsService
{
    public function procurementDashboard(?int $companyId = null): array
    {
        if ($companyId !== null) {
            TenantContext::setCompanyId($companyId);
        }
        $pr = new PurchaseRequest();
        $po = new PurchaseOrder();
        return [
            'purchase_requests' => $pr->count(),
            'purchase_orders' => $po->count(),
            'pr_by_status' => $pr->query(
                'SELECT status, COUNT(*) AS c FROM rateb_purchase_requests WHERE company_id = :cid GROUP BY status',
                ['cid' => TenantContext::companyId()]
            ),
            'po_by_status' => $po->query(
                'SELECT status, COUNT(*) AS c FROM rateb_purchase_orders WHERE company_id = :cid GROUP BY status',
                ['cid' => TenantContext::companyId()]
            ),
            'po_monthly' => $po->query(
                "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS total
                 FROM rateb_purchase_orders WHERE company_id = :cid GROUP BY month ORDER BY month DESC LIMIT 12",
                ['cid' => TenantContext::companyId()]
            ),
            'total_po_value' => (float) ($po->queryOne(
                'SELECT COALESCE(SUM(total_amount),0) AS t FROM rateb_purchase_orders WHERE company_id = :cid',
                ['cid' => TenantContext::companyId()]
            )['t'] ?? 0),
        ];
    }

    public function companyKpi(?int $companyId = null): array
    {
        if ($companyId !== null) {
            TenantContext::setCompanyId($companyId);
        }
        $cid = TenantContext::companyId();
        $inv = new Inventory();
        return array_merge((new DashboardService())->companyMetrics((int) $cid), [
            'low_stock' => (int) ($inv->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_inventory WHERE company_id = :cid AND quantity <= reorder_level AND reorder_level > 0',
                ['cid' => $cid]
            )['c'] ?? 0),
            'expiring_soon' => count((new InventoryWorkflowService())->expiringItems(30)),
            'pending_workflows' => (int) ($inv->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_approval_instances WHERE company_id = :cid AND status = :st',
                ['cid' => $cid, 'st' => 'pending']
            )['c'] ?? 0),
        ]);
    }

    public function executiveDashboard(): array
    {
        $dash = new DashboardService();
        return [
            'platform' => $dash->adminMetrics(),
            'charts' => $dash->adminCharts(),
            'top_companies_po' => (new PurchaseOrder())->query(
                'SELECT c.name AS company_name, COUNT(po.id) AS po_count, COALESCE(SUM(po.total_amount),0) AS total
                 FROM rateb_purchase_orders po JOIN rateb_companies c ON c.id = po.company_id
                 GROUP BY po.company_id ORDER BY total DESC LIMIT 10'
            ),
        ];
    }

    public function costAnalysis(?int $companyId = null): array
    {
        if ($companyId !== null) {
            TenantContext::setCompanyId($companyId);
        }
        $cid = TenantContext::companyId();
        return [
            'procurement_spend' => (float) ((new PurchaseOrder())->queryOne(
                'SELECT COALESCE(SUM(total_amount),0) AS t FROM rateb_purchase_orders WHERE company_id = :cid',
                ['cid' => $cid]
            )['t'] ?? 0),
            'inventory_value' => (new Inventory())->totalValue(),
            'asset_value' => (float) ((new Inventory())->queryOne(
                'SELECT COALESCE(SUM(current_value),0) AS t FROM rateb_assets WHERE company_id = :cid',
                ['cid' => $cid]
            )['t'] ?? 0),
            'po_by_supplier' => (new PurchaseOrder())->query(
                'SELECT s.name AS supplier_name, COALESCE(SUM(po.total_amount),0) AS total
                 FROM rateb_purchase_orders po LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id
                 WHERE po.company_id = :cid GROUP BY po.supplier_id ORDER BY total DESC LIMIT 20',
                ['cid' => $cid]
            ),
        ];
    }

    public function supplierPerformance(?int $companyId = null): array
    {
        if ($companyId !== null && $companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $cid = TenantContext::companyId();
        if (($cid === null || $cid < 1) && function_exists('rateb_resolve_ops_company_id')) {
            $cid = rateb_resolve_ops_company_id();
            if ($cid > 0) {
                TenantContext::setCompanyId($cid);
            }
        }
        if ($cid === null || $cid < 1) {
            return [];
        }
        return (new Supplier())->query(
            'SELECT s.id, s.code, s.name, s.rating, s.performance_kpi, sc.name AS classification_name,
                    (SELECT COUNT(*) FROM rateb_purchase_orders po WHERE po.supplier_id = s.id AND po.company_id = s.company_id) AS po_count,
                    (SELECT COALESCE(AVG(overall_score),0) FROM rateb_supplier_evaluations e WHERE e.supplier_id = s.id) AS avg_eval
             FROM rateb_suppliers s
             LEFT JOIN rateb_supplier_classifications sc ON sc.id = s.classification_id
             WHERE s.company_id = :cid ORDER BY avg_eval DESC, s.rating DESC LIMIT 100',
            ['cid' => $cid]
        );
    }
}
