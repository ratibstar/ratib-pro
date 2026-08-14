<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Employee;
use Rateb\App\Models\RecruitmentCandidate;

/**
 * Phase K — Recruitment ready→deployed → create/link rateb_employees.
 * Canonical employee SoT remains rateb_employees (no parallel employee master).
 * Idempotent via recruitment_candidate_id; national_id match links instead of duplicating.
 */
final class HrHireBridgeService
{
    /**
     * @return array{
     *   employee_id:int,
     *   created:bool,
     *   linked:bool,
     *   contract_id:?int,
     *   company_id:int
     * }
     */
    public function hireFromCandidate(int $candidateId, int $companyId = 0): array
    {
        if ($companyId < 1) {
            $companyId = RecruitmentSupport::requireCompanyId();
        }
        if ($candidateId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $candidate = RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $existing = $this->findEmployeeByCandidate($companyId, $candidateId);
        if ($existing !== null) {
            $contractId = $this->ensureDraftContractFromRecruitment($companyId, (int) $existing['id'], $candidateId);
            return [
                'employee_id' => (int) $existing['id'],
                'created' => false,
                'linked' => true,
                'contract_id' => $contractId,
                'company_id' => $companyId,
            ];
        }

        $nationalId = trim((string) ($candidate['national_id'] ?? ''));
        if ($nationalId !== '') {
            $byNational = $this->findEmployeeByNationalId($companyId, $nationalId);
            if ($byNational !== null) {
                $this->linkCandidateToEmployee($companyId, (int) $byNational['id'], $candidateId);
                $contractId = $this->ensureDraftContractFromRecruitment(
                    $companyId,
                    (int) $byNational['id'],
                    $candidateId
                );
                (new AuditService())->log('hirebridge_link', 'hr_employees', (int) $byNational['id'], [
                    'candidate_id' => $candidateId,
                    'company_id' => $companyId,
                    'match' => 'national_id',
                    'created' => false,
                ]);
                return [
                    'employee_id' => (int) $byNational['id'],
                    'created' => false,
                    'linked' => true,
                    'contract_id' => $contractId,
                    'company_id' => $companyId,
                ];
            }
        }

        $employeeId = $this->createEmployeeFromCandidate($companyId, $candidate);
        $contractId = $this->ensureDraftContractFromRecruitment($companyId, $employeeId, $candidateId);

        (new AuditService())->log('hirebridge_create', 'hr_employees', $employeeId, [
            'candidate_id' => $candidateId,
            'company_id' => $companyId,
            'created' => true,
            'contract_id' => $contractId,
        ]);

        return [
            'employee_id' => $employeeId,
            'created' => true,
            'linked' => false,
            'contract_id' => $contractId,
            'company_id' => $companyId,
        ];
    }

    /** @return array<string, mixed>|null */
    private function findEmployeeByCandidate(int $companyId, int $candidateId): ?array
    {
        if (!$this->hasRecruitmentCandidateColumn()) {
            return null;
        }
        $row = (new Employee())->queryOne(
            'SELECT * FROM rateb_employees
             WHERE company_id = :cid AND recruitment_candidate_id = :rcid
             LIMIT 1',
            ['cid' => $companyId, 'rcid' => $candidateId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function findEmployeeByNationalId(int $companyId, string $nationalId): ?array
    {
        $row = (new Employee())->queryOne(
            'SELECT * FROM rateb_employees
             WHERE company_id = :cid AND national_id = :nid
             LIMIT 1',
            ['cid' => $companyId, 'nid' => $nationalId]
        );

        return is_array($row) ? $row : null;
    }

    private function linkCandidateToEmployee(int $companyId, int $employeeId, int $candidateId): void
    {
        if (!$this->hasRecruitmentCandidateColumn()) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_employees
             SET recruitment_candidate_id = :rcid
             WHERE id = :id AND company_id = :cid
               AND (recruitment_candidate_id IS NULL OR recruitment_candidate_id = :rcid2)'
        );
        $stmt->execute([
            'rcid' => $candidateId,
            'id' => $employeeId,
            'cid' => $companyId,
            'rcid2' => $candidateId,
        ]);
        if ($stmt->rowCount() < 1) {
            // Already linked to another candidate — do not overwrite.
            $check = $this->findEmployeeByCandidate($companyId, $candidateId);
            if ($check === null) {
                throw new \RuntimeException(__('hr_hirebridge_duplicate_blocked'));
            }
        }
    }

    /** @param array<string, mixed> $candidate */
    private function createEmployeeFromCandidate(int $companyId, array $candidate): int
    {
        $model = new Employee();
        $name = trim((string) ($candidate['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($candidate['full_name_ar'] ?? ''));
        }
        if ($name === '') {
            $name = 'Candidate #' . (int) ($candidate['id'] ?? 0);
        }

        $data = [
            'company_id' => $companyId,
            'name' => $name,
            'email' => trim((string) ($candidate['email'] ?? '')) ?: null,
            'phone' => trim((string) ($candidate['phone'] ?? '')) ?: null,
            'national_id' => trim((string) ($candidate['national_id'] ?? '')) ?: null,
            'job_title' => trim((string) ($candidate['job_title_target'] ?? '')) ?: null,
            'hire_date' => date('Y-m-d'),
            'salary_base' => $this->resolveCandidateSalary($companyId, (int) ($candidate['id'] ?? 0)),
            'status' => 'active',
            'notes' => 'HireBridge from candidate ' . (string) ($candidate['candidate_no'] ?? (string) ($candidate['id'] ?? '')),
        ];

        if ($this->hasRecruitmentCandidateColumn()) {
            $data['recruitment_candidate_id'] = (int) ($candidate['id'] ?? 0);
        }

        $codes = new DocumentCodeService();
        $codes->assignIfEmpty($data, $model, DocumentCodeService::PREFIX_EMPLOYEE, 'employee_code');

        $id = (int) $model->create($data);
        if ($id < 1) {
            throw new \RuntimeException(__('db_operation_failed'));
        }

        (new AuditService())->log('create', 'hr_employees', $id, [
            'source' => 'hirebridge',
            'candidate_id' => (int) ($candidate['id'] ?? 0),
            'company_id' => $companyId,
            'employee_code' => $data['employee_code'] ?? null,
        ]);

        return $id;
    }

    private function resolveCandidateSalary(int $companyId, int $candidateId): float
    {
        try {
            $row = (new RecruitmentCandidate())->queryOne(
                'SELECT salary FROM rateb_recruitment_contracts
                 WHERE company_id = :cid AND candidate_id = :cand
                 ORDER BY id DESC LIMIT 1',
                ['cid' => $companyId, 'cand' => $candidateId]
            );
            if (is_array($row)) {
                return (float) ($row['salary'] ?? 0);
            }
        } catch (\Throwable $e) {
            // optional
        }

        return 0.0;
    }

    private function ensureDraftContractFromRecruitment(int $companyId, int $employeeId, int $candidateId): ?int
    {
        try {
            $svc = new HrEmploymentContractService();
            $existing = $svc->listForEmployee($companyId, $employeeId);
            foreach ($existing as $row) {
                if ((int) ($row['recruitment_candidate_id'] ?? 0) === $candidateId) {
                    return (int) $row['id'];
                }
            }

            $rc = (new RecruitmentCandidate())->queryOne(
                'SELECT id, salary, start_date, end_date, contract_no
                 FROM rateb_recruitment_contracts
                 WHERE company_id = :cid AND candidate_id = :cand
                 ORDER BY id DESC LIMIT 1',
                ['cid' => $companyId, 'cand' => $candidateId]
            );
            $start = is_array($rc) ? trim((string) ($rc['start_date'] ?? '')) : '';
            if ($start === '') {
                $start = date('Y-m-d');
            }
            $end = is_array($rc) ? (trim((string) ($rc['end_date'] ?? '')) ?: null) : null;
            $salary = is_array($rc) ? (float) ($rc['salary'] ?? 0) : 0.0;

            $created = $svc->create($companyId, [
                'employee_id' => $employeeId,
                'start_date' => $start,
                'end_date' => $end,
                'salary' => $salary,
                'status' => 'draft',
                'recruitment_candidate_id' => $candidateId,
                'recruitment_contract_id' => is_array($rc) ? (int) ($rc['id'] ?? 0) : null,
                'notes' => 'Auto draft from HireBridge',
            ]);

            return (int) ($created['id'] ?? 0) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hasRecruitmentCandidateColumn(): bool
    {
        try {
            return Database::liveTableHasColumn('rateb_employees', 'recruitment_candidate_id');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
