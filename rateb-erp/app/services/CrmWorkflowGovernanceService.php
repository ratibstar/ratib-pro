<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmStageGovernanceRule;

/**
 * Phase 8 — Pipeline stage governance (rules on existing stages; no separate workflow engine).
 */
final class CrmWorkflowGovernanceService
{
    /** @return list<array<string, mixed>> */
    public function listRules(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $sql = 'SELECT r.*, s.name AS stage_name, s.code AS stage_code
                FROM rateb_crm_stage_governance_rules r
                LEFT JOIN rateb_crm_pipeline_stages s ON s.id = r.stage_id
                WHERE r.company_id = :cid AND r.deleted_at IS NULL';
        if ($pipelineId !== null && $pipelineId > 0) {
            $sql .= ' AND r.pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $sql .= ' ORDER BY r.stage_id ASC';
        $rows = (new CrmStageGovernanceRule())->query($sql, $params);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveRule(array $data): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $stageId = (int) ($data['stage_id'] ?? 0);
        if ($stageId <= 0) {
            throw new \InvalidArgumentException('stage_id_required');
        }
        $payload = [
            'pipeline_id' => CrmSupport::intOrNull($data['pipeline_id'] ?? null),
            'required_fields_json' => $this->encodeList($data['required_fields'] ?? $data['required_fields_json'] ?? []),
            'required_actions_json' => $this->encodeList($data['required_actions'] ?? $data['required_actions_json'] ?? []),
            'approval_required' => !empty($data['approval_required']) ? 1 : 0,
            'ownership_required' => array_key_exists('ownership_required', $data)
                ? (!empty($data['ownership_required']) ? 1 : 0)
                : 1,
            'sla_hours' => CrmSupport::intOrNull($data['sla_hours'] ?? null),
            'is_enabled' => array_key_exists('is_enabled', $data) ? (!empty($data['is_enabled']) ? 1 : 0) : 1,
            'meta_json' => isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null,
        ];
        $existing = (new CrmStageGovernanceRule())->queryOne(
            'SELECT id FROM rateb_crm_stage_governance_rules
             WHERE company_id = :cid AND stage_id = :sid AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'sid' => $stageId]
        );
        if ($existing) {
            (new CrmStageGovernanceRule())->update((int) $existing['id'], array_merge($payload, CrmSupport::actorFields(false)));
            $id = (int) $existing['id'];
        } else {
            $id = (int) (new CrmStageGovernanceRule())->create(array_merge([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'stage_id' => $stageId,
            ], $payload, CrmSupport::actorFields(true)));
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.config', 'crm_stage_governance_rule', $id, [
                'stage_id' => $stageId,
            ]);
        }
        $row = (new CrmStageGovernanceRule())->queryOne(
            'SELECT * FROM rateb_crm_stage_governance_rules WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) ? $row : ['id' => $id];
    }

    /**
     * Enforce stage governance before opportunity stage move.
     *
     * @param array<string, mixed> $meta
     */
    public function assertStageMove(int $opportunityId, int $toStageId, array $meta = []): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $settings = (new CrmGovernanceService())->setting('workflow_governance', [
            'enforce_on_stage_move' => true,
            'default_ownership_required' => true,
        ]);
        if (empty($settings['enforce_on_stage_move'])) {
            return;
        }
        $rule = (new CrmStageGovernanceRule())->queryOne(
            'SELECT * FROM rateb_crm_stage_governance_rules
             WHERE company_id = :cid AND stage_id = :sid AND deleted_at IS NULL AND is_enabled = 1 LIMIT 1',
            ['cid' => $companyId, 'sid' => $toStageId]
        );
        if ($rule === null) {
            return;
        }
        $opp = (new CrmOpportunity())->queryOne(
            'SELECT * FROM rateb_crm_opportunities WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $opportunityId, 'cid' => $companyId]
        );
        if ($opp === null) {
            throw new \RuntimeException('opportunity_not_found');
        }
        if (!empty($rule['ownership_required']) && empty($opp['owner_user_id'])) {
            throw new \RuntimeException('workflow_ownership_required');
        }
        $fields = $this->decodeList($rule['required_fields_json'] ?? null);
        foreach ($fields as $field) {
            $field = trim((string) $field);
            if ($field === '') {
                continue;
            }
            $val = $opp[$field] ?? ($meta[$field] ?? null);
            if ($val === null || $val === '' || $val === []) {
                throw new \RuntimeException('workflow_mandatory_field:' . $field);
            }
        }
        $actions = $this->decodeList($rule['required_actions_json'] ?? null);
        if ($actions !== [] && !empty($meta['validate_actions'])) {
            $completed = $meta['completed_actions'] ?? $meta['actions'] ?? [];
            if (!is_array($completed)) {
                $completed = [];
            }
            foreach ($actions as $action) {
                $action = trim((string) $action);
                if ($action === '') {
                    continue;
                }
                if (!in_array($action, $completed, true) && !array_key_exists($action, $meta)) {
                    throw new \RuntimeException('workflow_required_action:' . $action);
                }
            }
        }
        if (!empty($rule['approval_required']) && empty($meta['approved']) && empty($meta['approval_id'])) {
            throw new \RuntimeException('workflow_approval_required');
        }
    }

    /** @return list<array<string, mixed>> */
    public function slaBreaches(int $limit = 30): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmStageGovernanceRule())->query(
            "SELECT o.id, o.name, o.owner_user_id, o.stage_id, o.stage_entered_at, r.sla_hours, s.name AS stage_name
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_stage_governance_rules r
                ON r.stage_id = o.stage_id AND r.company_id = o.company_id
                AND r.deleted_at IS NULL AND r.is_enabled = 1 AND r.sla_hours IS NOT NULL
             LEFT JOIN rateb_crm_pipeline_stages s ON s.id = o.stage_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL
               AND o.workflow_status NOT IN ('won','lost')
               AND o.stage_entered_at IS NOT NULL
               AND TIMESTAMPDIFF(HOUR, o.stage_entered_at, NOW()) > r.sla_hours
             ORDER BY o.stage_entered_at ASC
             LIMIT " . max(1, min(100, $limit)),
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param mixed $v */
    private function encodeList($v): string
    {
        if (is_string($v)) {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return (string) json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
            }
            $parts = array_filter(array_map('trim', explode(',', $v)));

            return (string) json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
        }
        if (!is_array($v)) {
            return '[]';
        }

        return (string) json_encode(array_values($v), JSON_UNESCAPED_UNICODE);
    }

    /** @return list<string> */
    private function decodeList(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }
}
