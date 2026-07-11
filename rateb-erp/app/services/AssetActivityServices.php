<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EamActivity;
use Rateb\App\Models\EamChecklist;
use Rateb\App\Models\EamChecklistItem;
use Rateb\App\Models\EamComment;
use Rateb\App\Models\EamDocumentMeta;
use Rateb\App\Models\EamInspection;
use Rateb\App\Models\EamInsurance;
use Rateb\App\Models\EamMaintenancePlan;
use Rateb\App\Models\EamMaintenanceRequest;
use Rateb\App\Models\EamMeterReading;
use Rateb\App\Models\EamPartsConsumption;
use Rateb\App\Models\EamSparePartRef;
use Rateb\App\Models\EamWarranty;
use Rateb\App\Models\EamWorkOrder;

/**
 * Phase 19A — EAM maintenance / inspection / activity domain services (ONLINE).
 */

final class MaintenancePlanService
{
    /** @return list<array<string, mixed>> */
    public function list(?int $assetId = null, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($assetId !== null && $assetId > 0) {
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $rows = (new EamMaintenancePlan())->query(
            'SELECT * FROM rateb_eam_maintenance_plans WHERE ' . $where
            . ' ORDER BY next_due_date ASC, id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $assetId = AssetSupport::intOrNull($input['asset_id'] ?? null);
        if ($assetId !== null) {
            AssetSupport::assertAsset($assetId, $companyId);
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_maintenance_plans', 'MPN', $companyId);
        }
        $planType = (string) ($input['plan_type'] ?? 'preventive');
        if (!in_array($planType, ['preventive', 'corrective', 'inspection', 'other'], true)) {
            $planType = 'preventive';
        }
        $id = (new EamMaintenancePlan())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'plan_type' => $planType,
            'frequency_days' => AssetSupport::intOrNull($input['frequency_days'] ?? null),
            'next_due_date' => AssetSupport::nullIfEmpty($input['next_due_date'] ?? null),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class MaintenanceRequestService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null, ?int $assetId = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($assetId !== null && $assetId > 0) {
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $totalRow = (new EamMaintenanceRequest())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_maintenance_requests WHERE ' . $where,
            $params
        );
        $items = (new EamMaintenanceRequest())->query(
            'SELECT * FROM rateb_eam_maintenance_requests WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return AssetSupport::findMaintenanceRequest($id, AssetSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, request_no: string}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $requestNo = trim((string) ($input['request_no'] ?? ''));
        if ($requestNo === '') {
            $requestNo = AssetSupport::nextRequestNo($companyId);
        }
        $requestType = (string) ($input['request_type'] ?? 'corrective');
        if (!in_array($requestType, ['preventive', 'corrective', 'inspection', 'other'], true)) {
            $requestType = 'corrective';
        }
        $id = (new EamMaintenanceRequest())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'request_no' => substr($requestNo, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => AssetSupport::nullIfEmpty($input['description'] ?? null),
            'request_type' => $requestType,
            'workflow_status' => AssetWorkflowService::REQ_NEW,
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'requested_by' => AssetSupport::intOrNull($input['requested_by'] ?? null) ?? AssetSupport::userId(),
            'scheduled_at' => AssetSupport::nullIfEmpty($input['scheduled_at'] ?? null),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new AssetTimelineService())->record(
            'request_created',
            'Maintenance request: ' . $title,
            null,
            $assetId,
            'maintenance_request',
            (int) $id
        );

        return ['id' => (int) $id, 'request_no' => $requestNo];
    }

    /** @return array<string, int> */
    public function boardCounts(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $out = [];
        foreach (AssetWorkflowService::requestStatuses() as $st) {
            $row = (new EamMaintenanceRequest())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_eam_maintenance_requests
                 WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $out[$st] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }
}

/** Alias used by AssetMaintenanceService naming in the phase brief. */
final class AssetMaintenanceService
{
    public function requests(): MaintenanceRequestService
    {
        return new MaintenanceRequestService();
    }

    public function plans(): MaintenancePlanService
    {
        return new MaintenancePlanService();
    }

    public function workOrders(): WorkOrderService
    {
        return new WorkOrderService();
    }
}

final class WorkOrderService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null, ?int $assetId = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($assetId !== null && $assetId > 0) {
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $totalRow = (new EamWorkOrder())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_work_orders WHERE ' . $where,
            $params
        );
        $items = (new EamWorkOrder())->query(
            'SELECT * FROM rateb_eam_work_orders WHERE ' . $where
            . ' ORDER BY scheduled_start ASC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return AssetSupport::findWorkOrder($id, AssetSupport::requireCompanyId());
    }

    /**
     * Calendar range for maintenance calendar view.
     *
     * @return list<array<string, mixed>>
     */
    public function calendar(?string $from = null, ?string $to = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL AND scheduled_start IS NOT NULL';
        if ($from !== null && $from !== '') {
            $where .= ' AND scheduled_start >= :from';
            $params['from'] = $from;
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND scheduled_start <= :to';
            $params['to'] = $to;
        }
        $rows = (new EamWorkOrder())->query(
            'SELECT * FROM rateb_eam_work_orders WHERE ' . $where . ' ORDER BY scheduled_start ASC LIMIT 200',
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, work_order_no: string}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $woNo = trim((string) ($input['work_order_no'] ?? ''));
        if ($woNo === '') {
            $woNo = AssetSupport::nextWorkOrderNo($companyId);
        }
        $workType = (string) ($input['work_type'] ?? 'corrective');
        if (!in_array($workType, ['preventive', 'corrective', 'inspection', 'other'], true)) {
            $workType = 'corrective';
        }
        $requestId = AssetSupport::intOrNull($input['request_id'] ?? null);
        if ($requestId !== null) {
            AssetSupport::assertMaintenanceRequest($requestId, $companyId);
        }
        $id = (new EamWorkOrder())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'request_id' => $requestId,
            'plan_id' => AssetSupport::intOrNull($input['plan_id'] ?? null),
            'work_order_no' => substr($woNo, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => AssetSupport::nullIfEmpty($input['description'] ?? null),
            'work_type' => $workType,
            'workflow_status' => AssetWorkflowService::REQ_NEW,
            'assignee_user_id' => AssetSupport::intOrNull($input['assignee_user_id'] ?? null),
            'scheduled_start' => AssetSupport::nullIfEmpty($input['scheduled_start'] ?? null),
            'scheduled_end' => AssetSupport::nullIfEmpty($input['scheduled_end'] ?? null),
            'labor_hours' => AssetSupport::floatOrNull($input['labor_hours'] ?? null),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new AssetTimelineService())->record(
            'work_order_created',
            'Work order: ' . $title,
            null,
            $assetId,
            'work_order',
            (int) $id
        );

        return ['id' => (int) $id, 'work_order_no' => $woNo];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function consumePart(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $woId = (int) ($input['work_order_id'] ?? 0);
        AssetSupport::assertWorkOrder($woId, $companyId);
        $partName = trim((string) ($input['part_name'] ?? ''));
        if ($partName === '') {
            throw new \InvalidArgumentException('part_name_required');
        }
        $id = (new EamPartsConsumption())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'work_order_id' => $woId,
            'spare_part_id' => AssetSupport::intOrNull($input['spare_part_id'] ?? null),
            'part_name' => substr($partName, 0, 190),
            'quantity' => AssetSupport::floatOrNull($input['quantity'] ?? null) ?? 1.0,
            'unit_cost' => AssetSupport::floatOrNull($input['unit_cost'] ?? null),
            'consumed_at' => AssetSupport::nullIfEmpty($input['consumed_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ChecklistService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamChecklist())->query(
            'SELECT * FROM rateb_eam_checklists WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_checklists', 'CHK', $companyId);
        }
        $type = (string) ($input['checklist_type'] ?? 'inspection');
        if (!in_array($type, ['inspection', 'maintenance', 'other'], true)) {
            $type = 'inspection';
        }
        $id = (new EamChecklist())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'checklist_type' => $type,
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        $items = $input['items'] ?? [];
        if (is_array($items)) {
            $sort = 0;
            foreach ($items as $itemText) {
                $text = trim((string) $itemText);
                if ($text === '') {
                    continue;
                }
                (new EamChecklistItem())->create(array_merge([
                    'public_uuid' => AssetSupport::uuidV4(),
                    'company_id' => $companyId,
                    'checklist_id' => (int) $id,
                    'sort_order' => $sort++,
                    'item_text' => substr($text, 0, 255),
                    'is_required' => 1,
                ], AssetSupport::actorFields(true)));
            }
        }

        return ['id' => (int) $id];
    }
}

final class InspectionService
{
    /** @return list<array<string, mixed>> */
    public function list(?int $assetId = null, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($assetId !== null && $assetId > 0) {
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $rows = (new EamInspection())->query(
            'SELECT * FROM rateb_eam_inspections WHERE ' . $where
            . ' ORDER BY inspected_at DESC, id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $inspNo = 'INSP-' . date('Y') . '-' . str_pad((string) (time() % 100000), 5, '0', STR_PAD_LEFT);
        $result = (string) ($input['result'] ?? 'pass');
        if (!in_array($result, ['pass', 'fail', 'conditional', 'na'], true)) {
            $result = 'pass';
        }
        $id = (new EamInspection())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'checklist_id' => AssetSupport::intOrNull($input['checklist_id'] ?? null),
            'work_order_id' => AssetSupport::intOrNull($input['work_order_id'] ?? null),
            'inspection_no' => $inspNo,
            'inspected_at' => AssetSupport::nullIfEmpty($input['inspected_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'result' => $result,
            'inspector_user_id' => AssetSupport::intOrNull($input['inspector_user_id'] ?? null) ?? AssetSupport::userId(),
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => 'draft',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new AssetTimelineService())->record(
            'inspection_created',
            'Inspection recorded',
            null,
            $assetId,
            'inspection',
            (int) $id
        );

        return ['id' => (int) $id];
    }
}

final class MeterReadingService
{
    /** @return list<array<string, mixed>> */
    public function listForAsset(int $assetId, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($assetId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new EamMeterReading())->query(
            'SELECT * FROM rateb_eam_meter_readings WHERE company_id = :cid AND asset_id = :aid AND deleted_at IS NULL
             ORDER BY reading_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'aid' => $assetId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        if (!isset($input['reading_value']) || $input['reading_value'] === '') {
            throw new \InvalidArgumentException('reading_value_required');
        }
        $id = (new EamMeterReading())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'meter_name' => substr(trim((string) ($input['meter_name'] ?? 'hours')), 0, 80) ?: 'hours',
            'reading_value' => (float) $input['reading_value'],
            'reading_at' => AssetSupport::nullIfEmpty($input['reading_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class WarrantyService
{
    /** @return list<array<string, mixed>> */
    public function listForAsset(int $assetId): array
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($assetId, $companyId);
        $rows = (new EamWarranty())->query(
            'SELECT * FROM rateb_eam_warranties WHERE company_id = :cid AND asset_id = :aid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'aid' => $assetId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $id = (new EamWarranty())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'provider_name' => AssetSupport::nullIfEmpty($input['provider_name'] ?? null),
            'policy_no' => AssetSupport::nullIfEmpty($input['policy_no'] ?? null),
            'start_date' => AssetSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => AssetSupport::nullIfEmpty($input['end_date'] ?? null),
            'coverage_notes' => AssetSupport::nullIfEmpty($input['coverage_notes'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class InsuranceService
{
    /** @return list<array<string, mixed>> */
    public function listForAsset(int $assetId): array
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($assetId, $companyId);
        $rows = (new EamInsurance())->query(
            'SELECT * FROM rateb_eam_insurance WHERE company_id = :cid AND asset_id = :aid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'aid' => $assetId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $id = (new EamInsurance())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'insurer_name' => AssetSupport::nullIfEmpty($input['insurer_name'] ?? null),
            'policy_no' => AssetSupport::nullIfEmpty($input['policy_no'] ?? null),
            'start_date' => AssetSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => AssetSupport::nullIfEmpty($input['end_date'] ?? null),
            'coverage_amount' => AssetSupport::floatOrNull($input['coverage_amount'] ?? null),
            'currency_code' => AssetSupport::nullIfEmpty($input['currency_code'] ?? null),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetActivityService
{
    /** @return list<array<string, mixed>> */
    public function listForAsset(int $assetId, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($assetId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new EamActivity())->query(
            'SELECT * FROM rateb_eam_activities WHERE company_id = :cid AND asset_id = :aid AND deleted_at IS NULL
             ORDER BY activity_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'aid' => $assetId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $id = (new EamActivity())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'activity_type' => substr(trim((string) ($input['activity_type'] ?? 'note')), 0, 40) ?: 'note',
            'subject' => substr($subject, 0, 190),
            'body' => AssetSupport::nullIfEmpty($input['body'] ?? null),
            'activity_at' => AssetSupport::nullIfEmpty($input['activity_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'owner_user_id' => AssetSupport::intOrNull($input['owner_user_id'] ?? null) ?? AssetSupport::userId(),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new AssetTimelineService())->record(
            'activity',
            $subject,
            AssetSupport::nullIfEmpty($input['body'] ?? null) !== null ? (string) $input['body'] : null,
            $assetId,
            'activity',
            (int) $id
        );

        return ['id' => (int) $id];
    }
}

final class AssetCommentService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $relatedType, int $relatedId, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EamComment())->query(
            'SELECT * FROM rateb_eam_comments WHERE company_id = :cid AND related_type = :rt AND related_id = :rid
             AND deleted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'rt' => $relatedType, 'rid' => $relatedId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('body_required');
        }
        $relatedType = substr(trim((string) ($input['related_type'] ?? 'asset')), 0, 40);
        $relatedId = (int) ($input['related_id'] ?? $input['asset_id'] ?? 0);
        if ($relatedId < 1) {
            throw new \InvalidArgumentException('related_id_required');
        }
        if ($relatedType === 'asset') {
            AssetSupport::assertAsset($relatedId, $companyId);
        }
        $id = (new EamComment())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'body' => $body,
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

/**
 * Document metadata only. Binary uploads remain ONLINE via DocumentService (not offline-replayable).
 */
final class AssetDocumentMetaService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $relatedType, int $relatedId): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamDocumentMeta())->query(
            'SELECT * FROM rateb_eam_document_meta WHERE company_id = :cid AND related_type = :rt AND related_id = :rid
             AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'rt' => $relatedType, 'rid' => $relatedId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $relatedType = substr(trim((string) ($input['related_type'] ?? 'asset')), 0, 40);
        $relatedId = (int) ($input['related_id'] ?? $input['asset_id'] ?? 0);
        if ($relatedId < 1) {
            throw new \InvalidArgumentException('related_id_required');
        }
        $id = (new EamDocumentMeta())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'document_id' => AssetSupport::intOrNull($input['document_id'] ?? null),
            'title' => substr($title, 0, 190),
            'doc_type' => AssetSupport::nullIfEmpty($input['doc_type'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class SparePartRefService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $partName = trim((string) ($input['part_name'] ?? ''));
        if ($partName === '') {
            throw new \InvalidArgumentException('part_name_required');
        }
        $assetId = AssetSupport::intOrNull($input['asset_id'] ?? null);
        if ($assetId !== null) {
            AssetSupport::assertAsset($assetId, $companyId);
        }
        $id = (new EamSparePartRef())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'part_sku' => AssetSupport::nullIfEmpty($input['part_sku'] ?? null),
            'part_name' => substr($partName, 0, 190),
            'inventory_item_id' => AssetSupport::intOrNull($input['inventory_item_id'] ?? null),
            'qty_on_hand' => AssetSupport::floatOrNull($input['qty_on_hand'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
