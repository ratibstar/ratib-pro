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
        return !in_array($sourceKey, ['workflow_instance', 'asset_depreciation', 'hr_payroll'], true);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            '' => 'all_approval_types',
            'workflow' => 'approval_category_workflow',
            'manager' => 'approval_category_manager',
            'accounting' => 'approval_category_accounting',
            'hr' => 'approval_category_hr',
            'operations' => 'approval_category_operations',
        ];
    }

    /** @return array<string, int> */
    public function summary(?int $companyFilter = null): array
    {
        $counts = [];
        foreach ($this->sources() as $key => $source) {
            $counts[$key] = $this->countSource($source, $companyFilter);
        }
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPending(?int $companyFilter = null, ?string $typeFilter = null, int $limit = 200): array
    {
        $items = [];
        $perSource = max(10, (int) ceil($limit / max(1, count($this->sources()))));
        foreach ($this->sources() as $key => $source) {
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
                'date_column' => 'entry_date',
                'status_column' => 'status',
                'status_value' => 'draft',
                'fixed_filters' => ['source_type' => 'manual'],
                'route' => 'journal-entries',
                'queue_route' => 'accounting/entry-approval',
            ],
            'cash_voucher' => [
                'category' => 'accounting',
                'label' => 'cash_vouchers',
                'table' => 'rateb_cash_vouchers',
                'no_column' => 'voucher_no',
                'date_column' => 'voucher_date',
                'status_column' => 'status',
                'status_value' => 'draft',
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
            $companyId = (int) ($row['company_id'] ?? 0);
            $entityId = (int) ($row['id'] ?? 0);
            $ref = trim((string) ($row['reference'] ?? ''));
            if ($ref === '' && $entityId > 0) {
                $ref = '#' . $entityId;
            }
            $route = (string) ($source['route'] ?? '');
            $queueRoute = (string) ($source['queue_route'] ?? $route);
            $out[] = [
                'category' => (string) ($source['category'] ?? ''),
                'type_label' => __((string) ($source['label'] ?? '')),
                'company_id' => $companyId,
                'company_name' => (string) ($row['company_name'] ?? ''),
                'reference' => $ref,
                'record_id' => $entityId,
                'entity_id' => $entityId,
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'view_url' => $this->opsUrl($route . '/' . $entityId, $companyId),
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
            $where .= ' AND ' . $alias . '.company_id = :cid';
            $params['cid'] = $companyFilter;
        }

        foreach ($source['fixed_filters'] ?? [] as $col => $val) {
            $paramKey = 'ff_' . preg_replace('/[^a-z0-9_]/', '_', (string) $col);
            $where .= ' AND ' . $alias . '.' . $col . ' = :' . $paramKey;
            $params[$paramKey] = (string) $val;
        }

        if ($countOnly) {
            return [
                'sql' => 'SELECT COUNT(*) FROM ' . $table . ' ' . $alias . $where,
                'params' => $params,
            ];
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
        try {
            $db = Database::connection();
            $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
            if ($stmt === false) {
                return false;
            }
            $exists = $stmt->fetch() !== false;
            $stmt->closeCursor();
            return $exists;
        } catch (\Throwable $e) {
            return false;
        }
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
            $acct = new AccountingService();
            $cid = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
            if ($action === 'approve') {
                $reason = $acct->postDraftEntryReason($recordId, $cid);
                if ($reason !== null) {
                    throw new \RuntimeException(__($reason));
                }
            } elseif (!$acct->rejectManualDraft($recordId, $cid, '', $uid > 0 ? $uid : null)) {
                throw new \RuntimeException(__('journal_reject_failed'));
            }
            return;
        }

        if ($sourceKey === 'cash_voucher') {
            $acct = new AccountingService();
            $cid = $this->resolveCompanyId($sourceKey, $recordId, $companyId);
            if ($action === 'approve') {
                $reason = $acct->postCashVoucherReason($recordId, $cid);
                if ($reason !== null) {
                    throw new \RuntimeException(__($reason));
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
            try {
                $db = Database::connection();
                $stmt = $db->prepare(
                    'UPDATE rateb_contracts SET approval_status = :st WHERE id = :id AND approval_status = :pending'
                    . ($companyId > 0 ? ' AND company_id = :cid' : '')
                );
                $params = [
                    'st' => $action === 'approve' ? 'approved' : 'rejected',
                    'id' => $recordId,
                    'pending' => 'pending',
                ];
                if ($companyId > 0) {
                    $params['cid'] = $companyId;
                }
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
        if ($sourceKey === 'hr_request') {
            $this->setEmployeeRequestStatus($recordId, $companyId, $action, $uid);
            return;
        }
        if ($sourceKey === 'hr_payroll') {
            if ($action === 'approve') {
                $hr->approvePayroll($recordId);
            } else {
                throw new \RuntimeException(__('invalid_request'));
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
            'can_approve' => $this->canActOnStatus($status, 'approve'),
            'can_reject' => $this->canActOnStatus($status, 'reject') && self::canReject($sourceKey),
            'can_undo' => $this->canActOnStatus($status, 'undo') && self::canUndo($sourceKey),
        ];
    }

    public function undo(string $sourceKey, int $recordId, int $companyId): void
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
            return;
        }
        if ($sourceKey === 'journal_entry') {
            $acct = new AccountingService();
            $db = Database::connection();
            $stmt = $db->prepare('SELECT status FROM rateb_journal_entries WHERE id = :id' . ($companyId > 0 ? ' AND company_id = :cid' : ''));
            $params = ['id' => $recordId];
            if ($companyId > 0) {
                $params['cid'] = $companyId;
            }
            $stmt->execute($params);
            $st = (string) ($stmt->fetchColumn() ?: '');
            if ($st === 'posted') {
                if (!$acct->voidPostedEntry($recordId, $companyId > 0 ? $companyId : null, ['manual'])) {
                    throw new \RuntimeException(__('journal_post_failed'));
                }
                $db->prepare('UPDATE rateb_journal_entries SET status = :st, posted_at = NULL WHERE id = :id')->execute(['st' => 'draft', 'id' => $recordId]);
            } elseif ($st === 'rejected') {
                $db->prepare(
                    'UPDATE rateb_journal_entries SET status = :st, reject_reason = NULL, rejected_at = NULL, rejected_by = NULL WHERE id = :id'
                )->execute(['st' => 'draft', 'id' => $recordId]);
            } elseif ($st === 'draft') {
                return;
            } else {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }
        if ($sourceKey === 'cash_voucher') {
            $acct = new AccountingService();
            $db = Database::connection();
            $stmt = $db->prepare('SELECT status FROM rateb_cash_vouchers WHERE id = :id' . ($companyId > 0 ? ' AND company_id = :cid' : ''));
            $params = ['id' => $recordId];
            if ($companyId > 0) {
                $params['cid'] = $companyId;
            }
            $stmt->execute($params);
            $st = (string) ($stmt->fetchColumn() ?: '');
            if ($st === 'posted') {
                if (!$acct->voidCashVoucher($recordId, $companyId > 0 ? $companyId : null)) {
                    throw new \RuntimeException(__('voucher_post_failed'));
                }
                $db->prepare('UPDATE rateb_cash_vouchers SET status = :st, posted_at = NULL, journal_entry_id = NULL WHERE id = :id')->execute(['st' => 'draft', 'id' => $recordId]);
            } elseif ($st === 'rejected') {
                $db->prepare(
                    'UPDATE rateb_cash_vouchers SET status = :st, reject_reason = NULL, rejected_at = NULL, rejected_by = NULL WHERE id = :id'
                )->execute(['st' => 'draft', 'id' => $recordId]);
            } elseif ($st === 'draft') {
                return;
            } else {
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
            $this->resetHrStatus('rateb_leave_requests', $recordId, $companyId);
            return;
        }
        if ($sourceKey === 'hr_permission') {
            $this->resetHrStatus('rateb_hr_permission_requests', $recordId, $companyId);
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
            return;
        }
        if ($sourceKey === 'contract_renewal') {
            $this->resetManagerApproval('rateb_contract_renewals', $recordId, $companyId);
            return;
        }

        throw new \RuntimeException(__('invalid_request'));
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
        $doneLike = ['approved', 'posted', 'rejected', 'void'];
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

    private function bootstrapCompany(int $companyId): void
    {
        if ($companyId > 0) {
            // List filters use ?company_id= in the URL; mutations must scope to the record's company.
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
