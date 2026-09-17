<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\Supplier;
use Rateb\App\Services\WorkflowSubmissionService;
use Rateb\App\Services\WorkflowService;
use Rateb\App\Core\Database;
use Rateb\App\Services\AuditService;

/**
 * Procurement Tool Executor
 * Delegates tool calls to existing Models/Services. Always tenant-scopes by ctx company_id.
 */
final class ProcurementToolExecutor
{
    private const PR_OPEN_STATUSES = ['draft', 'submitted', 'rejected'];
    private const PR_CANCELABLE = ['draft', 'submitted', 'rejected'];
    private const PR_TERMINAL = ['approved', 'cancelled'];

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
                case 'list_purchase_requests':
                    return self::listPurchaseRequests($arguments, $companyId);
                case 'get_purchase_request':
                    return self::getPurchaseRequest($arguments, $companyId);
                case 'list_purchase_orders':
                    return self::listPurchaseOrders($arguments, $companyId);
                case 'get_purchase_order':
                    return self::getPurchaseOrder($arguments, $companyId);
                case 'search_suppliers':
                    return self::searchSuppliers($arguments, $companyId);
                case 'list_pending_approvals':
                    return self::listPendingApprovals($arguments, $companyId);
                case 'get_approval_detail':
                    return self::getApprovalDetail($arguments, $companyId);
                case 'summarize_procurement':
                    return self::summarizeProcurement($arguments, $companyId);
                case 'analyze_procurement_intelligence':
                    return self::analyzeProcurementIntelligence($arguments, $companyId);
                case 'get_purchase_request_cycle':
                    return self::getPurchaseRequestCycle($arguments, $companyId);
                case 'analyze_advanced_procurement_operations':
                    return self::analyzeAdvancedProcurementOperations($arguments, $companyId);
                case 'get_procurement_operational_guidance':
                    return self::getProcurementOperationalGuidance($arguments, $companyId);
                case 'create_draft_purchase_request':
                    return self::createDraftPurchaseRequest($arguments, $companyId, $ctx->userId);
                case 'update_purchase_request':
                    return self::updatePurchaseRequest($arguments, $companyId, $ctx->userId);
                case 'cancel_purchase_request':
                    return self::cancelPurchaseRequest($arguments, $companyId, $ctx->userId);
                case 'submit_purchase_request':
                    return self::submitPurchaseRequest($arguments, $companyId, $ctx->userId);
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
        $message = self::errorMessage($code);
        return [
            'success' => false,
            'data' => null,
            'error' => $code,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }

    private static function errorMessage(string $code): string
    {
        $key = 'ai_tool_err_' . $code;
        $translated = __($key);
        if (is_string($translated) && $translated !== '' && $translated !== $key) {
            return $translated;
        }
        return $code;
    }

    private static function listPurchaseRequests(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $status = trim((string) ($args['status'] ?? ''));
        $overdue = !empty($args['overdue']);
        $pendingApproval = !empty($args['pending_approval']);
        $dateFrom = trim((string) ($args['date_from'] ?? ''));
        $dateTo = trim((string) ($args['date_to'] ?? ''));

        $sql = 'SELECT id, request_no, title, department, priority, status, expected_date,
                       currency, total_estimated, notes, created_at, updated_at
                FROM rateb_purchase_requests
                WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        if ($pendingApproval) {
            $sql .= " AND status = 'submitted'";
        }
        if ($overdue) {
            $sql .= " AND expected_date IS NOT NULL AND expected_date <> ''
                      AND expected_date < :today
                      AND status NOT IN ('approved','cancelled')";
            $params['today'] = date('Y-m-d');
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND expected_date >= :df';
            $params['df'] = $dateFrom;
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND expected_date <= :dt';
            $params['dt'] = $dateTo;
        }
        if ($search !== '') {
            $sql .= ' AND (request_no LIKE :q OR title LIKE :q OR department LIKE :q OR notes LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = (new PurchaseRequest())->query($sql, $params);

        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            $exp = (string) ($row['expected_date'] ?? '');
            $st = (string) ($row['status'] ?? '');
            $row['is_overdue'] = $exp !== '' && $exp < $today && !in_array($st, self::PR_TERMINAL, true);
            $row['total_estimated'] = (float) ($row['total_estimated'] ?? 0);
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getPurchaseRequest(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_pr_id');
        }

        $rows = (new PurchaseRequest())->query(
            'SELECT * FROM rateb_purchase_requests WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $pr = $rows[0] ?? null;
        if (!$pr) {
            return self::fail('pr_not_found');
        }

        $pr['line_items'] = \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id);
        $pr['total_estimated'] = (float) ($pr['total_estimated'] ?? 0);
        $approval = (new WorkflowSubmissionService())->instanceForEntity('purchase_request', $id, $companyId);
        $pr['approval'] = $approval ? [
            'status' => (string) ($approval['status'] ?? ''),
            'current_step' => (string) ($approval['current_step'] ?? ''),
        ] : null;

        return ['success' => true, 'data' => $pr, 'error' => null];
    }

    private static function listPurchaseOrders(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $status = trim((string) ($args['status'] ?? ''));
        $overdue = !empty($args['overdue']);
        $pendingApproval = !empty($args['pending_approval']);
        $dateFrom = trim((string) ($args['date_from'] ?? ''));
        $dateTo = trim((string) ($args['date_to'] ?? ''));
        $supplierId = (int) ($args['supplier_id'] ?? 0);

        $sql = 'SELECT po.id, po.order_no, po.purchase_request_id, po.supplier_id, po.status,
                       po.order_date, po.expected_date, po.currency, po.subtotal, po.tax_amount,
                       po.total_amount, po.notes, po.created_at, po.updated_at,
                       s.name AS supplier_name
                FROM rateb_purchase_orders po
                LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
                WHERE po.company_id = :cid';
        $params = ['cid' => $companyId];

        if ($status !== '') {
            $sql .= ' AND po.status = :st';
            $params['st'] = $status;
        }
        if ($pendingApproval) {
            $sql .= " AND po.status = 'draft'";
        }
        if ($overdue) {
            $sql .= " AND po.expected_date IS NOT NULL AND po.expected_date <> ''
                      AND po.expected_date < :today
                      AND po.status NOT IN ('received','cancelled','confirmed')";
            $params['today'] = date('Y-m-d');
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND po.order_date >= :df';
            $params['df'] = $dateFrom;
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND po.order_date <= :dt';
            $params['dt'] = $dateTo;
        }
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :sid';
            $params['sid'] = $supplierId;
        }
        if ($search !== '') {
            $sql .= ' AND (po.order_no LIKE :q OR po.notes LIKE :q OR s.name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY po.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = (new PurchaseOrder())->query($sql, $params);

        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            $exp = (string) ($row['expected_date'] ?? '');
            $st = (string) ($row['status'] ?? '');
            $row['is_overdue'] = $exp !== '' && $exp < $today && !in_array($st, ['received', 'cancelled', 'confirmed'], true);
            $row['total_amount'] = (float) ($row['total_amount'] ?? 0);
            $row['subtotal'] = (float) ($row['subtotal'] ?? 0);
            $row['tax_amount'] = (float) ($row['tax_amount'] ?? 0);
        }
        unset($row);

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getPurchaseOrder(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_po_id');
        }

        $rows = (new PurchaseOrder())->query(
            'SELECT po.*, s.name AS supplier_name
             FROM rateb_purchase_orders po
             LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
             WHERE po.id = :id AND po.company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $po = $rows[0] ?? null;
        if (!$po) {
            return self::fail('po_not_found');
        }

        $po['total_amount'] = (float) ($po['total_amount'] ?? 0);
        $approval = (new WorkflowSubmissionService())->instanceForEntity('purchase_order', $id, $companyId);
        $po['approval'] = $approval ? [
            'status' => (string) ($approval['status'] ?? ''),
            'current_step' => (string) ($approval['current_step'] ?? ''),
        ] : null;

        return ['success' => true, 'data' => $po, 'error' => null];
    }

    private static function searchSuppliers(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $status = (string) ($args['status'] ?? '');

        $sql = 'SELECT id, code, name, status, phone, email, address, notes
                FROM rateb_suppliers WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }

        $terms = self::expandSupplierSearchTerms($search);
        $byId = [];
        $model = new Supplier();
        foreach ($terms as $term) {
            $qSql = $sql;
            $qParams = $params;
            if ($term !== '') {
                $qSql .= ' AND (name LIKE :q OR code LIKE :q OR address LIKE :q OR notes LIKE :q)';
                $qParams['q'] = '%' . $term . '%';
            }
            $qSql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            foreach ($model->query($qSql, $qParams) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $byId[$id] = $row;
                }
            }
            if (count($byId) >= $limit) {
                break;
            }
        }

        return ['success' => true, 'data' => array_values($byId), 'error' => null];
    }

    /**
     * @return list<string>
     */
    private static function expandSupplierSearchTerms(string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return [''];
        }

        $aliases = [
            'الرياض' => ['الرياض', 'رياض', 'Riyadh', 'riyadh'],
            'رياض' => ['الرياض', 'رياض', 'Riyadh', 'riyadh'],
            'riyadh' => ['الرياض', 'رياض', 'Riyadh', 'riyadh'],
            'جدة' => ['جدة', 'جده', 'Jeddah', 'jeddah'],
            'jeddah' => ['جدة', 'جده', 'Jeddah', 'jeddah'],
            'الدمام' => ['الدمام', 'Dammam', 'dammam'],
            'dammam' => ['الدمام', 'Dammam', 'dammam'],
        ];

        $out = [$term];
        $lower = mb_strtolower($term, 'UTF-8');
        foreach ($aliases as $needle => $alts) {
            $n = mb_strtolower((string) $needle, 'UTF-8');
            if ($n !== '' && (mb_strpos($lower, $n) !== false || mb_strpos($term, (string) $needle) !== false)) {
                foreach ($alts as $alt) {
                    $out[] = $alt;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $out), static fn(string $v): bool => $v !== '')));
    }

    private static function listPendingApprovals(array $args, int $companyId): array
    {
        $limit = max(1, min(200, (int) ($args['limit'] ?? 50)));

        $workflowService = new WorkflowService();
        $allPending = $workflowService->listPending($companyId, $limit);

        $filtered = array_filter($allPending, function ($item) {
            $entityType = (string) ($item['entity_type'] ?? '');
            return $entityType === 'purchase_request' || $entityType === 'purchase_order';
        });

        return ['success' => true, 'data' => array_values($filtered), 'error' => null];
    }

    private static function getApprovalDetail(array $args, int $companyId): array
    {
        $instanceId = (int) ($args['instance_id'] ?? 0);
        if ($instanceId < 1) {
            return self::fail('invalid_approval_id');
        }

        $workflowService = new WorkflowService();
        $history = $workflowService->history($instanceId);

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT entity_type, entity_id, status, current_step FROM rateb_approval_instances
             WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $instanceId, 'cid' => $companyId]);
        $instance = $stmt->fetch();

        if (!$instance) {
            return self::fail('approval_not_found');
        }

        $entityType = (string) ($instance['entity_type'] ?? '');
        if ($entityType !== 'purchase_request' && $entityType !== 'purchase_order') {
            return self::fail('approval_not_found');
        }

        return [
            'success' => true,
            'data' => [
                'instance_id' => $instanceId,
                'entity_type' => $entityType,
                'entity_id' => (int) ($instance['entity_id'] ?? 0),
                'status' => (string) ($instance['status'] ?? ''),
                'current_step' => (string) ($instance['current_step'] ?? ''),
                'history' => $history,
            ],
            'error' => null,
        ];
    }

    private static function summarizeProcurement(array $args, int $companyId): array
    {
        $includeSuppliers = !array_key_exists('include_suppliers', $args) || !empty($args['include_suppliers']);
        $supplierLimit = max(1, min(50, (int) ($args['supplier_limit'] ?? 20)));
        $today = date('Y-m-d');
        $pr = new PurchaseRequest();
        $po = new PurchaseOrder();

        $prByStatus = $pr->query(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests WHERE company_id = :cid
             GROUP BY status',
            ['cid' => $companyId]
        );
        $poByStatus = $po->query(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount
             FROM rateb_purchase_orders WHERE company_id = :cid
             GROUP BY status',
            ['cid' => $companyId]
        );

        $prTotals = $pr->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $poTotals = $po->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount
             FROM rateb_purchase_orders WHERE company_id = :cid',
            ['cid' => $companyId]
        );

        $prOverdue = $pr->query(
            "SELECT COUNT(*) AS cnt FROM rateb_purchase_requests
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('approved','cancelled')",
            ['cid' => $companyId, 'today' => $today]
        );
        $poOverdue = $po->query(
            "SELECT COUNT(*) AS cnt FROM rateb_purchase_orders
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('received','cancelled','confirmed')",
            ['cid' => $companyId, 'today' => $today]
        );

        $prPending = $pr->query(
            "SELECT COUNT(*) AS cnt FROM rateb_purchase_requests
             WHERE company_id = :cid AND status = 'submitted'",
            ['cid' => $companyId]
        );

        $pendingApprovals = 0;
        try {
            $pendingApprovals = count((new WorkflowService())->listPending($companyId, 200));
            // recount procurement-only
            $all = (new WorkflowService())->listPending($companyId, 200);
            $pendingApprovals = count(array_filter($all, static function ($item): bool {
                $et = (string) ($item['entity_type'] ?? '');
                return $et === 'purchase_request' || $et === 'purchase_order';
            }));
        } catch (\Throwable $e) {
            $pendingApprovals = 0;
        }

        $summary = [
            'as_of' => $today,
            'purchase_requests' => [
                'count' => (int) ($prTotals[0]['cnt'] ?? 0),
                'total_estimated' => (float) ($prTotals[0]['amount'] ?? 0),
                'currency' => 'SAR',
                'by_status' => array_map(static function ($r) {
                    return [
                        'status' => (string) ($r['status'] ?? ''),
                        'count' => (int) ($r['cnt'] ?? 0),
                        'amount' => (float) ($r['amount'] ?? 0),
                    ];
                }, $prByStatus),
                'overdue_count' => (int) ($prOverdue[0]['cnt'] ?? 0),
                'pending_approval_status_count' => (int) ($prPending[0]['cnt'] ?? 0),
            ],
            'purchase_orders' => [
                'count' => (int) ($poTotals[0]['cnt'] ?? 0),
                'total_amount' => (float) ($poTotals[0]['amount'] ?? 0),
                'currency' => 'SAR',
                'by_status' => array_map(static function ($r) {
                    return [
                        'status' => (string) ($r['status'] ?? ''),
                        'count' => (int) ($r['cnt'] ?? 0),
                        'amount' => (float) ($r['amount'] ?? 0),
                    ];
                }, $poByStatus),
                'overdue_count' => (int) ($poOverdue[0]['cnt'] ?? 0),
            ],
            'approvals_pending_count' => $pendingApprovals,
        ];

        if ($includeSuppliers) {
            $linked = $po->query(
                'SELECT s.id, s.name, COUNT(po.id) AS order_count, COALESCE(SUM(po.total_amount),0) AS order_amount
                 FROM rateb_purchase_orders po
                 INNER JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
                 WHERE po.company_id = :cid AND po.supplier_id IS NOT NULL AND po.supplier_id > 0
                 GROUP BY s.id, s.name
                 ORDER BY order_amount DESC, order_count DESC
                 LIMIT ' . $supplierLimit,
                ['cid' => $companyId]
            );
            $summary['suppliers_linked_to_orders'] = array_map(static function ($r) {
                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'name' => (string) ($r['name'] ?? ''),
                    'order_count' => (int) ($r['order_count'] ?? 0),
                    'order_amount' => (float) ($r['order_amount'] ?? 0),
                ];
            }, $linked);
        }

        return ['success' => true, 'data' => $summary, 'error' => null];
    }

    /**
     * Phase 3: pending / overdue / abnormal + cycle links from live tenant data only.
     */
    private static function analyzeProcurementIntelligence(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $includeLinks = !array_key_exists('include_links', $args) || !empty($args['include_links']);
        $today = date('Y-m-d');
        $pr = new PurchaseRequest();
        $po = new PurchaseOrder();

        $pendingPr = $pr->query(
            "SELECT id, request_no, title, status, expected_date, currency, total_estimated, created_at
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND status = 'submitted'
             ORDER BY id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $overduePr = $pr->query(
            "SELECT id, request_no, title, status, expected_date, currency, total_estimated
             FROM rateb_purchase_requests
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('approved','cancelled')
             ORDER BY expected_date ASC LIMIT {$limit}",
            ['cid' => $companyId, 'today' => $today]
        );
        $overduePo = $po->query(
            "SELECT id, order_no, purchase_request_id, status, expected_date, currency, total_amount, supplier_id
             FROM rateb_purchase_orders
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('received','cancelled','confirmed')
             ORDER BY expected_date ASC LIMIT {$limit}",
            ['cid' => $companyId, 'today' => $today]
        );

        $approvedNoPo = $pr->query(
            "SELECT pr.id, pr.request_no, pr.title, pr.status, pr.currency, pr.total_estimated, pr.expected_date
             FROM rateb_purchase_requests pr
             WHERE pr.company_id = :cid
               AND pr.status = 'approved'
               AND NOT EXISTS (
                   SELECT 1 FROM rateb_purchase_orders po
                   WHERE po.company_id = pr.company_id
                     AND po.purchase_request_id = pr.id
               )
             ORDER BY pr.id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $poWithoutPr = $po->query(
            "SELECT id, order_no, status, currency, total_amount, expected_date, supplier_id
             FROM rateb_purchase_orders
             WHERE company_id = :cid
               AND (purchase_request_id IS NULL OR purchase_request_id = 0)
               AND status NOT IN ('cancelled')
             ORDER BY id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $zeroAmountOpen = $pr->query(
            "SELECT id, request_no, title, status, currency, total_estimated, expected_date
             FROM rateb_purchase_requests
             WHERE company_id = :cid
               AND status IN ('submitted','approved')
               AND COALESCE(total_estimated, 0) <= 0
             ORDER BY id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $pendingApprovals = [];
        try {
            $all = (new WorkflowService())->listPending($companyId, $limit);
            foreach ($all as $item) {
                $et = (string) ($item['entity_type'] ?? '');
                if ($et === 'purchase_request' || $et === 'purchase_order') {
                    $pendingApprovals[] = $item;
                }
            }
        } catch (\Throwable $e) {
            $pendingApprovals = [];
        }

        $prCounts = $pr->query(
            'SELECT status, COUNT(*) AS cnt FROM rateb_purchase_requests WHERE company_id = :cid GROUP BY status',
            ['cid' => $companyId]
        );
        $poCounts = $po->query(
            'SELECT status, COUNT(*) AS cnt FROM rateb_purchase_orders WHERE company_id = :cid GROUP BY status',
            ['cid' => $companyId]
        );
        $byStatus = static function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                $out[(string) ($r['status'] ?? '')] = (int) ($r['cnt'] ?? 0);
            }
            return $out;
        };

        $normalizeMoneyRows = static function (array $rows, string $amountKey): array {
            $out = [];
            foreach ($rows as $row) {
                $row[$amountKey] = (float) ($row[$amountKey] ?? 0);
                $out[] = $row;
            }
            return $out;
        };

        $data = [
            'as_of' => $today,
            'data_source' => 'live_tenant',
            'cycle_summary' => [
                'purchase_requests_by_status' => $byStatus($prCounts),
                'purchase_orders_by_status' => $byStatus($poCounts),
                'pending_purchase_requests' => count($pendingPr),
                'pending_approvals' => count($pendingApprovals),
                'overdue_purchase_requests' => count($overduePr),
                'overdue_purchase_orders' => count($overduePo),
                'abnormal_approved_without_po' => count($approvedNoPo),
                'abnormal_po_without_pr' => count($poWithoutPr),
                'abnormal_zero_amount_open' => count($zeroAmountOpen),
            ],
            'pending' => [
                'purchase_requests' => $normalizeMoneyRows($pendingPr, 'total_estimated'),
                'approvals' => $pendingApprovals,
            ],
            'overdue' => [
                'purchase_requests' => $normalizeMoneyRows($overduePr, 'total_estimated'),
                'purchase_orders' => $normalizeMoneyRows($overduePo, 'total_amount'),
            ],
            'abnormal' => [
                'approved_pr_without_po' => $normalizeMoneyRows($approvedNoPo, 'total_estimated'),
                'po_without_purchase_request' => $normalizeMoneyRows($poWithoutPr, 'total_amount'),
                'open_pr_zero_amount' => $normalizeMoneyRows($zeroAmountOpen, 'total_estimated'),
            ],
            'notes' => [
                'empty_buckets_mean_no_matching_records',
                'all_counts_and_rows_are_tenant_scoped',
            ],
        ];

        if ($includeLinks) {
            $linkRows = $pr->query(
                "SELECT pr.id AS purchase_request_id, pr.request_no, pr.title, pr.status AS pr_status,
                        pr.currency, pr.total_estimated,
                        ai.id AS approval_instance_id, ai.status AS approval_status, ai.current_step,
                        po.id AS purchase_order_id, po.order_no, po.status AS po_status, po.total_amount AS po_total_amount
                 FROM rateb_purchase_requests pr
                 LEFT JOIN rateb_approval_instances ai
                        ON ai.company_id = pr.company_id
                       AND ai.entity_type = 'purchase_request'
                       AND ai.entity_id = pr.id
                 LEFT JOIN rateb_purchase_orders po
                        ON po.company_id = pr.company_id
                       AND po.purchase_request_id = pr.id
                 WHERE pr.company_id = :cid
                 ORDER BY pr.id DESC
                 LIMIT {$limit}",
                ['cid' => $companyId]
            );

            $links = [];
            foreach ($linkRows as $row) {
                $prId = (int) ($row['purchase_request_id'] ?? 0);
                if ($prId < 1) {
                    continue;
                }
                if (!isset($links[$prId])) {
                    $links[$prId] = [
                        'purchase_request_id' => $prId,
                        'request_no' => (string) ($row['request_no'] ?? ''),
                        'title' => (string) ($row['title'] ?? ''),
                        'pr_status' => (string) ($row['pr_status'] ?? ''),
                        'currency' => (string) ($row['currency'] ?? 'SAR'),
                        'total_estimated' => (float) ($row['total_estimated'] ?? 0),
                        'approval' => null,
                        'purchase_orders' => [],
                        'linked_po_total_amount' => 0.0,
                    ];
                    $aid = (int) ($row['approval_instance_id'] ?? 0);
                    if ($aid > 0) {
                        $links[$prId]['approval'] = [
                            'instance_id' => $aid,
                            'status' => (string) ($row['approval_status'] ?? ''),
                            'current_step' => (string) ($row['current_step'] ?? ''),
                        ];
                    }
                }
                $poId = (int) ($row['purchase_order_id'] ?? 0);
                if ($poId > 0) {
                    $amount = (float) ($row['po_total_amount'] ?? 0);
                    $exists = false;
                    foreach ($links[$prId]['purchase_orders'] as $existing) {
                        if ((int) ($existing['id'] ?? 0) === $poId) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $links[$prId]['purchase_orders'][] = [
                            'id' => $poId,
                            'order_no' => (string) ($row['order_no'] ?? ''),
                            'status' => (string) ($row['po_status'] ?? ''),
                            'total_amount' => $amount,
                        ];
                        $links[$prId]['linked_po_total_amount'] += $amount;
                    }
                }
            }
            $data['links'] = array_values($links);
        }

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    private static function getPurchaseRequestCycle(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_pr_id');
        }

        $rows = (new PurchaseRequest())->query(
            'SELECT id, request_no, title, department, priority, status, expected_date,
                    currency, total_estimated, notes, created_at, updated_at
             FROM rateb_purchase_requests
             WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $pr = $rows[0] ?? null;
        if (!$pr) {
            return self::fail('pr_not_found');
        }

        $pr['total_estimated'] = (float) ($pr['total_estimated'] ?? 0);
        $approvalPayload = null;
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT id, status, current_step FROM rateb_approval_instances
                 WHERE company_id = :cid AND entity_type = 'purchase_request' AND entity_id = :eid
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['cid' => $companyId, 'eid' => $id]);
            $approval = $stmt->fetch();
            if (is_array($approval) && (int) ($approval['id'] ?? 0) > 0) {
                $approvalPayload = [
                    'instance_id' => (int) $approval['id'],
                    'status' => (string) ($approval['status'] ?? ''),
                    'current_step' => (string) ($approval['current_step'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $approvalPayload = null;
        }

        $poRows = (new PurchaseOrder())->query(
            'SELECT po.id, po.order_no, po.status, po.order_date, po.expected_date, po.currency,
                    po.subtotal, po.tax_amount, po.total_amount, po.supplier_id,
                    s.name AS supplier_name
             FROM rateb_purchase_orders po
             LEFT JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
             WHERE po.company_id = :cid AND po.purchase_request_id = :prid
             ORDER BY po.id DESC',
            ['cid' => $companyId, 'prid' => $id]
        );
        $orders = [];
        $linkedTotal = 0.0;
        foreach ($poRows as $row) {
            $amount = (float) ($row['total_amount'] ?? 0);
            $linkedTotal += $amount;
            $orders[] = [
                'id' => (int) ($row['id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'order_date' => (string) ($row['order_date'] ?? ''),
                'expected_date' => (string) ($row['expected_date'] ?? ''),
                'currency' => (string) ($row['currency'] ?? 'SAR'),
                'subtotal' => (float) ($row['subtotal'] ?? 0),
                'tax_amount' => (float) ($row['tax_amount'] ?? 0),
                'total_amount' => $amount,
                'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                'supplier_name' => (string) ($row['supplier_name'] ?? ''),
            ];
        }

        $today = date('Y-m-d');
        $exp = (string) ($pr['expected_date'] ?? '');
        $st = (string) ($pr['status'] ?? '');
        $isOverdue = $exp !== '' && $exp < $today && !in_array($st, self::PR_TERMINAL, true);

        return [
            'success' => true,
            'data' => [
                'purchase_request' => $pr,
                'approval' => $approvalPayload,
                'purchase_orders' => $orders,
                'amounts' => [
                    'request_total_estimated' => (float) $pr['total_estimated'],
                    'linked_po_total_amount' => $linkedTotal,
                    'currency' => (string) ($pr['currency'] ?? 'SAR'),
                ],
                'flags' => [
                    'is_overdue' => $isOverdue,
                    'has_approval' => $approvalPayload !== null,
                    'has_purchase_orders' => $orders !== [],
                    'approved_without_po' => $st === 'approved' && $orders === [],
                ],
                'data_source' => 'live_tenant',
            ],
            'error' => null,
        ];
    }

    /**
     * Phase 4: advanced operations intelligence (spend / frequency / bottlenecks / priorities / executive summary).
     */
    private static function analyzeAdvancedProcurementOperations(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 15)));
        $lookbackDays = max(7, min(180, (int) ($args['lookback_days'] ?? 30)));
        $today = date('Y-m-d');
        $since = date('Y-m-d', strtotime('-' . $lookbackDays . ' days') ?: time());
        $pr = new PurchaseRequest();
        $po = new PurchaseOrder();

        $prSpend = $pr->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $poSpend = $po->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount
             FROM rateb_purchase_orders WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $prPeriod = $pr->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND DATE(created_at) >= :since',
            ['cid' => $companyId, 'since' => $since]
        );
        $poPeriod = $po->query(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount
             FROM rateb_purchase_orders
             WHERE company_id = :cid AND DATE(COALESCE(order_date, created_at)) >= :since',
            ['cid' => $companyId, 'since' => $since]
        );

        $topSuppliers = $po->query(
            'SELECT s.id, s.name, COUNT(po.id) AS order_count, COALESCE(SUM(po.total_amount),0) AS order_amount
             FROM rateb_purchase_orders po
             INNER JOIN rateb_suppliers s ON s.id = po.supplier_id AND s.company_id = po.company_id
             WHERE po.company_id = :cid AND po.supplier_id IS NOT NULL AND po.supplier_id > 0
             GROUP BY s.id, s.name
             ORDER BY order_amount DESC, order_count DESC
             LIMIT ' . $limit,
            ['cid' => $companyId]
        );

        $repeatTitles = $pr->query(
            'SELECT title, COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND title IS NOT NULL AND title <> \'\'
             GROUP BY title
             HAVING COUNT(*) > 1
             ORDER BY cnt DESC, amount DESC
             LIMIT ' . $limit,
            ['cid' => $companyId]
        );

        $highValuePr = $pr->query(
            "SELECT id, request_no, title, status, currency, total_estimated, expected_date
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND COALESCE(total_estimated,0) > 0
             ORDER BY total_estimated DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $highValuePo = $po->query(
            "SELECT id, order_no, purchase_request_id, status, currency, total_amount, expected_date
             FROM rateb_purchase_orders
             WHERE company_id = :cid AND COALESCE(total_amount,0) > 0
             ORDER BY total_amount DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $overduePr = $pr->query(
            "SELECT id, request_no, title, status, expected_date, currency, total_estimated, priority
             FROM rateb_purchase_requests
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('approved','cancelled')
             ORDER BY expected_date ASC LIMIT {$limit}",
            ['cid' => $companyId, 'today' => $today]
        );
        $overduePo = $po->query(
            "SELECT id, order_no, purchase_request_id, status, expected_date, currency, total_amount
             FROM rateb_purchase_orders
             WHERE company_id = :cid
               AND expected_date IS NOT NULL AND expected_date <> ''
               AND expected_date < :today
               AND status NOT IN ('received','cancelled','confirmed')
             ORDER BY expected_date ASC LIMIT {$limit}",
            ['cid' => $companyId, 'today' => $today]
        );
        $pendingPr = $pr->query(
            "SELECT id, request_no, title, status, expected_date, currency, total_estimated, priority, created_at
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND status = 'submitted'
             ORDER BY
               CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
               id ASC
             LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $draftPr = $pr->query(
            "SELECT id, request_no, title, status, currency, total_estimated, created_at
             FROM rateb_purchase_requests
             WHERE company_id = :cid AND status = 'draft'
             ORDER BY id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );
        $approvedNoPo = $pr->query(
            "SELECT pr.id, pr.request_no, pr.title, pr.status, pr.currency, pr.total_estimated
             FROM rateb_purchase_requests pr
             WHERE pr.company_id = :cid AND pr.status = 'approved'
               AND NOT EXISTS (
                   SELECT 1 FROM rateb_purchase_orders po
                   WHERE po.company_id = pr.company_id AND po.purchase_request_id = pr.id
               )
             ORDER BY pr.total_estimated DESC, pr.id DESC LIMIT {$limit}",
            ['cid' => $companyId]
        );

        $pendingApprovals = [];
        try {
            foreach ((new WorkflowService())->listPending($companyId, $limit) as $item) {
                $et = (string) ($item['entity_type'] ?? '');
                if ($et === 'purchase_request' || $et === 'purchase_order') {
                    $pendingApprovals[] = $item;
                }
            }
        } catch (\Throwable $e) {
            $pendingApprovals = [];
        }

        $prByStatus = $pr->query(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_estimated),0) AS amount
             FROM rateb_purchase_requests WHERE company_id = :cid GROUP BY status',
            ['cid' => $companyId]
        );
        $poByStatus = $po->query(
            'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount
             FROM rateb_purchase_orders WHERE company_id = :cid GROUP BY status',
            ['cid' => $companyId]
        );

        $money = static function (array $rows, string $key): array {
            $out = [];
            foreach ($rows as $row) {
                $row[$key] = (float) ($row[$key] ?? 0);
                $out[] = $row;
            }
            return $out;
        };

        $bottlenecks = [
            [
                'code' => 'pending_approvals',
                'count' => count($pendingApprovals),
                'severity' => count($pendingApprovals) >= 5 ? 'high' : (count($pendingApprovals) > 0 ? 'medium' : 'none'),
            ],
            [
                'code' => 'submitted_purchase_requests',
                'count' => count($pendingPr),
                'severity' => count($pendingPr) >= 5 ? 'high' : (count($pendingPr) > 0 ? 'medium' : 'none'),
            ],
            [
                'code' => 'overdue_purchase_requests',
                'count' => count($overduePr),
                'severity' => count($overduePr) > 0 ? 'high' : 'none',
            ],
            [
                'code' => 'overdue_purchase_orders',
                'count' => count($overduePo),
                'severity' => count($overduePo) > 0 ? 'high' : 'none',
            ],
            [
                'code' => 'approved_without_po',
                'count' => count($approvedNoPo),
                'severity' => count($approvedNoPo) > 0 ? 'medium' : 'none',
            ],
            [
                'code' => 'draft_purchase_requests',
                'count' => count($draftPr),
                'severity' => count($draftPr) >= 10 ? 'medium' : (count($draftPr) > 0 ? 'low' : 'none'),
            ],
        ];

        $priorities = [];
        $pushPriority = static function (
            array &$priorities,
            int $rankScore,
            string $urgency,
            string $code,
            string $entityType,
            int $entityId,
            string $label,
            float $amount
        ): void {
            $priorities[] = [
                'rank_score' => $rankScore,
                'urgency' => $urgency,
                'code' => $code,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'label' => $label,
                'amount' => $amount,
            ];
        };

        foreach ($overduePr as $row) {
            $pushPriority(
                $priorities,
                100,
                'critical',
                'overdue_purchase_request',
                'purchase_request',
                (int) ($row['id'] ?? 0),
                (string) (($row['request_no'] ?? '') . ' ' . ($row['title'] ?? '')),
                (float) ($row['total_estimated'] ?? 0)
            );
        }
        foreach ($overduePo as $row) {
            $pushPriority(
                $priorities,
                95,
                'critical',
                'overdue_purchase_order',
                'purchase_order',
                (int) ($row['id'] ?? 0),
                (string) ($row['order_no'] ?? ''),
                (float) ($row['total_amount'] ?? 0)
            );
        }
        foreach ($pendingApprovals as $row) {
            $pushPriority(
                $priorities,
                85,
                'high',
                'pending_approval',
                (string) ($row['entity_type'] ?? 'purchase_request'),
                (int) ($row['entity_id'] ?? ($row['id'] ?? 0)),
                'approval#' . (string) ($row['id'] ?? $row['instance_id'] ?? ''),
                0.0
            );
        }
        foreach ($pendingPr as $row) {
            $prio = (string) ($row['priority'] ?? 'medium');
            $score = $prio === 'urgent' ? 80 : ($prio === 'high' ? 70 : 60);
            $pushPriority(
                $priorities,
                $score,
                $prio === 'urgent' || $prio === 'high' ? 'high' : 'medium',
                'submitted_purchase_request',
                'purchase_request',
                (int) ($row['id'] ?? 0),
                (string) (($row['request_no'] ?? '') . ' ' . ($row['title'] ?? '')),
                (float) ($row['total_estimated'] ?? 0)
            );
        }
        foreach ($approvedNoPo as $row) {
            $pushPriority(
                $priorities,
                55,
                'medium',
                'approved_without_po',
                'purchase_request',
                (int) ($row['id'] ?? 0),
                (string) (($row['request_no'] ?? '') . ' ' . ($row['title'] ?? '')),
                (float) ($row['total_estimated'] ?? 0)
            );
        }

        usort($priorities, static function (array $a, array $b): int {
            return ($b['rank_score'] <=> $a['rank_score']) ?: ($b['amount'] <=> $a['amount']);
        });
        $priorities = array_slice($priorities, 0, $limit);

        $prAmount = (float) ($prSpend[0]['amount'] ?? 0);
        $poAmount = (float) ($poSpend[0]['amount'] ?? 0);
        $executive = [
            'as_of' => $today,
            'lookback_days' => $lookbackDays,
            'headline_counts' => [
                'purchase_requests' => (int) ($prSpend[0]['cnt'] ?? 0),
                'purchase_orders' => (int) ($poSpend[0]['cnt'] ?? 0),
                'overdue_prs' => count($overduePr),
                'overdue_pos' => count($overduePo),
                'pending_approvals' => count($pendingApprovals),
                'submitted_prs' => count($pendingPr),
                'approved_without_po' => count($approvedNoPo),
                'draft_prs' => count($draftPr),
            ],
            'spend' => [
                'pr_total_estimated' => $prAmount,
                'po_total_amount' => $poAmount,
                'currency' => 'SAR',
            ],
            'actionable_focus' => array_map(static function (array $p): array {
                return [
                    'urgency' => $p['urgency'],
                    'code' => $p['code'],
                    'entity_type' => $p['entity_type'],
                    'entity_id' => $p['entity_id'],
                    'label' => $p['label'],
                    'amount' => $p['amount'],
                ];
            }, array_slice($priorities, 0, 5)),
            'notes' => [
                'all_values_from_live_tenant_data',
                'empty_lists_mean_no_matching_records',
                'does_not_execute_writes',
            ],
        ];

        return [
            'success' => true,
            'data' => [
                'as_of' => $today,
                'data_source' => 'live_tenant',
                'cycle' => [
                    'purchase_requests_by_status' => array_map(static function ($r) {
                        return [
                            'status' => (string) ($r['status'] ?? ''),
                            'count' => (int) ($r['cnt'] ?? 0),
                            'amount' => (float) ($r['amount'] ?? 0),
                        ];
                    }, $prByStatus),
                    'purchase_orders_by_status' => array_map(static function ($r) {
                        return [
                            'status' => (string) ($r['status'] ?? ''),
                            'count' => (int) ($r['cnt'] ?? 0),
                            'amount' => (float) ($r['amount'] ?? 0),
                        ];
                    }, $poByStatus),
                ],
                'spend_analysis' => [
                    'lifetime' => [
                        'pr_count' => (int) ($prSpend[0]['cnt'] ?? 0),
                        'pr_total_estimated' => $prAmount,
                        'po_count' => (int) ($poSpend[0]['cnt'] ?? 0),
                        'po_total_amount' => $poAmount,
                        'currency' => 'SAR',
                    ],
                    'period' => [
                        'lookback_days' => $lookbackDays,
                        'since' => $since,
                        'pr_count' => (int) ($prPeriod[0]['cnt'] ?? 0),
                        'pr_total_estimated' => (float) ($prPeriod[0]['amount'] ?? 0),
                        'po_count' => (int) ($poPeriod[0]['cnt'] ?? 0),
                        'po_total_amount' => (float) ($poPeriod[0]['amount'] ?? 0),
                    ],
                    'top_suppliers' => array_map(static function ($r) {
                        return [
                            'id' => (int) ($r['id'] ?? 0),
                            'name' => (string) ($r['name'] ?? ''),
                            'order_count' => (int) ($r['order_count'] ?? 0),
                            'order_amount' => (float) ($r['order_amount'] ?? 0),
                        ];
                    }, $topSuppliers),
                    'high_value_purchase_requests' => $money($highValuePr, 'total_estimated'),
                    'high_value_purchase_orders' => $money($highValuePo, 'total_amount'),
                ],
                'frequency' => [
                    'repeat_request_titles' => array_map(static function ($r) {
                        return [
                            'title' => (string) ($r['title'] ?? ''),
                            'count' => (int) ($r['cnt'] ?? 0),
                            'amount' => (float) ($r['amount'] ?? 0),
                        ];
                    }, $repeatTitles),
                    'period_pr_count' => (int) ($prPeriod[0]['cnt'] ?? 0),
                    'period_po_count' => (int) ($poPeriod[0]['cnt'] ?? 0),
                ],
                'bottlenecks' => $bottlenecks,
                'abnormal' => [
                    'approved_pr_without_po' => $money($approvedNoPo, 'total_estimated'),
                    'overdue_purchase_requests' => $money($overduePr, 'total_estimated'),
                    'overdue_purchase_orders' => $money($overduePo, 'total_amount'),
                ],
                'operational_priorities' => $priorities,
                'executive_summary' => $executive,
            ],
            'error' => null,
        ];
    }

    /**
     * Phase 4: operational guidance only — never executes WRITE.
     */
    private static function getProcurementOperationalGuidance(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 10)));
        $advanced = self::analyzeAdvancedProcurementOperations([
            'limit' => $limit,
            'lookback_days' => 30,
        ], $companyId);
        if (empty($advanced['success']) || !is_array($advanced['data'] ?? null)) {
            return self::fail('tool_exception');
        }

        $data = $advanced['data'];
        $actions = [];
        foreach (($data['operational_priorities'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $code = (string) ($p['code'] ?? '');
            $entityType = (string) ($p['entity_type'] ?? '');
            $entityId = (int) ($p['entity_id'] ?? 0);
            $suggestedRead = 'get_purchase_request';
            $suggestedWrite = null;
            $action = 'review';

            if ($code === 'overdue_purchase_order') {
                $suggestedRead = 'get_purchase_order';
                $action = 'review_overdue_purchase_order';
            } elseif ($code === 'pending_approval') {
                $suggestedRead = 'get_approval_detail';
                $action = 'review_pending_approval';
            } elseif ($code === 'submitted_purchase_request') {
                $action = 'follow_up_submitted_request';
                $suggestedRead = 'get_purchase_request_cycle';
            } elseif ($code === 'approved_without_po') {
                $action = 'create_or_link_purchase_order_manually';
                $suggestedRead = 'get_purchase_request_cycle';
            } elseif ($code === 'overdue_purchase_request') {
                $action = 'escalate_or_update_overdue_request';
                $suggestedRead = 'get_purchase_request_cycle';
                $suggestedWrite = 'update_purchase_request';
            }

            $actions[] = [
                'urgency' => (string) ($p['urgency'] ?? 'medium'),
                'action' => $action,
                'reason_code' => $code,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'label' => (string) ($p['label'] ?? ''),
                'amount' => (float) ($p['amount'] ?? 0),
                'suggested_read_tool' => $suggestedRead,
                'suggested_write_tool' => $suggestedWrite,
                'requires_confirmation_for_write' => $suggestedWrite !== null,
                'auto_execute_write' => false,
            ];
        }

        // Draft backlog guidance (submit only with confirmation — never auto)
        $drafts = $data['executive_summary']['headline_counts']['draft_prs'] ?? 0;
        if ((int) $drafts > 0 && count($actions) < $limit) {
            $actions[] = [
                'urgency' => (int) $drafts >= 10 ? 'medium' : 'low',
                'action' => 'review_draft_requests_for_submit',
                'reason_code' => 'draft_backlog',
                'entity_type' => 'purchase_request',
                'entity_id' => 0,
                'label' => 'draft_count=' . (int) $drafts,
                'amount' => 0.0,
                'suggested_read_tool' => 'list_purchase_requests',
                'suggested_write_tool' => 'submit_purchase_request',
                'requires_confirmation_for_write' => true,
                'auto_execute_write' => false,
            ];
        }

        if ($actions === []) {
            $actions[] = [
                'urgency' => 'none',
                'action' => 'no_urgent_action',
                'reason_code' => 'healthy_or_empty',
                'entity_type' => '',
                'entity_id' => 0,
                'label' => 'no_matching_operational_issues',
                'amount' => 0.0,
                'suggested_read_tool' => 'analyze_advanced_procurement_operations',
                'suggested_write_tool' => null,
                'requires_confirmation_for_write' => false,
                'auto_execute_write' => false,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => (string) ($data['as_of'] ?? date('Y-m-d')),
                'data_source' => 'live_tenant',
                'question' => 'what_should_i_do_now',
                'executive_focus' => $data['executive_summary']['actionable_focus'] ?? [],
                'recommended_actions' => array_slice($actions, 0, $limit),
                'guards' => [
                    'never_auto_write' => true,
                    'write_requires_confirmation' => true,
                    'tenant_scoped' => true,
                ],
            ],
            'error' => null,
        ];
    }

    private static function createDraftPurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $model = new PurchaseRequest();

        $data = [
            'request_no' => $model->generateRequestNo(),
            'title' => trim((string) ($args['title'] ?? '')),
            'department' => trim((string) ($args['department'] ?? '')),
            'priority' => (string) ($args['priority'] ?? 'medium'),
            'currency' => (string) ($args['currency'] ?? 'SAR'),
            'total_estimated' => (float) ($args['total_estimated'] ?? 0),
            'notes' => trim((string) ($args['notes'] ?? '')),
            'status' => 'draft',
            'company_id' => $companyId,
        ];
        $expectedDate = trim((string) ($args['expected_date'] ?? ''));
        if ($expectedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDate)) {
            $data['expected_date'] = $expectedDate;
        }

        $branchId = function_exists('rateb_resolve_create_branch_id')
            ? (int) rateb_resolve_create_branch_id()
            : 0;
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }

        if ($data['title'] === '') {
            return self::fail('title_required');
        }

        $prId = $model->create($data);

        $lineItems = $args['line_items'] ?? [];
        if (is_array($lineItems) && $lineItems !== []) {
            \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($prId, $lineItems);
            $agg = \Rateb\App\Helpers\LineItems::aggregateTotals($lineItems);
            $model->update($prId, ['total_estimated' => $agg['total']]);
            $data['total_estimated'] = $agg['total'];
        }

        (new AuditService())->log('create', 'purchase_requests', $prId, array_merge($data, [
            'via' => 'procurement_agent',
            'user_id' => $userId,
        ]));

        return [
            'success' => true,
            'data' => [
                'id' => $prId,
                'request_no' => $data['request_no'],
                'status' => 'draft',
                'total_estimated' => (float) $data['total_estimated'],
                'currency' => $data['currency'],
                'impact' => 'created_draft_purchase_request',
            ],
            'error' => null,
        ];
    }

    private static function updatePurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_pr_id');
        }

        $model = new PurchaseRequest();
        $rows = $model->query(
            'SELECT * FROM rateb_purchase_requests WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $pr = $rows[0] ?? null;
        if (!$pr) {
            return self::fail('pr_not_found');
        }

        $status = (string) ($pr['status'] ?? '');
        if (!in_array($status, self::PR_OPEN_STATUSES, true)) {
            return self::fail('pr_not_editable');
        }

        $data = [];
        foreach (['title', 'department', 'priority', 'currency', 'notes'] as $field) {
            if (array_key_exists($field, $args)) {
                $data[$field] = is_string($args[$field]) ? trim((string) $args[$field]) : $args[$field];
            }
        }
        if (array_key_exists('expected_date', $args)) {
            $expectedDate = trim((string) $args['expected_date']);
            if ($expectedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDate)) {
                $data['expected_date'] = $expectedDate;
            } elseif ($expectedDate === '') {
                $data['expected_date'] = null;
            }
        }
        if (array_key_exists('total_estimated', $args)) {
            $data['total_estimated'] = (float) $args['total_estimated'];
        }
        if (isset($data['title']) && $data['title'] === '') {
            return self::fail('title_required');
        }

        $lineItems = $args['line_items'] ?? null;
        if (is_array($lineItems)) {
            \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($id, $lineItems);
            $agg = \Rateb\App\Helpers\LineItems::aggregateTotals($lineItems);
            $data['total_estimated'] = $agg['total'];
        }

        if ($data === []) {
            return self::fail('no_fields_to_update');
        }

        $model->update($id, $data);
        (new AuditService())->log('update', 'purchase_requests', $id, array_merge($data, [
            'via' => 'procurement_agent',
            'user_id' => $userId,
        ]));

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'request_no' => (string) ($pr['request_no'] ?? ''),
                'updated' => array_keys($data),
                'status' => $status,
                'impact' => 'updated_purchase_request',
            ],
            'error' => null,
        ];
    }

    private static function cancelPurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_pr_id');
        }

        $model = new PurchaseRequest();
        $rows = $model->query(
            'SELECT * FROM rateb_purchase_requests WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $pr = $rows[0] ?? null;
        if (!$pr) {
            return self::fail('pr_not_found');
        }

        $status = (string) ($pr['status'] ?? '');
        if ($status === 'cancelled') {
            return self::fail('pr_already_cancelled');
        }
        if (!in_array($status, self::PR_CANCELABLE, true)) {
            return self::fail('pr_not_cancelable');
        }

        $reason = trim((string) ($args['reason'] ?? ''));
        $notes = (string) ($pr['notes'] ?? '');
        if ($reason !== '') {
            $notes = trim($notes . "\n[cancel] " . $reason);
        }

        $model->update($id, [
            'status' => 'cancelled',
            'notes' => $notes,
        ]);

        (new AuditService())->log('cancel', 'purchase_requests', $id, [
            'via' => 'procurement_agent',
            'user_id' => $userId,
            'from_status' => $status,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'request_no' => (string) ($pr['request_no'] ?? ''),
                'from_status' => $status,
                'status' => 'cancelled',
                'impact' => 'cancelled_purchase_request',
            ],
            'error' => null,
        ];
    }

    private static function submitPurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_pr_id');
        }

        $model = new PurchaseRequest();
        $rows = $model->query(
            'SELECT * FROM rateb_purchase_requests WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        $pr = $rows[0] ?? null;
        if (!$pr) {
            return self::fail('pr_not_found');
        }

        $currentStatus = (string) ($pr['status'] ?? '');
        if ($currentStatus !== 'draft') {
            return self::fail('pr_not_draft');
        }

        $oldStatus = $currentStatus;
        $model->update($id, ['status' => 'submitted']);
        (new WorkflowSubmissionService())->handlePurchaseRequestStatus($id, 'submitted', $oldStatus);
        (new AuditService())->log('submit', 'purchase_requests', $id, [
            'status' => 'submitted',
            'via' => 'procurement_agent',
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => 'submitted',
                'impact' => 'submitted_purchase_request_for_approval',
            ],
            'error' => null,
        ];
    }
}
