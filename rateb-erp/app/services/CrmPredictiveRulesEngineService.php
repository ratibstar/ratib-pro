<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmPredictiveRule;
use Rateb\App\Models\CrmTask;
use Rateb\App\Models\Customer;

/** Phase 9 — Configurable predictive rules engine (no ML). */
final class CrmPredictiveRulesEngineService
{
    /** @return list<array<string, mixed>> */
    public function listRules(): array
    {
        $rows = (new CrmPredictiveRule())->query(
            'SELECT * FROM rateb_crm_predictive_rules
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY priority ASC, id ASC',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveRule(array $data): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $key = substr(trim((string) ($data['rule_key'] ?? '')), 0, 60);
        $name = trim((string) ($data['name'] ?? ''));
        $type = substr(trim((string) ($data['rule_type'] ?? $key)), 0, 40);
        if ($key === '' || $name === '') {
            throw new \InvalidArgumentException('rule_key_and_name_required');
        }
        $config = $data['config'] ?? $data['config_json'] ?? [];
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($config)) {
            $config = [];
        }
        $payload = [
            'name' => substr($name, 0, 120),
            'rule_type' => $type,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'priority' => (int) ($data['priority'] ?? 100),
            'is_enabled' => array_key_exists('is_enabled', $data) ? (!empty($data['is_enabled']) ? 1 : 0) : 1,
        ];
        $existing = (new CrmPredictiveRule())->queryOne(
            'SELECT id FROM rateb_crm_predictive_rules
             WHERE company_id = :cid AND rule_key = :k AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'k' => $key]
        );
        if ($existing) {
            (new CrmPredictiveRule())->update((int) $existing['id'], array_merge($payload, CrmSupport::actorFields(false)));
            $id = (int) $existing['id'];
        } else {
            $id = (int) (new CrmPredictiveRule())->create(array_merge([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'rule_key' => $key,
            ], $payload, CrmSupport::actorFields(true)));
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.predictive.rule_save', 'crm_predictive_rule', $id, [
                'rule_key' => $key,
            ]);
        }
        $row = (new CrmPredictiveRule())->queryOne('SELECT * FROM rateb_crm_predictive_rules WHERE id = :id LIMIT 1', ['id' => $id]);

        return is_array($row) ? $row : ['id' => $id];
    }

    /**
     * @return array{matches:array<string,list<array<string,mixed>>>,counts:array<string,int>}
     */
    public function evaluate(int $limitPerRule = 20): array
    {
        $rules = $this->listRules();
        $matches = [];
        $counts = [];
        foreach ($rules as $rule) {
            if (empty($rule['is_enabled'])) {
                continue;
            }
            $type = (string) ($rule['rule_type'] ?? '');
            $config = json_decode((string) ($rule['config_json'] ?? '{}'), true);
            if (!is_array($config)) {
                $config = [];
            }
            $rows = match ($type) {
                'high_probability' => $this->matchHighProbability($config, $limitPerRule),
                'stale_pipeline' => $this->matchStalePipeline($config, $limitPerRule),
                'churn_risk' => $this->matchChurnRisk($config, $limitPerRule),
                'follow_up_priority' => $this->matchFollowUpPriority($config, $limitPerRule),
                default => [],
            };
            $matches[$type] = $rows;
            $counts[$type] = count($rows);
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.predictive.rule_execute', 'crm_predictive_rules', null, [
                'counts' => $counts,
            ]);
        }

        return ['matches' => $matches, 'counts' => $counts];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function matchHighProbability(array $config, int $limit): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT id, name, amount, probability_percent, intelligence_score, engagement_score, owner_user_id
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
               AND probability_percent >= :p
               AND COALESCE(intelligence_score,0) >= :i
               AND COALESCE(engagement_score,0) >= :e
             ORDER BY probability_percent DESC, amount DESC
             LIMIT " . max(1, min(50, $limit)),
            [
                'cid' => CrmSupport::requireCompanyId(),
                'p' => (float) ($config['min_probability'] ?? 70),
                'i' => (int) ($config['min_intelligence'] ?? 60),
                'e' => (int) ($config['min_engagement'] ?? 40),
            ]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function matchStalePipeline(array $config, int $limit): array
    {
        $staleDays = (int) ($config['stale_days'] ?? 14);
        $stageDays = (int) ($config['stage_days'] ?? 21);
        $rows = (new CrmOpportunity())->query(
            "SELECT id, name, amount, probability_percent, stage_entered_at, updated_at, owner_user_id, is_stale
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
               AND (
                    is_stale = 1
                    OR updated_at <= DATE_SUB(NOW(), INTERVAL {$staleDays} DAY)
                    OR (stage_entered_at IS NOT NULL AND stage_entered_at <= DATE_SUB(NOW(), INTERVAL {$stageDays} DAY))
               )
             ORDER BY updated_at ASC
             LIMIT " . max(1, min(50, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function matchChurnRisk(array $config, int $limit): array
    {
        $levels = $config['risk_levels'] ?? ['high', 'critical'];
        if (!is_array($levels) || $levels === []) {
            $levels = ['high', 'critical'];
        }
        $placeholders = [];
        $params = [
            'cid' => CrmSupport::requireCompanyId(),
            'h' => (int) ($config['max_health_score'] ?? 40),
        ];
        foreach (array_values($levels) as $i => $level) {
            $k = 'rl' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = (string) $level;
        }
        $rows = (new Customer())->query(
            'SELECT id, name, crm_health_score, crm_renewal_risk, crm_owner_user_id
             FROM rateb_customers
             WHERE company_id = :cid
               AND (crm_renewal_risk IN (' . implode(',', $placeholders) . ')
                    OR COALESCE(crm_health_score,100) <= :h)
             ORDER BY COALESCE(crm_health_score,0) ASC
             LIMIT ' . max(1, min(50, $limit)),
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function matchFollowUpPriority(array $config, int $limit): array
    {
        $hours = max(1, (int) ($config['overdue_hours'] ?? 24));
        $highValue = (float) ($config['high_value_amount'] ?? 50000);
        $tasks = (new CrmTask())->query(
            "SELECT t.id, t.subject, t.due_at, t.owner_user_id, t.opportunity_id, t.status,
                    o.name AS opportunity_name, o.amount
             FROM rateb_crm_tasks t
             LEFT JOIN rateb_crm_opportunities o ON o.id = t.opportunity_id
             WHERE t.company_id = :cid AND t.deleted_at IS NULL AND t.status = 'open'
               AND (
                    (t.due_at IS NOT NULL AND t.due_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR))
                    OR COALESCE(o.amount,0) >= :amt
               )
             ORDER BY t.due_at ASC, o.amount DESC
             LIMIT " . max(1, min(50, $limit)),
            ['cid' => CrmSupport::requireCompanyId(), 'amt' => $highValue]
        );

        return is_array($tasks) ? $tasks : [];
    }
}
