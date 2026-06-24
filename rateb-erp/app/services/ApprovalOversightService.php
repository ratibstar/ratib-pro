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
        ];
    }

    public function process(string $sourceKey, int $recordId, int $companyId, string $action): void
    {
        if ($recordId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $action = $action === 'reject' ? 'reject' : 'approve';
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
            $model = new \Rateb\App\Models\SupplierEvaluation();
            $item = $model->find($recordId);
            if (!$item || (string) ($item['manager_approval'] ?? '') !== 'pending') {
                throw new \RuntimeException(__('manager_approval_already_processed'));
            }
            $model->update($recordId, [
                'manager_approval' => $action === 'approve' ? 'approved' : 'rejected',
                'approved_by' => $uid > 0 ? $uid : null,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            if ($action === 'approve') {
                (new SupplierEvaluationService())->refreshSupplierRating((int) ($item['supplier_id'] ?? 0));
            }
            return;
        }

        if ($sourceKey === 'contract_renewal') {
            $svc = new ContractWorkflowService();
            if ($action === 'approve') {
                $svc->approveRenewal($recordId, $uid);
            } else {
                $svc->rejectRenewal($recordId, $uid);
            }
            return;
        }

        if ($sourceKey === 'inventory_audit') {
            $this->setManagerApproval('rateb_inventory_audits', $recordId, $companyId, $action, $uid);
            return;
        }

        if (isset($this->managerSlugs()[$sourceKey])) {
            $rec = new WorkflowRecordService();
            if ($action === 'approve') {
                $rec->approve($this->managerSlugs()[$sourceKey], $recordId);
            } else {
                $rec->reject($this->managerSlugs()[$sourceKey], $recordId);
            }
            return;
        }

        if ($sourceKey === 'journal_entry') {
            $acct = new AccountingService();
            if ($action === 'approve') {
                if ($acct->postDraftEntryReason($recordId, $companyId > 0 ? $companyId : null) !== null) {
                    throw new \RuntimeException(__('journal_post_failed'));
                }
            } elseif (!$acct->rejectManualDraft($recordId, $companyId > 0 ? $companyId : null, '', $uid > 0 ? $uid : null)) {
                throw new \RuntimeException(__('journal_reject_failed'));
            }
            return;
        }

        if ($sourceKey === 'cash_voucher') {
            $acct = new AccountingService();
            if ($action === 'approve') {
                if (!$acct->postCashVoucher($recordId, $companyId > 0 ? $companyId : null)) {
                    throw new \RuntimeException(__('voucher_post_failed'));
                }
            } elseif (!$acct->rejectCashVoucherDraft($recordId, $companyId > 0 ? $companyId : null, '', $uid > 0 ? $uid : null)) {
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
                if (!$asset->approveDepreciation($recordId)) {
                    throw new \RuntimeException(__('invalid_request'));
                }
            } else {
                throw new \RuntimeException(__('invalid_request'));
            }
            return;
        }

        if ($sourceKey === 'contract') {
            $db = Database::connection();
            $stmt = $db->prepare(
                'UPDATE rateb_contracts SET approval_status = :st WHERE id = :id'
                . ($companyId > 0 ? ' AND company_id = :cid' : '')
            );
            $params = ['st' => $action === 'approve' ? 'approved' : 'rejected', 'id' => $recordId];
            if ($companyId > 0) {
                $params['cid'] = $companyId;
            }
            $stmt->execute($params);
            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
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

    private function bootstrapCompany(int $companyId): void
    {
        if ($companyId > 0) {
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
        $db = Database::connection();
        $sql = sprintf(
            'UPDATE %s SET manager_approval = :st, approved_by = :uid, approved_at = NOW() WHERE id = :id AND manager_approval = :pending',
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
            throw new \RuntimeException(__('manager_approval_already_processed'));
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
