<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

/** Manager approval + display helpers for workflow ops tables. */
final class WorkflowRecordService
{
    /** @param array<string, mixed> $row */
    public function formatRow(array $row): array
    {
        $approval = (string) ($row['manager_approval'] ?? 'pending');
        if (str_starts_with($approval, 'manager_approval_')) {
            $approval = substr($approval, strlen('manager_approval_'));
        }
        $row['manager_approval_raw'] = $approval;
        $row['manager_approval'] = 'manager_approval_' . $approval;
        $row['manager_approval_label'] = __('manager_approval_' . $approval);
        $status = (string) ($row['status'] ?? '');
        if ($status !== '') {
            $row['status_label'] = __($status);
        }
        return $row;
    }

    /** @param array<string, mixed> $row */
    public function approvalState(array $row): string
    {
        $approval = (string) ($row['manager_approval_raw'] ?? $row['manager_approval'] ?? 'pending');
        if (str_starts_with($approval, 'manager_approval_')) {
            $approval = substr($approval, strlen('manager_approval_'));
        }
        return $approval;
    }

    public function approve(string $slug, int $id): void
    {
        $this->setApproval($slug, $id, 'approved');
    }

    public function reject(string $slug, int $id): void
    {
        $this->setApproval($slug, $id, 'rejected');
    }

    private function setApproval(string $slug, int $id, string $state): void
    {
        $cfg = WorkflowTableService::config($slug);
        if ($cfg === null || $id < 1) {
            throw new \RuntimeException(__('no_records'));
        }
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        $db = Database::connection();
        $sql = sprintf(
            'SELECT id, manager_approval FROM %s WHERE id = :id',
            (string) $cfg['table']
        );
        $params = ['id' => $id];
        if ($companyId > 0 && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new \RuntimeException(__('no_records'));
        }
        $current = (string) ($existing['manager_approval'] ?? 'pending');
        if ($current !== 'pending') {
            throw new \RuntimeException(__('manager_approval_already_processed'));
        }
        $uid = (int) SessionManager::get('rateb_user_id');
        $table = (string) $cfg['table'];
        $built = ManagerApprovalSchema::pendingApprovalUpdate($table, $id, $state, $uid, $companyId);
        $stmt = $db->prepare($built['sql']);
        $stmt->execute($built['params']);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('manager_approval_already_processed'));
        }
    }

    /** @param array<int, array{name:string,label:string,type?:string}> $columns */
    /** @return array<int, array{name:string,label:string}> */
    public function exportColumns(array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            $type = (string) ($col['type'] ?? '');
            if (in_array($type, ['action_link', 'id'], true) && ($col['name'] ?? '') === 'id') {
                continue;
            }
            $name = (string) ($col['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $label = (string) ($col['label'] ?? $name);
            $out[] = [
                'name' => $name === 'manager_approval' ? 'manager_approval_label' : ($name === 'status' ? 'status_label' : $name),
                'label' => rateb_label($label),
            ];
        }
        return $out;
    }

    /** @param array<int, array<string, mixed>> $rows */
    /** @param array<int, array{name:string,label:string}> $exportCols */
    public function rowsForExport(array $rows, array $exportCols): array
    {
        $out = [];
        foreach ($rows as $row) {
            $formatted = $this->formatRow($row);
            $line = [];
            foreach ($exportCols as $col) {
                $key = (string) $col['name'];
                $line[$key] = $formatted[$key] ?? $formatted[str_replace('_label', '', $key)] ?? '';
            }
            $out[] = $line;
        }
        return $out;
    }
}
