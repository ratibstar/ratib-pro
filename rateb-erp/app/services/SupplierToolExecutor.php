<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Supplier;

/**
 * Supplier Tool Executor — tenant-scoped READ/ANALYSIS against live ERP tables.
 */
final class SupplierToolExecutor
{
    private const OPEN_PO_STATUSES = ['draft', 'sent', 'confirmed', 'partial'];

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
                case 'list_suppliers':
                    return self::listSuppliers($arguments, $companyId);
                case 'get_supplier':
                    return self::getSupplier($arguments, $companyId);
                case 'analyze_suppliers':
                    return self::analyzeSuppliers($arguments, $companyId);
                case 'get_supplier_procurement_links':
                    return self::getSupplierProcurementLinks($arguments, $companyId);
                case 'get_supplier_inventory_links':
                    return self::getSupplierInventoryLinks($arguments, $companyId);
                case 'analyze_supplier_cross_domain':
                    return self::analyzeSupplierCrossDomain($arguments, $companyId);
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

    private static function listSuppliers(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $status = trim((string) ($args['status'] ?? ''));

        $sql = 'SELECT id, code, name, status, phone, email, address, rating, notes, created_at
                FROM rateb_suppliers WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (name LIKE :q OR code LIKE :q OR email LIKE :q OR phone LIKE :q OR address LIKE :q OR notes LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = (new Supplier())->query($sql, $params);
        foreach ($rows as &$row) {
            $row['rating'] = $row['rating'] !== null ? (float) $row['rating'] : null;
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getSupplier(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_supplier_id');
        }

        $rows = (new Supplier())->query(
            'SELECT id, code, name, status, phone, email, address, rating, notes, created_at, updated_at
             FROM rateb_suppliers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $item = $rows[0] ?? null;
        if (!$item) {
            return self::fail('supplier_not_found');
        }
        $item['rating'] = $item['rating'] !== null ? (float) $item['rating'] : null;

        return ['success' => true, 'data' => $item, 'error' => null];
    }

    private static function analyzeSuppliers(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        $model = new Supplier();
        $today = date('Y-m-d');
        $openList = "'" . implode("','", self::OPEN_PO_STATUSES) . "'";

        $totals = $model->query(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END),0) AS active_cnt,
                    COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END),0) AS inactive_cnt,
                    COALESCE(SUM(CASE WHEN status = 'blacklisted' THEN 1 ELSE 0 END),0) AS blacklisted_cnt
             FROM rateb_suppliers WHERE company_id = :cid"
            . ($supplierId > 0 ? ' AND id = :sid' : ''),
            $supplierId > 0 ? ['cid' => $companyId, 'sid' => $supplierId] : ['cid' => $companyId]
        );

        $poAgg = $model->query(
            "SELECT COUNT(*) AS po_cnt,
                    COALESCE(SUM(total_amount),0) AS po_amount,
                    COALESCE(SUM(CASE WHEN status IN ({$openList}) THEN 1 ELSE 0 END),0) AS open_po_cnt,
                    COALESCE(SUM(CASE WHEN status IN ({$openList}) THEN total_amount ELSE 0 END),0) AS open_po_amount,
                    COALESCE(SUM(CASE WHEN expected_date IS NOT NULL AND expected_date <> ''
                        AND expected_date < :today AND status IN ({$openList}) THEN 1 ELSE 0 END),0) AS overdue_po_cnt
             FROM rateb_purchase_orders
             WHERE company_id = :cid AND supplier_id IS NOT NULL AND supplier_id > 0"
            . ($supplierId > 0 ? ' AND supplier_id = :sid' : ''),
            $supplierId > 0
                ? ['cid' => $companyId, 'sid' => $supplierId, 'today' => $today]
                : ['cid' => $companyId, 'today' => $today]
        );

        $linkedPrCnt = 0;
        try {
            $linkedPr = $model->query(
                "SELECT COUNT(DISTINCT pri.purchase_request_id) AS pr_cnt
                 FROM rateb_purchase_request_items pri
                 INNER JOIN rateb_purchase_requests pr ON pr.id = pri.purchase_request_id AND pr.company_id = :cid
                 WHERE pri.supplier_id IS NOT NULL AND pri.supplier_id > 0"
                . ($supplierId > 0 ? ' AND pri.supplier_id = :sid' : ''),
                $supplierId > 0 ? ['cid' => $companyId, 'sid' => $supplierId] : ['cid' => $companyId]
            );
            $linkedPrCnt = (int) ($linkedPr[0]['pr_cnt'] ?? 0);
        } catch (\Throwable $e) {
            $linkedPrCnt = 0;
        }

        $topBySpend = $model->query(
            "SELECT s.id, s.code, s.name, s.status,
                    COUNT(po.id) AS order_count,
                    COALESCE(SUM(po.total_amount),0) AS total_amount,
                    COALESCE(SUM(CASE WHEN po.status IN ({$openList}) THEN 1 ELSE 0 END),0) AS open_orders,
                    COALESCE(SUM(CASE WHEN po.expected_date IS NOT NULL AND po.expected_date <> ''
                        AND po.expected_date < :today AND po.status IN ({$openList}) THEN 1 ELSE 0 END),0) AS overdue_orders
             FROM rateb_suppliers s
             INNER JOIN rateb_purchase_orders po ON po.supplier_id = s.id AND po.company_id = s.company_id
             WHERE s.company_id = :cid"
            . ($supplierId > 0 ? ' AND s.id = :sid' : '')
            . " GROUP BY s.id, s.code, s.name, s.status
             ORDER BY total_amount DESC
             LIMIT {$limit}",
            $supplierId > 0
                ? ['cid' => $companyId, 'sid' => $supplierId, 'today' => $today]
                : ['cid' => $companyId, 'today' => $today]
        );

        $openOps = $model->query(
            "SELECT s.id AS supplier_id, s.name AS supplier_name, po.id AS purchase_order_id, po.order_no,
                    po.status, po.expected_date, po.total_amount, po.purchase_request_id
             FROM rateb_purchase_orders po
             INNER JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
             WHERE po.company_id = :cid AND po.supplier_id IS NOT NULL AND po.supplier_id > 0
               AND po.status IN ({$openList})"
            . ($supplierId > 0 ? ' AND po.supplier_id = :sid' : '')
            . " ORDER BY po.expected_date IS NULL, po.expected_date ASC, po.id DESC
             LIMIT {$limit}",
            $supplierId > 0 ? ['cid' => $companyId, 'sid' => $supplierId] : ['cid' => $companyId]
        );

        $overdue = $model->query(
            "SELECT s.id AS supplier_id, s.name AS supplier_name, po.id AS purchase_order_id, po.order_no,
                    po.status, po.expected_date, po.total_amount
             FROM rateb_purchase_orders po
             INNER JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
             WHERE po.company_id = :cid AND po.supplier_id IS NOT NULL AND po.supplier_id > 0
               AND po.status IN ({$openList})
               AND po.expected_date IS NOT NULL AND po.expected_date <> '' AND po.expected_date < :today"
            . ($supplierId > 0 ? ' AND po.supplier_id = :sid' : '')
            . " ORDER BY po.expected_date ASC
             LIMIT {$limit}",
            $supplierId > 0
                ? ['cid' => $companyId, 'sid' => $supplierId, 'today' => $today]
                : ['cid' => $companyId, 'today' => $today]
        );

        $followUp = [];
        foreach ($overdue as $row) {
            $followUp[] = [
                'code' => 'overdue_purchase_order',
                'urgency' => 'high',
                'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                'purchase_order_id' => (int) ($row['purchase_order_id'] ?? 0),
                'label' => (string) (($row['supplier_name'] ?? '') . ' / ' . ($row['order_no'] ?? '')),
                'expected_date' => (string) ($row['expected_date'] ?? ''),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
            ];
        }
        foreach ($openOps as $row) {
            $sid = (int) ($row['supplier_id'] ?? 0);
            $poId = (int) ($row['purchase_order_id'] ?? 0);
            $already = false;
            foreach ($followUp as $fu) {
                if ((int) ($fu['purchase_order_id'] ?? 0) === $poId) {
                    $already = true;
                    break;
                }
            }
            if (!$already && $sid > 0) {
                $followUp[] = [
                    'code' => 'open_purchase_order',
                    'urgency' => 'medium',
                    'supplier_id' => $sid,
                    'purchase_order_id' => $poId,
                    'label' => (string) (($row['supplier_name'] ?? '') . ' / ' . ($row['order_no'] ?? '')),
                    'status' => (string) ($row['status'] ?? ''),
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                ];
            }
        }

        $normalizeMoney = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                if (isset($row['total_amount'])) {
                    $row['total_amount'] = (float) $row['total_amount'];
                }
                if (isset($row['order_count'])) {
                    $row['order_count'] = (int) $row['order_count'];
                }
                if (isset($row['open_orders'])) {
                    $row['open_orders'] = (int) $row['open_orders'];
                }
                if (isset($row['overdue_orders'])) {
                    $row['overdue_orders'] = (int) $row['overdue_orders'];
                }
                $out[] = $row;
            }
            return $out;
        };

        return [
            'success' => true,
            'data' => [
                'as_of' => $today,
                'data_source' => 'live_tenant',
                'summary' => [
                    'supplier_count' => (int) ($totals[0]['cnt'] ?? 0),
                    'active_count' => (int) ($totals[0]['active_cnt'] ?? 0),
                    'inactive_count' => (int) ($totals[0]['inactive_cnt'] ?? 0),
                    'blacklisted_count' => (int) ($totals[0]['blacklisted_cnt'] ?? 0),
                    'purchase_order_count' => (int) ($poAgg[0]['po_cnt'] ?? 0),
                    'purchase_order_amount' => (float) ($poAgg[0]['po_amount'] ?? 0),
                    'open_purchase_order_count' => (int) ($poAgg[0]['open_po_cnt'] ?? 0),
                    'open_purchase_order_amount' => (float) ($poAgg[0]['open_po_amount'] ?? 0),
                    'overdue_purchase_order_count' => (int) ($poAgg[0]['overdue_po_cnt'] ?? 0),
                    'purchase_requests_with_supplier_lines' => $linkedPrCnt,
                    'currency' => 'SAR',
                ],
                'top_by_spend' => $normalizeMoney($topBySpend),
                'open_operations' => $normalizeMoney($openOps),
                'overdue_operations' => $normalizeMoney($overdue),
                'follow_up' => array_slice($followUp, 0, $limit),
                'notes' => [
                    'empty_lists_mean_no_matching_records',
                    'all_values_from_live_tenant_data',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSupplierProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        $openOnly = !empty($args['open_only']);
        $model = new Supplier();
        $openList = "'" . implode("','", self::OPEN_PO_STATUSES) . "'";

        $suppliers = $model->query(
            'SELECT id, code, name, status FROM rateb_suppliers WHERE company_id = :cid'
            . ($supplierId > 0 ? ' AND id = :sid' : '')
            . ' ORDER BY id DESC LIMIT ' . max($limit * 3, 50),
            $supplierId > 0 ? ['cid' => $companyId, 'sid' => $supplierId] : ['cid' => $companyId]
        );

        $bySupplier = [];
        foreach ($suppliers as $s) {
            $sid = (int) ($s['id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            $bySupplier[$sid] = [
                'supplier_id' => $sid,
                'code' => (string) ($s['code'] ?? ''),
                'name' => (string) ($s['name'] ?? ''),
                'status' => (string) ($s['status'] ?? ''),
                'purchase_orders' => [],
                'purchase_requests' => [],
                'approvals' => [],
                'totals' => [
                    'po_count' => 0,
                    'po_amount' => 0.0,
                    'open_po_count' => 0,
                ],
                'follow_up' => [],
            ];
        }

        if ($bySupplier === []) {
            return [
                'success' => true,
                'data' => [
                    'as_of' => date('Y-m-d'),
                    'data_source' => 'live_tenant',
                    'links' => [],
                    'count' => 0,
                    'notes' => ['empty_links_mean_no_matching_relations'],
                ],
                'error' => null,
            ];
        }

        $ids = array_keys($bySupplier);
        $in = implode(',', array_map('intval', $ids));

        $poSql = "SELECT po.id, po.order_no, po.supplier_id, po.purchase_request_id, po.status,
                         po.total_amount, po.expected_date, po.order_date
                  FROM rateb_purchase_orders po
                  WHERE po.company_id = :cid AND po.supplier_id IN ({$in})";
        if ($openOnly) {
            $poSql .= " AND po.status IN ({$openList})";
        }
        $poSql .= ' ORDER BY po.id DESC LIMIT ' . max($limit * 10, 100);
        $pos = $model->query($poSql, ['cid' => $companyId]);

        $prIds = [];
        foreach ($pos as $po) {
            $sid = (int) ($po['supplier_id'] ?? 0);
            if (!isset($bySupplier[$sid])) {
                continue;
            }
            $poId = (int) ($po['id'] ?? 0);
            $amount = (float) ($po['total_amount'] ?? 0);
            $status = (string) ($po['status'] ?? '');
            $bySupplier[$sid]['purchase_orders'][] = [
                'id' => $poId,
                'order_no' => (string) ($po['order_no'] ?? ''),
                'status' => $status,
                'total_amount' => $amount,
                'expected_date' => (string) ($po['expected_date'] ?? ''),
                'purchase_request_id' => (int) ($po['purchase_request_id'] ?? 0),
            ];
            $bySupplier[$sid]['totals']['po_count']++;
            $bySupplier[$sid]['totals']['po_amount'] += $amount;
            if (in_array($status, self::OPEN_PO_STATUSES, true)) {
                $bySupplier[$sid]['totals']['open_po_count']++;
            }
            $prId = (int) ($po['purchase_request_id'] ?? 0);
            if ($prId > 0) {
                $prIds[$prId] = $sid;
            }

            try {
                $approval = (new WorkflowSubmissionService())->instanceForEntity('purchase_order', $poId, $companyId);
                if (is_array($approval) && $approval !== []) {
                    $bySupplier[$sid]['approvals'][] = [
                        'entity_type' => 'purchase_order',
                        'entity_id' => $poId,
                        'status' => (string) ($approval['status'] ?? ''),
                        'current_step' => (string) ($approval['current_step'] ?? ''),
                    ];
                }
            } catch (\Throwable $e) {
                // approvals optional when workflow tables differ
            }
        }

        try {
            $prLineSql = "SELECT DISTINCT pri.supplier_id, pr.id, pr.request_no, pr.title, pr.status, pr.total_estimated
                          FROM rateb_purchase_request_items pri
                          INNER JOIN rateb_purchase_requests pr
                                  ON pr.id = pri.purchase_request_id AND pr.company_id = :cid
                          WHERE pri.supplier_id IN ({$in})
                          ORDER BY pr.id DESC
                          LIMIT " . max($limit * 10, 100);
            $prLines = $model->query($prLineSql, ['cid' => $companyId]);
            foreach ($prLines as $pr) {
                $sid = (int) ($pr['supplier_id'] ?? 0);
                if (!isset($bySupplier[$sid])) {
                    continue;
                }
                $prId = (int) ($pr['id'] ?? 0);
                $exists = false;
                foreach ($bySupplier[$sid]['purchase_requests'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $prId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $bySupplier[$sid]['purchase_requests'][] = [
                        'id' => $prId,
                        'request_no' => (string) ($pr['request_no'] ?? ''),
                        'title' => (string) ($pr['title'] ?? ''),
                        'status' => (string) ($pr['status'] ?? ''),
                        'total_estimated' => (float) ($pr['total_estimated'] ?? 0),
                        'link_via' => 'purchase_request_items.supplier_id',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // PR line supplier_id may be absent on older schemas
        }

        if ($prIds !== []) {
            $prIn = implode(',', array_map('intval', array_keys($prIds)));
            $prs = $model->query(
                "SELECT id, request_no, title, status, total_estimated
                 FROM rateb_purchase_requests
                 WHERE company_id = :cid AND id IN ({$prIn})",
                ['cid' => $companyId]
            );
            foreach ($prs as $pr) {
                $prId = (int) ($pr['id'] ?? 0);
                $sid = (int) ($prIds[$prId] ?? 0);
                if ($sid < 1 || !isset($bySupplier[$sid])) {
                    continue;
                }
                $exists = false;
                foreach ($bySupplier[$sid]['purchase_requests'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $prId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $bySupplier[$sid]['purchase_requests'][] = [
                        'id' => $prId,
                        'request_no' => (string) ($pr['request_no'] ?? ''),
                        'title' => (string) ($pr['title'] ?? ''),
                        'status' => (string) ($pr['status'] ?? ''),
                        'total_estimated' => (float) ($pr['total_estimated'] ?? 0),
                        'link_via' => 'purchase_orders.purchase_request_id',
                    ];
                }
                try {
                    $approval = (new WorkflowSubmissionService())->instanceForEntity('purchase_request', $prId, $companyId);
                    if (is_array($approval) && $approval !== []) {
                        $bySupplier[$sid]['approvals'][] = [
                            'entity_type' => 'purchase_request',
                            'entity_id' => $prId,
                            'status' => (string) ($approval['status'] ?? ''),
                            'current_step' => (string) ($approval['current_step'] ?? ''),
                        ];
                    }
                } catch (\Throwable $e) {
                    // optional
                }
            }
        }

        $today = date('Y-m-d');
        foreach ($bySupplier as &$entry) {
            foreach ($entry['purchase_orders'] as $po) {
                $st = (string) ($po['status'] ?? '');
                $exp = (string) ($po['expected_date'] ?? '');
                if (in_array($st, self::OPEN_PO_STATUSES, true) && $exp !== '' && $exp < $today) {
                    $entry['follow_up'][] = [
                        'code' => 'overdue_purchase_order',
                        'purchase_order_id' => (int) ($po['id'] ?? 0),
                        'message' => 'open_po_past_expected_date',
                    ];
                }
            }
            if ($entry['totals']['open_po_count'] > 0) {
                $entry['follow_up'][] = [
                    'code' => 'open_operations',
                    'message' => 'supplier_has_open_purchase_orders',
                    'open_po_count' => $entry['totals']['open_po_count'],
                ];
            }
        }
        unset($entry);

        $list = array_values($bySupplier);
        if ($openOnly) {
            $list = array_values(array_filter($list, static fn($e) => (int) ($e['totals']['open_po_count'] ?? 0) > 0
                || $e['purchase_orders'] !== []));
        } elseif ($supplierId < 1) {
            $list = array_values(array_filter($list, static fn($e) =>
                $e['purchase_orders'] !== [] || $e['purchase_requests'] !== []
            ));
        }
        $list = array_slice($list, 0, $limit);

        return [
            'success' => true,
            'data' => [
                'as_of' => $today,
                'data_source' => 'live_tenant',
                'links' => $list,
                'count' => count($list),
                'notes' => [
                    'links_use_po_supplier_id_and_pr_line_supplier_id',
                    'approvals_from_existing_workflow_service_when_available',
                    'empty_links_mean_no_matching_relations',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSupplierInventoryLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        $model = new Supplier();

        try {
            $sql = "SELECT s.id AS supplier_id, s.code AS supplier_code, s.name AS supplier_name,
                           i.id AS inventory_id, i.item_code, i.item_name, i.quantity, i.unit, i.warehouse_id,
                           w.name AS warehouse_name,
                           po.id AS purchase_order_id, po.order_no, po.status AS po_status,
                           poi.quantity AS po_line_qty, poi.unit_price, poi.total_price
                    FROM rateb_suppliers s
                    INNER JOIN rateb_purchase_orders po
                            ON po.supplier_id = s.id AND po.company_id = s.company_id
                    INNER JOIN rateb_purchase_items poi
                            ON poi.purchase_order_id = po.id AND poi.company_id = po.company_id
                    INNER JOIN rateb_inventory i
                            ON i.id = poi.inventory_id AND i.company_id = po.company_id
                    LEFT JOIN rateb_warehouses w
                           ON w.id = i.warehouse_id AND w.company_id = i.company_id
                    WHERE s.company_id = :cid AND poi.inventory_id IS NOT NULL AND poi.inventory_id > 0";
            $params = ['cid' => $companyId];
            if ($supplierId > 0) {
                $sql .= ' AND s.id = :sid';
                $params['sid'] = $supplierId;
            }
            $sql .= ' ORDER BY s.id DESC, i.id DESC LIMIT ' . max($limit * 10, 100);
            $rows = $model->query($sql, $params);
        } catch (\Throwable $e) {
            // Fallback: PR line inventory + supplier when PO inventory join unavailable
            try {
                $sql = "SELECT s.id AS supplier_id, s.code AS supplier_code, s.name AS supplier_name,
                               i.id AS inventory_id, i.item_code, i.item_name, i.quantity, i.unit, i.warehouse_id,
                               w.name AS warehouse_name,
                               NULL AS purchase_order_id, NULL AS order_no, NULL AS po_status,
                               pri.quantity AS po_line_qty, NULL AS unit_price, NULL AS total_price
                        FROM rateb_suppliers s
                        INNER JOIN rateb_purchase_request_items pri ON pri.supplier_id = s.id
                        INNER JOIN rateb_purchase_requests pr
                                ON pr.id = pri.purchase_request_id AND pr.company_id = s.company_id
                        INNER JOIN rateb_inventory i
                                ON i.id = pri.inventory_id AND i.company_id = s.company_id
                        LEFT JOIN rateb_warehouses w
                               ON w.id = i.warehouse_id AND w.company_id = i.company_id
                        WHERE s.company_id = :cid AND pri.inventory_id IS NOT NULL AND pri.inventory_id > 0";
                $params = ['cid' => $companyId];
                if ($supplierId > 0) {
                    $sql .= ' AND s.id = :sid';
                    $params['sid'] = $supplierId;
                }
                $sql .= ' ORDER BY s.id DESC, i.id DESC LIMIT ' . max($limit * 10, 100);
                $rows = $model->query($sql, $params);
            } catch (\Throwable $e2) {
                $rows = [];
            }
        }

        $bySupplier = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['supplier_id'] ?? 0);
            $iid = (int) ($row['inventory_id'] ?? 0);
            if ($sid < 1 || $iid < 1) {
                continue;
            }
            if (!isset($bySupplier[$sid])) {
                $bySupplier[$sid] = [
                    'supplier_id' => $sid,
                    'code' => (string) ($row['supplier_code'] ?? ''),
                    'name' => (string) ($row['supplier_name'] ?? ''),
                    'items' => [],
                    'purchase_orders' => [],
                ];
            }
            $existsItem = false;
            foreach ($bySupplier[$sid]['items'] as $existing) {
                if ((int) ($existing['inventory_id'] ?? 0) === $iid) {
                    $existsItem = true;
                    break;
                }
            }
            if (!$existsItem) {
                $bySupplier[$sid]['items'][] = [
                    'inventory_id' => $iid,
                    'item_code' => (string) ($row['item_code'] ?? ''),
                    'item_name' => (string) ($row['item_name'] ?? ''),
                    'quantity' => (float) ($row['quantity'] ?? 0),
                    'unit' => (string) ($row['unit'] ?? ''),
                    'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                    'warehouse_name' => (string) ($row['warehouse_name'] ?? ''),
                    'po_line_qty' => isset($row['po_line_qty']) ? (float) $row['po_line_qty'] : null,
                ];
            }
            $poId = (int) ($row['purchase_order_id'] ?? 0);
            if ($poId > 0) {
                $existsPo = false;
                foreach ($bySupplier[$sid]['purchase_orders'] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $poId) {
                        $existsPo = true;
                        break;
                    }
                }
                if (!$existsPo) {
                    $bySupplier[$sid]['purchase_orders'][] = [
                        'id' => $poId,
                        'order_no' => (string) ($row['order_no'] ?? ''),
                        'status' => (string) ($row['po_status'] ?? ''),
                    ];
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
                    'links_require_inventory_id_on_po_or_pr_lines',
                    'empty_links_mean_no_matching_relations',
                    'no_invented_supplier_item_relations',
                ],
            ],
            'error' => null,
        ];
    }

    private static function analyzeSupplierCrossDomain(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);

        $baseArgs = ['limit' => $limit];
        if ($supplierId > 0) {
            $baseArgs['supplier_id'] = $supplierId;
        }
        $analysis = self::analyzeSuppliers($baseArgs, $companyId);
        $proc = self::getSupplierProcurementLinks($baseArgs + ['open_only' => false], $companyId);
        $inv = self::getSupplierInventoryLinks($baseArgs, $companyId);

        if (empty($analysis['success']) || empty($proc['success']) || empty($inv['success'])) {
            return self::fail('tool_exception');
        }

        $followUp = is_array($analysis['data']['follow_up'] ?? null) ? $analysis['data']['follow_up'] : [];
        foreach (($inv['data']['links'] ?? []) as $link) {
            if (!empty($link['items']) && empty($link['purchase_orders'])) {
                $followUp[] = [
                    'code' => 'supplier_items_without_open_po_context',
                    'urgency' => 'low',
                    'supplier_id' => (int) ($link['supplier_id'] ?? 0),
                    'message' => 'review_inventory_links',
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'domains' => ['suppliers', 'procurement', 'inventory'],
                'supplier_summary' => $analysis['data']['summary'] ?? [],
                'procurement_links' => $proc['data']['links'] ?? [],
                'inventory_links' => $inv['data']['links'] ?? [],
                'open_operations' => $analysis['data']['open_operations'] ?? [],
                'overdue_operations' => $analysis['data']['overdue_operations'] ?? [],
                'follow_up' => array_slice($followUp, 0, $limit),
                'notes' => [
                    'cross_domain_uses_existing_relations_only',
                    'no_separate_agent_workflow',
                ],
            ],
            'error' => null,
        ];
    }
}
