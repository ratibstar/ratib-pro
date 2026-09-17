<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;

/**
 * Sales Tool Executor — tenant-scoped READ/ANALYSIS against live POS + related ERP tables.
 * No WRITE. No invented entities.
 */
final class SalesToolExecutor
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
                case 'list_sales_orders':
                    return self::listSalesOrders($arguments, $companyId);
                case 'get_sales_order':
                    return self::getSalesOrder($arguments, $companyId);
                case 'list_sales_customers':
                    return self::listSalesCustomers($arguments, $companyId);
                case 'analyze_sales':
                    return self::analyzeSales($arguments, $companyId);
                case 'get_sales_operational_guidance':
                    return self::getSalesOperationalGuidance($arguments, $companyId);
                case 'get_sales_inventory_links':
                    return self::getSalesInventoryLinks($arguments, $companyId);
                case 'get_sales_procurement_links':
                    return self::getSalesProcurementLinks($arguments, $companyId);
                case 'get_sales_supplier_links':
                    return self::getSalesSupplierLinks($arguments, $companyId);
                case 'analyze_sales_cross_domain':
                    return self::analyzeSalesCrossDomain($arguments, $companyId);
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

    private static function listSalesOrders(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $status = trim((string) ($args['status'] ?? ''));
        $orderType = trim((string) ($args['order_type'] ?? ''));
        $search = trim((string) ($args['search'] ?? ''));

        $sql = 'SELECT o.id, o.order_no, o.order_type, o.status, o.customer_id, o.branch_id, o.warehouse_id,
                       o.subtotal, o.discount_total, o.tax, o.total, o.created_at, o.updated_at,
                       c.name AS customer_name, c.code AS customer_code
                FROM rateb_pos_orders o
                LEFT JOIN rateb_customers c ON c.id = o.customer_id AND c.company_id = o.company_id
                WHERE o.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== '') {
            $sql .= ' AND o.status = :st';
            $params['st'] = $status;
        }
        if ($orderType !== '') {
            $sql .= ' AND o.order_type = :ot';
            $params['ot'] = $orderType;
        }
        if ($search !== '') {
            $sql .= ' AND (o.order_no LIKE :q OR c.name LIKE :q OR c.code LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY o.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = self::model()->query($sql, $params);
        foreach ($rows as &$row) {
            $row['subtotal'] = (float) ($row['subtotal'] ?? 0);
            $row['discount_total'] = (float) ($row['discount_total'] ?? 0);
            $row['tax'] = (float) ($row['tax'] ?? 0);
            $row['total'] = (float) ($row['total'] ?? 0);
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getSalesOrder(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_sales_order_id');
        }

        $rows = self::model()->query(
            'SELECT o.*, c.name AS customer_name, c.code AS customer_code, c.phone AS customer_phone
             FROM rateb_pos_orders o
             LEFT JOIN rateb_customers c ON c.id = o.customer_id AND c.company_id = o.company_id
             WHERE o.id = :id AND o.company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $order = $rows[0] ?? null;
        if (!$order) {
            return self::fail('sales_order_not_found');
        }

        $lines = self::model()->query(
            'SELECT l.id, l.line_no, l.inventory_id, l.description, l.quantity, l.unit_price,
                    l.discount_amount, l.tax_amount, l.line_total,
                    i.item_code, i.item_name, i.quantity AS stock_qty, i.unit
             FROM rateb_pos_order_lines l
             LEFT JOIN rateb_inventory i ON i.id = l.inventory_id AND i.company_id = l.company_id
             WHERE l.order_id = :oid AND l.company_id = :cid
             ORDER BY l.line_no ASC, l.id ASC',
            ['oid' => $id, 'cid' => $companyId]
        );
        foreach ($lines as &$line) {
            $line['quantity'] = (float) ($line['quantity'] ?? 0);
            $line['unit_price'] = (float) ($line['unit_price'] ?? 0);
            $line['line_total'] = (float) ($line['line_total'] ?? 0);
            $line['stock_qty'] = isset($line['stock_qty']) ? (float) $line['stock_qty'] : null;
            $line['stock_shortfall'] = $line['stock_qty'] !== null
                && $line['quantity'] > $line['stock_qty'];
        }
        unset($line);

        $order['subtotal'] = (float) ($order['subtotal'] ?? 0);
        $order['total'] = (float) ($order['total'] ?? 0);
        $order['lines'] = $lines;
        $order['line_count'] = count($lines);

        return ['success' => true, 'data' => $order, 'error' => null];
    }

    private static function listSalesCustomers(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $search = trim((string) ($args['search'] ?? ''));

        $sql = 'SELECT c.id, c.code, c.name, c.phone, c.email, c.is_active,
                       COUNT(o.id) AS order_count,
                       COALESCE(SUM(o.total),0) AS order_total
                FROM rateb_customers c
                INNER JOIN rateb_pos_orders o ON o.customer_id = c.id AND o.company_id = c.company_id
                WHERE c.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($search !== '') {
            $sql .= ' AND (c.name LIKE :q OR c.code LIKE :q OR c.phone LIKE :q OR c.email LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' GROUP BY c.id, c.code, c.name, c.phone, c.email, c.is_active
                  ORDER BY order_count DESC, c.id DESC
                  LIMIT ' . $limit;
        $rows = self::model()->query($sql, $params);
        foreach ($rows as &$row) {
            $row['order_count'] = (int) ($row['order_count'] ?? 0);
            $row['order_total'] = (float) ($row['order_total'] ?? 0);
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function analyzeSales(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $m = self::model();

        $totals = $m->query(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(total),0) AS amount,
                    COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END),0) AS draft_cnt,
                    COALESCE(SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END),0) AS suspended_cnt,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END),0) AS completed_cnt,
                    COALESCE(SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END),0) AS void_cnt,
                    COALESCE(SUM(CASE WHEN status IN ('draft','suspended') THEN total ELSE 0 END),0) AS open_amount
             FROM rateb_pos_orders WHERE company_id = :cid",
            ['cid' => $companyId]
        );

        $openOrders = $m->query(
            "SELECT o.id, o.order_no, o.order_type, o.status, o.total, o.customer_id, o.created_at,
                    c.name AS customer_name
             FROM rateb_pos_orders o
             LEFT JOIN rateb_customers c ON c.id = o.customer_id AND c.company_id = o.company_id
             WHERE o.company_id = :cid AND o.status IN ('draft','suspended')
             ORDER BY o.id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $topProducts = [];
        try {
            $topProducts = $m->query(
                "SELECT l.inventory_id, COALESCE(i.item_code,'') AS item_code,
                        COALESCE(i.item_name, l.description) AS item_name,
                        COALESCE(i.quantity,0) AS stock_qty,
                        SUM(l.quantity) AS sold_qty,
                        COUNT(DISTINCT l.order_id) AS order_count
                 FROM rateb_pos_order_lines l
                 INNER JOIN rateb_pos_orders o ON o.id = l.order_id AND o.company_id = l.company_id
                 LEFT JOIN rateb_inventory i ON i.id = l.inventory_id AND i.company_id = l.company_id
                 WHERE l.company_id = :cid AND o.status <> 'void'
                 GROUP BY l.inventory_id, i.item_code, i.item_name, l.description, i.quantity
                 ORDER BY sold_qty DESC
                 LIMIT {$limit}",
                ['cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $topProducts = [];
        }

        $followUp = [];
        foreach ($openOrders as $row) {
            $followUp[] = [
                'code' => 'open_sales_order',
                'urgency' => ((string) ($row['status'] ?? '') === 'suspended') ? 'high' : 'medium',
                'sales_order_id' => (int) ($row['id'] ?? 0),
                'label' => (string) (($row['order_no'] ?? '') . ' / ' . ($row['customer_name'] ?? '')),
                'status' => (string) ($row['status'] ?? ''),
                'total' => (float) ($row['total'] ?? 0),
            ];
        }

        $shortfalls = self::getSalesInventoryLinks(['limit' => $limit, 'shortfall_only' => true], $companyId);
        $shortfallLinks = [];
        if (!empty($shortfalls['success']) && is_array($shortfalls['data']['links'] ?? null)) {
            $shortfallLinks = $shortfalls['data']['links'];
            foreach ($shortfallLinks as $link) {
                $followUp[] = [
                    'code' => 'sales_stock_shortfall',
                    'urgency' => 'high',
                    'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                    'label' => (string) (($link['item_code'] ?? '') . ' ' . ($link['item_name'] ?? '')),
                    'demand_qty' => (float) ($link['demand_qty'] ?? 0),
                    'stock_qty' => (float) ($link['stock_qty'] ?? 0),
                ];
            }
        }

        $normalizeMoney = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                if (isset($row['total'])) {
                    $row['total'] = (float) $row['total'];
                }
                if (isset($row['sold_qty'])) {
                    $row['sold_qty'] = (float) $row['sold_qty'];
                }
                if (isset($row['stock_qty'])) {
                    $row['stock_qty'] = (float) $row['stock_qty'];
                }
                $out[] = $row;
            }
            return $out;
        };

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'summary' => [
                    'order_count' => (int) ($totals[0]['cnt'] ?? 0),
                    'order_amount' => (float) ($totals[0]['amount'] ?? 0),
                    'draft_count' => (int) ($totals[0]['draft_cnt'] ?? 0),
                    'suspended_count' => (int) ($totals[0]['suspended_cnt'] ?? 0),
                    'completed_count' => (int) ($totals[0]['completed_cnt'] ?? 0),
                    'void_count' => (int) ($totals[0]['void_cnt'] ?? 0),
                    'open_amount' => (float) ($totals[0]['open_amount'] ?? 0),
                    'stock_shortfall_count' => count($shortfallLinks),
                    'currency' => 'SAR',
                ],
                'open_orders' => $normalizeMoney($openOrders),
                'top_products' => $normalizeMoney($topProducts),
                'stock_shortfalls' => $shortfallLinks,
                'follow_up' => array_slice($followUp, 0, $limit),
                'notes' => [
                    'empty_lists_mean_no_matching_records',
                    'all_values_from_live_tenant_pos_data',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSalesOperationalGuidance(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 10)));
        $analysis = self::analyzeSales(['limit' => $limit], $companyId);
        if (empty($analysis['success']) || !is_array($analysis['data'] ?? null)) {
            return self::fail('tool_exception');
        }
        $data = $analysis['data'];
        $actions = [];
        foreach (($data['follow_up'] ?? []) as $fu) {
            if (!is_array($fu)) {
                continue;
            }
            $code = (string) ($fu['code'] ?? '');
            $actions[] = [
                'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                'action' => $code === 'sales_stock_shortfall' ? 'review_sales_stock_shortfall' : 'review_open_sales_order',
                'reason_code' => $code,
                'label' => (string) ($fu['label'] ?? ''),
                'suggested_read_tool' => $code === 'sales_stock_shortfall'
                    ? 'get_sales_inventory_links'
                    : 'get_sales_order',
                'suggested_write_tool' => null,
                'requires_confirmation_for_write' => false,
                'auto_execute_write' => false,
            ];
        }
        if ($actions === []) {
            $actions[] = [
                'urgency' => 'none',
                'action' => 'no_urgent_sales_action',
                'reason_code' => 'healthy_or_empty',
                'label' => 'no_matching_sales_issues',
                'suggested_read_tool' => 'analyze_sales',
                'suggested_write_tool' => null,
                'requires_confirmation_for_write' => false,
                'auto_execute_write' => false,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'summary' => $data['summary'] ?? [],
                'actions' => array_slice($actions, 0, $limit),
                'notes' => [
                    'guidance_only_never_auto_writes',
                    'based_on_live_pos_and_inventory_relations',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSalesInventoryLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $shortfallOnly = !empty($args['shortfall_only']);

        try {
            $sql = "SELECT l.inventory_id,
                           COALESCE(i.item_code,'') AS item_code,
                           COALESCE(i.item_name, MAX(l.description)) AS item_name,
                           COALESCE(i.quantity,0) AS stock_qty,
                           COALESCE(i.unit,'') AS unit,
                           SUM(l.quantity) AS demand_qty,
                           COUNT(DISTINCT l.order_id) AS order_count,
                           GROUP_CONCAT(DISTINCT o.order_no ORDER BY o.id DESC SEPARATOR ',') AS order_nos
                    FROM rateb_pos_order_lines l
                    INNER JOIN rateb_pos_orders o ON o.id = l.order_id AND o.company_id = l.company_id
                    LEFT JOIN rateb_inventory i ON i.id = l.inventory_id AND i.company_id = l.company_id
                    WHERE l.company_id = :cid
                      AND l.inventory_id IS NOT NULL AND l.inventory_id > 0
                      AND o.status IN ('draft','suspended','completed')
                    GROUP BY l.inventory_id, i.item_code, i.item_name, i.quantity, i.unit";
            if ($shortfallOnly) {
                $sql .= ' HAVING demand_qty > stock_qty';
            }
            $sql .= ' ORDER BY (demand_qty - stock_qty) DESC, demand_qty DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, ['cid' => $companyId]);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $links = [];
        foreach ($rows as $row) {
            $demand = (float) ($row['demand_qty'] ?? 0);
            $stock = (float) ($row['stock_qty'] ?? 0);
            $links[] = [
                'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                'item_code' => (string) ($row['item_code'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'unit' => (string) ($row['unit'] ?? ''),
                'demand_qty' => $demand,
                'stock_qty' => $stock,
                'shortfall_qty' => max(0, $demand - $stock),
                'is_shortfall' => $demand > $stock,
                'order_count' => (int) ($row['order_count'] ?? 0),
                'order_nos' => array_values(array_filter(array_map('trim', explode(',', (string) ($row['order_nos'] ?? ''))))),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'links' => $links,
                'count' => count($links),
                'notes' => [
                    'links_require_inventory_id_on_pos_order_lines',
                    'empty_links_mean_no_matching_relations',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSalesProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $inv = self::getSalesInventoryLinks(['limit' => $limit, 'shortfall_only' => true], $companyId);
        if (empty($inv['success'])) {
            return $inv;
        }
        $links = [];
        foreach (($inv['data']['links'] ?? []) as $item) {
            $iid = (int) ($item['inventory_id'] ?? 0);
            if ($iid < 1) {
                continue;
            }
            $prs = [];
            $pos = [];
            try {
                $prs = self::model()->query(
                    "SELECT pr.id, pr.request_no, pr.title, pr.status, pr.total_estimated
                     FROM rateb_purchase_request_items pri
                     INNER JOIN rateb_purchase_requests pr
                             ON pr.id = pri.purchase_request_id AND pr.company_id = :cid
                     WHERE pri.inventory_id = :iid
                     ORDER BY pr.id DESC LIMIT 10",
                    ['cid' => $companyId, 'iid' => $iid]
                );
            } catch (\Throwable $e) {
                $prs = [];
            }
            try {
                $pos = self::model()->query(
                    "SELECT po.id, po.order_no, po.status, po.total_amount, po.supplier_id
                     FROM rateb_purchase_items poi
                     INNER JOIN rateb_purchase_orders po
                             ON po.id = poi.purchase_order_id AND po.company_id = :cid
                     WHERE poi.inventory_id = :iid
                     ORDER BY po.id DESC LIMIT 10",
                    ['cid' => $companyId, 'iid' => $iid]
                );
            } catch (\Throwable $e) {
                $pos = [];
            }

            $follow = [];
            if ($prs === [] && $pos === []) {
                $follow[] = [
                    'code' => 'sales_shortfall_without_procurement',
                    'message' => 'sales_demand_exceeds_stock_no_linked_pr_or_po',
                ];
            }

            $links[] = [
                'inventory_id' => $iid,
                'item_code' => (string) ($item['item_code'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'demand_qty' => (float) ($item['demand_qty'] ?? 0),
                'stock_qty' => (float) ($item['stock_qty'] ?? 0),
                'shortfall_qty' => (float) ($item['shortfall_qty'] ?? 0),
                'purchase_requests' => array_map(static function ($r) {
                    return [
                        'id' => (int) ($r['id'] ?? 0),
                        'request_no' => (string) ($r['request_no'] ?? ''),
                        'title' => (string) ($r['title'] ?? ''),
                        'status' => (string) ($r['status'] ?? ''),
                        'total_estimated' => (float) ($r['total_estimated'] ?? 0),
                    ];
                }, $prs),
                'purchase_orders' => array_map(static function ($r) {
                    return [
                        'id' => (int) ($r['id'] ?? 0),
                        'order_no' => (string) ($r['order_no'] ?? ''),
                        'status' => (string) ($r['status'] ?? ''),
                        'total_amount' => (float) ($r['total_amount'] ?? 0),
                        'supplier_id' => (int) ($r['supplier_id'] ?? 0),
                    ];
                }, $pos),
                'follow_up' => $follow,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'links' => array_slice($links, 0, $limit),
                'count' => min(count($links), $limit),
                'notes' => [
                    'links_use_existing_inventory_id_on_pr_po_lines',
                    'empty_links_mean_no_sales_shortfall_or_no_procurement_relation',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSalesSupplierLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $proc = self::getSalesProcurementLinks(['limit' => $limit], $companyId);
        if (empty($proc['success'])) {
            return $proc;
        }

        $bySupplier = [];
        foreach (($proc['data']['links'] ?? []) as $item) {
            foreach (($item['purchase_orders'] ?? []) as $po) {
                $sid = (int) ($po['supplier_id'] ?? 0);
                if ($sid < 1) {
                    continue;
                }
                if (!isset($bySupplier[$sid])) {
                    $nameRows = self::model()->query(
                        'SELECT id, code, name, status FROM rateb_suppliers WHERE id = :id AND company_id = :cid LIMIT 1',
                        ['id' => $sid, 'cid' => $companyId]
                    );
                    $sup = $nameRows[0] ?? null;
                    if (!$sup) {
                        continue;
                    }
                    $bySupplier[$sid] = [
                        'supplier_id' => $sid,
                        'code' => (string) ($sup['code'] ?? ''),
                        'name' => (string) ($sup['name'] ?? ''),
                        'status' => (string) ($sup['status'] ?? ''),
                        'items' => [],
                        'purchase_orders' => [],
                    ];
                }
                $iid = (int) ($item['inventory_id'] ?? 0);
                $existsItem = false;
                foreach ($bySupplier[$sid]['items'] as $existing) {
                    if ((int) ($existing['inventory_id'] ?? 0) === $iid) {
                        $existsItem = true;
                        break;
                    }
                }
                if (!$existsItem && $iid > 0) {
                    $bySupplier[$sid]['items'][] = [
                        'inventory_id' => $iid,
                        'item_code' => (string) ($item['item_code'] ?? ''),
                        'item_name' => (string) ($item['item_name'] ?? ''),
                        'shortfall_qty' => (float) ($item['shortfall_qty'] ?? 0),
                    ];
                }
                $poId = (int) ($po['id'] ?? 0);
                $existsPo = false;
                foreach ($bySupplier[$sid]['purchase_orders'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $poId) {
                        $existsPo = true;
                        break;
                    }
                }
                if (!$existsPo) {
                    $bySupplier[$sid]['purchase_orders'][] = $po;
                }
            }
        }

        $list = array_slice(array_values($bySupplier), 0, $limit);

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'links' => $list,
                'count' => count($list),
                'notes' => [
                    'supplier_links_require_po_supplier_id_and_inventory_id',
                    'empty_links_mean_no_existing_relation',
                ],
            ],
            'error' => null,
        ];
    }

    private static function analyzeSalesCrossDomain(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $base = ['limit' => $limit];

        $sales = self::analyzeSales($base, $companyId);
        $inv = self::getSalesInventoryLinks($base + ['shortfall_only' => false], $companyId);
        $proc = self::getSalesProcurementLinks($base, $companyId);
        $sup = self::getSalesSupplierLinks($base, $companyId);
        $guide = self::getSalesOperationalGuidance($base, $companyId);

        if (empty($sales['success'])) {
            return self::fail('tool_exception');
        }

        $followUp = is_array($sales['data']['follow_up'] ?? null) ? $sales['data']['follow_up'] : [];
        foreach (($proc['data']['links'] ?? []) as $link) {
            foreach (($link['follow_up'] ?? []) as $fu) {
                $followUp[] = array_merge(is_array($fu) ? $fu : [], [
                    'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                    'urgency' => 'high',
                ]);
            }
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'domains' => ['sales', 'inventory', 'procurement', 'suppliers'],
                'sales_summary' => $sales['data']['summary'] ?? [],
                'inventory_links' => $inv['data']['links'] ?? [],
                'procurement_links' => $proc['data']['links'] ?? [],
                'supplier_links' => $sup['data']['links'] ?? [],
                'guidance' => $guide['data']['actions'] ?? [],
                'follow_up' => array_slice($followUp, 0, $limit),
                'chain' => [
                    'sales_order' => 'rateb_pos_orders',
                    'stock_availability' => 'rateb_inventory via pos_order_lines.inventory_id',
                    'procurement_requirement' => 'rateb_purchase_request_items / rateb_purchase_items.inventory_id',
                    'supplier' => 'rateb_purchase_orders.supplier_id',
                ],
                'notes' => [
                    'cross_domain_uses_existing_relations_only',
                    'no_separate_sales_agent',
                    'failed_optional_joins_omitted_not_invented',
                ],
            ],
            'error' => null,
        ];
    }
}
