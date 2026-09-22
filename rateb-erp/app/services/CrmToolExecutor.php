<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Customer;

/**
 * CRM Tool Executor — tenant-scoped READ/ANALYSIS against live CRM + commercial relations.
 * No WRITE. No invented entities.
 */
final class CrmToolExecutor
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
                case 'list_crm_customers':
                    return self::listCustomers($arguments, $companyId);
                case 'get_crm_customer':
                    return self::getCustomer($arguments, $companyId);
                case 'list_crm_leads':
                    return self::listLeads($arguments, $companyId);
                case 'list_crm_opportunities':
                    return self::listOpportunities($arguments, $companyId);
                case 'list_crm_followups':
                    return self::listFollowups($arguments, $companyId);
                case 'analyze_crm':
                    return self::analyzeCrm($arguments, $companyId);
                case 'get_crm_operational_guidance':
                    return self::getOperationalGuidance($arguments, $companyId);
                case 'get_crm_sales_links':
                    return self::getSalesLinks($arguments, $companyId);
                case 'get_crm_inventory_links':
                    return self::getInventoryLinks($arguments, $companyId);
                case 'get_crm_procurement_links':
                    return self::getProcurementLinks($arguments, $companyId);
                case 'get_crm_supplier_links':
                    return self::getSupplierLinks($arguments, $companyId);
                case 'analyze_crm_commercial_intelligence':
                    return self::analyzeCommercialIntelligence($arguments, $companyId);
                case 'create_crm_lead':
                    return self::createCrmLead($arguments, $companyId, (int) $ctx->userId);
                case 'create_crm_followup':
                    return self::createCrmFollowup($arguments, $companyId, (int) $ctx->userId);
                case 'create_customer':
                    return self::createCustomer($arguments, $companyId, (int) $ctx->userId);
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

    private static function model(): Customer
    {
        return new Customer();
    }

    private static function listCustomers(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = trim((string) ($args['search'] ?? ''));
        $atRiskOnly = !empty($args['at_risk_only']);

        try {
            $sql = 'SELECT id, code, name, name_ar, phone, email, is_active, branch_id,
                           crm_lifecycle_stage, crm_health_score, crm_health_status,
                           crm_renewal_risk, crm_at_risk, crm_last_interaction_at
                    FROM rateb_customers WHERE company_id = :cid';
            $params = ['cid' => $companyId];
            if ($atRiskOnly) {
                $sql .= ' AND (crm_at_risk = 1 OR crm_renewal_risk IN (\'high\',\'critical\') OR crm_health_status IN (\'at_risk\',\'critical\'))';
            }
            if ($search !== '') {
                $sql .= ' AND (name LIKE :q OR name_ar LIKE :q OR code LIKE :q OR phone LIKE :q OR email LIKE :q)';
                $params['q'] = '%' . $search . '%';
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $sql = 'SELECT id, code, name, name_ar, phone, email, is_active, branch_id
                    FROM rateb_customers WHERE company_id = :cid';
            $params = ['cid' => $companyId];
            if ($search !== '') {
                $sql .= ' AND (name LIKE :q OR code LIKE :q OR phone LIKE :q OR email LIKE :q)';
                $params['q'] = '%' . $search . '%';
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $rows = self::model()->query($sql, $params);
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getCustomer(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_customer_id');
        }

        try {
            $rows = self::model()->query(
                'SELECT id, code, name, name_ar, phone, email, is_active, notes, branch_id,
                        crm_lifecycle_stage, crm_owner_user_id, crm_team_id, crm_territory_id,
                        crm_last_interaction_at, crm_activity_score, crm_engagement_score,
                        crm_health_score, crm_health_status, crm_renewal_risk,
                        crm_renewal_due_at, crm_at_risk
                 FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $rows = self::model()->query(
                'SELECT id, code, name, name_ar, phone, email, is_active, notes, branch_id
                 FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
        }
        $item = $rows[0] ?? null;
        if (!$item) {
            return self::fail('customer_not_found');
        }

        $contacts = [];
        try {
            $contacts = self::model()->query(
                'SELECT id, full_name, email, phone, mobile, job_title, is_primary, status
                 FROM rateb_crm_contacts
                 WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
                 ORDER BY is_primary DESC, id DESC LIMIT 20',
                ['cid' => $companyId, 'cuid' => $id]
            );
        } catch (\Throwable $e) {
            $contacts = [];
        }
        $item['contacts'] = $contacts;

        return ['success' => true, 'data' => $item, 'error' => null];
    }

    private static function listLeads(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['workflow_status'] ?? ''));
        $customerId = (int) ($args['customer_id'] ?? 0);

        try {
            $sql = 'SELECT id, lead_no, title, contact_name, email, phone, customer_id, workflow_status,
                           estimated_value, currency_code, expected_close_date, priority, status, created_at
                    FROM rateb_crm_leads
                    WHERE company_id = :cid AND deleted_at IS NULL';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND workflow_status = :st';
                $params['st'] = $status;
            }
            if ($customerId > 0) {
                $sql .= ' AND customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function listOpportunities(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['workflow_status'] ?? ''));
        $customerId = (int) ($args['customer_id'] ?? 0);

        try {
            $sql = 'SELECT id, opportunity_no, name, customer_id, workflow_status, amount, currency_code,
                           probability_percent, expected_close_date, status, is_stale, risk_level, created_at
                    FROM rateb_crm_opportunities
                    WHERE company_id = :cid AND deleted_at IS NULL';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND workflow_status = :st';
                $params['st'] = $status;
            }
            if ($customerId > 0) {
                $sql .= ' AND customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
            foreach ($rows as &$row) {
                $row['amount'] = (float) ($row['amount'] ?? 0);
            }
            unset($row);
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function listFollowups(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $customerId = (int) ($args['customer_id'] ?? 0);
        $items = [];

        try {
            $sql = "SELECT id, 'activity' AS kind, activity_type, subject, status, due_at, customer_id, activity_at
                    FROM rateb_crm_activities
                    WHERE company_id = :cid AND deleted_at IS NULL AND status = 'open'";
            $params = ['cid' => $companyId];
            if ($customerId > 0) {
                $sql .= ' AND customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' ORDER BY due_at IS NULL, due_at ASC, id DESC LIMIT ' . $limit;
            foreach (self::model()->query($sql, $params) as $row) {
                $items[] = $row;
            }
        } catch (\Throwable $e) {
            // optional
        }

        try {
            $sql = "SELECT id, 'task' AS kind, priority AS activity_type, subject, status, due_at, customer_id, NULL AS activity_at
                    FROM rateb_crm_tasks
                    WHERE company_id = :cid AND deleted_at IS NULL AND status NOT IN ('done','completed','cancelled')";
            $params = ['cid' => $companyId];
            if ($customerId > 0) {
                $sql .= ' AND customer_id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' ORDER BY due_at IS NULL, due_at ASC, id DESC LIMIT ' . $limit;
            foreach (self::model()->query($sql, $params) as $row) {
                $items[] = $row;
            }
        } catch (\Throwable $e) {
            // optional
        }

        usort($items, static function ($a, $b) {
            $da = (string) ($a['due_at'] ?? '');
            $db = (string) ($b['due_at'] ?? '');
            if ($da === $db) {
                return 0;
            }
            if ($da === '') {
                return 1;
            }
            if ($db === '') {
                return -1;
            }
            return $da <=> $db;
        });

        return ['success' => true, 'data' => array_slice($items, 0, $limit), 'error' => null];
    }

    private static function analyzeCrm(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $m = self::model();
        $today = date('Y-m-d');

        $customerCnt = $m->query(
            'SELECT COUNT(*) AS cnt FROM rateb_customers WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $atRisk = [];
        try {
            $atRisk = $m->query(
                "SELECT id, code, name, crm_lifecycle_stage, crm_health_score, crm_health_status,
                        crm_renewal_risk, crm_at_risk
                 FROM rateb_customers
                 WHERE company_id = :cid
                   AND (crm_at_risk = 1 OR crm_renewal_risk IN ('high','critical')
                        OR crm_health_status IN ('at_risk','critical'))
                 ORDER BY crm_health_score ASC, id DESC
                 LIMIT {$limit}",
                ['cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $atRisk = [];
        }

        $leadCnt = 0;
        $openLeads = [];
        try {
            $leadCnt = (int) (($m->query(
                'SELECT COUNT(*) AS cnt FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL',
                ['cid' => $companyId]
            )[0]['cnt'] ?? 0));
            $openLeads = $m->query(
                "SELECT id, lead_no, title, workflow_status, estimated_value, expected_close_date, customer_id
                 FROM rateb_crm_leads
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND workflow_status NOT IN ('won','lost','converted','closed')
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $leadCnt = 0;
            $openLeads = [];
        }

        $oppCnt = 0;
        $openOpps = [];
        $overdueOpps = [];
        try {
            $oppCnt = (int) (($m->query(
                'SELECT COUNT(*) AS cnt FROM rateb_crm_opportunities WHERE company_id = :cid AND deleted_at IS NULL',
                ['cid' => $companyId]
            )[0]['cnt'] ?? 0));
            $openOpps = $m->query(
                "SELECT id, opportunity_no, name, workflow_status, amount, expected_close_date, customer_id, is_stale
                 FROM rateb_crm_opportunities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND workflow_status NOT IN ('won','lost','closed')
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId]
            );
            $overdueOpps = $m->query(
                "SELECT id, opportunity_no, name, workflow_status, amount, expected_close_date, customer_id
                 FROM rateb_crm_opportunities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND workflow_status NOT IN ('won','lost','closed')
                   AND expected_close_date IS NOT NULL AND expected_close_date <> ''
                   AND expected_close_date < :today
                 ORDER BY expected_close_date ASC LIMIT {$limit}",
                ['cid' => $companyId, 'today' => $today]
            );
        } catch (\Throwable $e) {
            $oppCnt = 0;
            $openOpps = [];
            $overdueOpps = [];
        }

        $followups = self::listFollowups(['limit' => $limit], $companyId);
        $followData = is_array($followups['data'] ?? null) ? $followups['data'] : [];

        $followUp = [];
        foreach ($atRisk as $row) {
            $followUp[] = [
                'code' => 'customer_at_risk',
                'urgency' => 'high',
                'customer_id' => (int) ($row['id'] ?? 0),
                'label' => (string) (($row['code'] ?? '') . ' ' . ($row['name'] ?? '')),
            ];
        }
        foreach ($overdueOpps as $row) {
            $followUp[] = [
                'code' => 'overdue_opportunity',
                'urgency' => 'high',
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'opportunity_id' => (int) ($row['id'] ?? 0),
                'label' => (string) (($row['opportunity_no'] ?? '') . ' ' . ($row['name'] ?? '')),
                'expected_close_date' => (string) ($row['expected_close_date'] ?? ''),
            ];
        }
        foreach ($followData as $row) {
            $followUp[] = [
                'code' => 'open_crm_followup',
                'urgency' => 'medium',
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'label' => (string) (($row['kind'] ?? '') . ': ' . ($row['subject'] ?? '')),
                'due_at' => (string) ($row['due_at'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => $today,
                'data_source' => 'live_tenant',
                'summary' => [
                    'customer_count' => (int) ($customerCnt[0]['cnt'] ?? 0),
                    'at_risk_count' => count($atRisk),
                    'lead_count' => $leadCnt,
                    'open_lead_count' => count($openLeads),
                    'opportunity_count' => $oppCnt,
                    'open_opportunity_count' => count($openOpps),
                    'overdue_opportunity_count' => count($overdueOpps),
                    'open_followup_count' => count($followData),
                ],
                'at_risk_customers' => $atRisk,
                'open_leads' => $openLeads,
                'open_opportunities' => $openOpps,
                'overdue_opportunities' => $overdueOpps,
                'followups' => $followData,
                'follow_up' => array_slice($followUp, 0, $limit),
                'notes' => [
                    'empty_lists_mean_no_matching_records',
                    'all_values_from_live_tenant_crm_data',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getOperationalGuidance(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 10)));
        $analysis = self::analyzeCrm(['limit' => $limit], $companyId);
        if (empty($analysis['success'])) {
            return self::fail('tool_exception');
        }
        $actions = [];
        foreach (($analysis['data']['follow_up'] ?? []) as $fu) {
            if (!is_array($fu)) {
                continue;
            }
            $code = (string) ($fu['code'] ?? '');
            $actions[] = [
                'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                'action' => $code === 'customer_at_risk' ? 'review_at_risk_customer'
                    : ($code === 'overdue_opportunity' ? 'review_overdue_opportunity' : 'complete_crm_followup'),
                'reason_code' => $code,
                'label' => (string) ($fu['label'] ?? ''),
                'customer_id' => (int) ($fu['customer_id'] ?? 0),
                'suggested_read_tool' => $code === 'customer_at_risk' ? 'get_crm_customer' : 'list_crm_followups',
                'suggested_write_tool' => null,
                'requires_confirmation_for_write' => false,
                'auto_execute_write' => false,
            ];
        }
        if ($actions === []) {
            $actions[] = [
                'urgency' => 'none',
                'action' => 'no_urgent_crm_action',
                'reason_code' => 'healthy_or_empty',
                'label' => 'no_matching_crm_issues',
                'suggested_read_tool' => 'analyze_crm',
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
                'summary' => $analysis['data']['summary'] ?? [],
                'actions' => array_slice($actions, 0, $limit),
                'notes' => ['guidance_only_never_auto_writes'],
            ],
            'error' => null,
        ];
    }

    private static function getSalesLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $customerId = (int) ($args['customer_id'] ?? 0);
        $openOnly = !empty($args['open_only']);

        try {
            $sql = "SELECT c.id AS customer_id, c.code, c.name,
                           o.id AS sales_order_id, o.order_no, o.order_type, o.status,
                           o.total, o.created_at
                    FROM rateb_customers c
                    INNER JOIN rateb_pos_orders o ON o.customer_id = c.id AND o.company_id = c.company_id
                    WHERE c.company_id = :cid";
            $params = ['cid' => $companyId];
            if ($customerId > 0) {
                $sql .= ' AND c.id = :cuid';
                $params['cuid'] = $customerId;
            }
            if ($openOnly) {
                $sql .= " AND o.status IN ('draft','suspended')";
            }
            $sql .= ' ORDER BY o.id DESC LIMIT ' . max($limit * 10, 100);
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $byCustomer = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['customer_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            if (!isset($byCustomer[$cid])) {
                $byCustomer[$cid] = [
                    'customer_id' => $cid,
                    'code' => (string) ($row['code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'sales_orders' => [],
                    'totals' => ['order_count' => 0, 'order_amount' => 0.0, 'open_count' => 0],
                ];
            }
            $st = (string) ($row['status'] ?? '');
            $amount = (float) ($row['total'] ?? 0);
            $byCustomer[$cid]['sales_orders'][] = [
                'id' => (int) ($row['sales_order_id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'order_type' => (string) ($row['order_type'] ?? ''),
                'status' => $st,
                'total' => $amount,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
            $byCustomer[$cid]['totals']['order_count']++;
            $byCustomer[$cid]['totals']['order_amount'] += $amount;
            if (in_array($st, ['draft', 'suspended'], true)) {
                $byCustomer[$cid]['totals']['open_count']++;
            }
        }

        $list = array_slice(array_values($byCustomer), 0, $limit);

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'links' => $list,
                'count' => count($list),
                'notes' => [
                    'links_require_pos_orders.customer_id',
                    'empty_links_mean_no_matching_relations',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getInventoryLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $customerId = (int) ($args['customer_id'] ?? 0);

        try {
            $sql = "SELECT c.id AS customer_id, c.code AS customer_code, c.name AS customer_name,
                           l.inventory_id, COALESCE(i.item_code,'') AS item_code,
                           COALESCE(i.item_name, l.description) AS item_name,
                           COALESCE(i.quantity,0) AS stock_qty,
                           SUM(l.quantity) AS demand_qty,
                           COUNT(DISTINCT o.id) AS order_count
                    FROM rateb_customers c
                    INNER JOIN rateb_pos_orders o ON o.customer_id = c.id AND o.company_id = c.company_id
                    INNER JOIN rateb_pos_order_lines l ON l.order_id = o.id AND l.company_id = o.company_id
                    LEFT JOIN rateb_inventory i ON i.id = l.inventory_id AND i.company_id = o.company_id
                    WHERE c.company_id = :cid
                      AND l.inventory_id IS NOT NULL AND l.inventory_id > 0
                      AND o.status IN ('draft','suspended','completed')";
            $params = ['cid' => $companyId];
            if ($customerId > 0) {
                $sql .= ' AND c.id = :cuid';
                $params['cuid'] = $customerId;
            }
            $sql .= ' GROUP BY c.id, c.code, c.name, l.inventory_id, i.item_code, i.item_name, l.description, i.quantity
                      HAVING demand_qty > stock_qty
                      ORDER BY (demand_qty - stock_qty) DESC
                      LIMIT ' . $limit;
            $rows = self::model()->query($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $links = [];
        foreach ($rows as $row) {
            $demand = (float) ($row['demand_qty'] ?? 0);
            $stock = (float) ($row['stock_qty'] ?? 0);
            $links[] = [
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'customer_code' => (string) ($row['customer_code'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                'item_code' => (string) ($row['item_code'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'demand_qty' => $demand,
                'stock_qty' => $stock,
                'shortfall_qty' => max(0, $demand - $stock),
                'order_count' => (int) ($row['order_count'] ?? 0),
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
                    'links_require_customer_id_on_pos_and_inventory_id_on_lines',
                    'empty_links_mean_no_customer_stock_shortfall',
                ],
            ],
            'error' => null,
        ];
    }

    private static function getProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $inv = self::getInventoryLinks($args, $companyId);
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
                     INNER JOIN rateb_purchase_requests pr ON pr.id = pri.purchase_request_id AND pr.company_id = :cid
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
                     INNER JOIN rateb_purchase_orders po ON po.id = poi.purchase_order_id AND po.company_id = :cid
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
                    'code' => 'customer_shortfall_without_procurement',
                    'message' => 'customer_sales_demand_exceeds_stock_no_linked_pr_or_po',
                ];
            }

            $links[] = [
                'customer_id' => (int) ($item['customer_id'] ?? 0),
                'customer_name' => (string) ($item['customer_name'] ?? ''),
                'inventory_id' => $iid,
                'item_code' => (string) ($item['item_code'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'shortfall_qty' => (float) ($item['shortfall_qty'] ?? 0),
                'purchase_requests' => $prs,
                'purchase_orders' => $pos,
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
                'notes' => ['links_use_existing_inventory_id_on_pr_po_lines'],
            ],
            'error' => null,
        ];
    }

    private static function getSupplierLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $proc = self::getProcurementLinks($args, $companyId);
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
                        'customers' => [],
                        'items' => [],
                        'purchase_orders' => [],
                    ];
                }
                $cuid = (int) ($item['customer_id'] ?? 0);
                if ($cuid > 0) {
                    $exists = false;
                    foreach ($bySupplier[$sid]['customers'] as $existing) {
                        if ((int) ($existing['customer_id'] ?? 0) === $cuid) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $bySupplier[$sid]['customers'][] = [
                            'customer_id' => $cuid,
                            'customer_name' => (string) ($item['customer_name'] ?? ''),
                        ];
                    }
                }
                $iid = (int) ($item['inventory_id'] ?? 0);
                if ($iid > 0) {
                    $exists = false;
                    foreach ($bySupplier[$sid]['items'] as $existing) {
                        if ((int) ($existing['inventory_id'] ?? 0) === $iid) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $bySupplier[$sid]['items'][] = [
                            'inventory_id' => $iid,
                            'item_code' => (string) ($item['item_code'] ?? ''),
                            'item_name' => (string) ($item['item_name'] ?? ''),
                            'shortfall_qty' => (float) ($item['shortfall_qty'] ?? 0),
                        ];
                    }
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
                    $bySupplier[$sid]['purchase_orders'][] = [
                        'id' => $poId,
                        'order_no' => (string) ($po['order_no'] ?? ''),
                        'status' => (string) ($po['status'] ?? ''),
                        'total_amount' => (float) ($po['total_amount'] ?? 0),
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
                    'supplier_links_require_po_supplier_id_and_inventory_id',
                    'empty_links_mean_no_existing_relation',
                ],
            ],
            'error' => null,
        ];
    }

    private static function analyzeCommercialIntelligence(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $base = ['limit' => $limit];
        if ((int) ($args['customer_id'] ?? 0) > 0) {
            $base['customer_id'] = (int) $args['customer_id'];
        }

        $crm = self::analyzeCrm($base, $companyId);
        $sales = self::getSalesLinks($base + ['open_only' => false], $companyId);
        $inv = self::getInventoryLinks($base, $companyId);
        $proc = self::getProcurementLinks($base, $companyId);
        $sup = self::getSupplierLinks($base, $companyId);
        $guide = self::getOperationalGuidance($base, $companyId);

        if (empty($crm['success'])) {
            return self::fail('tool_exception');
        }

        $followUp = is_array($crm['data']['follow_up'] ?? null) ? $crm['data']['follow_up'] : [];
        foreach (($proc['data']['links'] ?? []) as $link) {
            foreach (($link['follow_up'] ?? []) as $fu) {
                $followUp[] = array_merge(is_array($fu) ? $fu : [], [
                    'customer_id' => (int) ($link['customer_id'] ?? 0),
                    'urgency' => 'high',
                ]);
            }
        }

        return [
            'success' => true,
            'data' => [
                'as_of' => date('Y-m-d'),
                'data_source' => 'live_tenant',
                'domains' => ['crm', 'sales', 'inventory', 'procurement', 'suppliers'],
                'crm_summary' => $crm['data']['summary'] ?? [],
                'sales_links' => $sales['data']['links'] ?? [],
                'inventory_links' => $inv['data']['links'] ?? [],
                'procurement_links' => $proc['data']['links'] ?? [],
                'supplier_links' => $sup['data']['links'] ?? [],
                'guidance' => $guide['data']['actions'] ?? [],
                'follow_up' => array_slice($followUp, 0, $limit),
                'chain' => [
                    'customer' => 'rateb_customers',
                    'sales_order' => 'rateb_pos_orders.customer_id',
                    'stock_availability' => 'rateb_inventory via pos_order_lines.inventory_id',
                    'procurement_requirement' => 'rateb_purchase_request_items / rateb_purchase_items.inventory_id',
                    'supplier' => 'rateb_purchase_orders.supplier_id',
                ],
                'notes' => [
                    'commercial_chain_uses_existing_relations_only',
                    'no_separate_crm_agent',
                    'failed_optional_joins_omitted_not_invented',
                ],
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function createCrmLead(array $args, int $companyId, int $userId): array
    {
        $title = trim((string) ($args['title'] ?? $args['name'] ?? ''));
        if ($title === '') {
            return self::fail('title_required');
        }
        try {
            $created = (new LeadService())->create([
                'title' => $title,
                'contact_name' => trim((string) ($args['contact_name'] ?? $args['name'] ?? '')),
                'email' => trim((string) ($args['email'] ?? '')),
                'phone' => trim((string) ($args['phone'] ?? '')),
                'notes' => trim((string) ($args['notes'] ?? '')),
                'priority' => trim((string) ($args['priority'] ?? 'normal')) ?: 'normal',
                'owner_user_id' => $userId > 0 ? $userId : null,
            ]);
            return [
                'success' => true,
                'data' => [
                    'id' => (int) ($created['id'] ?? 0),
                    'lead_no' => (string) ($created['lead_no'] ?? ''),
                    'title' => $title,
                ],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function createCrmFollowup(array $args, int $companyId, int $userId): array
    {
        $subject = trim((string) ($args['subject'] ?? $args['title'] ?? $args['notes'] ?? ''));
        if ($subject === '') {
            return self::fail('subject_required');
        }
        try {
            $created = (new TaskService())->create([
                'subject' => $subject,
                'priority' => trim((string) ($args['priority'] ?? 'normal')) ?: 'normal',
                'notes' => trim((string) ($args['notes'] ?? '')),
                'owner_user_id' => $userId > 0 ? $userId : null,
                'customer_id' => (int) ($args['customer_id'] ?? 0) ?: null,
                'lead_id' => (int) ($args['lead_id'] ?? 0) ?: null,
            ]);
            return [
                'success' => true,
                'data' => [
                    'id' => (int) ($created['id'] ?? 0),
                    'subject' => $subject,
                ],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function createCustomer(array $args, int $companyId, int $userId): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return self::fail('name_required');
        }
        $model = new Customer();
        $data = [
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 190),
            'email' => trim((string) ($args['email'] ?? '')),
            'phone' => trim((string) ($args['phone'] ?? '')),
            'notes' => trim((string) ($args['notes'] ?? '')),
            'is_active' => 1,
        ];
        $branchId = (int) ($args['branch_id'] ?? 0);
        if ($branchId < 1 && function_exists('rateb_resolve_create_branch_id')) {
            $branchId = (int) rateb_resolve_create_branch_id();
        }
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }
        (new DocumentCodeService())->assignIfEmpty($data, $model, DocumentCodeService::PREFIX_CUSTOMER, 'code');
        $id = (int) $model->create($data);
        if ($id < 1) {
            return self::fail('create_failed');
        }
        $rows = $model->query(
            'SELECT id, code, name, email, phone, is_active FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'customer' => $rows[0] ?? ['id' => $id, 'name' => $name],
                'created_by' => $userId > 0 ? $userId : null,
            ],
            'error' => null,
        ];
    }
}
