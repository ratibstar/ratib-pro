<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivityType;
use Rateb\App\Models\CrmAutomationRule;

/** Phase 4 — CRM admin configuration (stages/loss reasons reuse PipelineService). */
final class CrmAdminConfigService
{
    /**
     * @return array{
     *   pipelines: list<array<string,mixed>>,
     *   loss_reasons: list<array<string,mixed>>,
     *   activity_types: list<array<string,mixed>>,
     *   automation_rules: list<array<string,mixed>>
     * }
     */
    public function overview(): array
    {
        $pipe = new PipelineService();

        return [
            'pipelines' => $pipe->listPipelines(),
            'loss_reasons' => $pipe->listLossReasons(),
            'activity_types' => $this->listActivityTypes(),
            'automation_rules' => $this->listAutomationRules(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listActivityTypes(): array
    {
        $rows = (new CrmActivityType())->query(
            'SELECT * FROM rateb_crm_activity_types
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function saveActivityType(array $input, ?int $id = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        if ($code === '') {
            $code = strtolower(preg_replace('/\s+/', '_', $name) ?? 'type');
        }
        $payload = array_merge([
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'is_active' => isset($input['is_active']) ? (!empty($input['is_active']) ? 1 : 0) : 1,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ], CrmSupport::actorFields($id === null));

        if ($id !== null && $id > 0) {
            (new CrmActivityType())->update($id, $payload);
            $this->audit('crm.config.activity_type_update', 'crm_activity_type', $id, $payload);

            return ['id' => $id];
        }
        $newId = (new CrmActivityType())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
        ], $payload));
        $this->audit('crm.config.activity_type_create', 'crm_activity_type', (int) $newId, $payload);

        return ['id' => (int) $newId];
    }

    /** @return list<array<string, mixed>> */
    public function listAutomationRules(): array
    {
        $rows = (new CrmAutomationRule())->query(
            'SELECT * FROM rateb_crm_automation_rules
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY rule_key ASC',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateAutomationRule(int $id, array $input): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmAutomationRule())->queryOne(
            'SELECT * FROM rateb_crm_automation_rules WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            throw new \RuntimeException('automation_rule_not_found');
        }
        $patch = array_merge([
            'is_enabled' => !empty($input['is_enabled']) ? 1 : 0,
            'name' => array_key_exists('name', $input)
                ? substr(trim((string) $input['name']), 0, 160)
                : $row['name'],
            'config_json' => array_key_exists('config_json', $input)
                ? (string) $input['config_json']
                : ($row['config_json'] ?? null),
        ], CrmSupport::actorFields(false));
        (new CrmAutomationRule())->update($id, $patch);
        $this->audit('crm.config.automation_rule_update', 'crm_automation_rule', $id, $patch);
    }

    public function isRuleEnabled(string $ruleKey): bool
    {
        $row = (new CrmAutomationRule())->queryOne(
            'SELECT is_enabled FROM rateb_crm_automation_rules
             WHERE company_id = :cid AND rule_key = :rk AND deleted_at IS NULL LIMIT 1',
            ['cid' => CrmSupport::requireCompanyId(), 'rk' => $ruleKey]
        );
        if ($row === null) {
            return true;
        }

        return (int) ($row['is_enabled'] ?? 0) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleConfig(string $ruleKey): array
    {
        $row = (new CrmAutomationRule())->queryOne(
            'SELECT config_json FROM rateb_crm_automation_rules
             WHERE company_id = :cid AND rule_key = :rk AND deleted_at IS NULL LIMIT 1',
            ['cid' => CrmSupport::requireCompanyId(), 'rk' => $ruleKey]
        );
        if ($row === null || empty($row['config_json'])) {
            return [];
        }
        $decoded = json_decode((string) $row['config_json'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function audit(string $action, string $entityType, int $entityId, array $payload): void
    {
        if (class_exists(AuditService::class)) {
            (new AuditService())->log($action, $entityType, $entityId, $payload);
        }
    }
}
