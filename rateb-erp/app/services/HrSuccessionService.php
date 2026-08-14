<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Employee;

/**
 * Phase O — Succession planning on additive tables.
 * Does not invent manager hierarchy or change Employee SoT.
 */
final class HrSuccessionService
{
    /** @var list<string> */
    public const READINESS = ['ready', 'developing', 'not_ready'];

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_critical_positions')
                && Database::tableExists('rateb_hr_succession_candidates');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPositions(int $companyId, int $limit = 100): array
    {
        if ($companyId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT p.*, e.name AS current_employee_name, e.employee_code AS current_employee_code,
                    d.name AS department_name, j.name AS job_title_name,
                    (SELECT COUNT(*) FROM rateb_hr_succession_candidates c
                     WHERE c.company_id = p.company_id AND c.critical_position_id = p.id AND c.status = 'active') AS candidate_count
             FROM rateb_hr_critical_positions p
             LEFT JOIN rateb_employees e ON e.id = p.current_employee_id AND e.company_id = p.company_id
             LEFT JOIN rateb_hr_departments d ON d.id = p.department_id AND d.company_id = p.company_id
             LEFT JOIN rateb_hr_job_titles j ON j.id = p.job_title_id AND j.company_id = p.company_id
             WHERE p.company_id = :cid AND p.status = 'active'
             ORDER BY p.is_critical DESC, p.title ASC
             LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findPosition(int $companyId, int $id): ?array
    {
        if ($companyId < 1 || $id < 1 || !$this->schemaReady()) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            "SELECT p.*, e.name AS current_employee_name, e.employee_code AS current_employee_code
             FROM rateb_hr_critical_positions p
             LEFT JOIN rateb_employees e ON e.id = p.current_employee_id AND e.company_id = p.company_id
             WHERE p.id = :id AND p.company_id = :cid LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int,code:string}
     */
    public function createPosition(int $companyId, array $input, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($companyId < 1 || $title === '') {
            throw new \RuntimeException(__('invalid_request'));
        }
        $currentEmployeeId = (int) ($input['current_employee_id'] ?? 0);
        if ($currentEmployeeId > 0) {
            $this->assertEmployee($companyId, $currentEmployeeId);
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = $this->nextCode($companyId);
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO rateb_hr_critical_positions
             (company_id, code, title, department_id, job_title_id, current_employee_id, is_critical,
              skill_gap_notes, status, created_by, updated_by)
             VALUES
             (:cid, :code, :title, :did, :jid, :eid, :crit, :gap, \'active\', :cb, :ub)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'did' => ((int) ($input['department_id'] ?? 0)) ?: null,
            'jid' => ((int) ($input['job_title_id'] ?? 0)) ?: null,
            'eid' => $currentEmployeeId > 0 ? $currentEmployeeId : null,
            'crit' => !empty($input['is_critical']) ? 1 : 1,
            'gap' => trim((string) ($input['skill_gap_notes'] ?? '')) !== ''
                ? trim((string) $input['skill_gap_notes']) : null,
            'cb' => $actorUserId > 0 ? $actorUserId : null,
            'ub' => $actorUserId > 0 ? $actorUserId : null,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        (new AuditService())->log('hr_succession_position_create', 'hr_critical_position', $id, [
            'company_id' => $companyId,
            'code' => $code,
        ]);

        return ['id' => $id, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int}
     */
    public function addCandidate(int $companyId, int $positionId, array $input, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $pos = $this->findPosition($companyId, $positionId);
        if ($pos === null) {
            throw new \RuntimeException(__('no_records'));
        }
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $this->assertEmployee($companyId, $employeeId);
        $readiness = trim((string) ($input['readiness'] ?? 'developing'));
        if (!in_array($readiness, self::READINESS, true)) {
            $readiness = 'developing';
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO rateb_hr_succession_candidates
             (company_id, critical_position_id, employee_id, readiness, rank_order, skill_gap_notes, notes, status, created_by, updated_by)
             VALUES
             (:cid, :pid, :eid, :ready, :rank, :gap, :notes, \'active\', :cb, :ub)
             ON DUPLICATE KEY UPDATE
               readiness = VALUES(readiness),
               rank_order = VALUES(rank_order),
               skill_gap_notes = VALUES(skill_gap_notes),
               notes = VALUES(notes),
               status = \'active\',
               updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'pid' => $positionId,
            'eid' => $employeeId,
            'ready' => $readiness,
            'rank' => max(1, min(99, (int) ($input['rank_order'] ?? 1))),
            'gap' => trim((string) ($input['skill_gap_notes'] ?? '')) !== ''
                ? trim((string) $input['skill_gap_notes']) : null,
            'notes' => trim((string) ($input['notes'] ?? '')) !== ''
                ? trim((string) $input['notes']) : null,
            'cb' => $actorUserId > 0 ? $actorUserId : null,
            'ub' => $actorUserId > 0 ? $actorUserId : null,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        (new AuditService())->log('hr_succession_candidate_upsert', 'hr_succession_candidate', $id > 0 ? $id : $positionId, [
            'company_id' => $companyId,
            'critical_position_id' => $positionId,
            'employee_id' => $employeeId,
            'readiness' => $readiness,
        ]);

        return ['id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCandidates(int $companyId, int $positionId): array
    {
        if ($companyId < 1 || $positionId < 1 || !$this->schemaReady()) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            "SELECT c.*, e.name AS employee_name, e.employee_code, e.job_title
             FROM rateb_hr_succession_candidates c
             JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
             WHERE c.company_id = :cid AND c.critical_position_id = :pid AND c.status = 'active'
             ORDER BY c.rank_order ASC, c.id ASC
             LIMIT 50"
        );
        $stmt->execute(['cid' => $companyId, 'pid' => $positionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assertEmployee(int $companyId, int $employeeId): void
    {
        $row = (new Employee())->queryOne(
            'SELECT id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException(__('access_denied'));
        }
    }

    private function nextCode(int $companyId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_hr_critical_positions WHERE company_id = :cid'
        );
        $stmt->execute(['cid' => $companyId]);
        $n = (int) ($stmt->fetchColumn() ?: 0) + 1;

        return 'CP-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
