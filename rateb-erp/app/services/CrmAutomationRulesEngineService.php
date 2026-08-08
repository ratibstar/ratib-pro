<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAutomationLog;
use Rateb\App\Models\CrmAutomationRule;

/**
 * Phase 6 — Automation rules engine (conditions/actions) via NotificationService only.
 */
final class CrmAutomationRulesEngineService
{
    private NotificationService $notifier;

    public function __construct(?NotificationService $notifier = null)
    {
        $this->notifier = $notifier ?? new NotificationService();
    }

    /** @return list<array<string, mixed>> */
    public function listRules(): array
    {
        return (new CrmAdminConfigService())->listAutomationRules();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveRule(int $id, array $input): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmAutomationRule())->queryOne(
            'SELECT * FROM rateb_crm_automation_rules WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            throw new \RuntimeException('automation_rule_not_found');
        }
        $condition = array_key_exists('condition_json', $input)
            ? $this->normalizeJson($input['condition_json'])
            : ($row['condition_json'] ?? '{"type":"always"}');
        $action = array_key_exists('action_json', $input)
            ? $this->normalizeJson($input['action_json'])
            : ($row['action_json'] ?? '{"type":"notify"}');
        $patch = array_merge([
            'is_enabled' => !empty($input['is_enabled']) ? 1 : 0,
            'name' => array_key_exists('name', $input)
                ? substr(trim((string) $input['name']), 0, 160)
                : $row['name'],
            'config_json' => array_key_exists('config_json', $input)
                ? (string) $input['config_json']
                : ($row['config_json'] ?? null),
            'condition_json' => $condition,
            'action_json' => $action,
        ], CrmSupport::actorFields(false));
        (new CrmAutomationRule())->update($id, $patch);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.automation.rule_update', 'crm_automation_rule', $id, $patch);
        }
    }

    /**
     * Evaluate enabled rules against a context and execute notify actions.
     *
     * @param array<string, mixed> $context keys: entity_type, entity_id, owner_user_id, days_inactive, amount, ...
     * @return array{matched:int,executed:int,history:list<array<string,mixed>>}
     */
    public function evaluate(array $context = []): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safety = new CrmAutomationSafetyService();
        $gov = (new CrmGovernanceService())->automationGovernanceCheck();
        $blockAlwaysOverMax = !empty($safety->settings()['block_always_rules_over_max']);
        $rules = (new CrmAutomationRule())->query(
            'SELECT * FROM rateb_crm_automation_rules
             WHERE company_id = :cid AND deleted_at IS NULL AND is_enabled = 1
             ORDER BY id ASC',
            ['cid' => $companyId]
        );
        $matched = 0;
        $executed = 0;
        $history = [];
        $alwaysSeen = 0;
        $maxAlways = (int) ($gov['max_always_rules'] ?? 3);
        foreach (is_array($rules) ? $rules : [] as $rule) {
            $cond = $this->decode($rule['condition_json'] ?? null);
            $condType = (string) ($cond['type'] ?? 'always');
            if ($condType === 'always') {
                ++$alwaysSeen;
                if ($blockAlwaysOverMax && $alwaysSeen > $maxAlways) {
                    $history[] = [
                        'rule_id' => (int) ($rule['id'] ?? 0),
                        'skipped' => 'always_rule_cap',
                    ];
                    continue;
                }
            }
            if (!$this->matches($cond, $context)) {
                continue;
            }
            ++$matched;
            $ruleId = (int) ($rule['id'] ?? 0);
            if ($safety->recentlyFired('rules_engine', 'crm_automation_rule', $ruleId)) {
                $history[] = [
                    'rule_id' => $ruleId,
                    'skipped' => 'cooldown',
                ];
                continue;
            }
            $action = $this->decode($rule['action_json'] ?? null);
            $ok = $this->executeAction($action, $rule, $context);
            if ($ok) {
                ++$executed;
            }
            $payload = [
                'rule_id' => (int) $rule['id'],
                'rule_key' => (string) ($rule['rule_key'] ?? ''),
                'matched' => true,
                'executed' => $ok,
                'context' => $context,
            ];
            (new CrmAutomationLog())->create([
                'company_id' => $companyId,
                'event_type' => 'rules_engine',
                'entity_type' => (string) ($context['entity_type'] ?? 'crm'),
                'entity_id' => CrmSupport::intOrNull($context['entity_id'] ?? null),
                'user_id' => CrmSupport::intOrNull($context['owner_user_id'] ?? null),
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $history[] = $payload;
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.automation.rule_execute', 'crm_automation_rule', (int) $rule['id'], $payload);
            }
        }

        return ['matched' => $matched, 'executed' => $executed, 'history' => $history];
    }

    /**
     * Execution history from automation log.
     *
     * @return list<array<string, mixed>>
     */
    public function executionHistory(int $limit = 50): array
    {
        $rows = (new CrmAutomationLog())->query(
            "SELECT * FROM rateb_crm_automation_log
             WHERE company_id = :cid AND event_type IN ('rules_engine','follow_up_reminder','quote_expiry',
                'opportunity_inactivity','no_activity','renewal_reminder','stale_opportunity','customer_follow_up')
             ORDER BY id DESC LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $cond
     * @param array<string, mixed> $context
     */
    private function matches(array $cond, array $context): bool
    {
        $type = (string) ($cond['type'] ?? 'always');
        if ($type === 'always') {
            return true;
        }
        if ($type === 'entity') {
            $want = (string) ($cond['entity_type'] ?? '');

            return $want === '' || $want === (string) ($context['entity_type'] ?? '');
        }
        if ($type === 'days_inactive_gte') {
            $min = (int) ($cond['days'] ?? 0);

            return (int) ($context['days_inactive'] ?? 0) >= $min;
        }
        if ($type === 'amount_gte') {
            return (float) ($context['amount'] ?? 0) >= (float) ($cond['amount'] ?? 0);
        }
        if ($type === 'risk_level') {
            return (string) ($context['risk_level'] ?? '') === (string) ($cond['risk_level'] ?? '');
        }

        return false;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $context
     */
    private function executeAction(array $action, array $rule, array $context): bool
    {
        $type = (string) ($action['type'] ?? 'notify');
        if ($type !== 'notify') {
            // Only NotificationService actions are supported (architecture lock).
            return false;
        }
        $companyId = CrmSupport::requireCompanyId();
        $title = (string) ($action['title'] ?? ('CRM: ' . ($rule['name'] ?? $rule['rule_key'] ?? 'Rule')));
        $message = (string) ($action['message'] ?? ('Rule triggered: ' . ($rule['rule_key'] ?? '')));
        $entityType = (string) ($context['entity_type'] ?? 'crm');
        $entityId = (int) ($context['entity_id'] ?? 0);
        $owner = (int) ($context['owner_user_id'] ?? 0);
        if ($owner > 0) {
            $this->notifier->notifyUser(
                $owner,
                $companyId,
                $title,
                $message,
                (string) ($action['severity'] ?? 'info'),
                'crm.rules_engine',
                $entityType,
                $entityId > 0 ? $entityId : null
            );
        } else {
            $this->notifier->notifyCompany(
                $companyId,
                $title,
                $message,
                (string) ($action['severity'] ?? 'info'),
                'crm.rules_engine',
                $entityType,
                $entityId > 0 ? $entityId : null
            );
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return ['type' => 'always'];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : ['type' => 'always'];
    }

    private function normalizeJson(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return '{"type":"always"}';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('invalid_json');
        }

        return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
