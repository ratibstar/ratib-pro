<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAssignment;
use Rateb\App\Models\CrmCampaign;
use Rateb\App\Models\CrmCompany;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmEntityStatusHistory;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmLeadSource;
use Rateb\App\Models\CrmLossReason;
use Rateb\App\Models\CrmNote;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmOpportunityOutcome;
use Rateb\App\Models\CrmPipeline;
use Rateb\App\Models\CrmPipelineStage;
use Rateb\App\Models\CrmTag;

/**
 * Phase 17A — CRM core domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 */

final class LeadService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR contact_name LIKE :q2 OR lead_no LIKE :q3 OR email LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        $totalRow = (new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE ' . $where,
            $params
        );
        $items = (new CrmLead())->query(
            'SELECT * FROM rateb_crm_leads WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return CrmSupport::findLead($id, CrmSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, lead_no: string}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $leadNo = trim((string) ($input['lead_no'] ?? ''));
        if ($leadNo === '') {
            $leadNo = CrmSupport::nextLeadNo($companyId);
        }
        $id = (new CrmLead())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::intOrNull($input['branch_id'] ?? null) ?? CrmSupport::branchId(),
            'lead_no' => $leadNo,
            'title' => substr($title, 0, 190),
            'contact_name' => CrmSupport::nullIfEmpty($input['contact_name'] ?? null),
            'email' => CrmSupport::nullIfEmpty($input['email'] ?? null),
            'phone' => CrmSupport::nullIfEmpty($input['phone'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'source_id' => CrmSupport::intOrNull($input['source_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'workflow_status' => CrmWorkflowService::STATUS_NEW,
            'estimated_value' => isset($input['estimated_value']) && $input['estimated_value'] !== ''
                ? (float) $input['estimated_value'] : null,
            'currency_code' => CrmSupport::nullIfEmpty($input['currency_code'] ?? null),
            'expected_close_date' => CrmSupport::nullIfEmpty($input['expected_close_date'] ?? null),
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'status' => 'active',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'lead_created',
            'Lead created: ' . $title,
            null,
            'lead',
            (int) $id,
            ['lead_id' => (int) $id, 'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null)]
        );

        return ['id' => (int) $id, 'lead_no' => $leadNo];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = CrmSupport::requireCompanyId();
        CrmSupport::assertLead($id, $companyId);
        $patch = CrmSupport::actorFields(false);
        foreach (['title', 'contact_name', 'email', 'phone', 'notes', 'currency_code', 'expected_close_date', 'priority'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title' ? substr(trim((string) $input[$f]), 0, 190) : CrmSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['crm_company_id', 'contact_id', 'customer_id', 'source_id', 'owner_user_id', 'branch_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = CrmSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('estimated_value', $input)) {
            $patch['estimated_value'] = $input['estimated_value'] !== '' && $input['estimated_value'] !== null
                ? (float) $input['estimated_value'] : null;
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        // workflow_status must only change via CrmWorkflowService
        unset($patch['workflow_status']);
        (new CrmLead())->update($id, $patch);
        (new CrmTimelineService())->record('lead_updated', 'Lead updated', null, 'lead', $id, ['lead_id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $companyId = CrmSupport::requireCompanyId();
        CrmSupport::assertLead($id, $companyId);
        (new CrmLead())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], CrmSupport::actorFields(false)));
        (new CrmTimelineService())->record('lead_deleted', 'Lead soft-deleted', null, 'lead', $id, ['lead_id' => $id]);
    }

    /** @return array<string, int> */
    public function boardCounts(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $out = [];
        foreach (CrmWorkflowService::statuses() as $st) {
            $row = (new CrmLead())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_crm_leads
                 WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $out[$st] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }
}

final class OpportunityService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR opportunity_no LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new CrmOpportunity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_opportunities WHERE ' . $where,
            $params
        );
        $items = (new CrmOpportunity())->query(
            'SELECT * FROM rateb_crm_opportunities WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmOpportunity())->queryOne(
            'SELECT * FROM rateb_crm_opportunities WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, opportunity_no: string}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $no = trim((string) ($input['opportunity_no'] ?? ''));
        if ($no === '') {
            $no = CrmSupport::nextOpportunityNo($companyId);
        }
        $pipelineId = CrmSupport::intOrNull($input['pipeline_id'] ?? null);
        $stageId = CrmSupport::intOrNull($input['stage_id'] ?? null);
        if ($pipelineId === null) {
            $def = (new PipelineService())->defaultPipeline();
            $pipelineId = $def !== null ? (int) $def['id'] : null;
            if ($pipelineId !== null && $stageId === null) {
                $stages = (new PipelineService())->stagesFor($pipelineId);
                $stageId = $stages !== [] ? (int) $stages[0]['id'] : null;
            }
        }
        $id = (new CrmOpportunity())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::intOrNull($input['branch_id'] ?? null) ?? CrmSupport::branchId(),
            'opportunity_no' => $no,
            'name' => substr($name, 0, 190),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'pipeline_id' => $pipelineId,
            'stage_id' => $stageId,
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'team_id' => CrmSupport::intOrNull($input['team_id'] ?? null),
            'amount' => (float) ($input['amount'] ?? 0),
            'currency_code' => CrmSupport::nullIfEmpty($input['currency_code'] ?? null),
            'probability_percent' => (float) ($input['probability_percent'] ?? 0),
            'expected_close_date' => CrmSupport::nullIfEmpty($input['expected_close_date'] ?? null),
            'stage_entered_at' => date('Y-m-d H:i:s'),
            'workflow_status' => 'open',
            'status' => 'active',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        $custId = CrmSupport::intOrNull($input['customer_id'] ?? null);
        if ($custId !== null && $custId > 0) {
            try {
                (new CrmLifecycleService())->ensureAtLeast($custId, 'opportunity', 'opportunity_created');
            } catch (\Throwable $e) {
                // best-effort lifecycle
            }
        }

        (new CrmTimelineService())->record(
            'opportunity_created',
            'Opportunity created: ' . $name,
            null,
            'opportunity',
            (int) $id,
            [
                'opportunity_id' => (int) $id,
                'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            ]
        );

        return ['id' => (int) $id, 'opportunity_no' => $no];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new \RuntimeException('opportunity_not_found');
        }
        $patch = CrmSupport::actorFields(false);
        foreach (['name', 'name_ar', 'notes', 'currency_code', 'expected_close_date', 'workflow_status'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name' ? substr(trim((string) $input[$f]), 0, 190) : CrmSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['lead_id', 'crm_company_id', 'contact_id', 'customer_id', 'pipeline_id', 'stage_id', 'owner_user_id', 'team_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = CrmSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('amount', $input)) {
            $patch['amount'] = (float) $input['amount'];
        }
        if (array_key_exists('probability_percent', $input)) {
            $patch['probability_percent'] = (float) $input['probability_percent'];
        }
        (new CrmOpportunity())->update($id, $patch);
        (new CrmTimelineService())->record(
            'opportunity_updated',
            'Opportunity updated',
            null,
            'opportunity',
            $id,
            ['opportunity_id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $meta loss_reason_id / loss_notes for lost stages
     */
    public function moveStage(int $id, int $stageId, array $meta = []): void
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new \RuntimeException('opportunity_not_found');
        }
        $companyId = CrmSupport::requireCompanyId();
        $stage = (new CrmPipelineStage())->queryOne(
            'SELECT * FROM rateb_crm_pipeline_stages
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $stageId, 'cid' => $companyId]
        );
        if ($stage === null) {
            throw new \RuntimeException('stage_not_found');
        }
        $prob = (float) ($stage['probability_percent'] ?? 0);
        $amount = (float) ($row['amount'] ?? 0);
        $expected = round($amount * $prob / 100, 2);
        $fromStageId = CrmSupport::intOrNull($row['stage_id'] ?? null);
        $patch = array_merge([
            'stage_id' => $stageId,
            'pipeline_id' => (int) ($stage['pipeline_id'] ?? 0),
            'probability_percent' => $prob,
            'stage_entered_at' => date('Y-m-d H:i:s'),
        ], CrmSupport::actorFields(false));
        $outcome = null;
        if ((int) ($stage['is_won'] ?? 0) === 1) {
            $patch['workflow_status'] = 'won';
            $outcome = 'won';
        } elseif ((int) ($stage['is_lost'] ?? 0) === 1) {
            $patch['workflow_status'] = 'lost';
            $outcome = 'lost';
            $lossReasonId = CrmSupport::intOrNull($meta['loss_reason_id'] ?? null);
            $reasons = (new PipelineService())->listLossReasons();
            if ($lossReasonId === null && $reasons !== []) {
                throw new \InvalidArgumentException('loss_reason_required');
            }
            if ($lossReasonId !== null) {
                $patch['loss_reason_id'] = $lossReasonId;
            }
            $patch['loss_notes'] = CrmSupport::nullIfEmpty($meta['loss_notes'] ?? null);
        }
        (new CrmPipelineHealthService())->recordTransition(
            $id,
            $fromStageId,
            $stageId,
            (int) ($stage['pipeline_id'] ?? ($row['pipeline_id'] ?? 0)) ?: null,
            isset($row['stage_entered_at']) ? (string) $row['stage_entered_at'] : null,
            CrmSupport::intOrNull($row['owner_user_id'] ?? null),
            CrmSupport::intOrNull($row['team_id'] ?? null),
            ['outcome' => $outcome]
        );
        (new CrmOpportunity())->update($id, $patch);
        (new CrmEntityStatusHistory())->create([
            'company_id' => $companyId,
            'entity_type' => 'opportunity_stage',
            'entity_id' => $id,
            'from_status' => $fromStageId !== null ? (string) $fromStageId : null,
            'to_status' => (string) $stageId,
            'reason' => $outcome,
            'created_by' => CrmSupport::userId(),
        ]);
        if ($outcome !== null) {
            (new CrmOpportunityOutcome())->create([
                'company_id' => $companyId,
                'opportunity_id' => $id,
                'outcome' => $outcome,
                'loss_reason_id' => $outcome === 'lost' ? ($patch['loss_reason_id'] ?? null) : null,
                'amount' => $amount,
                'probability_percent' => $prob,
                'expected_revenue' => $expected,
                'notes' => $patch['loss_notes'] ?? null,
                'created_by' => CrmSupport::userId(),
            ]);
        }
        $stageName = (string) ($stage['name'] ?? $stageId);
        (new CrmTimelineService())->record(
            'opportunity_stage',
            'Stage → ' . $stageName,
            $outcome === 'lost' ? (string) ($patch['loss_notes'] ?? '') : null,
            'opportunity',
            $id,
            ['opportunity_id' => $id]
        );
        (new CrmAutomationService())->onOpportunityStageChanged(
            $id,
            $stageName,
            CrmSupport::intOrNull($row['owner_user_id'] ?? null),
            $patch['workflow_status'] ?? null
        );
        if ($outcome === 'won') {
            $custId = CrmSupport::intOrNull($row['customer_id'] ?? null);
            if ($custId !== null && $custId > 0) {
                try {
                    (new CrmLifecycleService())->ensureAtLeast($custId, 'active_customer', 'opportunity_won');
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.opportunity.stage', 'crm_opportunity', $id, [
                'stage_id' => $stageId,
                'from_stage_id' => $fromStageId,
                'stage' => $stageName,
                'outcome' => $outcome,
                'expected_revenue' => $expected,
            ]);
        }
    }

    public static function expectedRevenue(float $amount, float $probabilityPercent): float
    {
        return round($amount * $probabilityPercent / 100, 2);
    }
}

final class PipelineService
{
    /** @return list<array<string, mixed>> */
    public function listPipelines(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmPipeline())->query(
            'SELECT * FROM rateb_crm_pipelines WHERE company_id = :cid AND deleted_at IS NULL ORDER BY is_default DESC, name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function defaultPipeline(): ?array
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmPipeline())->queryOne(
            'SELECT * FROM rateb_crm_pipelines
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY is_default DESC, id ASC LIMIT 1',
            ['cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function stagesFor(int $pipelineId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmPipelineStage())->query(
            'SELECT * FROM rateb_crm_pipeline_stages
             WHERE company_id = :cid AND pipeline_id = :pid AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC',
            ['cid' => $companyId, 'pid' => $pipelineId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createPipeline(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_pipelines', 'PL', $companyId);
        }
        $id = (new CrmPipeline())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'is_default' => !empty($input['is_default']) ? 1 : 0,
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        $defaults = [
            ['qualification', 'Qualification', 10, 0, 0],
            ['proposal', 'Proposal', 40, 0, 0],
            ['negotiation', 'Negotiation', 70, 0, 0],
            ['won', 'Won', 100, 1, 0],
            ['lost', 'Lost', 0, 0, 1],
        ];
        $order = 0;
        foreach ($defaults as [$scode, $sname, $prob, $won, $lost]) {
            (new CrmPipelineStage())->create(array_merge([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'pipeline_id' => (int) $id,
                'code' => $scode,
                'name' => $sname,
                'sort_order' => $order++,
                'probability_percent' => $prob,
                'is_won' => $won,
                'is_lost' => $lost,
                'status' => 'active',
            ], CrmSupport::actorFields(true)));
        }

        return ['id' => (int) $id];
    }

    /**
     * @return array{pipeline: array<string,mixed>|null, stages: list<array<string,mixed>>, opportunities: list<array<string,mixed>>}
     */
    public function board(?int $pipelineId = null): array
    {
        $pipe = $pipelineId !== null && $pipelineId > 0
            ? (new CrmPipeline())->queryOne(
                'SELECT * FROM rateb_crm_pipelines WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['id' => $pipelineId, 'cid' => CrmSupport::requireCompanyId()]
            )
            : $this->defaultPipeline();
        if (!is_array($pipe)) {
            return ['pipeline' => null, 'stages' => [], 'opportunities' => []];
        }
        $pid = (int) $pipe['id'];
        $stages = $this->stagesFor($pid);
        $opps = (new CrmOpportunity())->query(
            'SELECT * FROM rateb_crm_opportunities
             WHERE company_id = :cid AND pipeline_id = :pid AND deleted_at IS NULL
             ORDER BY updated_at DESC',
            ['cid' => CrmSupport::requireCompanyId(), 'pid' => $pid]
        );

        $list = is_array($opps) ? $opps : [];
        foreach ($list as &$opp) {
            $opp['expected_revenue'] = OpportunityService::expectedRevenue(
                (float) ($opp['amount'] ?? 0),
                (float) ($opp['probability_percent'] ?? 0)
            );
        }
        unset($opp);

        return [
            'pipeline' => $pipe,
            'stages' => $stages,
            'opportunities' => $list,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function upsertStage(array $input, ?int $stageId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $pipelineId = (int) ($input['pipeline_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($pipelineId < 1 || $name === '') {
            throw new \InvalidArgumentException('stage_fields_required');
        }
        $pipe = (new CrmPipeline())->queryOne(
            'SELECT id FROM rateb_crm_pipelines WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $pipelineId, 'cid' => $companyId]
        );
        if ($pipe === null) {
            throw new \RuntimeException('pipeline_not_found');
        }
        $durationDays = array_key_exists('expected_duration_days', $input)
            ? CrmSupport::intOrNull($input['expected_duration_days'])
            : null;
        $payload = array_merge([
            'pipeline_id' => $pipelineId,
            'code' => substr(trim((string) ($input['code'] ?? preg_replace('/\s+/', '_', strtolower($name)))), 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'probability_percent' => max(0, min(100, (float) ($input['probability_percent'] ?? 0))),
            'expected_duration_days' => $durationDays !== null && $durationDays > 0 ? $durationDays : null,
            'is_won' => !empty($input['is_won']) ? 1 : 0,
            'is_lost' => !empty($input['is_lost']) ? 1 : 0,
            'status' => 'active',
        ], CrmSupport::actorFields($stageId === null));

        if ($stageId !== null && $stageId > 0) {
            $existing = (new CrmPipelineStage())->queryOne(
                'SELECT id FROM rateb_crm_pipeline_stages WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['id' => $stageId, 'cid' => $companyId]
            );
            if ($existing === null) {
                throw new \RuntimeException('stage_not_found');
            }
            (new CrmPipelineStage())->update($stageId, $payload);
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.pipeline.stage_update', 'crm_pipeline_stage', $stageId, $payload);
            }

            return ['id' => $stageId];
        }

        $id = (new CrmPipelineStage())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
        ], $payload));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.pipeline.stage_create', 'crm_pipeline_stage', (int) $id, $payload);
        }

        return ['id' => (int) $id];
    }

    /** @return list<array<string, mixed>> */
    public function listLossReasons(): array
    {
        $rows = (new CrmLossReason())->query(
            "SELECT * FROM rateb_crm_loss_reasons
             WHERE company_id = :cid AND deleted_at IS NULL AND status = 'active'
             ORDER BY sort_order ASC, name ASC",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createLossReason(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_loss_reasons', 'LR', $companyId);
        }
        $id = (new CrmLossReason())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class CrmCompanyService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR email LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new CrmCompany())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_companies WHERE ' . $where,
            $params
        );
        $items = (new CrmCompany())->query(
            'SELECT * FROM rateb_crm_companies WHERE ' . $where
            . ' ORDER BY name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = (new CrmCompany())->queryOne(
            'SELECT * FROM rateb_crm_companies WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_companies', 'AC', $companyId);
        }
        $id = (new CrmCompany())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'industry' => CrmSupport::nullIfEmpty($input['industry'] ?? null),
            'website' => CrmSupport::nullIfEmpty($input['website'] ?? null),
            'phone' => CrmSupport::nullIfEmpty($input['phone'] ?? null),
            'email' => CrmSupport::nullIfEmpty($input['email'] ?? null),
            'city' => CrmSupport::nullIfEmpty($input['city'] ?? null),
            'country_code' => CrmSupport::nullIfEmpty($input['country_code'] ?? null),
            'status' => 'active',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'crm_company_created',
            'Company created: ' . $name,
            null,
            'crm_company',
            (int) $id,
            ['crm_company_id' => (int) $id, 'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null)]
        );

        return ['id' => (int) $id];
    }

    /**
     * @return array{
     *   contacts: list<array<string,mixed>>,
     *   leads: list<array<string,mixed>>,
     *   opportunities: list<array<string,mixed>>
     * }
     */
    public function relatedGraph(int $crmCompanyId): array
    {
        $tenantId = CrmSupport::requireCompanyId();
        $contacts = (new CrmContact())->query(
            'SELECT * FROM rateb_crm_contacts
             WHERE company_id = :cid AND crm_company_id = :aid AND deleted_at IS NULL
             ORDER BY is_primary DESC, full_name ASC LIMIT 50',
            ['cid' => $tenantId, 'aid' => $crmCompanyId]
        );
        $leads = (new CrmLead())->query(
            'SELECT id, lead_no, title, workflow_status, contact_id
             FROM rateb_crm_leads
             WHERE company_id = :cid AND crm_company_id = :aid AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $tenantId, 'aid' => $crmCompanyId]
        );
        $opps = (new CrmOpportunity())->query(
            'SELECT id, opportunity_no, name, workflow_status, amount, lead_id, contact_id
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND crm_company_id = :aid AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $tenantId, 'aid' => $crmCompanyId]
        );

        return [
            'contacts' => is_array($contacts) ? $contacts : [],
            'leads' => is_array($leads) ? $leads : [],
            'opportunities' => is_array($opps) ? $opps : [],
        ];
    }
}

final class ContactService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?int $crmCompanyId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'c.company_id = :cid AND c.deleted_at IS NULL';
        if ($crmCompanyId !== null && $crmCompanyId > 0) {
            $where .= ' AND c.crm_company_id = :aid';
            $params['aid'] = $crmCompanyId;
        }
        if ($search !== '') {
            $where .= ' AND (c.full_name LIKE :q OR c.email LIKE :q2 OR c.phone LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new CrmContact())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_contacts c WHERE ' . $where,
            $params
        );
        $items = (new CrmContact())->query(
            'SELECT c.*, co.name AS crm_company_name
             FROM rateb_crm_contacts c
             LEFT JOIN rateb_crm_companies co ON co.id = c.crm_company_id
             WHERE ' . $where
            . ' ORDER BY c.full_name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = (new CrmContact())->queryOne(
            'SELECT c.*, co.name AS crm_company_name
             FROM rateb_crm_contacts c
             LEFT JOIN rateb_crm_companies co ON co.id = c.crm_company_id
             WHERE c.id = :id AND c.company_id = :cid AND c.deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{leads: list<array<string,mixed>>, opportunities: list<array<string,mixed>>}
     */
    public function relatedGraph(int $contactId): array
    {
        $tenantId = CrmSupport::requireCompanyId();
        $leads = (new CrmLead())->query(
            'SELECT id, lead_no, title, workflow_status, crm_company_id
             FROM rateb_crm_leads
             WHERE company_id = :cid AND contact_id = :ct AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $tenantId, 'ct' => $contactId]
        );
        $opps = (new CrmOpportunity())->query(
            'SELECT id, opportunity_no, name, workflow_status, amount, lead_id
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND contact_id = :ct AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $tenantId, 'ct' => $contactId]
        );

        return [
            'leads' => is_array($leads) ? $leads : [],
            'opportunities' => is_array($opps) ? $opps : [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['full_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('full_name_required');
        }
        $id = (new CrmContact())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'full_name' => substr($name, 0, 190),
            'full_name_ar' => CrmSupport::nullIfEmpty($input['full_name_ar'] ?? null),
            'job_title' => CrmSupport::nullIfEmpty($input['job_title'] ?? null),
            'email' => CrmSupport::nullIfEmpty($input['email'] ?? null),
            'phone' => CrmSupport::nullIfEmpty($input['phone'] ?? null),
            'mobile' => CrmSupport::nullIfEmpty($input['mobile'] ?? null),
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'status' => 'active',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'contact_created',
            'Contact created: ' . $name,
            null,
            'contact',
            (int) $id,
            [
                'contact_id' => (int) $id,
                'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            ]
        );

        return ['id' => (int) $id];
    }
}

final class CampaignService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new CrmCampaign())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_campaigns WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new CrmCampaign())->query(
            'SELECT * FROM rateb_crm_campaigns WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_campaigns', 'CP', $companyId);
        }
        $type = (string) ($input['campaign_type'] ?? 'other');
        if (!in_array($type, ['email', 'call', 'event', 'social', 'other'], true)) {
            $type = 'other';
        }
        $id = (new CrmCampaign())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'campaign_type' => $type,
            'start_date' => CrmSupport::nullIfEmpty($input['start_date'] ?? null),
            'end_date' => CrmSupport::nullIfEmpty($input['end_date'] ?? null),
            'budget' => isset($input['budget']) && $input['budget'] !== '' ? (float) $input['budget'] : null,
            'status' => 'draft',
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
        ], CrmSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class CrmNoteService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('body_required');
        }
        $relatedType = trim((string) ($input['related_type'] ?? 'lead'));
        $relatedId = (int) ($input['related_id'] ?? 0);
        if ($relatedId < 1) {
            throw new \InvalidArgumentException('related_id_required');
        }
        $id = (new CrmNote())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'related_type' => substr($relatedType, 0, 40),
            'related_id' => $relatedId,
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? ($relatedType === 'lead' ? $relatedId : null)),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'body' => $body,
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'note',
            'Note added',
            $body,
            $relatedType,
            $relatedId,
            [
                'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? ($relatedType === 'lead' ? $relatedId : null)),
                'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            ]
        );

        return ['id' => (int) $id];
    }
}

final class CrmAssignmentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function assign(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $relatedType = trim((string) ($input['related_type'] ?? ''));
        $relatedId = (int) ($input['related_id'] ?? 0);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($relatedType === '' || $relatedId < 1 || $assignee < 1) {
            throw new \InvalidArgumentException('assignment_fields_required');
        }
        if ($relatedType === 'lead') {
            (new CrmLead())->update($relatedId, array_merge([
                'owner_user_id' => $assignee,
            ], CrmSupport::actorFields(false)));
            $lead = CrmSupport::findLead($relatedId, $companyId);
            (new CrmAutomationService())->onLeadAssigned(
                $relatedId,
                $assignee,
                is_array($lead) ? (string) ($lead['title'] ?? null) : null
            );
        }
        $id = (new CrmAssignment())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'related_type' => substr($relatedType, 0, 40),
            'related_id' => $relatedId,
            'assignee_user_id' => $assignee,
            'role_label' => CrmSupport::nullIfEmpty($input['role_label'] ?? null),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        if ($relatedType === 'lead') {
            (new CrmLead())->update($relatedId, array_merge([
                'owner_user_id' => $assignee,
            ], CrmSupport::actorFields(false)));
            (new CrmTimelineService())->record(
                'assignment',
                'Assigned to user #' . $assignee,
                null,
                'lead',
                $relatedId,
                ['lead_id' => $relatedId]
            );
        }

        return ['id' => (int) $id];
    }
}

final class LeadSourceService
{
    /** @return list<array<string, mixed>> */
    public function listActive(): array
    {
        $rows = (new CrmLeadSource())->query(
            'SELECT * FROM rateb_crm_lead_sources
             WHERE company_id = :cid AND deleted_at IS NULL AND status = \'active\'
             ORDER BY name ASC',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_lead_sources', 'SRC', $companyId);
        }
        $id = (new CrmLeadSource())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class CrmTagService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_tags', 'TG', $companyId);
        }
        $id = (new CrmTag())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 120),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'color' => CrmSupport::nullIfEmpty($input['color'] ?? null),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
