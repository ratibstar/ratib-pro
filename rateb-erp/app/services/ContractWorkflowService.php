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
        $sql = 'SELECT r.*, c.contract_no, c.title AS contract_title FROM rateb_contract_renewals r
                LEFT JOIN rateb_contracts c ON c.id = r.contract_id WHERE 1=1';
        $params = [];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND r.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT ' . max(1, min(500, $limit));
        return (new Contract())->query($sql, $params);
    }

    /** @param array<string, mixed> $data */
    public function createRenewal(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $contractId = (int) ($data['contract_id'] ?? 0);
        TenantGuard::assertContract($contractId, $cid);
        $db = \Rateb\App\Core\Database::connection();
        $no = (new WorkflowTableService())->generateRecordNo('contract-renewals');
        $db->prepare(
            'INSERT INTO rateb_contract_renewals (company_id, renewal_no, contract_id, renewal_date, new_end_date, new_value, status, notes)
             VALUES (:cid, :no, :contract_id, :rd, :ned, :nv, :st, :notes)'
        )->execute([
            'cid' => $cid,
            'no' => $no,
            'contract_id' => $contractId,
            'rd' => $data['renewal_date'] ?? date('Y-m-d'),
            'ned' => $data['new_end_date'] ?? null,
            'nv' => (float) ($data['new_value'] ?? 0),
            'st' => $data['status'] ?? 'planned',
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $db->lastInsertId();
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
