<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

/** Cross-company pending approvals for admin oversight (مراقبة الإدارة). */
final class ApprovalOversightService
{
    /** Sources where reject is not supported from oversight UI. */
    public static function rejectDisabledSources(): array
    {
        return ['asset_depreciation', 'hr_payroll'];
    }

    public static function canReject(string $sourceKey): bool
    {
        return !in_array($sourceKey, self::rejectDisabledSources(), true);
    }

    public static function canUndo(string $sourceKey): bool
    {
        return !in_array($sourceKey, ['workflow_instance', 'hr_payroll', 'company_registration'], true);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            '' => 'all_approval_types',
            'companies' => 'approval_category_companies',
            'workflow' => 'approval_category_workflow',
            'manager' => 'approval_category_manager',
            'accounting' => 'approval_category_accounting',
            'hr' => 'approval_category_hr',
            'operations' => 'approval_category_operations',
        ];
    }

    /** @return list<string> */
    public static function hrSourceKeys(): array
    {
        return ['hr_leave', 'hr_permission', 'hr_request', 'hr_decision', 'hr_payroll'];
    }

    /**
     * HR oversight type tabs: query value => [label key, source_key].
     *
     * @return array<string, array{label: string, source: string}>
     */
    public static function hrTypeOptions(): array
    {
        return [
            'leave' => ['label' => 'hr_leaves', 'source' => 'hr_leave'],
            'permission' => ['label' => 'hr_permission_requests', 'source' => 'hr_permission'],
            'request' => ['label' => 'hr_employee_requests', 'source' => 'hr_request'],
            'decision' => ['label' => 'hr_decisions', 'source' => 'hr_decision'],
            'payroll' => ['label' => 'hr_payroll', 'source' => 'hr_payroll'],
        ];
    }

    public static function hrSourceKeyForType(string $hrType): ?string
    {
        $opts = self::hrTypeOptions();
        return isset($opts[$hrType]) ? $opts[$hrType]['source'] : null;
    }

    /** @return array<string, int> */
    public function summary(?int $companyFilter = null, bool $bypassCache = false): array
    {
        // Never ALTER schema on list/count GET — that blocked oversight pages for tens of seconds.
        $cid = ($companyFilter !== null && $companyFilter > 0) ? (int) $companyFilter : 0;
        $sessionKey = 'rateb_approval_summary_v1_' . $cid;
        if (!$bypassCache) {
            $raw = \Rateb\App\Core\SessionManager::get($sessionKey);
            if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
                /** @var array<string, int> $cached */
                $cached = $raw['data'];
                return $cached;
            }
        }
        $counts = [];
        foreach ($this->sources() as $key => $source) {
            $counts[$key] = $this->countSource($source, $companyFilter);
        }
        $counts['total'] = array_sum($counts);
        try {
            \Rateb\App\Core\SessionManager::set($sessionKey, [
                'exp' => time() + 60,
                'data' => $counts,
            ]);
        } catch (\Throwable $e) {
            // Session warm is best-effort.
        }
        return $counts;
    }

    /**
     * Derive sidebar menu badges from a summary() payload (no extra COUNTs).
     *
     * @param array<string, int> $summary
     * @return array{approvals:int,hr:int,procurement:int,rfq:int,inventory:int,supplier_evaluations:int,company_pending:int,total:int}
     */
    public function menuCountsFromSummary(array $summary): array
    {
        $counts = [
            'approvals' => 0,
            'hr' => 0,
            'procurement' => 0,
            'rfq' => 0,
            'inventory' => 0,
            'supplier_evaluations' => 0,
            'company_pending' => 0,
        ];
        foreach ($this->sources() as $key => $source) {
            if ($key === 'workflow_instance') {
                $counts['approvals'] += (int) ($summary[$key] ?? 0);
                continue;
            }
            $n = (int) ($summary[$key] ?? 0);
            $menu = self::menuKeyForSource($key);
            $counts[$menu] += $n;
            if ($key === 'company_registration') {
                $counts['company_pending'] = $n;
            }
        }
        $counts['total'] = (int) ($summary['total'] ?? array_sum($counts));
        return $counts;
    }

    /**
     * Pending approval counts per admin oversight sidebar menu.
     *
     * @return array{approvals:int,hr:int,procurement:int,rfq:int,inventory:int,supplier_evaluations:int,total:int}
     */
    public function menuCounts(?int $companyFilter = null): array
    {
        // Never run schema ALTER from sidebar/nav — that blocked /admin for 30–60s.
        $counts = [
            'approvals' => 0,
            'hr' => 0,
            'procurement' => 0,
            'rfq' => 0,
            'inventory' => 0,
            'supplier_evaluations' => 0,
            'company_pending' => 0,
        ];
        foreach ($this->sources() as $key => $source) {
            if ($key === 'workflow_instance') {
                continue;
            }
            $menu = self::menuKeyForSource($key);
            $counts[$menu] += $this->countSource($source, $companyFilter);
            if ($key === 'company_registration') {
                $counts['company_pending'] = $this->countSource($source, $companyFilter);
            }
        }
        foreach ($this->countWorkflowInstancesByMenu($companyFilter) as $menu => $n) {
            $counts[$menu] += $n;
        }
        $counts['rfq'] += $this->countRfqQuotationsPending($companyFilter);
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    /**
     * Pending action counts for ops sidebar (المشتريات، المخزون، …).
     * Intentionally excludes oversight approval queue — those badges live under مراقبة الإدارة only.
     *
     * @return array<string, int>
     */
    public function opsNavCounts(?int $companyFilter = null): array
    {
        if (function_exists('rateb_oversight_approve_only') && rateb_oversight_approve_only()) {
            return $this->opsActionCounts($companyFilter);
        }

        // Never schema-ensure from nav badges (ALTER MODIFY hung Admin for tens of seconds).
        $counts = [];
        $add = static function (array &$counts, string $path, int $n): void {
            if ($path === '' || $n <= 0) {
                return;
            }
            $counts[$path] = ($counts[$path] ?? 0) + $n;
        };

        foreach ($this->sources() as $key => $source) {
            if (in_array($key, ['workflow_instance', 'company_registration'], true)) {
                continue;
            }
            $route = (string) ($source['route'] ?? '');
            if ($route === '') {
                continue;
            }
            $add($counts, $route, $this->countSource($source, $companyFilter));
        }

        foreach ($this->countWorkflowInstancesByRoute($companyFilter) as $route => $n) {
            $add($counts, $route, $n);
        }

        $rfq = $this->countRfqQuotationsPending($companyFilter);
        $add($counts, 'rfq', $rfq);
        $add($counts, 'quotations', $rfq);
        $add($counts, 'branch-transfers', $this->countBranchTransfersPending($companyFilter));

        return $counts;
    }

    /**
     * Ops-only work: drafts / rejected records still needing user action — not oversight approval queue.
     *
     * @return array<string, int>
     */
    private function opsActionCounts(?int $companyFilter): array
    {
        $counts = [];
        $add = static function (array &$counts, string $path, int $n): void {
            if ($path !== '' && $n > 0) {
                $counts[$path] = ($counts[$path] ?? 0) + $n;
            }
        };

        /** @var list<array{0:string,1:string,2:list<string>}> $specs */
        $specs = [
            ['purchase-requests', 'rateb_purchase_requests', ['draft', 'rejected']],
            ['purchase-orders', 'rateb_purchase_orders', ['draft']],
            ['rfq', 'rateb_rfq', ['draft']],
            ['supplier-evaluations', 'rateb_supplier_evaluations', ['draft']],
            ['inventory-audits', 'rateb_inventory_audits', ['draft']],
            ['journal-entries', 'rateb_journal_entries', ['draft']],
            ['cash-vouchers', 'rateb_cash_vouchers', ['draft']],
            ['contracts', 'rateb_contracts', ['draft']],
            ['hr/payroll', 'rateb_payroll_periods', ['draft']],
        ];

        foreach ($specs as [$path, $table, $statuses]) {
            $add($counts, $path, $this->countTableStatuses($table, $statuses, $companyFilter));
        }

        return $counts;
    }

    /**
     * @param list<string> $statuses
     */
    private function countTableStatuses(string $table, array $statuses, ?int $companyFilter): int
    {
        if ($statuses === [] || !$this->tableExists($table)) {
            return 0;
        }
        if (!$this->tableHasColumn($table, 'status')) {
            return 0;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql = "SELECT COUNT(*) FROM {$table} t WHERE t.status IN ({$placeholders})";
            $params = $statuses;
            if ($companyFilter !== null && $companyFilter > 0 && $this->tableHasColumn($table, 'company_id')) {
                $sql .= ' AND t.company_id = ?';
                $params[] = $companyFilter;
            }
            $db = Database::connection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $safeTable = str_replace('`', '', $table);
        $safeCol = str_replace('`', '', $column);
        if (!$this->tableExists($safeTable)) {
            return false;
        }
        // Reuse Database::$columnCache — single source of truth for column existence.
        return Database::tableHasColumn($safeTable, $safeCol);
    }

    public static function routeKeyForWorkflowEntity(string $entityType): string
    {
        return match ($entityType) {
            'purchase_request' => 'purchase-requests',
            'purchase_order' => 'purchase-orders',
            'supplier_evaluation' => 'supplier-evaluations',
            'warehouse_transfer' => 'warehouse-transfers',
            'inventory_audit' => 'inventory-audits',
            default => '',
        };
    }

    /** Notify platform super-admins that a record awaits oversight approval. */
    public static function notifyPendingSubmission(
        int $companyId,
        string $entityType,
        string $entityLabel,
        int $entityId
    ): void {
        if ($entityId < 1) {
            return;
        }
        (new NotificationService())->notifyOversightPending($companyId, $entityLabel, $entityType, $entityId);
    }

    public static function menuKeyForSource(string $sourceKey): string
    {
        return match ($sourceKey) {
            'company_registration' => 'approvals',
            'hr_leave', 'hr_permission', 'hr_request', 'hr_payroll', 'hr_decision' => 'hr',
            'supplier_evaluation' => 'supplier_evaluations',
            'inventory_audit', 'warehouse_transfer' => 'inventory',
            default => 'approvals',
        };
    }

    public static function menuKeyForWorkflowEntity(string $entityType): string
    {
        return match ($entityType) {
            'purchase_request', 'purchase_order' => 'procurement',
            'supplier_evaluation' => 'supplier_evaluations',
            'warehouse_transfer', 'inventory_audit' => 'inventory',
            default => 'approvals',
        };
    }

    /** @return array<string, int> */
    private function countWorkflowInstancesByMenu(?int $companyFilter): array
    {
        $counts = [
            'approvals' => 0,
            'procurement' => 0,
            'rfq' => 0,
            'inventory' => 0,
            'supplier_evaluations' => 0,
        ];
        try {
            $sql = 'SELECT entity_type, COUNT(*) AS c FROM rateb_approval_instances i WHERE i.status = \'pending\'';
            $params = [];
            if ($companyFilter !== null && $companyFilter > 0) {
                $sql .= ' AND i.company_id = :cid';
                $params['cid'] = $companyFilter;
            }
            $sql .= ' GROUP BY entity_type';
            $db = Database::connection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $menu = self::menuKeyForWorkflowEntity((string) ($row['entity_type'] ?? ''));
                $counts[$menu] += (int) ($row['c'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Table may not exist before migrations.
        }
        return $counts;
    }

    /** @return array<string, int> */
    private function countWorkflowInstancesByRoute(?int $companyFilter): array
    {
        $counts = [];
        try {
            $sql = 'SELECT entity_type, COUNT(*) AS c FROM rateb_approval_instances i WHERE i.status = \'pending\'';
            $params = [];
            if ($companyFilter !== null && $companyFilter > 0) {
                $sql .= ' AND i.company_id = :cid';
                $params['cid'] = $companyFilter;
            }
            $sql .= ' GROUP BY entity_type';
            $db = Database::connection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $route = self::routeKeyForWorkflowEntity((string) ($row['entity_type'] ?? ''));
                if ($route === '') {
                    continue;
                }
                $counts[$route] = ($counts[$route] ?? 0) + (int) ($row['c'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Table may not exist before migrations.
        }
        return $counts;
    }

    private function countBranchTransfersPending(?int $companyFilter): int
    {
        if (!$this->tableExists('rateb_branch_transfers')) {
            return 0;
        }
        try {
            $sql = 'SELECT COUNT(*) FROM rateb_branch_transfers t WHERE t.status = :st';
            $params = ['st' => 'pending'];
            if ($companyFilter !== null && $companyFilter > 0) {
                $sql .= ' AND t.company_id = :cid';
                $params['cid'] = $companyFilter;
            }
            $db = Database::connection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countRfqQuotationsPending(?int $companyFilter): int
    {
        if (!$this->tableExists('rateb_supplier_quotations')) {
            return 0;
        }
        try {
            $sql = 'SELECT COUNT(*) FROM rateb_supplier_quotations q WHERE q.status = :st';
            $params = ['st' => 'under_review'];
            if ($companyFilter !== null && $companyFilter > 0) {
                $sql .= ' AND q.company_id = :cid';
                $params['cid'] = $companyFilter;
            }
            $db = Database::connection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPending(?int $companyFilter = null, ?string $typeFilter = null, int $limit = 200, ?string $sourceKeyFilter = null): array
    {
        // Never ALTER schema on list GET — approve/reject paths ensure columns when needed.
        $items = [];
        $sourceCount = $sourceKeyFilter !== null && $sourceKeyFilter !== '' ? 1 : count($this->sources());
        $perSource = max(10, (int) ceil($limit / max(1, $sourceCount)));
        foreach ($this->sources() as $key => $source) {
            if ($sourceKeyFilter !== null && $sourceKeyFilter !== '' && $key !== $sourceKeyFilter) {
                continue;
            }
            if ($typeFilter !== null && $typeFilter !== '' && ($source['category'] ?? '') !== $typeFilter) {
                continue;
            }
            foreach ($this->fetchSource($source, $companyFilter, $perSource) as $row) {
                $row['source_key'] = $key;
                $row['can_reject'] = self::canReject($key);
                $items[] = $row;
            }
        }
        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['submitted_at'] ?? ''), (string) ($a['submitted_at'] ?? ''));
        });
        return array_slice($items, 0, max(1, min(500, $limit)));
    }

    /** @return array<string, array<string, mixed>> */
    private function sources(): array
    {
        return [
            'workflow_instance' => [
                'category' => 'workflow',
                'label' => 'approval_type_workflow',
                'queue_route' => 'workflows',
            ],
            'company_registration' => [
                'category' => 'companies',
                'label' => 'companies_approvals_oversight',
                'table' => 'rateb_companies',
                'no_column' => 'name',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'platform_entity' => true,
                'admin_route' => 'admin/companies',
            ],
            'supplier_evaluation' => [
                'category' => 'manager',
                'label' => 'supplier_evaluations',
                'table' => 'rateb_supplier_evaluations',
                'no_column' => 'evaluation_no',
                'date_column' => 'evaluation_date',
                'route' => 'supplier-evaluations',
                'queue_route' => 'supplier-evaluations/approvals',
            ],
            'contract_renewal' => [
                'category' => 'manager',
                'label' => 'contract_renewals',
                'table' => 'rateb_contract_renewals',
                'no_column' => 'renewal_no',
                'date_column' => 'created_at',
                'route' => 'contract-renewals',
                'queue_route' => 'contract-renewals',
            ],
            'asset_maintenance' => [
                'category' => 'manager',
                'label' => 'asset_maintenance',
                'table' => 'rateb_asset_maintenance',
                'no_column' => 'maintenance_no',
                'date_column' => 'created_at',
                'route' => 'asset-maintenance',
                'queue_route' => 'asset-maintenance',
            ],
            'asset_assignment' => [
                'category' => 'manager',
                'label' => 'asset_assignments',
                'table' => 'rateb_asset_assignments',
                'no_column' => 'assignment_no',
                'date_column' => 'created_at',
                'route' => 'asset-assignments',
                'queue_route' => 'asset-assignments',
            ],
            'device_maintenance' => [
                'category' => 'manager',
                'label' => 'device_maintenance',
                'table' => 'rateb_device_service_history',
                'no_column' => 'service_no',
                'date_column' => 'created_at',
                'route' => 'device-maintenance',
                'queue_route' => 'device-maintenance',
            ],
            'device_spare_part' => [
                'category' => 'manager',
                'label' => 'device_spare_parts',
                'table' => 'rateb_device_spare_parts',
                'no_column' => 'part_no',
                'date_column' => 'created_at',
                'route' => 'device-spare-parts',
                'queue_route' => 'device-spare-parts',
            ],
            'inventory_audit' => [
                'category' => 'manager',
                'label' => 'inventory_audits',
                'table' => 'rateb_inventory_audits',
                'no_column' => 'audit_no',
                'date_column' => 'created_at',
                'route' => 'inventory-audits',
                'queue_route' => 'inventory-audits',
            ],
            'journal_entry' => [
                'category' => 'accounting',
                'label' => 'journal_entries',
                'table' => 'rateb_journal_entries',
                'no_column' => 'entry_no',
                'date_column' => 'submitted_for_approval_at',
                'status_column' => 'status',
                'status_value' => 'draft',
                'fixed_filters' => ['source_type' => 'manual'],
                'requires_submission' => true,
                'route' => 'journal-entries',
                'queue_route' => 'accounting/entry-approval',
            ],
            'cash_voucher' => [
                'category' => 'accounting',
                'label' => 'cash_vouchers',
                'table' => 'rateb_cash_vouchers',
                'no_column' => 'voucher_no',
                'date_column' => 'submitted_for_approval_at',
                'status_column' => 'status',
                'status_value' => 'draft',
                'requires_submission' => true,
                'route' => 'cash-vouchers',
                'queue_route' => 'accounting/voucher-approval',
            ],
            'warehouse_transfer' => [
                'category' => 'operations',
                'label' => 'warehouse_transfers',
                'table' => 'rateb_warehouse_transfers',
                'no_column' => 'transfer_no',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'route' => 'warehouse-transfers',
                'queue_route' => 'warehouse-transfers',
            ],
            'asset_depreciation' => [
                'category' => 'operations',
                'label' => 'asset_depreciation',
                'table' => 'rateb_asset_depreciation',
                'no_column' => 'depreciation_no',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'draft',
                'route' => 'asset-depreciation',
                'queue_route' => 'asset-depreciation',
            ],
            'contract' => [
                'category' => 'operations',
                'label' => 'contracts',
                'table' => 'rateb_contracts',
                'no_column' => 'contract_no',
                'date_column' => 'created_at',
                'status_column' => 'approval_status',
                'status_value' => 'pending',
                'route' => 'contracts',
                'queue_route' => 'contracts',
            ],
            'hr_leave' => [
                'category' => 'hr',
                'label' => 'hr_leaves',
                'table' => 'rateb_leave_requests',
                'reference_expr' => 'CONCAT(\'LR-\', t.id)',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'route' => 'hr/leaves',
                'queue_route' => 'hr/leaves',
            ],
            'hr_permission' => [
                'category' => 'hr',
                'label' => 'hr_permission_requests',
                'table' => 'rateb_hr_permission_requests',
                'reference_expr' => 'CONCAT(\'HP-\', t.id)',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'route' => 'hr/permission-requests',
                'queue_route' => 'hr/permission-requests',
            ],
            'hr_request' => [
                'category' => 'hr',
                'label' => 'hr_employee_requests',
                'table' => 'rateb_hr_employee_requests',
                'no_column' => 'request_no',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'route' => 'hr/requests',
                'queue_route' => 'hr/requests',
            ],
            'hr_payroll' => [
                'category' => 'hr',
                'label' => 'hr_payroll',
                'table' => 'rateb_payroll_periods',
                'reference_expr' => 'CONCAT(t.period_year, \'-\', LPAD(t.period_month, 2, \'0\'))',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'draft',
                'route' => 'hr/payroll',
                'queue_route' => 'hr/payroll',
            ],
            'hr_decision' => [
                'category' => 'hr',
                'label' => 'hr_decisions',
                'table' => 'rateb_hr_decisions',
                'no_column' => 'decision_no',
                'date_column' => 'created_at',
                'status_column' => 'status',
                'status_value' => 'pending',
                'route' => 'hr/decisions',
                'queue_route' => 'hr/decisions',
            ],
        ];
    }

    /** @param array<string, mixed> $source */
    private function countSource(array $source, ?int $companyFilter): int
    {
        if (($source['table'] ?? '') === '') {
            return $this->countWorkflowInstances($companyFilter);
        }
        if (!$this->tableExists((string) $source['table'])) {
            return 0;
        }
        $sql = $this->buildSelectSql($source, $companyFilter, true);
        $db = Database::connection();
        $stmt = $db->prepare($sql['sql']);
        $stmt->execute($sql['params']);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @param array<string, mixed> $source
     * @return array<int, array<string, mixed>>
     */
    private function fetchSource(array $source, ?int $companyFilter, int $limit): array
    {
        if (($source['table'] ?? '') === '') {
            return $this->fetchWorkflowInstances($companyFilter, $limit);
        }
        if (!$this->tableExists((string) $source['table'])) {
            return [];
        }
        $built = $this->buildSelectSql($source, $companyFilter, false, $limit);
        $db = Database::connection();
        $stmt = $db->prepare($built['sql']);
        $stmt->execute($built['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['id'] ?? 0);
            $companyId = !empty($source['platform_entity'])
                ? $entityId
                : (int) ($row['company_id'] ?? 0);
            $ref = trim((string) ($row['reference'] ?? ''));
            if ($ref === '' && $entityId > 0) {
                $ref = '#' . $entityId;
            }
            $route = (string) ($source['route'] ?? '');
            $adminRoute = (string) ($source['admin_route'] ?? '');
            $queueRoute = (string) ($source['queue_route'] ?? $route);
            if ($adminRoute !== '') {
                $viewUrl = rateb_url($adminRoute . '/' . $entityId . '/edit');
                $editUrl = $viewUrl;
            } else {
                $viewUrl = $this->opsUrl($route . '/' . $entityId, $companyId);
                $editUrl = $this->opsUrl($route . '/' . $entityId . '/edit', $companyId);
            }
            $out[] = [
                'category' => (string) ($source['category'] ?? ''),
                'type_label' => __((string) ($source['label'] ?? '')),
                'company_id' => $companyId,
                'company_name' => (string) ($row['company_name'] ?? ''),
                'reference' => $ref,
                'record_id' => $entityId,
                'entity_id' => $entityId,
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'view_url' => $viewUrl,
                'edit_url' => $editUrl,
                'queue_url' => $queueRoute !== '' ? $this->opsUrl($queueRoute, $companyId) : '',
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $source
     * @return array{sql: string, params: array<string, int|string>}
     */
    private function buildSelectSql(array $source, ?int $companyFilter, bool $countOnly, int $limit = 50): array
    {
        $table = (string) $source['table'];
        $alias = 't';
        $noCol = (string) ($source['no_column'] ?? 'id');
        $refExpr = isset($source['reference_expr']) ? (string) $source['reference_expr'] : ($alias . '.' . $noCol);
        $dateCol = (string) ($source['date_column'] ?? 'created_at');
        $params = [];
        $where = ' WHERE 1=1';

        if (isset($source['status_column'], $source['status_value'])) {
            $where .= ' AND ' . $alias . '.' . $source['status_column'] . ' = :st';
            $params['st'] = (string) $source['status_value'];
        } else {
            $where .= ' AND ' . $alias . '.manager_approval = :st';
            $params['st'] = 'pending';
        }

        if ($companyFilter !== null && $companyFilter > 0) {
            if (!empty($source['platform_entity'])) {
                $where .= ' AND ' . $alias . '.id = :cid';
            } else {
                $where .= ' AND ' . $alias . '.company_id = :cid';
            }
            $params['cid'] = $companyFilter;
        }

        foreach ($source['fixed_filters'] ?? [] as $col => $val) {
            $paramKey = 'ff_' . preg_replace('/[^a-z0-9_]/', '_', (string) $col);
            $where .= ' AND ' . $alias . '.' . $col . ' = :' . $paramKey;
            $params[$paramKey] = (string) $val;
        }

        if (!empty($source['requires_submission'])) {
            $where .= ' AND ' . $alias . '.submitted_for_approval_at IS NOT NULL';
        }

        if ($countOnly) {
            return [
                'sql' => 'SELECT COUNT(*) FROM ' . $table . ' ' . $alias . $where,
                'params' => $params,
            ];
        }

        if (!empty($source['platform_entity'])) {
            $sql = sprintf(
                'SELECT %s.id, %s.id AS company_id, %s AS reference, %s.%s AS submitted_at, %s.name AS company_name
                 FROM %s %s
                 %s
                 ORDER BY %s.%s DESC
                 LIMIT %d',
                $alias,
                $alias,
                $refExpr,
                $alias,
                $dateCol,
                $alias,
                $table,
                $alias,
                $where,
                $alias,
                $dateCol,
                max(1, min(500, $limit))
            );
            return ['sql' => $sql, 'params' => $params];
        }

        $sql = sprintf(
            'SELECT %s.id, %s.company_id, %s AS reference, %s.%s AS submitted_at, c.name AS company_name
             FROM %s %s
             LEFT JOIN rateb_companies c ON c.id = %s.company_id
             %s
             ORDER BY %s.%s DESC
             LIMIT %d',
            $alias,
            $alias,
            $refExpr,
            $alias,
            $dateCol,
            $table,
            $alias,
            $alias,
            $where,
            $alias,
            $dateCol,
            max(1, min(100, $limit))
        );

        return ['sql' => $sql, 'params' => $params];
    }

    private function countWorkflowInstances(?int $companyFilter): int
    {
        $sql = 'SELECT COUNT(*) FROM rateb_approval_instances i WHERE i.status = \'pending\'';
        $params = [];
        if ($companyFilter !== null && $companyFilter > 0) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyFilter;
        }
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchWorkflowInstances(?int $companyFilter, int $limit): array
    {
        $rows = (new WorkflowService())->listPending($companyFilter, $limit);
        $out = [];
        foreach ($rows as $row) {
            $entityType = (string) ($row['entity_type'] ?? '');
            $entityId = (int) ($row['entity_id'] ?? 0);
            $companyId = (int) ($row['company_id'] ?? 0);
            $instanceId = (int) ($row['id'] ?? 0);
            $out[] = [
                'category' => 'workflow',
                'type_label' => WorkflowService::entityTypeLabel($entityType),
                'company_id' => $companyId,
                'company_name' => (string) ($row['company_name'] ?? ''),
                'reference' => WorkflowService::entityTypeLabel($entityType) . ' #' . $entityId,
                'record_id' => $instanceId,
                'entity_id' => $entityId,
                'submitted_at' => (string) ($row['created_at'] ?? ''),
                'view_url' => WorkflowService::entityDocumentUrl($entityType, $entityId, $companyId),
                'workflow_name' => (string) ($row['workflow_name'] ?? ''),
            ];
        }
        return $out;
    }

    private function opsUrl(string $path, int $companyId): string
    {
        $url = rateb_url(rateb_app_route(ltrim($path, '/')));
        if ($companyId > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'company_id=' . $companyId;
        }
        return $url;
    }

    private function tableExists(string $table): bool
    {
        return Database::tableExists($table);
    }

    /** @return array<string, string> */
    private function managerSlugs(): array
    {
        return [
            'contract_renewal' => 'contract-renewals',
            'asset_maintenance' => 'asset-maintenance',
            'asset_assignment' => 'asset-assignments',
            'device_maintenance' => 'device-maintenance',
            'device_spare_part' => 'device-spare-parts',
            'inventory_audit' => 'inventory-audits',
        ];
    }

    public function process(string $sourceKey, int $recordId, int $companyId, string $action): void
    {
        try {
            $this->processOnce($sourceKey, $recordId, $companyId, $action);
        } catch (\Throwable $e) {
            if (!$this->shouldAutoMigrate($e)) {
                throw $e;
            }
            try {
                (new MigrationService())->runAll();
                ManagerApprovalSchema::clearCache();
            } catch (\Throwable $ignored) {
                // Retry with whatever schema we have.
            }
            $this->processOnce($sourceKey, $recordId, $companyId, $action);
        }
    }

    private function shouldAutoMigrate(\Throwable $e): bool
    {
        if (DatabaseErrorService::isSchemaIssue($e)) {
            return true;
        }
        $msg = trim($e->getMessage());
        $generic = __('db_operation_failed');
        return $msg === $generic || str_contains($msg, __('db_schema_outdated'));
    }

    private function processOnce(string $sourceKey, int $recordId, int $companyId, string $action): void
    {
        if ($recordId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $action = $action === 'reject' ? 'reject' : 'approve';
        $resolvedCompanyId = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
        if ($resolvedCompanyId !== null && $resolvedCompanyId > 0) {
            $companyId = $resolvedCompanyId;
        }
        $this->bootstrapCompany($companyId);
        $uid = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0);

        if ($sourceKey === 'workflow_instance') {
            $wf = new WorkflowService();
            $ok = $action === 'approve'
                ? $wf->approveAsOversight($recordId)
                : $wf->rejectAsOversight($recordId);
            if (!$ok) {
                throw new \RuntimeException(__('manager_approval_already_processed'));
            }
            return;
        }

        if ($sourceKey === 'supplier_evaluation') {
            $this->setManagerApproval('rateb_supplier_evaluations', $recordId, $companyId, $action, $uid);
            if ($action === 'approve') {
                try {
                    $db = Database::connection();
                    $stmt = $db->prepare('SELECT supplier_id FROM rateb_supplier_evaluations WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $recordId]);
                    $supplierId = (int) ($stmt->fetchColumn() ?: 0);
                    if ($supplierId > 0) {
                        (new SupplierEvaluationService())->refreshSupplierRating($supplierId);
                    }
                } catch (\Throwable $e) {
                    // Rating refresh is optional; approval already saved.
                }
            }
            return;
        }

        if ($sourceKey === 'contract_renewal') {
            $svc = new ContractWorkflowService();
            $this->runDb(function () use ($svc, $recordId, $uid, $action): void {
                if ($action === 'approve') {
                    $svc->approveRenewal($recordId, $uid);
                } else {
                    $svc->rejectRenewal($recordId, $uid);
                }
            });
            return;
        }

        if (isset($this->managerSlugs()[$sourceKey])) {
            $rec = new WorkflowRecordService();
            $slug = $this->managerSlugs()[$sourceKey];
            $this->runDb(static function () use ($rec, $slug, $recordId, $action): void {
                if ($action === 'approve') {
                    $rec->approve($slug, $recordId);
                } else {
                    $rec->reject($slug, $recordId);
                }
            });
            return;
        }

        if ($sourceKey === 'journal_entry') {
            $this->ensureAccountingSubmitSchema();
            $acct = new AccountingService();
            $cid = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
            if ($action === 'approve') {
                $reason = $acct->postDraftEntryReason($recordId, $cid, true);
                if ($reason !== null) {
                    throw new \RuntimeException(__($reason));
                }
            } elseif (!$acct->rejectManualDraft($recordId, $cid, '', $uid > 0 ? $uid : null)) {
                throw new \RuntimeException(__('journal_reject_failed'));
            }
            return;
        }

        if ($sourceKey === 'cash_voucher') {
            $this->ensureAccountingSubmitSchema();
            $acct = new AccountingService();
            $cid = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
            if ($action === 'approve') {
                $reason = $acct->postCashVoucherReason($recordId, $cid, true);
                if ($reason !== null) {
                    $msg = __($reason);
                    $detail = $acct->lastVoucherPostDetail();
                    if ($detail !== '' && !str_contains($msg, $detail)) {
                        $msg .= ' — ' . $detail;
                    }
                    throw new \RuntimeException($msg);
                }
            } elseif (!$acct->rejectCashVoucherDraft($recordId, $cid, '', $uid > 0 ? $uid : null)) {
                throw new \RuntimeException(__('voucher_reject_failed'));
            }
            return;
        }

        if ($sourceKey === 'warehouse_transfer') {
            $inv = new InventoryWorkflowService();
            if ($action === 'approve') {
                if (!$inv->approveTransfer($recordId)) {
                    throw new \RuntimeException(__('invalid_request'));
                }
            } else {
                $db = Database::connection();
                $sql = 'UPDATE rateb_warehouse_transfers SET status = :st WHERE id = :id AND status = :pending';
                $params = ['st' => 'rejected', 'id' => $recordId, 'pending' => 'pending'];
                if ($companyId > 0) {
                    $sql .= ' AND company_id = :cid';
                    $params['cid'] = $companyId;
                }
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('invalid_request'));
                }
            }
            return;
        }

        if ($sourceKey === 'asset_depreciation') {
            $asset = new AssetDeviceWorkflowService();
            if ($action === 'approve') {
                if ($companyId < 1) {
                    throw new \RuntimeException(__('select_company_ops'));
                }
                try {
                    if (!$asset->approveDepreciationForCompany($recordId, $companyId)) {
                        throw new \RuntimeException(__('invalid_request'));
                    }
                } catch (\PDOException $e) {
                    throw DatabaseErrorService::toRuntimeException($e);
                }
            } else {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }

        if ($sourceKey === 'contract') {
            ManagerApprovalSchema::ensureContractApprovalStatus();
            ManagerApprovalSchema::normalizeContractApprovalStatus();
            try {
                $db = Database::connection();
                $sql = 'UPDATE rateb_contracts SET approval_status = :st WHERE id = :id AND approval_status = :pending';
                $params = [
                    'st' => $action === 'approve' ? 'approved' : 'rejected',
                    'id' => $recordId,
                    'pending' => 'pending',
                ];
                if ($companyId > 0 && !(function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
                    $sql .= ' AND company_id = :cid';
                    $params['cid'] = $companyId;
                }
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() < 1) {
                    throw new \RuntimeException(__('manager_approval_already_processed'));
                }
            } catch (\PDOException $e) {
                throw DatabaseErrorService::toRuntimeException($e);
            }
            return;
        }

        $hr = new HrService();
        if ($sourceKey === 'hr_leave' || $sourceKey === 'hr_permission' || $sourceKey === 'hr_request' || $sourceKey === 'hr_decision') {
            // Phase G/M: optional matrix overlay — domain finalize only on final stage / passthrough.
            $gate = (new HrApprovalMatrixService())->gateOversightDecision(
                $sourceKey,
                $recordId,
                $companyId,
                $action,
                $uid
            );
            if ($gate === HrApprovalMatrixService::OUTCOME_STAGE_ADVANCED) {
                return;
            }
            if ($sourceKey === 'hr_leave') {
                if ($action === 'approve') {
                    $hr->approveLeave($recordId, $uid);
                } else {
                    $hr->rejectLeave($recordId, $uid);
                }
                return;
            }
            if ($sourceKey === 'hr_permission') {
                $this->setHrStatus('rateb_hr_permission_requests', $recordId, $companyId, $action, $uid);
                return;
            }
            if ($sourceKey === 'hr_decision') {
                $dec = new HrDecisionService();
                if ($action === 'approve') {
                    $dec->finalizeApprove($companyId, $recordId, $uid);
                } else {
                    $dec->finalizeReject($companyId, $recordId, $uid);
                }
                return;
            }
            $this->setEmployeeRequestStatus($recordId, $companyId, $action, $uid);
            return;
        }
        if ($sourceKey === 'hr_payroll') {
            if ($action === 'approve') {
                $hr->approvePayroll($recordId, $companyId > 0 ? $companyId : null);
            } else {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }

        if ($sourceKey === 'company_registration') {
            $model = new \Rateb\App\Models\Company();
            $row = $model->find($recordId);
            if (!$row || (string) ($row['status'] ?? '') !== 'pending') {
                throw new \RuntimeException(__('manager_approval_already_processed'));
            }
            if ($action === 'approve') {
                $model->activate($recordId);
                (new \Rateb\App\Services\AuthorizationService())->ensureCompanyRoles($recordId);
                $planId = (int) ($row['plan_id'] ?? 0);
                if ($planId > 0) {
                    (new \Rateb\App\Services\BillingService())->ensureInitialSubscription($recordId, $planId, 'active');
                }
                (new \Rateb\App\Services\AuditService())->log('oversight_activate', 'company', $recordId);
            } else {
                $model->suspend($recordId);
                (new \Rateb\App\Services\AuditService())->log('oversight_reject', 'company', $recordId);
            }
            return;
        }

        throw new \RuntimeException(__('invalid_request'));
    }

    /** @return array<string, mixed> */
    public function detail(string $sourceKey, int $recordId, int $companyId): array
    {
        if ($recordId < 1 || !isset($this->sources()[$sourceKey])) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $resolvedCompanyId = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
        if ($resolvedCompanyId !== null && $resolvedCompanyId > 0) {
            $companyId = $resolvedCompanyId;
        }
        $this->bootstrapCompany($companyId);
        if ($sourceKey === 'workflow_instance') {
            return $this->detailWorkflowInstance($recordId, $companyId);
        }
        if ($sourceKey === 'company_registration') {
            return $this->detailCompanyRegistration($recordId);
        }
        $source = $this->sources()[$sourceKey];
        $table = (string) ($source['table'] ?? '');
        if ($table === '' || !$this->tableExists($table)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $sql = 'SELECT t.*, c.name AS company_name FROM ' . $table . ' t LEFT JOIN rateb_companies c ON c.id = t.company_id WHERE t.id = :id';
        $params = ['id' => $recordId];
        if ($companyId > 0 && !\Rateb\App\Core\TenantContext::isSuperAdmin()) {
            $sql .= ' AND t.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        $status = $this->recordStatus($sourceKey, $source, $row);
        $noCol = (string) ($source['no_column'] ?? 'id');
        $ref = trim((string) ($row[$noCol] ?? ''));
        if ($ref === '' && isset($source['reference_expr'])) {
            $ref = '#' . $recordId;
        }
        $dateCol = (string) ($source['date_column'] ?? 'created_at');
        $route = (string) ($source['route'] ?? '');
        return [
            'source_key' => $sourceKey,
            'record_id' => $recordId,
            'company_id' => (int) ($row['company_id'] ?? $companyId),
            'company_name' => (string) ($row['company_name'] ?? ''),
            'type_label' => __((string) ($source['label'] ?? '')),
            'reference' => $ref !== '' ? $ref : ('#' . $recordId),
            'submitted_at' => (string) ($row[$dateCol] ?? $row['created_at'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'fields' => $this->detailFields($sourceKey, $row),
            'view_url' => $this->opsUrl($route . '/' . $recordId, (int) ($row['company_id'] ?? $companyId)),
            'edit_url' => $this->opsUrl($route . '/' . $recordId . '/edit', (int) ($row['company_id'] ?? $companyId)),
            'can_approve' => $this->canActOnStatus($status, 'approve'),
            'can_reject' => $this->canActOnStatus($status, 'reject') && self::canReject($sourceKey),
            'can_undo' => $this->canActOnStatus($status, 'undo') && self::canUndo($sourceKey),
        ];
    }

    public function undo(string $sourceKey, int $recordId, int $companyId): void
    {
        try {
            $this->undoOnce($sourceKey, $recordId, $companyId);
        } catch (\Throwable $e) {
            if (!$this->shouldAutoMigrate($e)) {
                throw $e;
            }
            try {
                (new MigrationService())->runAll();
                ManagerApprovalSchema::clearCache();
            } catch (\Throwable $ignored) {
                // Retry with whatever schema we have.
            }
            $this->undoOnce($sourceKey, $recordId, $companyId);
        }
    }

    private function undoOnce(string $sourceKey, int $recordId, int $companyId): void
    {
        if ($recordId < 1 || !self::canUndo($sourceKey)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $resolvedCompanyId = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
        if ($resolvedCompanyId !== null && $resolvedCompanyId > 0) {
            $companyId = $resolvedCompanyId;
        }
        $this->bootstrapCompany($companyId);

        if ($sourceKey === 'supplier_evaluation') {
            $this->resetManagerApproval('rateb_supplier_evaluations', $recordId, $companyId);
            return;
        }
        if (isset($this->managerSlugs()[$sourceKey])) {
            $source = $this->sources()[$sourceKey];
            $this->resetManagerApproval((string) ($source['table'] ?? ''), $recordId, $companyId);
            if ($sourceKey === 'contract_renewal') {
                Database::connection()->prepare(
                    "UPDATE rateb_contract_renewals SET status = 'planned' WHERE id = :id AND status = 'cancelled'"
                )->execute(['id' => $recordId]);
            }
            return;
        }
        if ($sourceKey === 'asset_depreciation') {
            $cid = $companyId > 0 ? $companyId : ($this->resolveCompanyId($sourceKey, $recordId, $companyId) ?? 0);
            if ($cid < 1) {
                throw new \RuntimeException(__('select_company_ops'));
            }
            if (!(new AssetDeviceWorkflowService())->undoDepreciationForCompany($recordId, $cid)) {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'journal_entry') {
            $acct = new AccountingService();
            $cid = $companyId > 0 ? $companyId : null;
            if (!$acct->undoJournalFromOversight($recordId, $cid)) {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'cash_voucher') {
            $acct = new AccountingService();
            $cid = $companyId > 0 ? $companyId : null;
            if (!$acct->undoCashVoucherFromOversight($recordId, $cid)) {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'warehouse_transfer') {
            $db = Database::connection();
            $sql = 'UPDATE rateb_warehouse_transfers SET status = :st WHERE id = :id AND status IN (\'approved\', \'rejected\')';
            $params = ['st' => 'pending', 'id' => $recordId];
            if ($companyId > 0) {
                $sql .= ' AND company_id = :cid';
                $params['cid'] = $companyId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'contract') {
            $db = Database::connection();
            $sql = 'UPDATE rateb_contracts SET approval_status = :st WHERE id = :id AND approval_status IN (\'approved\', \'rejected\')';
            $params = ['st' => 'pending', 'id' => $recordId];
            if ($companyId > 0) {
                $sql .= ' AND company_id = :cid';
                $params['cid'] = $companyId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'hr_leave') {
            (new HrService())->undoLeaveApproval($recordId);
            (new HrApprovalMatrixService())->resetProgress($sourceKey, $recordId, $companyId);
            return;
        }
        if ($sourceKey === 'hr_permission') {
            $this->resetHrStatus('rateb_hr_permission_requests', $recordId, $companyId);
            (new HrApprovalMatrixService())->resetProgress($sourceKey, $recordId, $companyId);
            return;
        }
        if ($sourceKey === 'hr_request') {
            $db = Database::connection();
            $sql = 'UPDATE rateb_hr_employee_requests SET status = :st, processed_by = NULL, processed_at = NULL WHERE id = :id AND status IN (\'approved\', \'rejected\')';
            $params = ['st' => 'pending', 'id' => $recordId];
            if ($companyId > 0) {
                $sql .= ' AND company_id = :cid';
                $params['cid'] = $companyId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
            }
            (new HrApprovalMatrixService())->resetProgress($sourceKey, $recordId, $companyId);
            return;
        }
        if ($sourceKey === 'hr_decision') {
            (new HrDecisionService())->undoToPending($companyId, $recordId);
            (new HrApprovalMatrixService())->resetProgress($sourceKey, $recordId, $companyId);
            return;
        }
        if ($sourceKey === 'contract_renewal') {
            $this->resetManagerApproval('rateb_contract_renewals', $recordId, $companyId);
            return;
        }

        throw new \RuntimeException(__('invalid_request'));
    }

    /** @return array<string, mixed> */
    private function detailCompanyRegistration(int $recordId): array
    {
        $model = new \Rateb\App\Models\Company();
        $row = $model->find($recordId);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        $status = (string) ($row['status'] ?? 'pending');
        $editUrl = rateb_url('admin/companies/' . $recordId . '/edit');
        return [
            'source_key' => 'company_registration',
            'record_id' => $recordId,
            'company_id' => $recordId,
            'company_name' => (string) ($row['name'] ?? ''),
            'type_label' => __('companies_approvals_oversight'),
            'reference' => (string) ($row['name'] ?? ('#' . $recordId)),
            'submitted_at' => (string) ($row['created_at'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'fields' => [
                ['label' => __('name'), 'value' => (string) ($row['name'] ?? '')],
                ['label' => __('email'), 'value' => (string) ($row['email'] ?? '')],
                ['label' => __('phone'), 'value' => (string) ($row['phone'] ?? '')],
                ['label' => __('status'), 'value' => $this->statusLabel($status)],
            ],
            'view_url' => $editUrl,
            'edit_url' => $editUrl,
            'can_approve' => $status === 'pending',
            'can_reject' => $status === 'pending',
            'can_undo' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function detailWorkflowInstance(int $instanceId, int $companyId): array
    {
        $db = Database::connection();
        $sql = 'SELECT i.*, w.name AS workflow_name, c.name AS company_name
                FROM rateb_approval_instances i
                JOIN rateb_approval_workflows w ON w.id = i.workflow_id
                LEFT JOIN rateb_companies c ON c.id = i.company_id
                WHERE i.id = :id';
        $params = ['id' => $instanceId];
        if ($companyId > 0 && !\Rateb\App\Core\TenantContext::isSuperAdmin()) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        $entityType = (string) ($row['entity_type'] ?? '');
        $entityId = (int) ($row['entity_id'] ?? 0);
        $cid = (int) ($row['company_id'] ?? 0);
        $status = (string) ($row['status'] ?? 'pending');
        return [
            'source_key' => 'workflow_instance',
            'record_id' => $instanceId,
            'company_id' => $cid,
            'company_name' => (string) ($row['company_name'] ?? ''),
            'type_label' => WorkflowService::entityTypeLabel($entityType),
            'reference' => WorkflowService::entityTypeLabel($entityType) . ' #' . $entityId,
            'submitted_at' => (string) ($row['created_at'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'workflow_name' => (string) ($row['workflow_name'] ?? ''),
            'fields' => [
                ['label' => __('workflow_definitions'), 'value' => (string) ($row['workflow_name'] ?? '')],
                ['label' => __('reference'), 'value' => '#' . $entityId],
            ],
            'view_url' => WorkflowService::entityDocumentUrl($entityType, $entityId, $cid),
            'can_approve' => $status === 'pending',
            'can_reject' => $status === 'pending',
            'can_undo' => false,
        ];
    }

    /** @param array<string, mixed> $source
     * @param array<string, mixed> $row */
    private function recordStatus(string $sourceKey, array $source, array $row): string
    {
        if (isset($source['status_column'])) {
            return (string) ($row[(string) $source['status_column']] ?? '');
        }
        return (string) ($row['manager_approval'] ?? 'pending');
    }

    private function statusLabel(string $status): string
    {
        $key = match ($status) {
            'pending', 'draft' => 'status_pending',
            'approved', 'posted' => 'approved',
            'rejected', 'void' => 'rejected',
            default => 'status',
        };
        $t = __($key);
        return $t !== $key ? $t : $status;
    }

    private function canActOnStatus(string $status, string $action): bool
    {
        $pendingLike = ['pending', 'draft'];
        $doneLike = ['approved', 'posted', 'rejected', 'void', 'cancelled'];
        if ($action === 'approve' || $action === 'reject') {
            return in_array($status, $pendingLike, true);
        }
        if ($action === 'undo') {
            return in_array($status, $doneLike, true);
        }
        return false;
    }

    /** @param array<string, mixed> $row
     * @return array<int, array{label: string, value: string}> */
    private function detailFields(string $sourceKey, array $row): array
    {
        $fields = [];
        $map = [
            'notes' => 'notes',
            'description' => 'description',
            'reason' => 'reason',
            'amount' => 'amount',
            'total_amount' => 'total_amount',
            'title' => 'title',
            'subject' => 'subject',
        ];
        foreach ($map as $col => $labelKey) {
            if (!empty($row[$col])) {
                $fields[] = ['label' => __($labelKey), 'value' => (string) $row[$col]];
            }
        }
        $status = '';
        if (isset($row['manager_approval'])) {
            $status = (string) $row['manager_approval'];
        } elseif (isset($row['approval_status'])) {
            $status = (string) $row['approval_status'];
        } elseif (isset($row['status'])) {
            $status = (string) $row['status'];
        }
        if ($status !== '') {
            $fields[] = ['label' => __('status'), 'value' => $this->statusLabel($status)];
        }
        return $fields;
    }

    private function resetManagerApproval(string $table, int $id, int $companyId): void
    {
        if ($table === '') {
            throw new \RuntimeException(__('invalid_request'));
        }
        ManagerApprovalSchema::executeResetApproval($table, $id, $companyId);
    }

    private function resetHrStatus(string $table, int $id, int $companyId): void
    {
        $db = Database::connection();
        $sql = sprintf(
            'UPDATE %s SET status = :st, approved_by = NULL, approved_at = NULL WHERE id = :id AND status IN (\'approved\', \'rejected\')',
            $table
        );
        $params = ['st' => 'pending', 'id' => $id];
        if ($companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
    }

    private function resolveCompanyId(string $sourceKey, int $recordId, int $companyId): ?int
    {
        $fromRecord = $this->fetchRecordCompanyId($sourceKey, $recordId);
        if ($fromRecord !== null && $fromRecord > 0) {
            return $fromRecord;
        }
        return $companyId > 0 ? $companyId : null;
    }

    private function fetchRecordCompanyId(string $sourceKey, int $recordId): ?int
    {
        if ($recordId < 1) {
            return null;
        }
        if ($sourceKey === 'workflow_instance') {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT company_id FROM rateb_approval_instances WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $recordId]);
            $cid = (int) ($stmt->fetchColumn() ?: 0);
            return $cid > 0 ? $cid : null;
        }
        if ($sourceKey === 'company_registration') {
            return $recordId;
        }
        $sources = $this->sources();
        $table = (string) ($sources[$sourceKey]['table'] ?? '');
        if ($table === '' || !$this->tableExists($table)) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT company_id FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $recordId]);
        $cid = (int) ($stmt->fetchColumn() ?: 0);
        return $cid > 0 ? $cid : null;
    }

    private function ensureAccountingSubmitSchema(): void
    {
        if (\Rateb\App\Core\SessionManager::get('rateb_acct_submit_schema_ok') === '1') {
            return;
        }
        $acct = new AccountingService();
        $acct->ensureApprovalSubmitColumns();
        $acct->ensureAccountingRejectColumns();
        \Rateb\App\Core\SessionManager::set('rateb_acct_submit_schema_ok', '1');
    }

    private function bootstrapCompany(int $companyId): void
    {
        if (function_exists('rateb_is_super_admin')) {
            \Rateb\App\Core\TenantContext::setSuperAdmin(rateb_is_super_admin());
        }
        if ($companyId > 0) {
            $_GET['company_id'] = (string) $companyId;
            $_POST['company_id'] = (string) $companyId;
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $companyId);
            \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        }
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
    }

    private function setManagerApproval(string $table, int $id, int $companyId, string $action, int $uid): void
    {
        $state = $action === 'approve' ? 'approved' : 'rejected';
        $this->runDb(function () use ($table, $id, $companyId, $state, $uid): void {
            ManagerApprovalSchema::executePendingApproval($table, $id, $state, $uid, $companyId);
        });
    }

    private function runDb(callable $fn): void
    {
        try {
            $fn();
        } catch (\PDOException $e) {
            throw DatabaseErrorService::toRuntimeException($e);
        }
    }

    private function setHrStatus(string $table, int $id, int $companyId, string $action, int $uid): void
    {
        $state = $action === 'approve' ? 'approved' : 'rejected';
        $db = Database::connection();
        $sql = sprintf(
            'UPDATE %s SET status = :st, approved_by = :uid, approved_at = NOW() WHERE id = :id AND status = :pending',
            $table
        );
        $params = ['st' => $state, 'uid' => $uid > 0 ? $uid : null, 'id' => $id, 'pending' => 'pending'];
        if ($companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
    }

    private function setEmployeeRequestStatus(int $id, int $companyId, string $action, int $uid): void
    {
        $state = $action === 'approve' ? 'approved' : 'rejected';
        $db = Database::connection();
        $sql = 'UPDATE rateb_hr_employee_requests SET status = :st, processed_by = :uid, processed_at = NOW() WHERE id = :id AND status = :pending';
        $params = ['st' => $state, 'uid' => $uid > 0 ? $uid : null, 'id' => $id, 'pending' => 'pending'];
        if ($companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
    }
}
