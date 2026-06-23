<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Contract;

final class ContractWorkflowService
{
    /** @return array<int, array<string, mixed>> */
    public function listRenewals(int $limit = 100): array
    {
        $companyId = TenantContext::companyId();
        $sql = 'SELECT r.*, c.contract_no, c.title AS contract_title, c.end_date AS contract_end_date, c.value AS contract_value,
                       s.name AS supplier_name
                FROM rateb_contract_renewals r
                LEFT JOIN rateb_contracts c ON c.id = r.contract_id AND c.company_id = r.company_id
                LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id AND s.company_id = r.company_id
                WHERE 1=1';
        $params = [];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND r.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY ' . rateb_list_order_sql('r') . ' LIMIT ' . max(1, min(500, $limit));
        $rows = (new Contract())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->formatRenewalDisplay($row);
        }
        return $out;
    }

    /** @param array<string, mixed> $data */
    public function createRenewal(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $contractId = (int) ($data['contract_id'] ?? 0);
        TenantGuard::assertContract($contractId, $cid);
        $db = \Rateb\App\Core\Database::connection();
        $no = (new WorkflowTableService())->generateRecordNo('contract-renewals');
        $newEnd = $this->normalizeDate($data['new_end_date'] ?? null);
        $db->prepare(
            'INSERT INTO rateb_contract_renewals (company_id, renewal_no, contract_id, renewal_date, new_end_date, new_value, status, manager_approval, notes)
             VALUES (:cid, :no, :contract_id, :rd, :ned, :nv, :st, :ma, :notes)'
        )->execute([
            'cid' => $cid,
            'no' => $no,
            'contract_id' => $contractId,
            'rd' => $data['renewal_date'] ?? date('Y-m-d'),
            'ned' => $newEnd,
            'nv' => (float) ($data['new_value'] ?? 0),
            'st' => 'planned',
            'ma' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findRenewal(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $companyId = TenantContext::companyId();
        $sql = 'SELECT r.*, c.contract_no, c.title AS contract_title, c.end_date AS contract_end_date, c.value AS contract_value,
                       s.name AS supplier_name, u.name AS approved_by_name
                FROM rateb_contract_renewals r
                LEFT JOIN rateb_contracts c ON c.id = r.contract_id AND c.company_id = r.company_id
                LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id AND s.company_id = r.company_id
                LEFT JOIN rateb_users u ON u.id = r.approved_by
                WHERE r.id = :id';
        $params = ['id' => $id];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND r.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $row = (new Contract())->queryOne($sql, $params);
        return $row ? $this->formatRenewalDisplay($row) : null;
    }

    /** @return array<int, array{name:string,label:string}> */
    public function exportColumns(): array
    {
        return [
            ['name' => 'renewal_no', 'label' => __('record_id')],
            ['name' => 'contract_no', 'label' => __('contract_no')],
            ['name' => 'contract_title', 'label' => __('title')],
            ['name' => 'supplier_name', 'label' => __('suppliers')],
            ['name' => 'renewal_date', 'label' => __('renewal_date')],
            ['name' => 'new_end_date', 'label' => __('new_end_date')],
            ['name' => 'new_value', 'label' => __('new_value')],
            ['name' => 'status_label', 'label' => __('status')],
            ['name' => 'manager_approval_label', 'label' => __('manager_approval')],
            ['name' => 'notes', 'label' => __('notes')],
        ];
    }

    /** @param array<string, mixed> $row */
    public function formatRenewalDisplay(array $row): array
    {
        if (empty($row['contract_no']) && (int) ($row['contract_id'] ?? 0) > 0) {
            $row['contract_no'] = '#' . (int) $row['contract_id'];
        }
        $end = (string) ($row['new_end_date'] ?? '');
        if ($end === '0000-00-00') {
            $row['new_end_date'] = '';
        }
        $contractEnd = (string) ($row['contract_end_date'] ?? '');
        if ($contractEnd === '0000-00-00') {
            $row['contract_end_date'] = '';
        }
        $approval = (string) ($row['manager_approval'] ?? 'pending');
        if (str_starts_with($approval, 'manager_approval_')) {
            $approval = substr($approval, strlen('manager_approval_'));
        }
        $row['manager_approval_raw'] = $approval;
        $row['manager_approval'] = 'manager_approval_' . $approval;
        $row['manager_approval_label'] = __('manager_approval_' . $approval);
        $status = (string) ($row['status'] ?? 'planned');
        $row['status_label'] = __($status);
        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function listRenewalsForExport(int $limit = 500): array
    {
        return $this->listRenewals($limit);
    }

    private function approvalState(array $row): string
    {
        $approval = (string) ($row['manager_approval_raw'] ?? $row['manager_approval'] ?? 'pending');
        if (str_starts_with($approval, 'manager_approval_')) {
            $approval = substr($approval, strlen('manager_approval_'));
        }
        return $approval;
    }

    /** @param array<string, mixed> $data */
    public function updateRenewal(int $id, array $data): void
    {
        $row = $this->findRenewal($id);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        if ($this->approvalState($row) === 'approved') {
            throw new \RuntimeException(__('contract_renewal_already_processed'));
        }
        $contractId = (int) ($data['contract_id'] ?? $row['contract_id'] ?? 0);
        TenantGuard::assertContract($contractId, (int) $row['company_id']);
        $newEnd = $this->normalizeDate($data['new_end_date'] ?? null);
        \Rateb\App\Core\Database::connection()->prepare(
            'UPDATE rateb_contract_renewals SET contract_id = :contract_id, renewal_date = :rd, new_end_date = :ned,
             new_value = :nv, notes = :notes, manager_approval = :ma, status = :st
             WHERE id = :id AND company_id = :cid'
        )->execute([
            'contract_id' => $contractId,
            'rd' => $data['renewal_date'] ?? $row['renewal_date'],
            'ned' => $newEnd,
            'nv' => (float) ($data['new_value'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'ma' => $this->approvalState($row) === 'rejected' ? 'pending' : $this->approvalState($row),
            'st' => 'planned',
            'id' => $id,
            'cid' => (int) $row['company_id'],
        ]);
    }

    public function approveRenewal(int $id, int $userId): void
    {
        $row = $this->findRenewal($id);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        if ($this->approvalState($row) !== 'pending') {
            throw new \RuntimeException(__('contract_renewal_already_processed'));
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare(
                'UPDATE rateb_contract_renewals SET manager_approval = :ma, status = :st, approved_by = :uid, approved_at = NOW()
                 WHERE id = :id AND company_id = :cid'
            )->execute([
                'ma' => 'approved',
                'st' => 'completed',
                'uid' => $userId > 0 ? $userId : null,
                'id' => $id,
                'cid' => (int) $row['company_id'],
            ]);
            $contractId = (int) ($row['contract_id'] ?? 0);
            if ($contractId > 0) {
                $newEnd = $this->normalizeDate($row['new_end_date'] ?? null);
                $newValue = (float) ($row['new_value'] ?? 0);
                $sets = ['renewal_date = :rd'];
                $params = [
                    'rd' => $row['renewal_date'] ?? date('Y-m-d'),
                    'id' => $contractId,
                    'cid' => (int) $row['company_id'],
                ];
                if ($newEnd !== null) {
                    $sets[] = 'end_date = :ned';
                    $params['ned'] = $newEnd;
                }
                if ($newValue > 0) {
                    $sets[] = 'value = :nv';
                    $params['nv'] = $newValue;
                }
                $sets[] = 'status = :cst';
                $params['cst'] = 'active';
                $db->prepare(
                    'UPDATE rateb_contracts SET ' . implode(', ', $sets) . ' WHERE id = :id AND company_id = :cid'
                )->execute($params);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function rejectRenewal(int $id, int $userId): void
    {
        $row = $this->findRenewal($id);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        if ($this->approvalState($row) !== 'pending') {
            throw new \RuntimeException(__('contract_renewal_already_processed'));
        }
        \Rateb\App\Core\Database::connection()->prepare(
            'UPDATE rateb_contract_renewals SET manager_approval = :ma, status = :st, approved_by = :uid, approved_at = NOW()
             WHERE id = :id AND company_id = :cid'
        )->execute([
            'ma' => 'rejected',
            'st' => 'cancelled',
            'uid' => $userId > 0 ? $userId : null,
            'id' => $id,
            'cid' => (int) $row['company_id'],
        ]);
    }

    private function normalizeDate(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '' || $s === '0000-00-00') {
            return null;
        }
        return $s;
    }

    /** @return array<int, array<string, mixed>> */
    public function expiringContracts(int $days = 30): array
    {
        $companyId = TenantContext::companyId();
        $sql = 'SELECT c.*, s.name AS supplier_name FROM rateb_contracts c
                LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
                WHERE c.end_date IS NOT NULL AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                AND c.status IN (\'active\', \'draft\')';
        $params = ['days' => $days];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND c.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY c.end_date ASC LIMIT 200';
        return (new Contract())->query($sql, $params);
    }

    public function processExpiryAlerts(?int $companyId = null): int
    {
        $companyId = $companyId ?? TenantContext::companyId();
        if ($companyId === null) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $contracts = $this->expiringContracts(30);
        $notifier = new NotificationService();
        $count = 0;
        foreach ($contracts as $c) {
            $exists = (new \Rateb\App\Models\Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
                ['cid' => $companyId, 'tt' => 'contract_expiry', 'et' => 'contract', 'eid' => (int) $c['id']]
            );
            if ($exists) {
                continue;
            }
            $notifier->triggerContractExpiry(
                $companyId,
                (string) ($c['contract_no'] ?? ''),
                (string) ($c['end_date'] ?? ''),
                (int) ($c['id'] ?? 0)
            );
            $count++;
        }
        return $count;
    }
}
