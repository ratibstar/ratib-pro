<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrmAssignment;
use Rateb\App\Models\HrmCertification;
use Rateb\App\Models\HrmComment;
use Rateb\App\Models\HrmCompetency;
use Rateb\App\Models\HrmDepartment;
use Rateb\App\Models\HrmEmployeeDocumentMeta;
use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\HrmGoal;
use Rateb\App\Models\HrmGrade;
use Rateb\App\Models\HrmLocation;
use Rateb\App\Models\HrmNote;
use Rateb\App\Models\HrmOrgUnit;
use Rateb\App\Models\HrmPerformanceReview;
use Rateb\App\Models\HrmPosition;
use Rateb\App\Models\HrmPromotion;
use Rateb\App\Models\HrmTraining;
use Rateb\App\Models\HrmTrainingHistory;
use Rateb\App\Models\HrmTransfer;

/**
 * Phase 23A — Enterprise Human Resources (HRMS) Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_hrm_* — workflow_status changes via HumanResourcesWorkflowService only.
 * Soft-links legacy_* ids only; no payroll / attendance / stock posting.
 *
 * Note: HrmAssignmentService (not AssignmentService) — Recruitment already owns AssignmentService.
 */

final class HumanResourcesEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $out = [];
        $maps = [
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE => [
                'table' => 'rateb_hrm_employee_profiles',
                'model' => new HrmEmployeeProfile(),
            ],
            HumanResourcesWorkflowService::ENTITY_TRAINING => [
                'table' => 'rateb_hrm_training',
                'model' => new HrmTraining(),
            ],
            HumanResourcesWorkflowService::ENTITY_PERFORMANCE => [
                'table' => 'rateb_hrm_performance_reviews',
                'model' => new HrmPerformanceReview(),
            ],
        ];
        foreach ($maps as $entityType => $cfg) {
            $counts = [];
            foreach (HumanResourcesWorkflowService::statuses($entityType) as $st) {
                $row = $cfg['model']->queryOne(
                    'SELECT COUNT(*) AS c FROM ' . $cfg['table']
                    . ' WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                    ['cid' => $companyId, 'st' => $st]
                );
                $counts[$st] = (int) ($row['c'] ?? 0);
            }
            $out[$entityType] = $counts;
        }

        return $out;
    }
}

final class EmployeeProfileService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (first_name LIKE :q OR last_name LIKE :q2 OR code LIKE :q3 OR email LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        $totalRow = (new HrmEmployeeProfile())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_employee_profiles WHERE ' . $where,
            $params
        );
        $items = (new HrmEmployeeProfile())->query(
            'SELECT * FROM rateb_hrm_employee_profiles WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return HumanResourcesSupport::findProfile($id, HumanResourcesSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        if ($first === '' || $last === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_employee_profiles', 'HRM-EMP', $companyId);
        }
        $deptId = HumanResourcesSupport::intOrNull($input['department_id'] ?? null);
        if ($deptId !== null) {
            HumanResourcesSupport::assertDepartment($deptId, $companyId);
        }
        $managerId = HumanResourcesSupport::intOrNull($input['manager_profile_id'] ?? null);
        if ($managerId !== null) {
            HumanResourcesSupport::assertProfile($managerId, $companyId);
        }
        $employmentType = substr(trim((string) ($input['employment_type'] ?? 'full_time')), 0, 40) ?: 'full_time';
        $legacyEmployeeId = HumanResourcesSupport::intOrNull($input['legacy_employee_id'] ?? null);
        HumanResourcesSupport::assertLegacyEmployee($legacyEmployeeId, $companyId);
        $id = (new HrmEmployeeProfile())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::intOrNull($input['branch_id'] ?? null)
                ?? HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'legacy_employee_id' => $legacyEmployeeId,
            'first_name' => substr($first, 0, 120),
            'last_name' => substr($last, 0, 120),
            'first_name_ar' => HumanResourcesSupport::nullIfEmpty($input['first_name_ar'] ?? null),
            'last_name_ar' => HumanResourcesSupport::nullIfEmpty($input['last_name_ar'] ?? null),
            'email' => HumanResourcesSupport::nullIfEmpty($input['email'] ?? null),
            'phone' => HumanResourcesSupport::nullIfEmpty($input['phone'] ?? null),
            'department_id' => $deptId,
            'position_id' => HumanResourcesSupport::intOrNull($input['position_id'] ?? null),
            'grade_id' => HumanResourcesSupport::intOrNull($input['grade_id'] ?? null),
            'location_id' => HumanResourcesSupport::intOrNull($input['location_id'] ?? null),
            'org_unit_id' => HumanResourcesSupport::intOrNull($input['org_unit_id'] ?? null),
            'manager_profile_id' => $managerId,
            'hire_date' => HumanResourcesSupport::dateOrNull($input['hire_date'] ?? null),
            'termination_date' => HumanResourcesSupport::dateOrNull($input['termination_date'] ?? null),
            'employment_type' => $employmentType,
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            (int) $id,
            'employee_created',
            'Employee profile created: ' . $first . ' ' . $last
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = HumanResourcesSupport::assertProfile($id, $companyId);
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['first_name', 'last_name', 'first_name_ar', 'last_name_ar', 'email', 'phone', 'employment_type', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if (in_array($f, ['first_name', 'last_name'], true)) {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 120);
                } elseif ($f === 'employment_type') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'full_time')), 0, 40) ?: 'full_time';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        foreach (['hire_date', 'termination_date'] as $df) {
            if (array_key_exists($df, $input)) {
                $patch[$df] = HumanResourcesSupport::dateOrNull($input[$df]);
            }
        }
        if (array_key_exists('legacy_employee_id', $input)) {
            $legacyEmployeeId = HumanResourcesSupport::intOrNull($input['legacy_employee_id']);
            HumanResourcesSupport::assertLegacyEmployee($legacyEmployeeId, $companyId);
            $patch['legacy_employee_id'] = $legacyEmployeeId;
        }
        if (array_key_exists('department_id', $input)) {
            $deptId = HumanResourcesSupport::intOrNull($input['department_id']);
            if ($deptId !== null) {
                HumanResourcesSupport::assertDepartment($deptId, $companyId);
            }
            $patch['department_id'] = $deptId;
        }
        foreach (['position_id', 'grade_id', 'location_id', 'org_unit_id'] as $fk) {
            if (array_key_exists($fk, $input)) {
                $patch[$fk] = HumanResourcesSupport::intOrNull($input[$fk]);
            }
        }
        if (array_key_exists('manager_profile_id', $input)) {
            $mid = HumanResourcesSupport::intOrNull($input['manager_profile_id']);
            if ($mid !== null) {
                if ($mid === $id) {
                    throw new \InvalidArgumentException('invalid_manager');
                }
                HumanResourcesSupport::assertProfile($mid, $companyId);
            }
            $patch['manager_profile_id'] = $mid;
        }
        if (isset($patch['first_name']) && $patch['first_name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        if (isset($patch['last_name']) && $patch['last_name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmEmployeeProfile())->update($id, $patch);
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $id,
            'employee_updated',
            'Employee profile updated'
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        HumanResourcesSupport::assertProfile($id, $companyId);
        (new HrmEmployeeProfile())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $id,
            'employee_deleted',
            'Employee profile soft-deleted'
        );
    }
}

final class DepartmentService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR name_ar LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmDepartment())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_departments WHERE ' . $where,
            $params
        );
        $items = (new HrmDepartment())->query(
            'SELECT * FROM rateb_hrm_departments WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return HumanResourcesSupport::findDepartment($id, HumanResourcesSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_departments', 'HRM-DEPT', $companyId);
        }
        $parentId = HumanResourcesSupport::intOrNull($input['parent_id'] ?? null);
        if ($parentId !== null) {
            HumanResourcesSupport::assertDepartment($parentId, $companyId);
        }
        $managerId = HumanResourcesSupport::intOrNull($input['manager_profile_id'] ?? null);
        if ($managerId !== null) {
            HumanResourcesSupport::assertProfile($managerId, $companyId);
        }
        $id = (new HrmDepartment())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'parent_id' => $parentId,
            'manager_profile_id' => $managerId,
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = HumanResourcesSupport::assertDepartment($id, $companyId);
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('parent_id', $input)) {
            $parentId = HumanResourcesSupport::intOrNull($input['parent_id']);
            if ($parentId !== null) {
                if ($parentId === $id) {
                    throw new \InvalidArgumentException('invalid_parent');
                }
                HumanResourcesSupport::assertDepartment($parentId, $companyId);
            }
            $patch['parent_id'] = $parentId;
        }
        if (array_key_exists('manager_profile_id', $input)) {
            $mid = HumanResourcesSupport::intOrNull($input['manager_profile_id']);
            if ($mid !== null) {
                HumanResourcesSupport::assertProfile($mid, $companyId);
            }
            $patch['manager_profile_id'] = $mid;
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'inactive', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmDepartment())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        HumanResourcesSupport::assertDepartment($id, $companyId);
        (new HrmDepartment())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class PositionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?int $departmentId = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($departmentId !== null && $departmentId > 0) {
            $where .= ' AND department_id = :did';
            $params['did'] = $departmentId;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmPosition())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_positions WHERE ' . $where,
            $params
        );
        $items = (new HrmPosition())->query(
            'SELECT * FROM rateb_hrm_positions WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmPosition())->queryOne(
            'SELECT * FROM rateb_hrm_positions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_positions', 'HRM-POS', $companyId);
        }
        $deptId = HumanResourcesSupport::intOrNull($input['department_id'] ?? null);
        if ($deptId !== null) {
            HumanResourcesSupport::assertDepartment($deptId, $companyId);
        }
        $id = (new HrmPosition())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'department_id' => $deptId,
            'grade_id' => HumanResourcesSupport::intOrNull($input['grade_id'] ?? null),
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'legacy_job_title_id' => HumanResourcesSupport::intOrNull($input['legacy_job_title_id'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('position_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('department_id', $input)) {
            $deptId = HumanResourcesSupport::intOrNull($input['department_id']);
            if ($deptId !== null) {
                HumanResourcesSupport::assertDepartment($deptId, $companyId);
            }
            $patch['department_id'] = $deptId;
        }
        if (array_key_exists('grade_id', $input)) {
            $patch['grade_id'] = HumanResourcesSupport::intOrNull($input['grade_id']);
        }
        if (array_key_exists('legacy_job_title_id', $input)) {
            $patch['legacy_job_title_id'] = HumanResourcesSupport::intOrNull($input['legacy_job_title_id']);
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmPosition())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('position_not_found');
        }
        (new HrmPosition())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class GradeService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmGrade())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_grades WHERE ' . $where,
            $params
        );
        $items = (new HrmGrade())->query(
            'SELECT * FROM rateb_hrm_grades WHERE ' . $where
            . ' ORDER BY level_rank ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmGrade())->queryOne(
            'SELECT * FROM rateb_hrm_grades WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_grades', 'HRM-GRD', $companyId);
        }
        $id = (new HrmGrade())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'level_rank' => (int) ($input['level_rank'] ?? 1),
            'min_salary' => HumanResourcesSupport::floatOrNull($input['min_salary'] ?? null),
            'max_salary' => HumanResourcesSupport::floatOrNull($input['max_salary'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('grade_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('level_rank', $input)) {
            $patch['level_rank'] = (int) $input['level_rank'];
        }
        if (array_key_exists('min_salary', $input)) {
            $patch['min_salary'] = HumanResourcesSupport::floatOrNull($input['min_salary']);
        }
        if (array_key_exists('max_salary', $input)) {
            $patch['max_salary'] = HumanResourcesSupport::floatOrNull($input['max_salary']);
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmGrade())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('grade_not_found');
        }
        (new HrmGrade())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class OrganizationService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listOrgUnits(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmOrgUnit())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_org_units WHERE ' . $where,
            $params
        );
        $items = (new HrmOrgUnit())->query(
            'SELECT * FROM rateb_hrm_org_units WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listLocations(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR city LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmLocation())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_locations WHERE ' . $where,
            $params
        );
        $items = (new HrmLocation())->query(
            'SELECT * FROM rateb_hrm_locations WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function getOrgUnit(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmOrgUnit())->queryOne(
            'SELECT * FROM rateb_hrm_org_units WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function getLocation(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmLocation())->queryOne(
            'SELECT * FROM rateb_hrm_locations WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function createOrgUnit(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_org_units', 'HRM-ORG', $companyId);
        }
        $deptId = HumanResourcesSupport::intOrNull($input['department_id'] ?? null);
        if ($deptId !== null) {
            HumanResourcesSupport::assertDepartment($deptId, $companyId);
        }
        $id = (new HrmOrgUnit())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'parent_id' => HumanResourcesSupport::intOrNull($input['parent_id'] ?? null),
            'department_id' => $deptId,
            'unit_type' => substr(trim((string) ($input['unit_type'] ?? 'unit')), 0, 40) ?: 'unit',
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function createLocation(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_locations', 'HRM-LOC', $companyId);
        }
        $id = (new HrmLocation())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'address' => HumanResourcesSupport::nullIfEmpty($input['address'] ?? null),
            'city' => HumanResourcesSupport::nullIfEmpty($input['city'] ?? null),
            'country_code' => HumanResourcesSupport::nullIfEmpty($input['country_code'] ?? null),
            'legacy_workplace_id' => HumanResourcesSupport::intOrNull($input['legacy_workplace_id'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function updateOrgUnit(int $id, array $input): void
    {
        $row = $this->getOrgUnit($id);
        if ($row === null) {
            throw new \RuntimeException('org_unit_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'unit_type', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'name') {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 190);
                } elseif ($f === 'unit_type') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'unit')), 0, 40) ?: 'unit';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        foreach (['parent_id', 'department_id'] as $fk) {
            if (array_key_exists($fk, $input)) {
                $patch[$fk] = HumanResourcesSupport::intOrNull($input[$fk]);
            }
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmOrgUnit())->update($id, $patch);
    }

    /** @param array<string, mixed> $input */
    public function updateLocation(int $id, array $input): void
    {
        $row = $this->getLocation($id);
        if ($row === null) {
            throw new \RuntimeException('location_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'address', 'city', 'country_code', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('legacy_workplace_id', $input)) {
            $patch['legacy_workplace_id'] = HumanResourcesSupport::intOrNull($input['legacy_workplace_id']);
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmLocation())->update($id, $patch);
    }

    public function softDeleteOrgUnit(int $id): void
    {
        if ($this->getOrgUnit($id) === null) {
            throw new \RuntimeException('org_unit_not_found');
        }
        (new HrmOrgUnit())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }

    public function softDeleteLocation(int $id): void
    {
        if ($this->getLocation($id) === null) {
            throw new \RuntimeException('location_not_found');
        }
        (new HrmLocation())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }

    /** Convenience aliases matching other services' create/list/get/update/softDelete shape for org units. */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        return $this->listOrgUnits($limit, $offset, $search);
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return $this->getOrgUnit($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        return $this->createOrgUnit($input);
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $this->updateOrgUnit($id, $input);
    }

    public function softDelete(int $id): void
    {
        $this->softDeleteOrgUnit($id);
    }
}

final class CertificationService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR issuer LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmCertification())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_certifications WHERE ' . $where,
            $params
        );
        $items = (new HrmCertification())->query(
            'SELECT * FROM rateb_hrm_certifications WHERE ' . $where
            . ' ORDER BY issued_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmCertification())->queryOne(
            'SELECT * FROM rateb_hrm_certifications WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_certifications', 'HRM-CERT', $companyId);
        }
        $id = (new HrmCertification())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'issuer' => HumanResourcesSupport::nullIfEmpty($input['issuer'] ?? null),
            'issued_at' => HumanResourcesSupport::dateOrNull($input['issued_at'] ?? null),
            'expires_at' => HumanResourcesSupport::dateOrNull($input['expires_at'] ?? null),
            'credential_id' => HumanResourcesSupport::nullIfEmpty($input['credential_id'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $profileId,
            'certification_created',
            'Certification added: ' . $name,
            null,
            ['certification_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('certification_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'issuer', 'credential_id', 'notes', 'code'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['issued_at', 'expires_at'] as $df) {
            if (array_key_exists($df, $input)) {
                $patch[$df] = HumanResourcesSupport::dateOrNull($input[$df]);
            }
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmCertification())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('certification_not_found');
        }
        (new HrmCertification())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class TrainingService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR code LIKE :q2 OR provider LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmTraining())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_training WHERE ' . $where,
            $params
        );
        $items = (new HrmTraining())->query(
            'SELECT * FROM rateb_hrm_training WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return HumanResourcesSupport::findTraining($id, HumanResourcesSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_training', 'HRM-TRN', $companyId);
        }
        $id = (new HrmTraining())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'title_ar' => HumanResourcesSupport::nullIfEmpty($input['title_ar'] ?? null),
            'provider' => HumanResourcesSupport::nullIfEmpty($input['provider'] ?? null),
            'location_id' => HumanResourcesSupport::intOrNull($input['location_id'] ?? null),
            'planned_start' => HumanResourcesSupport::dateOrNull($input['planned_start'] ?? null),
            'planned_end' => HumanResourcesSupport::dateOrNull($input['planned_end'] ?? null),
            'hours' => HumanResourcesSupport::floatOrZero($input['hours'] ?? 0),
            'capacity' => HumanResourcesSupport::intOrNull($input['capacity'] ?? null),
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'workflow_status' => 'planned',
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_TRAINING,
            (int) $id,
            'training_created',
            'Training created: ' . $title
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = HumanResourcesSupport::assertTraining($id, $companyId);
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['title', 'title_ar', 'provider', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['planned_start', 'planned_end'] as $df) {
            if (array_key_exists($df, $input)) {
                $patch[$df] = HumanResourcesSupport::dateOrNull($input[$df]);
            }
        }
        if (array_key_exists('location_id', $input)) {
            $patch['location_id'] = HumanResourcesSupport::intOrNull($input['location_id']);
        }
        if (array_key_exists('hours', $input)) {
            $patch['hours'] = HumanResourcesSupport::floatOrZero($input['hours']);
        }
        if (array_key_exists('capacity', $input)) {
            $patch['capacity'] = HumanResourcesSupport::intOrNull($input['capacity']);
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmTraining())->update($id, $patch);
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_TRAINING,
            $id,
            'training_updated',
            'Training updated'
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        HumanResourcesSupport::assertTraining($id, $companyId);
        (new HrmTraining())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_TRAINING,
            $id,
            'training_deleted',
            'Training soft-deleted'
        );
    }

    /**
     * Enroll an employee via rateb_hrm_training_history.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function enroll(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $trainingId = (int) ($input['training_id'] ?? 0);
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertTraining($trainingId, $companyId);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $existing = (new HrmTrainingHistory())->queryOne(
            'SELECT id FROM rateb_hrm_training_history
             WHERE company_id = :cid AND training_id = :tid AND employee_profile_id = :epid
               AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'tid' => $trainingId, 'epid' => $profileId]
        );
        if (is_array($existing)) {
            throw new \RuntimeException('already_enrolled');
        }
        $id = (new HrmTrainingHistory())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'training_id' => $trainingId,
            'employee_profile_id' => $profileId,
            'result_status' => substr(trim((string) ($input['result_status'] ?? 'enrolled')), 0, 40) ?: 'enrolled',
            'score' => HumanResourcesSupport::floatOrNull($input['score'] ?? null),
            'completed_at' => HumanResourcesSupport::dateOrNull($input['completed_at'] ?? null),
            'certificate_ref' => HumanResourcesSupport::nullIfEmpty($input['certificate_ref'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_TRAINING,
            $trainingId,
            'training_enrolled',
            'Employee enrolled in training',
            null,
            ['employee_profile_id' => $profileId, 'history_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @return list<array<string, mixed>> */
    public function listEnrollments(int $trainingId): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        HumanResourcesSupport::assertTraining($trainingId, $companyId);
        $rows = (new HrmTrainingHistory())->query(
            'SELECT * FROM rateb_hrm_training_history
             WHERE company_id = :cid AND training_id = :tid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'tid' => $trainingId]
        );

        return is_array($rows) ? $rows : [];
    }
}

final class PerformanceReviewService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null, ?int $employeeProfileId = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (code LIKE :q OR summary LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmPerformanceReview())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_performance_reviews WHERE ' . $where,
            $params
        );
        $items = (new HrmPerformanceReview())->query(
            'SELECT * FROM rateb_hrm_performance_reviews WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return HumanResourcesSupport::findPerformanceReview($id, HumanResourcesSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $reviewerId = HumanResourcesSupport::intOrNull($input['reviewer_profile_id'] ?? null);
        if ($reviewerId !== null) {
            HumanResourcesSupport::assertProfile($reviewerId, $companyId);
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_performance_reviews', 'HRM-PERF', $companyId);
        }
        $id = (new HrmPerformanceReview())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'employee_profile_id' => $profileId,
            'reviewer_profile_id' => $reviewerId,
            'period_start' => HumanResourcesSupport::dateOrNull($input['period_start'] ?? null),
            'period_end' => HumanResourcesSupport::dateOrNull($input['period_end'] ?? null),
            'overall_score' => HumanResourcesSupport::floatOrNull($input['overall_score'] ?? null),
            'summary' => HumanResourcesSupport::nullIfEmpty($input['summary'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_PERFORMANCE,
            (int) $id,
            'performance_created',
            'Performance review created',
            null,
            ['employee_profile_id' => $profileId]
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = HumanResourcesSupport::assertPerformanceReview($id, $companyId);
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['summary', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['period_start', 'period_end'] as $df) {
            if (array_key_exists($df, $input)) {
                $patch[$df] = HumanResourcesSupport::dateOrNull($input[$df]);
            }
        }
        if (array_key_exists('overall_score', $input)) {
            $patch['overall_score'] = HumanResourcesSupport::floatOrNull($input['overall_score']);
        }
        if (array_key_exists('reviewer_profile_id', $input)) {
            $rid = HumanResourcesSupport::intOrNull($input['reviewer_profile_id']);
            if ($rid !== null) {
                HumanResourcesSupport::assertProfile($rid, $companyId);
            }
            $patch['reviewer_profile_id'] = $rid;
        }
        if (array_key_exists('employee_profile_id', $input)) {
            $epid = (int) $input['employee_profile_id'];
            HumanResourcesSupport::assertProfile($epid, $companyId);
            $patch['employee_profile_id'] = $epid;
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmPerformanceReview())->update($id, $patch);
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_PERFORMANCE,
            $id,
            'performance_updated',
            'Performance review updated'
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        HumanResourcesSupport::assertPerformanceReview($id, $companyId);
        (new HrmPerformanceReview())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_PERFORMANCE,
            $id,
            'performance_deleted',
            'Performance review soft-deleted'
        );
    }
}

final class GoalService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $totalRow = (new HrmGoal())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_goals WHERE ' . $where,
            $params
        );
        $items = (new HrmGoal())->query(
            'SELECT * FROM rateb_hrm_goals WHERE ' . $where
            . ' ORDER BY target_date ASC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmGoal())->queryOne(
            'SELECT * FROM rateb_hrm_goals WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $reviewId = HumanResourcesSupport::intOrNull($input['review_id'] ?? null);
        if ($reviewId !== null) {
            HumanResourcesSupport::assertPerformanceReview($reviewId, $companyId);
        }
        $id = (new HrmGoal())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'review_id' => $reviewId,
            'title' => substr($title, 0, 190),
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'target_date' => HumanResourcesSupport::dateOrNull($input['target_date'] ?? null),
            'progress_percent' => HumanResourcesSupport::floatOrZero($input['progress_percent'] ?? 0),
            'goal_status' => substr(trim((string) ($input['goal_status'] ?? 'open')), 0, 40) ?: 'open',
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $profileId,
            'goal_created',
            'Goal created: ' . $title,
            null,
            ['goal_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('goal_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['title', 'description', 'goal_status', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'title') {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 190);
                } elseif ($f === 'goal_status') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'open')), 0, 40) ?: 'open';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        if (array_key_exists('target_date', $input)) {
            $patch['target_date'] = HumanResourcesSupport::dateOrNull($input['target_date']);
        }
        if (array_key_exists('progress_percent', $input)) {
            $patch['progress_percent'] = HumanResourcesSupport::floatOrZero($input['progress_percent']);
        }
        if (array_key_exists('review_id', $input)) {
            $rid = HumanResourcesSupport::intOrNull($input['review_id']);
            if ($rid !== null) {
                HumanResourcesSupport::assertPerformanceReview($rid, HumanResourcesSupport::requireCompanyId());
            }
            $patch['review_id'] = $rid;
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmGoal())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('goal_not_found');
        }
        (new HrmGoal())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class CompetencyService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR category LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmCompetency())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_competencies WHERE ' . $where,
            $params
        );
        $items = (new HrmCompetency())->query(
            'SELECT * FROM rateb_hrm_competencies WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmCompetency())->queryOne(
            'SELECT * FROM rateb_hrm_competencies WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_competencies', 'HRM-COMP', $companyId);
        }
        $profileId = HumanResourcesSupport::intOrNull($input['employee_profile_id'] ?? null);
        if ($profileId !== null) {
            HumanResourcesSupport::assertProfile($profileId, $companyId);
        }
        $id = (new HrmCompetency())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => HumanResourcesSupport::nullIfEmpty($input['name_ar'] ?? null),
            'category' => HumanResourcesSupport::nullIfEmpty($input['category'] ?? null),
            'level_score' => HumanResourcesSupport::floatOrNull($input['level_score'] ?? null),
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('competency_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['name', 'name_ar', 'category', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : HumanResourcesSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('level_score', $input)) {
            $patch['level_score'] = HumanResourcesSupport::floatOrNull($input['level_score']);
        }
        if (array_key_exists('employee_profile_id', $input)) {
            $epid = HumanResourcesSupport::intOrNull($input['employee_profile_id']);
            if ($epid !== null) {
                HumanResourcesSupport::assertProfile($epid, HumanResourcesSupport::requireCompanyId());
            }
            $patch['employee_profile_id'] = $epid;
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmCompetency())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('competency_not_found');
        }
        (new HrmCompetency())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class PromotionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (code LIKE :q OR reason LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmPromotion())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_promotions WHERE ' . $where,
            $params
        );
        $items = (new HrmPromotion())->query(
            'SELECT * FROM rateb_hrm_promotions WHERE ' . $where
            . ' ORDER BY effective_date DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmPromotion())->queryOne(
            'SELECT * FROM rateb_hrm_promotions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_promotions', 'HRM-PROMO', $companyId);
        }
        $id = (new HrmPromotion())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'from_position_id' => HumanResourcesSupport::intOrNull($input['from_position_id'] ?? null),
            'to_position_id' => HumanResourcesSupport::intOrNull($input['to_position_id'] ?? null),
            'from_grade_id' => HumanResourcesSupport::intOrNull($input['from_grade_id'] ?? null),
            'to_grade_id' => HumanResourcesSupport::intOrNull($input['to_grade_id'] ?? null),
            'effective_date' => HumanResourcesSupport::dateOrNull($input['effective_date'] ?? null),
            'promotion_status' => substr(trim((string) ($input['promotion_status'] ?? 'draft')), 0, 40) ?: 'draft',
            'reason' => HumanResourcesSupport::nullIfEmpty($input['reason'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $profileId,
            'promotion_created',
            'Promotion recorded',
            null,
            ['promotion_id' => (int) $id]
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('promotion_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['reason', 'notes', 'promotion_status'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'promotion_status') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'draft')), 0, 40) ?: 'draft';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        if (array_key_exists('effective_date', $input)) {
            $patch['effective_date'] = HumanResourcesSupport::dateOrNull($input['effective_date']);
        }
        foreach (['from_position_id', 'to_position_id', 'from_grade_id', 'to_grade_id'] as $fk) {
            if (array_key_exists($fk, $input)) {
                $patch[$fk] = HumanResourcesSupport::intOrNull($input[$fk]);
            }
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmPromotion())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('promotion_not_found');
        }
        (new HrmPromotion())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

final class TransferService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (code LIKE :q OR reason LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new HrmTransfer())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_transfers WHERE ' . $where,
            $params
        );
        $items = (new HrmTransfer())->query(
            'SELECT * FROM rateb_hrm_transfers WHERE ' . $where
            . ' ORDER BY effective_date DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmTransfer())->queryOne(
            'SELECT * FROM rateb_hrm_transfers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = HumanResourcesSupport::nextCode('rateb_hrm_transfers', 'HRM-XFER', $companyId);
        }
        $toDept = HumanResourcesSupport::intOrNull($input['to_department_id'] ?? null);
        if ($toDept !== null) {
            HumanResourcesSupport::assertDepartment($toDept, $companyId);
        }
        $id = (new HrmTransfer())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'from_department_id' => HumanResourcesSupport::intOrNull($input['from_department_id'] ?? null),
            'to_department_id' => $toDept,
            'from_position_id' => HumanResourcesSupport::intOrNull($input['from_position_id'] ?? null),
            'to_position_id' => HumanResourcesSupport::intOrNull($input['to_position_id'] ?? null),
            'from_location_id' => HumanResourcesSupport::intOrNull($input['from_location_id'] ?? null),
            'to_location_id' => HumanResourcesSupport::intOrNull($input['to_location_id'] ?? null),
            'effective_date' => HumanResourcesSupport::dateOrNull($input['effective_date'] ?? null),
            'transfer_status' => substr(trim((string) ($input['transfer_status'] ?? 'draft')), 0, 40) ?: 'draft',
            'reason' => HumanResourcesSupport::nullIfEmpty($input['reason'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $profileId,
            'transfer_created',
            'Transfer recorded',
            null,
            ['transfer_id' => (int) $id]
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('transfer_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['reason', 'notes', 'transfer_status'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'transfer_status') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'draft')), 0, 40) ?: 'draft';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        if (array_key_exists('effective_date', $input)) {
            $patch['effective_date'] = HumanResourcesSupport::dateOrNull($input['effective_date']);
        }
        foreach (['from_department_id', 'to_department_id', 'from_position_id', 'to_position_id', 'from_location_id', 'to_location_id'] as $fk) {
            if (array_key_exists($fk, $input)) {
                $val = HumanResourcesSupport::intOrNull($input[$fk]);
                if ($val !== null && in_array($fk, ['from_department_id', 'to_department_id'], true)) {
                    HumanResourcesSupport::assertDepartment($val, $companyId);
                }
                $patch[$fk] = $val;
            }
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmTransfer())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('transfer_not_found');
        }
        (new HrmTransfer())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}

/**
 * HR entity assignments (rateb_hrm_assignments).
 * Named HrmAssignmentService — Recruitment already owns AssignmentService.
 */
final class HrmAssignmentService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $entityType = null, ?int $entityId = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($entityType !== null && $entityType !== '') {
            $where .= ' AND entity_type = :et';
            $params['et'] = $entityType;
        }
        if ($entityId !== null && $entityId > 0) {
            $where .= ' AND entity_id = :eid';
            $params['eid'] = $entityId;
        }
        $totalRow = (new HrmAssignment())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_assignments WHERE ' . $where,
            $params
        );
        $items = (new HrmAssignment())->query(
            'SELECT * FROM rateb_hrm_assignments WHERE ' . $where
            . ' ORDER BY id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmAssignment())->queryOne(
            'SELECT * FROM rateb_hrm_assignments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($entityType === '' || $entityId < 1 || $assignee < 1) {
            throw new \InvalidArgumentException('assignment_fields_required');
        }
        $status = (string) ($input['status'] ?? 'active');
        if (!in_array($status, ['active', 'completed', 'cancelled'], true)) {
            $status = 'active';
        }
        $id = (new HrmAssignment())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'assignee_user_id' => $assignee,
            'role_label' => HumanResourcesSupport::nullIfEmpty($input['role_label'] ?? null),
            'status' => $status,
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            $entityType,
            $entityId,
            'assigned',
            'Assigned to user #' . $assignee,
            null,
            ['assignment_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('assignment_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        if (array_key_exists('assignee_user_id', $input)) {
            $assignee = (int) $input['assignee_user_id'];
            if ($assignee < 1) {
                throw new \InvalidArgumentException('assignee_required');
            }
            $patch['assignee_user_id'] = $assignee;
        }
        if (array_key_exists('role_label', $input)) {
            $patch['role_label'] = HumanResourcesSupport::nullIfEmpty($input['role_label']);
        }
        if (array_key_exists('notes', $input)) {
            $patch['notes'] = HumanResourcesSupport::nullIfEmpty($input['notes']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'completed', 'cancelled'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmAssignment())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('assignment_not_found');
        }
        (new HrmAssignment())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'cancelled',
        ], HumanResourcesSupport::actorFields(false)));
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId): array
    {
        return $this->list(100, 0, $entityType, $entityId)['items'];
    }
}

final class EmployeeCommentService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $entityType = null, ?int $entityId = null): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($entityType !== null && $entityType !== '') {
            $where .= ' AND entity_type = :et';
            $params['et'] = $entityType;
        }
        if ($entityId !== null && $entityId > 0) {
            $where .= ' AND entity_id = :eid';
            $params['eid'] = $entityId;
        }
        $totalRow = (new HrmComment())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_comments WHERE ' . $where,
            $params
        );
        $items = (new HrmComment())->query(
            'SELECT * FROM rateb_hrm_comments WHERE ' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmComment())->queryOne(
            'SELECT * FROM rateb_hrm_comments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $body = trim((string) ($input['body'] ?? ''));
        if ($entityType === '' || $entityId < 1 || $body === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        $id = (new HrmComment())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'body' => $body,
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            $entityType,
            $entityId,
            'comment_added',
            'Comment added',
            $body,
            ['comment_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /**
     * Create a note on an HR entity (rateb_hrm_notes).
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createNote(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $body = trim((string) ($input['body'] ?? ''));
        if ($entityType === '' || $entityId < 1 || $body === '') {
            throw new \InvalidArgumentException('note_fields_required');
        }
        $id = (new HrmNote())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => HumanResourcesSupport::nullIfEmpty($input['title'] ?? null),
            'body' => $body,
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            $entityType,
            $entityId,
            'note_added',
            (string) ($input['title'] ?? 'Note added'),
            $body,
            ['note_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('comment_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        if (array_key_exists('body', $input)) {
            $body = trim((string) $input['body']);
            if ($body === '') {
                throw new \InvalidArgumentException('comment_fields_required');
            }
            $patch['body'] = $body;
        }
        if (array_key_exists('notes', $input)) {
            $patch['notes'] = HumanResourcesSupport::nullIfEmpty($input['notes']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'hidden', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmComment())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('comment_not_found');
        }
        (new HrmComment())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->list($limit, 0, $entityType, $entityId)['items'];
    }
}

final class EmployeeDocumentMetaService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $employeeProfileId = null, string $search = ''): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($employeeProfileId !== null && $employeeProfileId > 0) {
            $where .= ' AND employee_profile_id = :epid';
            $params['epid'] = $employeeProfileId;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR file_name LIKE :q2 OR doc_type LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new HrmEmployeeDocumentMeta())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_employee_documents_meta WHERE ' . $where,
            $params
        );
        $items = (new HrmEmployeeDocumentMeta())->query(
            'SELECT * FROM rateb_hrm_employee_documents_meta WHERE ' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $row = (new HrmEmployeeDocumentMeta())->queryOne(
            'SELECT * FROM rateb_hrm_employee_documents_meta WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Metadata only — no binary upload.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $profileId = (int) ($input['employee_profile_id'] ?? 0);
        HumanResourcesSupport::assertProfile($profileId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $id = (new HrmEmployeeDocumentMeta())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'doc_type' => substr(trim((string) ($input['doc_type'] ?? 'general')), 0, 40) ?: 'general',
            'title' => substr($title, 0, 190),
            'file_name' => HumanResourcesSupport::nullIfEmpty($input['file_name'] ?? null),
            'mime_type' => HumanResourcesSupport::nullIfEmpty($input['mime_type'] ?? null),
            'file_size' => HumanResourcesSupport::intOrNull($input['file_size'] ?? null),
            'storage_key' => HumanResourcesSupport::nullIfEmpty($input['storage_key'] ?? null),
            'issued_at' => HumanResourcesSupport::dateOrNull($input['issued_at'] ?? null),
            'expires_at' => HumanResourcesSupport::dateOrNull($input['expires_at'] ?? null),
            'legacy_document_id' => HumanResourcesSupport::intOrNull($input['legacy_document_id'] ?? null),
            'status' => 'active',
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));

        (new EmployeeTimelineService())->append(
            HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
            $profileId,
            'document_meta_created',
            'Document meta added: ' . $title,
            null,
            ['document_meta_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->get($id);
        if ($row === null) {
            throw new \RuntimeException('document_meta_not_found');
        }
        HumanResourcesSupport::assertOptimisticVersion($row, $input['expected_version'] ?? null);
        $patch = HumanResourcesSupport::actorFields(false);
        foreach (['title', 'file_name', 'mime_type', 'storage_key', 'doc_type', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'title') {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 190);
                } elseif ($f === 'doc_type') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'general')), 0, 40) ?: 'general';
                } else {
                    $patch[$f] = HumanResourcesSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        foreach (['issued_at', 'expires_at'] as $df) {
            if (array_key_exists($df, $input)) {
                $patch[$df] = HumanResourcesSupport::dateOrNull($input[$df]);
            }
        }
        if (array_key_exists('file_size', $input)) {
            $patch['file_size'] = HumanResourcesSupport::intOrNull($input['file_size']);
        }
        if (array_key_exists('legacy_document_id', $input)) {
            $patch['legacy_document_id'] = HumanResourcesSupport::intOrNull($input['legacy_document_id']);
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new HrmEmployeeDocumentMeta())->update($id, $patch);
    }

    public function softDelete(int $id): void
    {
        if ($this->get($id) === null) {
            throw new \RuntimeException('document_meta_not_found');
        }
        (new HrmEmployeeDocumentMeta())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], HumanResourcesSupport::actorFields(false)));
    }
}
