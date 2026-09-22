<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;

/**
 * Logistics Tool Executor — tenant-scoped READ/ANALYSIS against live logistics + related ERP tables.
 * No WRITE. No invented entities. No LogisticsAgent.
 */
final class LogisticsToolExecutor
{
    private const OPEN_SHIPMENT_STATUSES = ['created', 'picked', 'packed', 'shipped', 'out_for_delivery'];
    private const OPEN_DO_STATUSES = ['draft', 'confirmed', 'dispatched'];
    private const OPEN_TRIP_STATUSES = ['draft', 'assigned', 'started'];

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
                case 'list_logistics_shipments':
                    return self::listShipments($arguments, $companyId);
                case 'get_logistics_shipment':
                    return self::getShipment($arguments, $companyId);
                case 'list_logistics_delivery_orders':
                    return self::listDeliveryOrders($arguments, $companyId);
                case 'list_logistics_trips':
                    return self::listTrips($arguments, $companyId);
                case 'analyze_logistics':
                    return self::analyzeLogistics($arguments, $companyId);
                case 'get_logistics_operational_guidance':
                    return self::getOperationalGuidance($arguments, $companyId);
                case 'get_logistics_crm_links':
                    return self::getCrmLinks($arguments, $companyId);
                case 'get_logistics_sales_links':
                    return self::getSalesLinks($arguments, $companyId);
                case 'get_logistics_inventory_links':
                    return self::getInventoryLinks($arguments, $companyId);
                case 'get_logistics_procurement_links':
                    return self::getProcurementLinks($arguments, $companyId);
                case 'get_logistics_supplier_links':
                    return self::getSupplierLinks($arguments, $companyId);
                case 'analyze_logistics_end_to_end':
                    return self::analyzeEndToEnd($arguments, $companyId);
                case 'update_shipment_status':
                    return ErpAiWriteTools::updateShipmentStatus($arguments, $companyId, (int) $ctx->userId);
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

    private static function model(): Inventory
    {
        return new Inventory();
    }

    private static function listShipments(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $status = trim((string) ($args['status'] ?? ''));
        $delayedOnly = !empty($args['delayed_only']);
        $customerId = (int) ($args['customer_id'] ?? 0);
        $orderId = (int) ($args['order_id'] ?? 0);

        try {
            $sql = 'SELECT s.id, s.tracking_number, s.status, s.customer_id, s.order_id, s.delivery_order_id,
                           s.trip_id, s.pickup_location, s.delivery_location, s.dispatched_at, s.delivered_at,
                           s.created_at, s.branch_id,
                           c.name AS customer_name, c.code AS customer_code
                    FROM rateb_logistics_shipments s
                    LEFT JOIN rateb_customers c ON c.id = s.customer_id AND c.company_id = s.company_id
                    WHERE s.company_id = :cid';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND s.status = :st';
                $params['st'] = $status;
            }
            if ($customerId > 0) {
                $sql .= ' AND s.customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            if ($orderId > 0) {
                $sql .= ' AND s.order_id = :oid';
                $params['oid'] = $orderId;
            }
            if ($delayedOnly) {
                $sql .= " AND s.status IN ('created','picked','packed','shipped','out_for_delivery')
                          AND (
                            (s.dispatched_at IS NOT NULL AND s.dispatched_at < DATE_SUB(NOW(), INTERVAL 2 DAY))
                            OR (s.dispatched_at IS NULL AND s.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
                          )";
            }
            $sql .= ' ORDER BY s.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getShipment(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_shipment_id');
        }

        try {
            $rows = self::model()->query(
                'SELECT s.id, s.tracking_number, s.status, s.customer_id, s.order_id, s.delivery_order_id,
                        s.trip_id, s.pickup_location, s.delivery_location, s.dispatched_at, s.delivered_at,
                        s.notes, s.created_at, s.branch_id,
                        c.name AS customer_name, c.code AS customer_code,
                        d.delivery_no, d.status AS delivery_order_status, d.planned_date AS delivery_planned_date,
                        t.status AS trip_status, t.planned_date AS trip_planned_date
                 FROM rateb_logistics_shipments s
                 LEFT JOIN rateb_customers c ON c.id = s.customer_id AND c.company_id = s.company_id
                 LEFT JOIN rateb_logistics_delivery_orders d ON d.id = s.delivery_order_id AND d.company_id = s.company_id
                 LEFT JOIN rateb_logistics_trips t ON t.id = s.trip_id AND t.company_id = s.company_id
                 WHERE s.id = :id AND s.company_id = :cid LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $rows = self::model()->query(
                'SELECT id, tracking_number, status, customer_id, order_id, delivery_order_id, trip_id,
                        pickup_location, delivery_location, dispatched_at, delivered_at, notes, created_at, branch_id
                 FROM rateb_logistics_shipments WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
        }

        $item = $rows[0] ?? null;
        if (!$item) {
            return self::fail('shipment_not_found');
        }

        $proof = null;
        try {
            $proofRows = self::model()->query(
                'SELECT id, receiver_name, delivered_at, gps_lat, gps_long
                 FROM rateb_logistics_delivery_proofs
                 WHERE company_id = :cid AND shipment_id = :sid LIMIT 1',
                ['cid' => $companyId, 'sid' => $id]
            );
            $proof = $proofRows[0] ?? null;
        } catch (\Throwable $e) {
            $proof = null;
        }
        $item['delivery_proof'] = $proof;

        return ['success' => true, 'data' => $item, 'error' => null];
    }

    private static function listDeliveryOrders(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['status'] ?? ''));
        $incompleteOnly = !empty($args['incomplete_only']);
        $customerId = (int) ($args['customer_id'] ?? 0);
        $orderId = (int) ($args['order_id'] ?? 0);

        try {
            $sql = 'SELECT d.id, d.delivery_no, d.status, d.customer_id, d.order_id, d.order_ref,
                           d.pickup_location, d.delivery_location, d.planned_date, d.created_at, d.branch_id,
                           c.name AS customer_name
                    FROM rateb_logistics_delivery_orders d
                    LEFT JOIN rateb_customers c ON c.id = d.customer_id AND c.company_id = d.company_id
                    WHERE d.company_id = :cid';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND d.status = :st';
                $params['st'] = $status;
            }
            if ($incompleteOnly) {
                $sql .= " AND d.status IN ('draft','confirmed','dispatched')";
            }
            if ($customerId > 0) {
                $sql .= ' AND d.customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            if ($orderId > 0) {
                $sql .= ' AND d.order_id = :oid';
                $params['oid'] = $orderId;
            }
            $sql .= ' ORDER BY d.id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function listTrips(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['status'] ?? ''));
        $openOnly = !empty($args['open_only']);

        try {
            $sql = 'SELECT t.id, t.status, t.driver_id, t.vehicle_id, t.route_id, t.origin, t.destination,
                           t.planned_date, t.start_time, t.end_time, t.created_at, t.branch_id,
                           v.plate_number, r.code AS route_code, r.name AS route_name
                    FROM rateb_logistics_trips t
                    LEFT JOIN rateb_logistics_vehicles v ON v.id = t.vehicle_id AND v.company_id = t.company_id
                    LEFT JOIN rateb_logistics_routes r ON r.id = t.route_id AND r.company_id = t.company_id
                    WHERE t.company_id = :cid';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND t.status = :st';
                $params['st'] = $status;
            }
            if ($openOnly) {
                $sql .= " AND t.status IN ('draft','assigned','started')";
            }
            $sql .= ' ORDER BY t.id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function analyzeLogistics(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $shipments = self::listShipments(['limit' => $limit], $companyId);
        $delayed = self::listShipments(['limit' => $limit, 'delayed_only' => true], $companyId);
        $deliveries = self::listDeliveryOrders(['limit' => $limit, 'incomplete_only' => true], $companyId);
        $trips = self::listTrips(['limit' => $limit, 'open_only' => true], $companyId);

        $shipmentRows = is_array($shipments['data'] ?? null) ? $shipments['data'] : [];
        $delayedRows = is_array($delayed['data'] ?? null) ? $delayed['data'] : [];
        $doRows = is_array($deliveries['data'] ?? null) ? $deliveries['data'] : [];
        $tripRows = is_array($trips['data'] ?? null) ? $trips['data'] : [];

        $openShipments = array_values(array_filter(
            $shipmentRows,
            static fn($r) => in_array((string) ($r['status'] ?? ''), self::OPEN_SHIPMENT_STATUSES, true)
        ));

        $followUp = [];
        foreach ($delayedRows as $row) {
            $followUp[] = [
                'code' => 'delayed_shipment',
                'urgency' => 'high',
                'shipment_id' => (int) ($row['id'] ?? 0),
                'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'message' => 'shipment_needs_follow_up',
            ];
        }
        foreach ($doRows as $row) {
            $planned = (string) ($row['planned_date'] ?? '');
            if ($planned !== '' && $planned < date('Y-m-d') && in_array((string) ($row['status'] ?? ''), self::OPEN_DO_STATUSES, true)) {
                $followUp[] = [
                    'code' => 'overdue_delivery_order',
                    'urgency' => 'high',
                    'delivery_order_id' => (int) ($row['id'] ?? 0),
                    'delivery_no' => (string) ($row['delivery_no'] ?? ''),
                    'message' => 'delivery_order_past_planned_date',
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'as_of' => date('Y-m-d'),
                'summary' => [
                    'shipment_sample_count' => count($shipmentRows),
                    'open_shipment_count' => count($openShipments),
                    'delayed_shipment_count' => count($delayedRows),
                    'incomplete_delivery_order_count' => count($doRows),
                    'open_trip_count' => count($tripRows),
                ],
                'delayed_shipments' => $delayedRows,
                'incomplete_delivery_orders' => $doRows,
                'open_trips' => $tripRows,
                'follow_up' => $followUp,
            ],
            'error' => null,
        ];
    }

    private static function getOperationalGuidance(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 10)));
        $analysis = self::analyzeLogistics(['limit' => $limit], $companyId);
        $data = is_array($analysis['data'] ?? null) ? $analysis['data'] : [];
        $actions = [];
        foreach (($data['follow_up'] ?? []) as $fu) {
            if (!is_array($fu)) {
                continue;
            }
            $actions[] = [
                'action' => (string) ($fu['code'] ?? 'review_logistics'),
                'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                'message' => (string) ($fu['message'] ?? ''),
                'refs' => array_filter([
                    'shipment_id' => $fu['shipment_id'] ?? null,
                    'delivery_order_id' => $fu['delivery_order_id'] ?? null,
                    'tracking_number' => $fu['tracking_number'] ?? null,
                ]),
            ];
        }
        if ($actions === []) {
            $actions[] = [
                'action' => 'monitor_logistics',
                'urgency' => 'low',
                'message' => 'no_urgent_logistics_follow_up_from_live_data',
                'refs' => [],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'actions' => array_slice($actions, 0, $limit),
                'summary' => $data['summary'] ?? [],
            ],
            'error' => null,
        ];
    }

    private static function getCrmLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $customerId = (int) ($args['customer_id'] ?? 0);

        try {
            $sql = 'SELECT c.id AS customer_id, c.code AS customer_code, c.name AS customer_name,
                           s.id AS shipment_id, s.tracking_number, s.status AS shipment_status,
                           s.order_id, s.created_at
                    FROM rateb_logistics_shipments s
                    INNER JOIN rateb_customers c ON c.id = s.customer_id AND c.company_id = s.company_id
                    WHERE s.company_id = :cid AND s.customer_id IS NOT NULL AND s.customer_id > 0';
            $params = ['cid' => $companyId];
            if ($customerId > 0) {
                $sql .= ' AND s.customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' ORDER BY s.id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $links = [];
        foreach ($rows as $row) {
            $links[] = [
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'customer_code' => (string) ($row['customer_code'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'shipment_id' => (int) ($row['shipment_id'] ?? 0),
                'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                'shipment_status' => (string) ($row['shipment_status'] ?? ''),
                'order_id' => (int) ($row['order_id'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'notes' => ['links_require_customer_id_on_shipments'],
            ],
            'error' => null,
        ];
    }

    private static function getSalesLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $orderId = (int) ($args['order_id'] ?? 0);
        $openOnly = !empty($args['open_only']);

        try {
            $sql = 'SELECT s.id AS shipment_id, s.tracking_number, s.status AS shipment_status,
                           s.order_id, s.customer_id, o.order_no, o.order_type, o.status AS sales_status,
                           o.total_amount, o.created_at AS sales_created_at
                    FROM rateb_logistics_shipments s
                    INNER JOIN rateb_pos_orders o ON o.id = s.order_id AND o.company_id = s.company_id
                    WHERE s.company_id = :cid AND s.order_id IS NOT NULL AND s.order_id > 0';
            $params = ['cid' => $companyId];
            if ($orderId > 0) {
                $sql .= ' AND s.order_id = :oid';
                $params['oid'] = $orderId;
            }
            if ($openOnly) {
                $openList = "'" . implode("','", self::OPEN_SHIPMENT_STATUSES) . "'";
                $sql .= ' AND s.status IN (' . $openList . ')';
            }
            $sql .= ' ORDER BY s.id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        // Also delivery orders linked to POS
        $doRows = [];
        try {
            $sqlDo = 'SELECT d.id AS delivery_order_id, d.delivery_no, d.status AS delivery_status,
                             d.order_id, d.customer_id, o.order_no, o.status AS sales_status
                      FROM rateb_logistics_delivery_orders d
                      INNER JOIN rateb_pos_orders o ON o.id = d.order_id AND o.company_id = d.company_id
                      WHERE d.company_id = :cid AND d.order_id IS NOT NULL AND d.order_id > 0';
            $paramsDo = ['cid' => $companyId];
            if ($orderId > 0) {
                $sqlDo .= ' AND d.order_id = :oid';
                $paramsDo['oid'] = $orderId;
            }
            if ($openOnly) {
                $sqlDo .= " AND d.status IN ('draft','confirmed','dispatched')";
            }
            $sqlDo .= ' ORDER BY d.id DESC LIMIT ' . $limit;
            $doRows = self::model()->query($sqlDo, $paramsDo);
        } catch (\Throwable $e) {
            $doRows = [];
        }

        $links = [];
        foreach ($rows as $row) {
            $links[] = [
                'type' => 'shipment',
                'shipment_id' => (int) ($row['shipment_id'] ?? 0),
                'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                'shipment_status' => (string) ($row['shipment_status'] ?? ''),
                'sales_order_id' => (int) ($row['order_id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'sales_status' => (string) ($row['sales_status'] ?? ''),
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
            ];
        }
        foreach ($doRows as $row) {
            $links[] = [
                'type' => 'delivery_order',
                'delivery_order_id' => (int) ($row['delivery_order_id'] ?? 0),
                'delivery_no' => (string) ($row['delivery_no'] ?? ''),
                'delivery_status' => (string) ($row['delivery_status'] ?? ''),
                'sales_order_id' => (int) ($row['order_id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'sales_status' => (string) ($row['sales_status'] ?? ''),
                'customer_id' => (int) ($row['customer_id'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => array_slice($links, 0, $limit),
                'notes' => ['links_require_order_id_on_shipments_or_delivery_orders'],
            ],
            'error' => null,
        ];
    }

    private static function getInventoryLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $shipmentId = (int) ($args['shipment_id'] ?? 0);
        $links = [];

        // Path 1: stock movements with reference_type = logistics_shipment
        try {
            $sql = "SELECT sm.id AS movement_id, sm.inventory_id, sm.quantity, sm.movement_type,
                           sm.reference_id AS shipment_id, sm.created_at,
                           i.item_code, i.item_name, i.quantity AS stock_qty,
                           s.tracking_number, s.status AS shipment_status, s.order_id
                    FROM rateb_stock_movements sm
                    INNER JOIN rateb_logistics_shipments s
                        ON s.id = sm.reference_id AND s.company_id = sm.company_id
                    LEFT JOIN rateb_inventory i ON i.id = sm.inventory_id AND i.company_id = sm.company_id
                    WHERE sm.company_id = :cid
                      AND sm.reference_type = 'logistics_shipment'
                      AND sm.inventory_id IS NOT NULL AND sm.inventory_id > 0";
            $params = ['cid' => $companyId];
            if ($shipmentId > 0) {
                $sql .= ' AND sm.reference_id = :sid';
                $params['sid'] = $shipmentId;
            }
            $sql .= ' ORDER BY sm.id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
            foreach ($rows as $row) {
                $links[] = [
                    'source' => 'stock_movement',
                    'movement_id' => (int) ($row['movement_id'] ?? 0),
                    'shipment_id' => (int) ($row['shipment_id'] ?? 0),
                    'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                    'shipment_status' => (string) ($row['shipment_status'] ?? ''),
                    'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                    'item_code' => (string) ($row['item_code'] ?? ''),
                    'item_name' => (string) ($row['item_name'] ?? ''),
                    'movement_qty' => (float) ($row['quantity'] ?? 0),
                    'stock_qty' => (float) ($row['stock_qty'] ?? 0),
                    'order_id' => (int) ($row['order_id'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            // table/column may differ — fall through to sales-line path
        }

        // Path 2: shipments with order_id → POS lines → inventory
        if (count($links) < $limit) {
            try {
                $remain = $limit - count($links);
                $sql = 'SELECT s.id AS shipment_id, s.tracking_number, s.status AS shipment_status, s.order_id,
                               l.inventory_id, COALESCE(i.item_code,\'\') AS item_code,
                               COALESCE(i.item_name, l.description, \'\') AS item_name,
                               SUM(l.quantity) AS demand_qty, COALESCE(i.quantity, 0) AS stock_qty
                        FROM rateb_logistics_shipments s
                        INNER JOIN rateb_pos_orders o ON o.id = s.order_id AND o.company_id = s.company_id
                        INNER JOIN rateb_pos_order_lines l ON l.order_id = o.id AND l.company_id = o.company_id
                        LEFT JOIN rateb_inventory i ON i.id = l.inventory_id AND i.company_id = o.company_id
                        WHERE s.company_id = :cid
                          AND s.order_id IS NOT NULL AND s.order_id > 0
                          AND l.inventory_id IS NOT NULL AND l.inventory_id > 0';
                $params = ['cid' => $companyId];
                if ($shipmentId > 0) {
                    $sql .= ' AND s.id = :sid';
                    $params['sid'] = $shipmentId;
                }
                $sql .= ' GROUP BY s.id, s.tracking_number, s.status, s.order_id, l.inventory_id, i.item_code, i.item_name, l.description, i.quantity
                          ORDER BY s.id DESC LIMIT ' . $remain;
                $rows = self::model()->query($sql, $params);
                foreach ($rows as $row) {
                    $demand = (float) ($row['demand_qty'] ?? 0);
                    $stock = (float) ($row['stock_qty'] ?? 0);
                    $links[] = [
                        'source' => 'sales_order_line',
                        'shipment_id' => (int) ($row['shipment_id'] ?? 0),
                        'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                        'shipment_status' => (string) ($row['shipment_status'] ?? ''),
                        'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                        'item_code' => (string) ($row['item_code'] ?? ''),
                        'item_name' => (string) ($row['item_name'] ?? ''),
                        'demand_qty' => $demand,
                        'stock_qty' => $stock,
                        'is_shortfall' => $stock < $demand,
                        'shortfall_qty' => max(0, $demand - $stock),
                        'order_id' => (int) ($row['order_id'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                // keep existing links
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'notes' => [
                    'stock_movements_reference_type_logistics_shipment',
                    'or_pos_order_lines_via_shipment_order_id',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $inv = self::getInventoryLinks(['limit' => $limit], $companyId);
        $invLinks = is_array($inv['data']['links'] ?? null) ? $inv['data']['links'] : [];
        $links = [];

        foreach ($invLinks as $item) {
            $iid = (int) ($item['inventory_id'] ?? 0);
            if ($iid < 1) {
                continue;
            }
            $prs = [];
            $pos = [];
            try {
                $prs = self::model()->query(
                    'SELECT pr.id, pr.request_no, pr.status
                     FROM rateb_purchase_request_items pri
                     INNER JOIN rateb_purchase_requests pr ON pr.id = pri.purchase_request_id AND pr.company_id = :cid
                     WHERE pri.inventory_id = :iid
                     ORDER BY pr.id DESC LIMIT 5',
                    ['cid' => $companyId, 'iid' => $iid]
                );
            } catch (\Throwable $e) {
                $prs = [];
            }
            try {
                $pos = self::model()->query(
                    'SELECT po.id, po.order_no, po.status, po.total_amount, po.supplier_id
                     FROM rateb_purchase_items poi
                     INNER JOIN rateb_purchase_orders po ON po.id = poi.purchase_order_id AND po.company_id = :cid
                     WHERE poi.inventory_id = :iid
                     ORDER BY po.id DESC LIMIT 5',
                    ['cid' => $companyId, 'iid' => $iid]
                );
            } catch (\Throwable $e) {
                $pos = [];
            }

            if ($prs === [] && $pos === []) {
                continue;
            }

            $links[] = [
                'shipment_id' => (int) ($item['shipment_id'] ?? 0),
                'inventory_id' => $iid,
                'item_code' => (string) ($item['item_code'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'purchase_requests' => $prs,
                'purchase_orders' => $pos,
                'follow_up' => ($prs !== [] || $pos !== []) ? [[
                    'code' => 'logistics_inventory_has_procurement',
                    'message' => 'review_procurement_for_logistics_stock',
                ]] : [],
            ];
            if (count($links) >= $limit) {
                break;
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'notes' => ['links_use_existing_inventory_id_on_pr_po_lines'],
            ],
            'error' => null,
        ];
    }

    private static function getSupplierLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $proc = self::getProcurementLinks(['limit' => $limit], $companyId);
        $procLinks = is_array($proc['data']['links'] ?? null) ? $proc['data']['links'] : [];
        $bySupplier = [];

        foreach ($procLinks as $link) {
            foreach (($link['purchase_orders'] ?? []) as $po) {
                $sid = (int) ($po['supplier_id'] ?? 0);
                if ($sid < 1) {
                    continue;
                }
                if (!isset($bySupplier[$sid])) {
                    $name = '';
                    try {
                        $srows = self::model()->query(
                            'SELECT id, name, code FROM rateb_suppliers WHERE id = :id AND company_id = :cid LIMIT 1',
                            ['id' => $sid, 'cid' => $companyId]
                        );
                        $name = (string) ($srows[0]['name'] ?? '');
                        $code = (string) ($srows[0]['code'] ?? '');
                    } catch (\Throwable $e) {
                        $name = '';
                        $code = '';
                    }
                    $bySupplier[$sid] = [
                        'supplier_id' => $sid,
                        'supplier_name' => $name,
                        'supplier_code' => $code ?? '',
                        'purchase_orders' => [],
                        'inventory_ids' => [],
                        'shipment_ids' => [],
                    ];
                }
                $bySupplier[$sid]['purchase_orders'][] = [
                    'id' => (int) ($po['id'] ?? 0),
                    'order_no' => (string) ($po['order_no'] ?? ''),
                    'status' => (string) ($po['status'] ?? ''),
                ];
                $iid = (int) ($link['inventory_id'] ?? 0);
                if ($iid > 0 && !in_array($iid, $bySupplier[$sid]['inventory_ids'], true)) {
                    $bySupplier[$sid]['inventory_ids'][] = $iid;
                }
                $shipId = (int) ($link['shipment_id'] ?? 0);
                if ($shipId > 0 && !in_array($shipId, $bySupplier[$sid]['shipment_ids'], true)) {
                    $bySupplier[$sid]['shipment_ids'][] = $shipId;
                }
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => array_slice(array_values($bySupplier), 0, $limit),
                'notes' => ['supplier_links_require_po_supplier_id_and_inventory_id'],
            ],
            'error' => null,
        ];
    }

    private static function analyzeEndToEnd(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $customerId = (int) ($args['customer_id'] ?? 0);

        $logistics = self::analyzeLogistics(['limit' => $limit], $companyId);
        $crmArgs = ['limit' => $limit];
        if ($customerId > 0) {
            $crmArgs['customer_id'] = $customerId;
        }
        $crm = self::getCrmLinks($crmArgs, $companyId);
        $sales = self::getSalesLinks(['limit' => $limit, 'open_only' => false], $companyId);
        $inv = self::getInventoryLinks(['limit' => $limit], $companyId);
        $proc = self::getProcurementLinks(['limit' => $limit], $companyId);
        $sup = self::getSupplierLinks(['limit' => $limit], $companyId);

        $chain = [];
        $salesLinks = is_array($sales['data']['links'] ?? null) ? $sales['data']['links'] : [];
        foreach ($salesLinks as $sl) {
            $oid = (int) ($sl['sales_order_id'] ?? 0);
            $cuid = (int) ($sl['customer_id'] ?? 0);
            if ($customerId > 0 && $cuid !== $customerId) {
                continue;
            }
            $invForOrder = [];
            foreach (($inv['data']['links'] ?? []) as $il) {
                if ((int) ($il['order_id'] ?? 0) === $oid || (int) ($il['shipment_id'] ?? 0) === (int) ($sl['shipment_id'] ?? 0)) {
                    $invForOrder[] = $il;
                }
            }
            $procFor = [];
            $supFor = [];
            foreach ($invForOrder as $il) {
                $iid = (int) ($il['inventory_id'] ?? 0);
                foreach (($proc['data']['links'] ?? []) as $pl) {
                    if ((int) ($pl['inventory_id'] ?? 0) === $iid) {
                        $procFor[] = $pl;
                        foreach (($pl['purchase_orders'] ?? []) as $po) {
                            $sid = (int) ($po['supplier_id'] ?? 0);
                            if ($sid > 0) {
                                foreach (($sup['data']['links'] ?? []) as $sul) {
                                    if ((int) ($sul['supplier_id'] ?? 0) === $sid) {
                                        $supFor[] = $sul;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $chain[] = [
                'customer_id' => $cuid,
                'sales_order_id' => $oid,
                'order_no' => (string) ($sl['order_no'] ?? ''),
                'sales_status' => (string) ($sl['sales_status'] ?? ''),
                'logistics' => [
                    'type' => (string) ($sl['type'] ?? 'shipment'),
                    'shipment_id' => (int) ($sl['shipment_id'] ?? 0),
                    'tracking_number' => (string) ($sl['tracking_number'] ?? ''),
                    'shipment_status' => (string) ($sl['shipment_status'] ?? ''),
                    'delivery_order_id' => (int) ($sl['delivery_order_id'] ?? 0),
                    'delivery_status' => (string) ($sl['delivery_status'] ?? ''),
                ],
                'inventory_links' => $invForOrder,
                'procurement_links' => $procFor,
                'supplier_links' => $supFor,
            ];
            if (count($chain) >= $limit) {
                break;
            }
        }

        $followUp = is_array($logistics['data']['follow_up'] ?? null) ? $logistics['data']['follow_up'] : [];
        foreach (($inv['data']['links'] ?? []) as $il) {
            if (!empty($il['is_shortfall'])) {
                $followUp[] = [
                    'code' => 'logistics_sales_stock_shortfall',
                    'urgency' => 'high',
                    'shipment_id' => (int) ($il['shipment_id'] ?? 0),
                    'inventory_id' => (int) ($il['inventory_id'] ?? 0),
                    'message' => 'logistics_linked_sales_stock_insufficient',
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'as_of' => date('Y-m-d'),
                'domains' => ['crm', 'sales', 'inventory', 'procurement', 'suppliers', 'logistics'],
                'chain' => $chain,
                'logistics_summary' => $logistics['data']['summary'] ?? [],
                'crm_links' => $crm['data']['links'] ?? [],
                'sales_links' => $salesLinks,
                'inventory_links' => $inv['data']['links'] ?? [],
                'procurement_links' => $proc['data']['links'] ?? [],
                'supplier_links' => $sup['data']['links'] ?? [],
                'follow_up' => $followUp,
                'relation_map' => [
                    'customer' => 'rateb_logistics_shipments.customer_id / rateb_customers',
                    'sales_order' => 'rateb_logistics_shipments.order_id → rateb_pos_orders',
                    'stock_availability' => 'rateb_stock_movements(reference_type=logistics_shipment) or pos lines',
                    'procurement_requirement' => 'rateb_purchase_request_items / rateb_purchase_items.inventory_id',
                    'supplier' => 'rateb_purchase_orders.supplier_id',
                    'logistics' => 'rateb_logistics_shipments / delivery_orders / trips',
                ],
            ],
            'error' => null,
        ];
    }
}
