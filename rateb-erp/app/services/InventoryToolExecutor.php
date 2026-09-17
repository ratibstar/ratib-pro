<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;
use Rateb\App\Models\StockMovement;
use Rateb\App\Models\Warehouse;

/**
 * Inventory Tool Executor — tenant-scoped READ/ANALYSIS against live ERP tables.
 */
final class InventoryToolExecutor
{
    /**
     * @param array<string, mixed> $arguments
     * @return array{success: bool, data: mixed, error: string|null}
     */
    public static function execute(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::fail('tenant_mismatch');
        }

        try {
            switch ($toolName) {
                case 'list_inventory_items':
                    return self::listInventoryItems($arguments, $companyId);
                case 'get_inventory_item':
                    return self::getInventoryItem($arguments, $companyId);
                case 'list_warehouses':
                    return self::listWarehouses($arguments, $companyId);
                case 'list_stock_movements':
                    return self::listStockMovements($arguments, $companyId);
                case 'analyze_inventory':
                    return self::analyzeInventory($arguments, $companyId);
                case 'get_inventory_procurement_links':
                    return self::getInventoryProcurementLinks($arguments, $companyId);
                default:
                    return self::fail('tool_not_implemented');
            }
        } catch (\Throwable $e) {
            return self::fail('tool_exception');
        }
    }

    /**
     * @return array{success: false, data: null, error: string, error_code: string, error_message: string}
     */
    private static function fail(string $code): array
    {
        $key = 'ai_tool_err_' . $code;
        $translated = __($key);
        $message = (is_string($translated) && $translated !== '' && $translated !== $key) ? $translated : $code;
        return [
            'success' => false,
            'data' => null,
            'error' => $code,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }

    private static function listInventoryItems(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $warehouseId = (int) ($args['warehouse_id'] ?? 0);
        $lowStock = !empty($args['low_stock']);
        $status = trim((string) ($args['status'] ?? ''));

        $sql = 'SELECT i.id, i.item_code, i.item_name, i.sku, i.category, i.quantity, i.unit,
                       i.unit_cost, i.reorder_level, i.min_stock, i.max_stock, i.status,
                       i.warehouse_id, i.expiry_date, i.branch_id, w.name AS warehouse_name
                FROM rateb_inventory i
                LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
                WHERE i.company_id = :cid';
        $params = ['cid' => $companyId];

        if ($warehouseId > 0) {
            $sql .= ' AND i.warehouse_id = :wid';
            $params['wid'] = $warehouseId;
        }
        if ($status !== '') {
            $sql .= ' AND i.status = :st';
            $params['st'] = $status;
        }
        if ($lowStock) {
            $sql .= ' AND (
                (i.reorder_level > 0 AND i.quantity <= i.reorder_level)
                OR (i.min_stock > 0 AND i.quantity <= i.min_stock)
            )';
        }
        if ($search !== '') {
            $sql .= ' AND (i.item_name LIKE :q OR i.item_code LIKE :q OR i.sku LIKE :q OR i.barcode LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY i.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = (new Inventory())->query($sql, $params);
        foreach ($rows as &$row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $reorder = (float) ($row['reorder_level'] ?? 0);
            $min = (float) ($row['min_stock'] ?? 0);
            $row['quantity'] = $qty;
            $row['unit_cost'] = (float) ($row['unit_cost'] ?? 0);
            $row['is_low_stock'] = ($reorder > 0 && $qty <= $reorder) || ($min > 0 && $qty <= $min);
            $row['is_available'] = $qty > 0;
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getInventoryItem(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_inventory_id');
        }

        $rows = (new Inventory())->query(
            'SELECT i.*, w.name AS warehouse_name, w.code AS warehouse_code
             FROM rateb_inventory i
             LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
             WHERE i.id = :id AND i.company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $item = $rows[0] ?? null;
        if (!$item) {
            return self::fail('inventory_not_found');
        }

        $qty = (float) ($item['quantity'] ?? 0);
        $reorder = (float) ($item['reorder_level'] ?? 0);
        $min = (float) ($item['min_stock'] ?? 0);
        $item['quantity'] = $qty;
        $item['unit_cost'] = (float) ($item['unit_cost'] ?? 0);
        $item['is_low_stock'] = ($reorder > 0 && $qty <= $reorder) || ($min > 0 && $qty <= $min);
        $item['is_available'] = $qty > 0;
        $item['line_value'] = $qty * (float) $item['unit_cost'];

        return ['success' => true, 'data' => $item, 'error' => null];
    }

    private static function listWarehouses(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['status'] ?? ''));

        $sql = 'SELECT id, code, name, location, manager_name, status, branch_id
                FROM rateb_warehouses WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY id ASC LIMIT ' . $limit;
        $rows = (new Warehouse())->query($sql, $params);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function listStockMovements(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $inventoryId = (int) ($args['inventory_id'] ?? 0);
        $movementType = trim((string) ($args['movement_type'] ?? ''));

        $sql = 'SELECT m.id, m.movement_no, m.inventory_id, m.warehouse_id, m.movement_type,
                       m.quantity, m.reference_type, m.reference_id, m.notes, m.created_at,
                       i.item_name, i.item_code, w.name AS warehouse_name
                FROM rateb_stock_movements m
                LEFT JOIN rateb_inventory i ON i.id = m.inventory_id AND i.company_id = m.company_id
                LEFT JOIN rateb_warehouses w ON w.id = m.warehouse_id AND w.company_id = m.company_id
                WHERE m.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($inventoryId > 0) {
            $sql .= ' AND m.inventory_id = :iid';
            $params['iid'] = $inventoryId;
        }
        if ($movementType !== '') {
            $sql .= ' AND m.movement_type = :mt';
            $params['mt'] = $movementType;
        }
        $sql .= ' ORDER BY m.id DESC LIMIT ' . $limit;
        $rows = (new StockMovement())->query($sql, $params);
        foreach ($rows as &$row) {
            $row['quantity'] = (float) ($row['quantity'] ?? 0);
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function analyzeInventory(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $expiryDays = max(1, min(180, (int) ($args['expiry_days'] ?? 30)));
        $inv = new Inventory();
        $today = date('Y-m-d');

        $totals = $inv->query(
            'SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(quantity),0) AS qty,
                    COALESCE(SUM(quantity * unit_cost),0) AS value,
                    COALESCE(SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END),0) AS available_cnt,
                    COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END),0) AS zero_cnt
             FROM rateb_inventory WHERE company_id = :cid',
            ['cid' => $companyId]
        );

        $lowStock = $inv->query(
            "SELECT i.id, i.item_code, i.item_name, i.quantity, i.unit, i.reorder_level, i.min_stock,
                    i.warehouse_id, w.name AS warehouse_name
             FROM rateb_inventory i
             LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
             WHERE i.company_id = :cid
               AND (
                   (i.reorder_level > 0 AND i.quantity <= i.reorder_level)
                   OR (i.min_stock > 0 AND i.quantity <= i.min_stock)
               )
             ORDER BY i.quantity ASC
             LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $available = $inv->query(
            "SELECT i.id, i.item_code, i.item_name, i.quantity, i.unit, i.warehouse_id, w.name AS warehouse_name
             FROM rateb_inventory i
             LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
             WHERE i.company_id = :cid AND i.quantity > 0
             ORDER BY i.quantity DESC
             LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $expiring = $inv->query(
            "SELECT i.id, i.item_code, i.item_name, i.quantity, i.expiry_date, i.warehouse_id, w.name AS warehouse_name
             FROM rateb_inventory i
             LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
             WHERE i.company_id = :cid
               AND i.expiry_date IS NOT NULL AND i.expiry_date <> ''
               AND i.expiry_date <= DATE_ADD(:today, INTERVAL {$expiryDays} DAY)
             ORDER BY i.expiry_date ASC
             LIMIT {$limit}",
            ['cid' => $companyId, 'today' => $today]
        );

        $warehouses = (new Warehouse())->query(
            'SELECT COUNT(*) AS cnt FROM rateb_warehouses WHERE company_id = :cid',
            ['cid' => $companyId]
        );

        $recentMovements = (new StockMovement())->query(
            "SELECT COUNT(*) AS cnt FROM rateb_stock_movements
             WHERE company_id = :cid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            ['cid' => $companyId]
        );

        $reorder = [];
        try {
            // Use existing workflow service against live data (tenant via company filter in service).
            $prev = \Rateb\App\Core\TenantContext::companyId();
            \Rateb\App\Core\TenantContext::setCompanyId($companyId);
            $reorder = (new InventoryWorkflowService())->reorderSuggestions($companyId);
            if ($prev !== null) {
                \Rateb\App\Core\TenantContext::setCompanyId((int) $prev);
            }
            $reorder = array_slice(is_array($reorder) ? $reorder : [], 0, $limit);
        } catch (\Throwable $e) {
            $reorder = [];
        }

        $normalize = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                $row['quantity'] = (float) ($row['quantity'] ?? 0);
                $out[] = $row;
            }
            return $out;
        };

        $followUp = [];
        foreach ($normalize($lowStock) as $row) {
            $followUp[] = [
                'code' => 'low_stock',
                'urgency' => 'high',
                'inventory_id' => (int) ($row['id'] ?? 0),
                'label' => (string) (($row['item_code'] ?? '') . ' ' . ($row['item_name'] ?? '')),
                'quantity' => (float) ($row['quantity'] ?? 0),
            ];
        }
        foreach ($normalize($expiring) as $row) {
            $followUp[] = [
                'code' => 'expiring_soon',
                'urgency' => 'medium',
                'inventory_id' => (int) ($row['id'] ?? 0),
                'label' => (string) (($row['item_code'] ?? '') . ' ' . ($row['item_name'] ?? '')),
                'expiry_date' => (string) ($row['expiry_date'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => $today,
                'data_source' => 'live_tenant',
                'summary' => [
                    'item_count' => (int) ($totals[0]['cnt'] ?? 0),
                    'total_quantity' => (float) ($totals[0]['qty'] ?? 0),
                    'total_value' => (float) ($totals[0]['value'] ?? 0),
                    'available_item_count' => (int) ($totals[0]['available_cnt'] ?? 0),
                    'zero_stock_item_count' => (int) ($totals[0]['zero_cnt'] ?? 0),
                    'warehouse_count' => (int) ($warehouses[0]['cnt'] ?? 0),
                    'movements_last_7_days' => (int) ($recentMovements[0]['cnt'] ?? 0),
                    'low_stock_count' => count($lowStock),
                    'expiring_count' => count($expiring),
                    'currency' => 'SAR',
                ],
                'low_stock' => $normalize($lowStock),
                'available' => $normalize($available),
                'expiring' => $normalize($expiring),
                'reorder_suggestions' => $normalize($reorder),
                'follow_up' => array_slice($followUp, 0, $limit),
                'notes' => [
                    'empty_lists_mean_no_matching_records',
                    'all_values_from_live_tenant_data',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getInventoryProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $inventoryId = (int) ($args['inventory_id'] ?? 0);
        $lowStockOnly = !empty($args['low_stock_only']);

        try {
            $sql = "SELECT i.id AS inventory_id, i.item_code, i.item_name, i.quantity, i.reorder_level, i.min_stock,
                           i.unit, i.warehouse_id, w.name AS warehouse_name,
                           pri.purchase_request_id, pr.request_no, pr.title AS pr_title, pr.status AS pr_status,
                           pr.total_estimated AS pr_total_estimated,
                           poi.purchase_order_id, po.order_no, po.status AS po_status, po.total_amount AS po_total_amount
                    FROM rateb_inventory i
                    LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
                    LEFT JOIN rateb_purchase_request_items pri
                           ON pri.inventory_id = i.id
                    LEFT JOIN rateb_purchase_requests pr
                           ON pr.id = pri.purchase_request_id AND pr.company_id = i.company_id
                    LEFT JOIN rateb_purchase_items poi
                           ON poi.inventory_id = i.id
                    LEFT JOIN rateb_purchase_orders po
                           ON po.id = poi.purchase_order_id AND po.company_id = i.company_id
                    WHERE i.company_id = :cid";
            $params = ['cid' => $companyId];

            if ($inventoryId > 0) {
                $sql .= ' AND i.id = :iid';
                $params['iid'] = $inventoryId;
            }
            if ($lowStockOnly) {
                $sql .= ' AND (
                    (i.reorder_level > 0 AND i.quantity <= i.reorder_level)
                    OR (i.min_stock > 0 AND i.quantity <= i.min_stock)
                )';
            }

            if ($inventoryId < 1) {
                $sql .= ' AND (pri.purchase_request_id IS NOT NULL OR poi.purchase_order_id IS NOT NULL
                            OR (i.reorder_level > 0 AND i.quantity <= i.reorder_level)
                            OR (i.min_stock > 0 AND i.quantity <= i.min_stock))';
            }

            $sql .= ' ORDER BY i.id DESC LIMIT ' . max($limit * 5, 50);
            $rows = (new Inventory())->query($sql, $params);
        } catch (\Throwable $e) {
            // Fallback: inventory-only follow-up when line-item joins are unavailable.
            $sql = "SELECT i.id AS inventory_id, i.item_code, i.item_name, i.quantity, i.reorder_level, i.min_stock,
                           i.unit, i.warehouse_id, w.name AS warehouse_name,
                           NULL AS purchase_request_id, NULL AS request_no, NULL AS pr_title, NULL AS pr_status,
                           NULL AS pr_total_estimated,
                           NULL AS purchase_order_id, NULL AS order_no, NULL AS po_status, NULL AS po_total_amount
                    FROM rateb_inventory i
                    LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id AND w.company_id = i.company_id
                    WHERE i.company_id = :cid";
            $params = ['cid' => $companyId];
            if ($inventoryId > 0) {
                $sql .= ' AND i.id = :iid';
                $params['iid'] = $inventoryId;
            }
            if ($lowStockOnly || $inventoryId < 1) {
                $sql .= ' AND (
                    (i.reorder_level > 0 AND i.quantity <= i.reorder_level)
                    OR (i.min_stock > 0 AND i.quantity <= i.min_stock)
                    OR i.quantity <= 0
                )';
            }
            $sql .= ' ORDER BY i.id DESC LIMIT ' . $limit;
            $rows = (new Inventory())->query($sql, $params);
        }

        $byItem = [];
        foreach ($rows as $row) {
            $iid = (int) ($row['inventory_id'] ?? 0);
            if ($iid < 1) {
                continue;
            }
            if (!isset($byItem[$iid])) {
                $qty = (float) ($row['quantity'] ?? 0);
                $reorder = (float) ($row['reorder_level'] ?? 0);
                $min = (float) ($row['min_stock'] ?? 0);
                $byItem[$iid] = [
                    'inventory_id' => $iid,
                    'item_code' => (string) ($row['item_code'] ?? ''),
                    'item_name' => (string) ($row['item_name'] ?? ''),
                    'quantity' => $qty,
                    'unit' => (string) ($row['unit'] ?? ''),
                    'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                    'warehouse_name' => (string) ($row['warehouse_name'] ?? ''),
                    'is_low_stock' => ($reorder > 0 && $qty <= $reorder) || ($min > 0 && $qty <= $min),
                    'purchase_requests' => [],
                    'purchase_orders' => [],
                    'follow_up' => [],
                ];
            }

            $prId = (int) ($row['purchase_request_id'] ?? 0);
            if ($prId > 0) {
                $exists = false;
                foreach ($byItem[$iid]['purchase_requests'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $prId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $byItem[$iid]['purchase_requests'][] = [
                        'id' => $prId,
                        'request_no' => (string) ($row['request_no'] ?? ''),
                        'title' => (string) ($row['pr_title'] ?? ''),
                        'status' => (string) ($row['pr_status'] ?? ''),
                        'total_estimated' => (float) ($row['pr_total_estimated'] ?? 0),
                    ];
                }
            }

            $poId = (int) ($row['purchase_order_id'] ?? 0);
            if ($poId > 0) {
                $exists = false;
                foreach ($byItem[$iid]['purchase_orders'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $poId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $byItem[$iid]['purchase_orders'][] = [
                        'id' => $poId,
                        'order_no' => (string) ($row['order_no'] ?? ''),
                        'status' => (string) ($row['po_status'] ?? ''),
                        'total_amount' => (float) ($row['po_total_amount'] ?? 0),
                    ];
                }
            }
        }

        foreach ($byItem as &$item) {
            if (!empty($item['is_low_stock']) && $item['purchase_requests'] === [] && $item['purchase_orders'] === []) {
                $item['follow_up'][] = [
                    'code' => 'low_stock_without_open_procurement',
                    'message' => 'low_stock_item_has_no_linked_pr_or_po',
                ];
            }
            if (!empty($item['is_low_stock']) && $item['purchase_requests'] !== []) {
                $item['follow_up'][] = [
                    'code' => 'low_stock_with_purchase_request',
                    'message' => 'review_linked_purchase_requests',
                ];
            }
        }
        unset($item);

        $list = array_slice(array_values($byItem), 0, $limit);

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'links' => $list,
                'count' => count($list),
                'notes' => [
                    'links_require_inventory_id_on_pr_po_line_items',
                    'empty_links_mean_no_matching_relations',
                ],
            ],
            'error' => null,
        ];
    }
}
