<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrEmploymentContract;
use Rateb\App\Models\Notification;
use PDO;

/**
 * Phase K — HR employment contracts (not commercial rateb_contracts).
 * Lifecycle: draft → active → expired|terminated.
 */
final class HrEmploymentContractService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';

    public const PREFIX = 'EC-';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
            self::STATUS_TERMINATED,
        ];
    }

    public function schemaReady(): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("SHOW TABLES LIKE 'rateb_hr_employment_contracts'");
            return $stmt !== false && $stmt->fetch(PDO::FETCH_NUM) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(int $companyId, ?string $status = null, int $limit = 200): array
    {
        if ($companyId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT c.*, e.name AS employee_name, e.employee_code
                FROM rateb_hr_employment_contracts c
                JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
                WHERE c.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND c.status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY c.id DESC LIMIT ' . $limit;

        $rows = (new HrEmploymentContract())->query($sql, $params);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEmployee(int $companyId, int $employeeId): array
    {
        if ($companyId < 1 || $employeeId < 1 || !$this->schemaReady()) {
            return [];
        }
        $rows = (new HrEmploymentContract())->query(
            'SELECT * FROM rateb_hr_employment_contracts
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC LIMIT 50',
            ['cid' => $companyId, 'eid' => $employeeId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findForCompany(int $companyId, int $contractId): ?array
    {
        if ($companyId < 1 || $contractId < 1 || !$this->schemaReady()) {
            return null;
        }
        $row = (new HrEmploymentContract())->queryOne(
            'SELECT c.*, e.name AS employee_name, e.employee_code
             FROM rateb_hr_employment_contracts c
             JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
             WHERE c.id = :id AND c.company_id = :cid
             LIMIT 1',
            ['id' => $contractId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(int $companyId, array $input): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        if ($companyId < 1) {
            throw new \RuntimeException(__('access_denied'));
        }
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $this->assertEmployeeBelongs($companyId, $employeeId);

        $start = trim((string) ($input['start_date'] ?? ''));
        if ($start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $end = trim((string) ($input['end_date'] ?? ''));
        $end = $end !== '' ? $end : null;
        if ($end !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($end !== null && $end < $start) {
            throw new \RuntimeException(__('hr_contract_end_before_start'));
        }

        $status = trim((string) ($input['status'] ?? self::STATUS_DRAFT));
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true)) {
            $status = self::STATUS_DRAFT;
        }

        $model = new HrEmploymentContract();
        $data = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'start_date' => $start,
            'end_date' => $end,
            'salary' => round((float) ($input['salary'] ?? 0), 2),
            'status' => $status,
            'alert_days' => max(1, min(365, (int) ($input['alert_days'] ?? 30))),
            'recruitment_candidate_id' => ((int) ($input['recruitment_candidate_id'] ?? 0)) ?: null,
            'recruitment_contract_id' => ((int) ($input['recruitment_contract_id'] ?? 0)) ?: null,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'created_by' => $this->actorId(),
            'updated_by' => $this->actorId(),
        ];
        if ($status === self::STATUS_ACTIVE) {
            $data['activated_at'] = date('Y-m-d H:i:s');
        }

        $contractNo = trim((string) ($input['contract_no'] ?? ''));
        if ($contractNo === '') {
            $codes = new DocumentCodeService();
            $tmp = [];
            $codes->assignIfEmpty($tmp, $model, DocumentCodeService::PREFIX_EMPLOYMENT_CONTRACT, 'contract_no');
            $data['contract_no'] = $tmp['contract_no'] ?? $this->nextContractNo($companyId);
        } else {
            $data['contract_no'] = $contractNo;
        }
        if (trim((string) ($data['contract_no'] ?? '')) === '') {
            $data['contract_no'] = $this->nextContractNo($companyId);
        }

        $id = (int) $model->create($data);
        if ($id < 1) {
            throw new \RuntimeException(__('db_operation_failed'));
        }

        (new AuditService())->log('hr_employment_contract_create', 'hr_employment_contract', $id, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'status' => $status,
            'contract_no' => $data['contract_no'],
        ]);

        $row = $this->findForCompany($companyId, $id);
        if ($row === null) {
            throw new \RuntimeException(__('db_operation_failed'));
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $companyId, int $contractId, array $input): array
    {
        $row = $this->findForCompany($companyId, $contractId);
        if ($row === null) {
            throw new \RuntimeException(__('access_denied'));
        }
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true)) {
            throw new \RuntimeException(__('hr_contract_not_editable'));
        }

        $patch = ['updated_by' => $this->actorId()];
        if (array_key_exists('start_date', $input)) {
            $start = trim((string) $input['start_date']);
            if ($start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $patch['start_date'] = $start;
        }
        if (array_key_exists('end_date', $input)) {
            $end = trim((string) ($input['end_date'] ?? ''));
            $patch['end_date'] = $end !== '' ? $end : null;
        }
        if (array_key_exists('salary', $input)) {
            $patch['salary'] = round((float) $input['salary'], 2);
        }
        if (array_key_exists('alert_days', $input)) {
            $patch['alert_days'] = max(1, min(365, (int) $input['alert_days']));
        }
        if (array_key_exists('notes', $input)) {
            $patch['notes'] = trim((string) $input['notes']) ?: null;
        }

        $start = (string) ($patch['start_date'] ?? $row['start_date'] ?? '');
        $end = $patch['end_date'] ?? $row['end_date'] ?? null;
        if ($end !== null && $end !== '' && $start !== '' && (string) $end < $start) {
            throw new \RuntimeException(__('hr_contract_end_before_start'));
        }

        (new HrEmploymentContract())->update($contractId, $patch);
        (new AuditService())->log('hr_employment_contract_update', 'hr_employment_contract', $contractId, [
            'company_id' => $companyId,
            'patch' => $patch,
        ]);

        $out = $this->findForCompany($companyId, $contractId);
        if ($out === null) {
            throw new \RuntimeException(__('access_denied'));
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function activate(int $companyId, int $contractId): array
    {
        $row = $this->findForCompany($companyId, $contractId);
        if ($row === null) {
            throw new \RuntimeException(__('access_denied'));
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_DRAFT) {
            throw new \RuntimeException(__('hr_contract_activate_draft_only'));
        }

        (new HrEmploymentContract())->update($contractId, [
            'status' => self::STATUS_ACTIVE,
            'activated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->actorId(),
        ]);
        (new AuditService())->log('hr_employment_contract_activate', 'hr_employment_contract', $contractId, [
            'company_id' => $companyId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
        ]);

        $out = $this->findForCompany($companyId, $contractId);
        if ($out === null) {
            throw new \RuntimeException(__('access_denied'));
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function terminate(int $companyId, int $contractId, ?string $notes = null): array
    {
        $row = $this->findForCompany($companyId, $contractId);
        if ($row === null) {
            throw new \RuntimeException(__('access_denied'));
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_ACTIVE) {
            throw new \RuntimeException(__('hr_contract_terminate_active_only'));
        }

        $patch = [
            'status' => self::STATUS_TERMINATED,
            'terminated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->actorId(),
        ];
        if ($notes !== null && trim($notes) !== '') {
            $prev = trim((string) ($row['notes'] ?? ''));
            $patch['notes'] = trim($prev . "\n" . trim($notes));
        }
        (new HrEmploymentContract())->update($contractId, $patch);
        (new AuditService())->log('hr_employment_contract_terminate', 'hr_employment_contract', $contractId, [
            'company_id' => $companyId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
        ]);

        $out = $this->findForCompany($companyId, $contractId);
        if ($out === null) {
            throw new \RuntimeException(__('access_denied'));
        }

        return $out;
    }

    /** Mark active contracts past end_date as expired. */
    public function processExpiryStatus(): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $db = Database::connection();
        $n = $db->exec(
            "UPDATE rateb_hr_employment_contracts
             SET status = 'expired', updated_at = NOW()
             WHERE status = 'active'
               AND end_date IS NOT NULL
               AND end_date < CURDATE()"
        );

        return (int) $n;
    }

    /** Near-expiry company notifications (not commercial contract_expiry). */
    public function processExpiryAlerts(): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $count = 0;
        $companies = (new Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            TenantContext::setCompanyId($cid);
            $rows = (new HrEmploymentContract())->query(
                "SELECT * FROM rateb_hr_employment_contracts
                 WHERE company_id = :cid AND status = 'active'
                   AND end_date IS NOT NULL
                   AND end_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(alert_days, 30) DAY)
                   AND end_date >= CURDATE()",
                ['cid' => $cid]
            );
            foreach ($rows as $contract) {
                $id = (int) ($contract['id'] ?? 0);
                $endDate = (string) ($contract['end_date'] ?? '');
                $alertDays = (int) ($contract['alert_days'] ?? 30);
                if ($id < 1 || $endDate === '') {
                    continue;
                }
                $daysLeft = (int) floor((strtotime($endDate) - strtotime('today')) / 86400);
                if ($daysLeft > $alertDays) {
                    continue;
                }
                $exists = (new Notification())->queryOne(
                    'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
                     AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                     LIMIT 1',
                    [
                        'cid' => $cid,
                        'tt' => 'hr_employment_contract_expiry',
                        'et' => 'hr_employment_contract',
                        'eid' => $id,
                    ]
                );
                if ($exists) {
                    continue;
                }
                $no = (string) ($contract['contract_no'] ?? '');
                (new NotificationService())->notifyCompany(
                    $cid,
                    __('hr_employment_contract_expiry_title'),
                    str_replace(
                        [':no', ':date', ':days'],
                        [$no, $endDate, (string) $daysLeft],
                        __('hr_employment_contract_expiry_body')
                    ),
                    'warning',
                    'hr_employment_contract_expiry',
                    'hr_employment_contract',
                    $id
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);

        return $count;
    }

    private function assertEmployeeBelongs(int $companyId, int $employeeId): void
    {
        if ($employeeId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $emp = (new Employee())->queryOne(
            'SELECT id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!is_array($emp)) {
            throw new \RuntimeException(__('access_denied'));
        }
    }

    private function nextContractNo(int $companyId): string
    {
        $row = (new HrEmploymentContract())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hr_employment_contracts WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return self::PREFIX . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function actorId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }
}
