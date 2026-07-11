<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\QmsAudit;
use Rateb\App\Models\QmsChecklist;
use Rateb\App\Models\QmsComment;
use Rateb\App\Models\QmsComplaint;
use Rateb\App\Models\QmsCorrectiveAction;
use Rateb\App\Models\QmsDefect;
use Rateb\App\Models\QmsDocumentMeta;
use Rateb\App\Models\QmsInspection;
use Rateb\App\Models\QmsNonconformity;
use Rateb\App\Models\QmsPlan;
use Rateb\App\Models\QmsPreventiveAction;
use Rateb\App\Models\QmsStandard;
use Rateb\App\Models\QmsSupplierQuality;

/**
 * Phase 25A — Enterprise Quality Management (QMS) Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_qms_* — workflow_status changes via QualityWorkflowService only.
 * Soft-links MFG / EAM / procurement / HRMS ids only — never mutates those modules.
 *
 * Note: QualityInspectionService (not QualityCheckService) — Manufacturing already owns QualityCheckService.
 */

final class QualityEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $out = [];
        $maps = [
            QualityWorkflowService::ENTITY_INSPECTION => [
                'table' => 'rateb_qms_inspections',
                'model' => new QmsInspection(),
            ],
            QualityWorkflowService::ENTITY_CORRECTIVE => [
                'table' => 'rateb_qms_corrective_actions',
                'model' => new QmsCorrectiveAction(),
            ],
            QualityWorkflowService::ENTITY_AUDIT => [
                'table' => 'rateb_qms_audits',
                'model' => new QmsAudit(),
            ],
        ];
        foreach ($maps as $entityType => $cfg) {
            $counts = [];
            foreach (QualityWorkflowService::statuses($entityType) as $st) {
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

final class QualityPlanService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = QualitySupport::requireCompanyId();
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
        $totalRow = (new QmsPlan())->queryOne('SELECT COUNT(*) AS c FROM rateb_qms_plans WHERE ' . $where, $params);
        $items = (new QmsPlan())->query(
            'SELECT * FROM rateb_qms_plans WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_plans', 'QMS-PLAN', $companyId);
        }
        $id = (new QmsPlan())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'program_id' => QualitySupport::intOrNull($input['program_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => QualitySupport::nullIfEmpty($input['name_ar'] ?? null),
            'scope_text' => QualitySupport::nullIfEmpty($input['scope_text'] ?? null),
            'effective_from' => QualitySupport::dateOrNull($input['effective_from'] ?? null),
            'effective_to' => QualitySupport::dateOrNull($input['effective_to'] ?? null),
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record('plan_created', 'Quality plan: ' . $name, 'plan', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityStandardService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsStandard())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_standards WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsStandard())->query(
            'SELECT * FROM rateb_qms_standards WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_standards', 'QMS-STD', $companyId);
        }
        $id = (new QmsStandard())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => QualitySupport::nullIfEmpty($input['name_ar'] ?? null),
            'standard_ref' => QualitySupport::nullIfEmpty($input['standard_ref'] ?? null),
            'revision' => QualitySupport::nullIfEmpty($input['revision'] ?? null),
            'description' => QualitySupport::nullIfEmpty($input['description'] ?? null),
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityChecklistService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsChecklist())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_checklists WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsChecklist())->query(
            'SELECT * FROM rateb_qms_checklists WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_checklists', 'QMS-CHK', $companyId);
        }
        $id = (new QmsChecklist())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'plan_id' => QualitySupport::intOrNull($input['plan_id'] ?? null),
            'standard_id' => QualitySupport::intOrNull($input['standard_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => QualitySupport::nullIfEmpty($input['name_ar'] ?? null),
            'checklist_type' => substr(trim((string) ($input['checklist_type'] ?? 'inspection')), 0, 40) ?: 'inspection',
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityInspectionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        $totalRow = (new QmsInspection())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_inspections WHERE ' . $where,
            $params
        );
        $items = (new QmsInspection())->query(
            'SELECT * FROM rateb_qms_inspections WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return QualitySupport::findInspection($id, QualitySupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_inspections', 'QMS-INSP', $companyId);
        }
        $id = (new QmsInspection())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'plan_id' => QualitySupport::intOrNull($input['plan_id'] ?? null),
            'checklist_id' => QualitySupport::intOrNull($input['checklist_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'title_ar' => QualitySupport::nullIfEmpty($input['title_ar'] ?? null),
            'planned_at' => QualitySupport::dateOrNull($input['planned_at'] ?? null),
            'inspector_user_id' => QualitySupport::intOrNull($input['inspector_user_id'] ?? null),
            'mfg_quality_check_id' => QualitySupport::intOrNull($input['mfg_quality_check_id'] ?? null),
            'eam_inspection_id' => QualitySupport::intOrNull($input['eam_inspection_id'] ?? null),
            'workflow_status' => 'planned',
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record('inspection_created', 'Inspection: ' . $title, 'inspection', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityDefectService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsDefect())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_defects WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsDefect())->query(
            'SELECT * FROM rateb_qms_defects WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_defects', 'QMS-DEF', $companyId);
        }
        $severity = (string) ($input['severity'] ?? 'medium');
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }
        $id = (new QmsDefect())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'inspection_id' => QualitySupport::intOrNull($input['inspection_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'severity' => $severity,
            'defect_type' => QualitySupport::nullIfEmpty($input['defect_type'] ?? null),
            'quantity' => max(0, QualitySupport::floatOrZero($input['quantity'] ?? 1)),
            'status' => 'open',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityNonconformityService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsNonconformity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_nonconformities WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsNonconformity())->query(
            'SELECT * FROM rateb_qms_nonconformities WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_nonconformities', 'QMS-NC', $companyId);
        }
        $severity = (string) ($input['severity'] ?? 'medium');
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }
        $id = (new QmsNonconformity())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'inspection_id' => QualitySupport::intOrNull($input['inspection_id'] ?? null),
            'defect_id' => QualitySupport::intOrNull($input['defect_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => QualitySupport::nullIfEmpty($input['description'] ?? null),
            'severity' => $severity,
            'detected_at' => QualitySupport::dateOrNull($input['detected_at'] ?? null) ?? date('Y-m-d'),
            'status' => 'open',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record('nc_created', 'Nonconformity: ' . $title, 'nonconformity', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QmsCorrectiveActionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        $totalRow = (new QmsCorrectiveAction())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_corrective_actions WHERE ' . $where,
            $params
        );
        $items = (new QmsCorrectiveAction())->query(
            'SELECT * FROM rateb_qms_corrective_actions WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return QualitySupport::findCorrectiveAction($id, QualitySupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_corrective_actions', 'QMS-CA', $companyId);
        }
        $id = (new QmsCorrectiveAction())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'nonconformity_id' => QualitySupport::intOrNull($input['nonconformity_id'] ?? null),
            'root_cause_id' => QualitySupport::intOrNull($input['root_cause_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => QualitySupport::nullIfEmpty($input['description'] ?? null),
            'assignee_user_id' => QualitySupport::intOrNull($input['assignee_user_id'] ?? null),
            'due_date' => QualitySupport::dateOrNull($input['due_date'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record('ca_created', 'Corrective action: ' . $title, 'corrective_action', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QmsPreventiveActionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsPreventiveAction())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_preventive_actions WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsPreventiveAction())->query(
            'SELECT * FROM rateb_qms_preventive_actions WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_preventive_actions', 'QMS-PA', $companyId);
        }
        $id = (new QmsPreventiveAction())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'nonconformity_id' => QualitySupport::intOrNull($input['nonconformity_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => QualitySupport::nullIfEmpty($input['description'] ?? null),
            'assignee_user_id' => QualitySupport::intOrNull($input['assignee_user_id'] ?? null),
            'due_date' => QualitySupport::dateOrNull($input['due_date'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityAuditService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsAudit())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_audits WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsAudit())->query(
            'SELECT * FROM rateb_qms_audits WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_audits', 'QMS-AUD', $companyId);
        }
        $auditType = (string) ($input['audit_type'] ?? 'internal');
        if (!in_array($auditType, ['internal', 'external', 'supplier', 'process'], true)) {
            $auditType = 'internal';
        }
        $id = (new QmsAudit())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'program_id' => QualitySupport::intOrNull($input['program_id'] ?? null),
            'standard_id' => QualitySupport::intOrNull($input['standard_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'audit_type' => $auditType,
            'planned_start' => QualitySupport::dateOrNull($input['planned_start'] ?? null),
            'planned_end' => QualitySupport::dateOrNull($input['planned_end'] ?? null),
            'lead_auditor_user_id' => QualitySupport::intOrNull($input['lead_auditor_user_id'] ?? null),
            'workflow_status' => 'planned',
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        (new QualityTimelineService())->record('audit_created', 'Audit: ' . $title, 'audit', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityComplaintService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsComplaint())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_complaints WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsComplaint())->query(
            'SELECT * FROM rateb_qms_complaints WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_complaints', 'QMS-CMP', $companyId);
        }
        $id = (new QmsComplaint())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'complainant' => QualitySupport::nullIfEmpty($input['complainant'] ?? null),
            'complaint_date' => QualitySupport::dateOrNull($input['complaint_date'] ?? null) ?? date('Y-m-d'),
            'description' => QualitySupport::nullIfEmpty($input['description'] ?? null),
            'severity' => in_array($input['severity'] ?? '', ['low', 'medium', 'high', 'critical'], true)
                ? $input['severity'] : 'medium',
            'status' => 'open',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class SupplierQualityService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsSupplierQuality())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_supplier_quality WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsSupplierQuality())->query(
            'SELECT * FROM rateb_qms_supplier_quality WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $name = trim((string) ($input['supplier_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('supplier_name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = QualitySupport::nextCode('rateb_qms_supplier_quality', 'QMS-SQ', $companyId);
        }
        $id = (new QmsSupplierQuality())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'code' => substr($code, 0, 40),
            'supplier_name' => substr($name, 0, 190),
            'legacy_supplier_id' => QualitySupport::intOrNull($input['legacy_supplier_id'] ?? null),
            'eproc_profile_id' => QualitySupport::intOrNull($input['eproc_profile_id'] ?? null),
            'score' => QualitySupport::floatOrZero($input['score'] ?? null) ?: null,
            'rating' => QualitySupport::nullIfEmpty($input['rating'] ?? null),
            'last_review_date' => QualitySupport::dateOrNull($input['last_review_date'] ?? null),
            'status' => 'active',
            'notes' => QualitySupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class QualityCommentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = QualitySupport::intOrNull($input['entity_id'] ?? null);
        $text = trim((string) ($input['comment_text'] ?? ''));
        if ($entityType === '' || $entityId === null || $text === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        $id = (new QmsComment())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'entity_type' => substr($entityType, 0, 40),
            'entity_id' => $entityId,
            'comment_text' => $text,
            'status' => 'active',
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class QualityDocumentMetaService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createMeta(array $input): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = QualitySupport::intOrNull($input['entity_id'] ?? null);
        $title = trim((string) ($input['title'] ?? ''));
        if ($entityType === '' || $entityId === null || $title === '') {
            throw new \InvalidArgumentException('document_meta_required');
        }
        $id = (new QmsDocumentMeta())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'entity_type' => substr($entityType, 0, 40),
            'entity_id' => $entityId,
            'doc_type' => substr(trim((string) ($input['doc_type'] ?? 'attachment')), 0, 40),
            'title' => substr($title, 0, 190),
            'file_name' => QualitySupport::nullIfEmpty($input['file_name'] ?? null),
            'mime_type' => QualitySupport::nullIfEmpty($input['mime_type'] ?? null),
            'file_size' => QualitySupport::intOrNull($input['file_size'] ?? null),
            'storage_key' => QualitySupport::nullIfEmpty($input['storage_key'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], QualitySupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
