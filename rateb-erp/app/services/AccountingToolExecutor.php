<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\JournalEntry;

/**
 * Accounting Tool Executor — tenant-scoped READ/ANALYSIS + limited WRITE via AccountingService.
 * No AccountingAgent. No invented financial figures. LLM never writes the ledger directly.
 */
final class AccountingToolExecutor
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
                case 'list_chart_accounts':
                    return self::listChartAccounts($arguments, $companyId);
                case 'get_account':
                    return self::getAccount($arguments, $companyId);
                case 'list_journal_entries':
                    return self::listJournalEntries($arguments, $companyId);
                case 'get_journal_entry':
                    return self::getJournalEntry($arguments, $companyId);
                case 'list_accounts_receivable':
                    return self::listAccountsReceivable($arguments, $companyId);
                case 'list_accounts_payable':
                    return self::listAccountsPayable($arguments, $companyId);
                case 'list_supplier_payments':
                    return self::listSupplierPayments($arguments, $companyId);
                case 'get_financial_summary':
                    return self::getFinancialSummary($arguments, $companyId);
                case 'get_vat_report':
                    return self::getVatReport($arguments, $companyId);
                case 'analyze_accounting':
                    return self::analyzeAccounting($arguments, $companyId);
                case 'get_accounting_operational_guidance':
                    return self::getOperationalGuidance($arguments, $companyId);
                case 'get_accounting_sales_links':
                    return self::getSalesLinks($arguments, $companyId);
                case 'get_accounting_procurement_links':
                    return self::getProcurementLinks($arguments, $companyId);
                case 'get_accounting_supplier_links':
                    return self::getSupplierLinks($arguments, $companyId);
                case 'get_accounting_inventory_links':
                    return self::getInventoryLinks($arguments, $companyId);
                case 'get_accounting_logistics_links':
                    return self::getLogisticsLinks($arguments, $companyId);
                case 'analyze_financial_intelligence':
                    return self::analyzeFinancialIntelligence($arguments, $companyId);
                case 'submit_journal_for_approval':
                    return self::submitJournalForApproval($arguments, $companyId);
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

    private static function accounting(): AccountingService
    {
        return new AccountingService();
    }

    private static function listChartAccounts(array $args, int $companyId): array
    {
        $limit = max(1, min(200, (int) ($args['limit'] ?? 100)));
        $type = trim((string) ($args['account_type'] ?? ''));
        $search = trim((string) ($args['search'] ?? ''));
        $sql = 'SELECT id, code, name, name_ar, account_type, is_active, parent_id
                FROM rateb_chart_of_accounts
                WHERE company_id = :cid AND is_active = 1';
        $params = ['cid' => $companyId];
        if ($type !== '') {
            $sql .= ' AND account_type = :atype';
            $params['atype'] = $type;
        }
        if ($search !== '') {
            $sql .= ' AND (code LIKE :q OR name LIKE :q OR name_ar LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY code ASC LIMIT ' . $limit;
        $rows = (new ChartOfAccount())->query($sql, $params);
        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getAccount(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_account_id');
        }
        $svc = self::accounting();
        $account = (new ChartOfAccount())->queryOne(
            'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$account) {
            return self::fail('account_not_found');
        }
        $statement = $svc->accountStatement($companyId, $id, null, null);
        return [
            'success' => true,
            'data' => [
                'account' => $account,
                'statement_summary' => [
                    'opening' => $statement['opening'] ?? 0,
                    'closing' => $statement['closing'] ?? 0,
                    'total_debit' => $statement['total_debit'] ?? 0,
                    'total_credit' => $statement['total_credit'] ?? 0,
                    'line_count' => is_array($statement['lines'] ?? null) ? count($statement['lines']) : 0,
                ],
            ],
            'error' => null,
        ];
    }

    private static function listJournalEntries(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $status = trim((string) ($args['status'] ?? ''));
        $sourceType = trim((string) ($args['source_type'] ?? ''));
        $sql = 'SELECT id, entry_no, entry_date, status, source_type, source_id, description, description_ar,
                       submitted_for_approval_at, posted_at, created_at, updated_at
                FROM rateb_journal_entries WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        if ($sourceType !== '') {
            $sql .= ' AND source_type = :src';
            $params['src'] = $sourceType;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        try {
            $rows = (new JournalEntry())->query($sql, $params);
        } catch (\Throwable $e) {
            $sql = 'SELECT id, entry_no, entry_date, status, source_type, source_id, description, created_at
                    FROM rateb_journal_entries WHERE company_id = :cid';
            $params = ['cid' => $companyId];
            if ($status !== '') {
                $sql .= ' AND status = :st';
                $params['st'] = $status;
            }
            if ($sourceType !== '') {
                $sql .= ' AND source_type = :src';
                $params['src'] = $sourceType;
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
            $rows = (new JournalEntry())->query($sql, $params);
        }
        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getJournalEntry(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_journal_id');
        }
        $entry = (new JournalEntry())->queryOne(
            'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$entry) {
            return self::fail('journal_not_found');
        }
        $lines = (new JournalEntry())->query(
            'SELECT l.id, l.account_id, l.debit, l.credit, l.memo, a.code AS account_code, a.name AS account_name
             FROM rateb_journal_lines l
             LEFT JOIN rateb_chart_of_accounts a ON a.id = l.account_id
             WHERE l.journal_entry_id = :jid
             ORDER BY l.id ASC',
            ['jid' => $id]
        );
        $entry['lines'] = $lines;
        return ['success' => true, 'data' => $entry, 'error' => null];
    }

    private static function listAccountsReceivable(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $overdueOnly = !empty($args['overdue_only']);
        $data = self::accounting()->accountsReceivable($companyId);
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        if ($overdueOnly) {
            $rows = array_values(array_filter($rows, static function ($r): bool {
                $status = (string) ($r['status'] ?? '');
                return $status === 'overdue'
                    || ($status === 'sent' && !empty($r['due_date']) && (string) $r['due_date'] < date('Y-m-d'));
            }));
        }
        $rows = array_slice($rows, 0, $limit);
        return [
            'success' => true,
            'data' => [
                'rows' => $rows,
                'total_open' => (float) ($data['total_open'] ?? 0),
                'total_paid' => (float) ($data['total_paid'] ?? 0),
                'overdue_only' => $overdueOnly,
            ],
            'error' => null,
        ];
    }

    private static function listAccountsPayable(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        $data = self::accounting()->accountsPayable($companyId);
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        if ($supplierId > 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn($r) => (int) ($r['supplier_id'] ?? 0) === $supplierId
            ));
        }
        $rows = array_slice($rows, 0, $limit);
        return [
            'success' => true,
            'data' => [
                'rows' => $rows,
                'total_open' => (float) ($data['total_open'] ?? 0),
                'total_posted' => (float) ($data['total_posted'] ?? 0),
            ],
            'error' => null,
        ];
    }

    private static function listSupplierPayments(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $rows = self::accounting()->listSupplierPayments($companyId, $limit);
        return ['success' => true, 'data' => $rows, 'error' => null];
    }

    private static function getFinancialSummary(array $args, int $companyId): array
    {
        $svc = self::accounting();
        $summary = $svc->financialSummary($companyId);
        $out = [
            'data_source' => 'live_tenant',
            'as_of' => date('Y-m-d'),
            'summary' => $summary,
        ];
        if (!empty($args['include_cfo'])) {
            $out['cfo_metrics'] = $svc->cfoMetrics($companyId);
        }
        return ['success' => true, 'data' => $out, 'error' => null];
    }

    private static function getVatReport(array $args, int $companyId): array
    {
        $from = trim((string) ($args['from_date'] ?? ''));
        $to = trim((string) ($args['to_date'] ?? ''));
        $report = self::accounting()->vatReport(
            $companyId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null
        );
        return ['success' => true, 'data' => $report, 'error' => null];
    }

    private static function analyzeAccounting(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $svc = self::accounting();
        $summary = $svc->financialSummary($companyId);
        $cfo = $svc->cfoMetrics($companyId);
        $ar = $svc->accountsReceivable($companyId);
        $ap = $svc->accountsPayable($companyId);
        $arRows = is_array($ar['rows'] ?? null) ? $ar['rows'] : [];
        $apRows = is_array($ap['rows'] ?? null) ? $ap['rows'] : [];

        $overdue = [];
        foreach ($arRows as $row) {
            $status = (string) ($row['status'] ?? '');
            $due = (string) ($row['due_date'] ?? '');
            if ($status === 'overdue' || ($status === 'sent' && $due !== '' && $due < date('Y-m-d'))) {
                $overdue[] = [
                    'invoice_id' => (int) ($row['id'] ?? 0),
                    'invoice_no' => (string) ($row['invoice_no'] ?? $row['number'] ?? ''),
                    'status' => $status,
                    'due_date' => $due,
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                    'journal_id' => (int) ($row['journal_id'] ?? 0),
                ];
            }
        }
        $overdue = array_slice($overdue, 0, $limit);

        $unpaidPosted = [];
        foreach ($apRows as $row) {
            $total = (float) ($row['total_amount'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            if (!empty($row['journal_id']) && $total > $paid + 0.0001) {
                $unpaidPosted[] = [
                    'purchase_order_id' => (int) ($row['id'] ?? 0),
                    'order_no' => (string) ($row['order_no'] ?? ''),
                    'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'outstanding' => $total - $paid,
                    'journal_id' => (int) ($row['journal_id'] ?? 0),
                ];
            }
        }
        $unpaidPosted = array_slice($unpaidPosted, 0, $limit);

        $draftJournals = self::listJournalEntries(['limit' => $limit, 'status' => 'draft'], $companyId);
        $draftRows = is_array($draftJournals['data'] ?? null) ? $draftJournals['data'] : [];

        $followUp = [];
        foreach ($overdue as $row) {
            $followUp[] = [
                'code' => 'overdue_receivable',
                'urgency' => 'high',
                'invoice_id' => $row['invoice_id'],
                'amount' => $row['total_amount'],
                'message' => 'overdue_invoice_needs_collection',
            ];
        }
        foreach ($unpaidPosted as $row) {
            $followUp[] = [
                'code' => 'outstanding_payable',
                'urgency' => 'medium',
                'purchase_order_id' => $row['purchase_order_id'],
                'supplier_id' => $row['supplier_id'],
                'amount' => $row['outstanding'],
                'message' => 'supplier_payable_outstanding',
            ];
        }
        foreach ($draftRows as $row) {
            if (trim((string) ($row['submitted_for_approval_at'] ?? '')) === '') {
                $followUp[] = [
                    'code' => 'draft_journal_pending_submit',
                    'urgency' => 'low',
                    'journal_id' => (int) ($row['id'] ?? 0),
                    'message' => 'manual_draft_journal_not_submitted',
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'as_of' => date('Y-m-d'),
                'summary' => $summary,
                'cfo_metrics' => $cfo,
                'overdue_receivables' => $overdue,
                'outstanding_payables' => $unpaidPosted,
                'draft_journals' => $draftRows,
                'follow_up' => array_slice($followUp, 0, $limit),
                'totals' => [
                    'ar_open' => (float) ($ar['total_open'] ?? 0),
                    'ar_paid' => (float) ($ar['total_paid'] ?? 0),
                    'ap_open' => (float) ($ap['total_open'] ?? 0),
                    'ap_posted' => (float) ($ap['total_posted'] ?? 0),
                    'overdue_receivable_count' => count($overdue),
                    'outstanding_payable_count' => count($unpaidPosted),
                ],
            ],
            'error' => null,
        ];
    }

    private static function getOperationalGuidance(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 10)));
        $analysis = self::analyzeAccounting(['limit' => $limit], $companyId);
        $data = is_array($analysis['data'] ?? null) ? $analysis['data'] : [];
        $actions = [];
        foreach (($data['follow_up'] ?? []) as $fu) {
            if (!is_array($fu)) {
                continue;
            }
            $actions[] = [
                'action' => (string) ($fu['code'] ?? 'review_accounting'),
                'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                'message' => (string) ($fu['message'] ?? ''),
                'refs' => array_filter([
                    'invoice_id' => $fu['invoice_id'] ?? null,
                    'purchase_order_id' => $fu['purchase_order_id'] ?? null,
                    'supplier_id' => $fu['supplier_id'] ?? null,
                    'journal_id' => $fu['journal_id'] ?? null,
                    'amount' => $fu['amount'] ?? null,
                ], static fn($v) => $v !== null && $v !== '' && $v !== 0 && $v !== 0.0),
            ];
        }
        if ($actions === []) {
            $actions[] = [
                'action' => 'monitor_financials',
                'urgency' => 'low',
                'message' => 'no_urgent_financial_follow_up_from_live_data',
                'refs' => [],
            ];
        }
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'as_of' => date('Y-m-d'),
                'actions' => array_slice($actions, 0, $limit),
            ],
            'error' => null,
        ];
    }

    private static function getSalesLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $svc = self::accounting();
        $ar = $svc->accountsReceivable($companyId);
        $summary = $svc->financialSummary($companyId);
        $rows = array_slice(is_array($ar['rows'] ?? null) ? $ar['rows'] : [], 0, $limit);
        $links = [];
        foreach ($rows as $row) {
            $links[] = [
                'invoice_id' => (int) ($row['id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
                'journal_id' => (int) ($row['journal_id'] ?? 0),
                'journal_entry_no' => (string) ($row['entry_no'] ?? ''),
                'posted_to_ledger' => !empty($row['journal_id']),
                'domain_pair' => 'sales→accounting',
            ];
        }
        $mismatches = array_values(array_filter(
            $links,
            static fn($l) => in_array($l['status'], ['sent', 'overdue', 'paid'], true) && empty($l['posted_to_ledger'])
        ));
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'mismatches' => $mismatches,
                'summary' => [
                    'invoices_open_total' => (float) ($summary['invoices_open_total'] ?? 0),
                    'invoices_paid_total' => (float) ($summary['invoices_paid_total'] ?? 0),
                    'payments_total' => (float) ($summary['payments_total'] ?? 0),
                    'mismatch_count' => count($mismatches),
                ],
            ],
            'error' => null,
        ];
    }

    private static function getProcurementLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $svc = self::accounting();
        $ap = $svc->accountsPayable($companyId);
        $rows = array_slice(is_array($ap['rows'] ?? null) ? $ap['rows'] : [], 0, $limit);
        $links = [];
        foreach ($rows as $row) {
            $total = (float) ($row['total_amount'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            $links[] = [
                'purchase_order_id' => (int) ($row['id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'total_amount' => $total,
                'paid_amount' => $paid,
                'outstanding' => max(0.0, $total - $paid),
                'journal_id' => (int) ($row['journal_id'] ?? 0),
                'posted_to_ledger' => !empty($row['journal_id']),
                'domain_pair' => 'procurement→accounting',
            ];
        }
        $mismatches = array_values(array_filter(
            $links,
            static fn($l) => $l['outstanding'] > 0.0001 && !empty($l['posted_to_ledger']) && $l['paid_amount'] + 0.0001 < $l['total_amount']
        ));
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'payment_gaps' => $mismatches,
                'summary' => [
                    'total_open' => (float) ($ap['total_open'] ?? 0),
                    'total_posted' => (float) ($ap['total_posted'] ?? 0),
                    'payment_gap_count' => count($mismatches),
                ],
            ],
            'error' => null,
        ];
    }

    private static function getSupplierLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        $svc = self::accounting();
        $ap = self::listAccountsPayable(['limit' => $limit, 'supplier_id' => $supplierId > 0 ? $supplierId : null], $companyId);
        $payments = $svc->listSupplierPayments($companyId, $limit);
        $exposure = [];
        $bySupplier = [];
        foreach ((is_array($ap['data']['rows'] ?? null) ? $ap['data']['rows'] : []) as $row) {
            $sid = (int) ($row['supplier_id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            if (!isset($bySupplier[$sid])) {
                $bySupplier[$sid] = [
                    'supplier_id' => $sid,
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'supplier_code' => (string) ($row['supplier_code'] ?? ''),
                    'outstanding' => 0.0,
                    'po_count' => 0,
                ];
            }
            $total = (float) ($row['total_amount'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            $bySupplier[$sid]['outstanding'] += max(0.0, $total - $paid);
            $bySupplier[$sid]['po_count']++;
        }
        $exposure = array_values($bySupplier);
        usort($exposure, static fn($a, $b) => ($b['outstanding'] <=> $a['outstanding']));
        $exposure = array_slice($exposure, 0, $limit);
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'supplier_exposure' => $exposure,
                'recent_payments' => array_slice(is_array($payments) ? $payments : [], 0, $limit),
                'domain_pair' => 'supplier→accounting',
            ],
            'error' => null,
        ];
    }

    private static function getInventoryLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        try {
            $rows = (new JournalEntry())->query(
                "SELECT id, entry_no, entry_date, status, source_type, source_id, description
                 FROM rateb_journal_entries
                 WHERE company_id = :cid
                   AND source_type IN ('stock_movement','inventory','inventory_adjustment')
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId]
            );
        } catch (\Throwable $e) {
            $rows = [];
        }
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'stock_journals' => $rows,
                'count' => count($rows),
                'domain_pair' => 'inventory→accounting',
                'note' => $rows === [] ? 'no_posted_stock_journals_found' : null,
            ],
            'error' => null,
        ];
    }

    private static function getLogisticsLinks(array $args, int $companyId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $links = [];
        try {
            $shipments = (new Inventory())->query(
                'SELECT s.id, s.tracking_number, s.status, s.order_id, s.customer_id, o.total AS order_total, o.status AS order_status
                 FROM rateb_logistics_shipments s
                 LEFT JOIN rateb_pos_orders o ON o.id = s.order_id AND o.company_id = s.company_id
                 WHERE s.company_id = :cid AND s.order_id IS NOT NULL
                 ORDER BY s.id DESC LIMIT ' . $limit,
                ['cid' => $companyId]
            );
            foreach ($shipments as $row) {
                $orderId = (int) ($row['order_id'] ?? 0);
                $invoice = null;
                if ($orderId > 0) {
                    try {
                        $invRows = (new Inventory())->query(
                            'SELECT i.id, i.status, i.total_amount, je.id AS journal_id
                             FROM rateb_invoices i
                             LEFT JOIN rateb_journal_entries je ON je.source_type = \'invoice\'
                               AND je.source_id = i.id AND je.status = \'posted\' AND je.company_id = i.company_id
                             WHERE i.company_id = :cid AND (i.order_id = :oid OR i.pos_order_id = :oid)
                             ORDER BY i.id DESC LIMIT 1',
                            ['cid' => $companyId, 'oid' => $orderId]
                        );
                        $invoice = $invRows[0] ?? null;
                    } catch (\Throwable $e) {
                        $invoice = null;
                    }
                }
                $links[] = [
                    'shipment_id' => (int) ($row['id'] ?? 0),
                    'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                    'shipment_status' => (string) ($row['status'] ?? ''),
                    'order_id' => $orderId,
                    'order_total' => isset($row['order_total']) ? (float) $row['order_total'] : null,
                    'invoice_id' => (int) ($invoice['id'] ?? 0),
                    'invoice_status' => (string) ($invoice['status'] ?? ''),
                    'journal_id' => (int) ($invoice['journal_id'] ?? 0),
                    'domain_pair' => 'logistics→accounting',
                ];
            }
        } catch (\Throwable $e) {
            $links = [];
        }
        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'links' => $links,
                'count' => count($links),
                'note' => $links === [] ? 'no_logistics_accounting_links_found' : null,
            ],
            'error' => null,
        ];
    }

    private static function analyzeFinancialIntelligence(array $args, int $companyId): array
    {
        $limit = max(1, min(30, (int) ($args['limit'] ?? 15)));
        $accounting = self::analyzeAccounting(['limit' => $limit], $companyId);
        $sales = self::getSalesLinks(['limit' => $limit], $companyId);
        $proc = self::getProcurementLinks(['limit' => $limit], $companyId);
        $sup = self::getSupplierLinks(['limit' => $limit], $companyId);
        $inv = self::getInventoryLinks(['limit' => $limit], $companyId);
        $log = self::getLogisticsLinks(['limit' => $limit], $companyId);

        $accData = is_array($accounting['data'] ?? null) ? $accounting['data'] : [];
        $salesData = is_array($sales['data'] ?? null) ? $sales['data'] : [];
        $procData = is_array($proc['data'] ?? null) ? $proc['data'] : [];
        $supData = is_array($sup['data'] ?? null) ? $sup['data'] : [];
        $invData = is_array($inv['data'] ?? null) ? $inv['data'] : [];
        $logData = is_array($log['data'] ?? null) ? $log['data'] : [];

        $correlations = [];
        if (!empty($salesData['links'])) {
            $correlations[] = [
                'code' => 'sales_accounting',
                'domains' => ['sales', 'accounting'],
                'evidence_count' => count($salesData['links']),
                'mismatch_count' => (int) ($salesData['summary']['mismatch_count'] ?? 0),
            ];
        }
        if (!empty($procData['links'])) {
            $correlations[] = [
                'code' => 'procurement_accounting',
                'domains' => ['procurement', 'accounting'],
                'evidence_count' => count($procData['links']),
                'payment_gap_count' => (int) ($procData['summary']['payment_gap_count'] ?? 0),
            ];
        }
        if (!empty($supData['supplier_exposure'])) {
            $correlations[] = [
                'code' => 'supplier_accounting',
                'domains' => ['suppliers', 'accounting'],
                'evidence_count' => count($supData['supplier_exposure']),
            ];
        }
        if (!empty($invData['stock_journals'])) {
            $correlations[] = [
                'code' => 'inventory_accounting',
                'domains' => ['inventory', 'accounting'],
                'evidence_count' => count($invData['stock_journals']),
            ];
        }
        if (!empty($logData['links'])) {
            $correlations[] = [
                'code' => 'logistics_accounting',
                'domains' => ['logistics', 'accounting'],
                'evidence_count' => count($logData['links']),
            ];
        }

        $risks = [];
        foreach (($accData['overdue_receivables'] ?? []) as $row) {
            $risks[] = [
                'code' => 'overdue_receivable',
                'urgency' => 'high',
                'domain' => 'accounting',
                'amount' => $row['total_amount'] ?? null,
                'invoice_id' => $row['invoice_id'] ?? null,
            ];
        }
        foreach (($procData['payment_gaps'] ?? []) as $row) {
            $risks[] = [
                'code' => 'purchase_payment_mismatch',
                'urgency' => 'medium',
                'domain' => 'accounting',
                'amount' => $row['outstanding'] ?? null,
                'purchase_order_id' => $row['purchase_order_id'] ?? null,
            ];
        }
        foreach (($salesData['mismatches'] ?? []) as $row) {
            $risks[] = [
                'code' => 'sales_payment_ledger_gap',
                'urgency' => 'medium',
                'domain' => 'accounting',
                'invoice_id' => $row['invoice_id'] ?? null,
                'status' => $row['status'] ?? null,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'data_source' => 'live_tenant',
                'as_of' => date('Y-m-d'),
                'accounting_summary' => $accData['totals'] ?? [],
                'cfo_metrics' => $accData['cfo_metrics'] ?? [],
                'correlations' => $correlations,
                'risks' => array_slice($risks, 0, $limit),
                'follow_up' => array_slice($accData['follow_up'] ?? [], 0, $limit),
                'sales_links' => $salesData,
                'procurement_links' => $procData,
                'supplier_links' => $supData,
                'inventory_links' => $invData,
                'logistics_links' => $logData,
            ],
            'error' => null,
        ];
    }

    private static function submitJournalForApproval(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return self::fail('invalid_journal_id');
        }
        $reason = self::accounting()->submitJournalForApproval($id, $companyId);
        if ($reason !== null) {
            return [
                'success' => false,
                'data' => ['id' => $id],
                'error' => $reason,
                'error_code' => $reason,
                'error_message' => $reason,
            ];
        }
        $entry = self::getJournalEntry(['id' => $id], $companyId);
        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => (string) (($entry['data']['status'] ?? 'draft')),
                'submitted_for_approval_at' => (string) (($entry['data']['submitted_for_approval_at'] ?? '')),
                'entry' => $entry['data'] ?? null,
            ],
            'error' => null,
        ];
    }
}
