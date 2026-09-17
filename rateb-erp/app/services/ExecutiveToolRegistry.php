<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Executive Tool Registry — READ/ANALYSIS for Executive Intelligence (Unified ERP Agent).
 * No WRITE. No ExecutiveAgent.
 */
final class ExecutiveToolRegistry
{
    /**
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     permission: string,
     *     module: string,
     *     write: bool,
     *     parameters: array
     * }>
     */
    public static function getTools(): array
    {
        return [
            'analyze_executive_intelligence' => [
                'name' => 'analyze_executive_intelligence',
                'description' => 'Executive intelligence pack from live domain evidence: KPIs, trends, risks, priorities, forecasts, and summary. Never invents numbers.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 15],
                    ],
                    'required' => [],
                ],
            ],
            'get_executive_kpis' => [
                'name' => 'get_executive_kpis',
                'description' => 'Aggregate available live KPIs across entitled ERP domains with evidence trails.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_executive_summary' => [
                'name' => 'get_executive_summary',
                'description' => 'Executive summary sections from live evidence only. Never auto-executes writes.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 15],
                    ],
                    'required' => [],
                ],
            ],
            'get_executive_forecast' => [
                'name' => 'get_executive_forecast',
                'description' => 'Simple historical forecasts when comparable periods exist. Returns UNAVAILABLE when insufficient data. Predictions are not facts.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_executive_priorities' => [
                'name' => 'get_executive_priorities',
                'description' => 'Evidence-based executive priorities and recommended actions. Never auto-writes.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_executive_action_bridge' => [
                'name' => 'get_executive_action_bridge',
                'description' => 'Bridge top executive insight to an ErpActionPlanner recommendation. Requires confirmation for any write.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
                    ],
                    'required' => [],
                ],
            ],
            'scan_early_warnings' => [
                'name' => 'scan_early_warnings',
                'description' => 'Proactive on-demand scan: signals → evidence → risks/opportunities → deduplicated early warnings. Never auto-executes writes.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 40, 'default' => 20],
                        'notify' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_early_warnings' => [
                'name' => 'get_early_warnings',
                'description' => 'List open tenant-scoped early warnings with evidence, severity, and recommended actions.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'include_closed' => ['type' => 'boolean', 'default' => false],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_early_warning_digest' => [
                'name' => 'get_early_warning_digest',
                'description' => 'Proactive executive digest of critical/high warnings and opportunities. Deduplicated; never auto-writes.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 15],
                    ],
                    'required' => [],
                ],
            ],
            'update_early_warning_status' => [
                'name' => 'update_early_warning_status',
                'description' => 'Update early-warning lifecycle status (ACKNOWLEDGED/IN_PROGRESS/RESOLVED/DISMISSED). Not an ERP business write.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'warning_id' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['ACKNOWLEDGED', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED']],
                    ],
                    'required' => ['warning_id', 'status'],
                ],
            ],
            'revalidate_early_warning' => [
                'name' => 'revalidate_early_warning',
                'description' => 'Stale-warning protection: re-read live evidence and re-validate before any action. Blocks stale actions.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'warning_id' => ['type' => 'string'],
                    ],
                    'required' => ['warning_id'],
                ],
            ],
            'get_early_warning_action_bridge' => [
                'name' => 'get_early_warning_action_bridge',
                'description' => 'Bridge a revalidated early warning to ErpActionPlanner. Requires governance and confirmation for writes.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'warning_id' => ['type' => 'string'],
                    ],
                    'required' => ['warning_id'],
                ],
            ],
            'analyze_operational_learning' => [
                'name' => 'analyze_operational_learning',
                'description' => 'Operational learning insights from tenant outcomes: effectiveness, patterns, optimization weights. Never modifies governance.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_action_outcomes' => [
                'name' => 'get_action_outcomes',
                'description' => 'List recorded action outcomes (execution vs business classification).',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_learning_signals' => [
                'name' => 'get_learning_signals',
                'description' => 'List evidence-based operational learning signals for this tenant.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_recommendation_effectiveness' => [
                'name' => 'get_recommendation_effectiveness',
                'description' => 'Recommendation effectiveness metrics when sufficient evidence exists.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_warning_effectiveness' => [
                'name' => 'get_warning_effectiveness',
                'description' => 'Early-warning effectiveness: actions, resolution, repeated occurrence.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_forecast_feedback' => [
                'name' => 'get_forecast_feedback',
                'description' => 'Forecast vs actual feedback. Does not mutate forecast models.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_optimization_insights' => [
                'name' => 'get_optimization_insights',
                'description' => 'Continuous optimization insights and learning audit. Governance remains immutable.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'record_recommendation_feedback' => [
                'name' => 'record_recommendation_feedback',
                'description' => 'Record simple user feedback (useful/not_useful/action_taken/action_not_taken). Not alone a business outcome.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target_type' => ['type' => 'string', 'default' => 'recommendation'],
                        'target_id' => ['type' => 'string'],
                        'feedback' => ['type' => 'string', 'enum' => ['useful', 'not_useful', 'action_taken', 'action_not_taken']],
                    ],
                    'required' => ['target_id', 'feedback'],
                ],
            ],
            'measure_action_outcome' => [
                'name' => 'measure_action_outcome',
                'description' => 'Measure business outcome from live evidence after an action. Returns UNKNOWN when insufficient evidence.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'outcome_id' => ['type' => 'string'],
                        'warning_id' => ['type' => 'string'],
                        'kpi_before' => ['type' => 'number'],
                        'kpi_after' => ['type' => 'number'],
                        'improve_direction' => ['type' => 'string', 'enum' => ['up', 'down'], 'default' => 'up'],
                    ],
                    'required' => ['outcome_id'],
                ],
            ],
            'record_forecast_feedback' => [
                'name' => 'record_forecast_feedback',
                'description' => 'Record forecast vs actual for display optimization only. Never fine-tunes models.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'forecast' => ['type' => 'object'],
                        'actual' => ['type' => 'number'],
                        'period' => ['type' => 'string'],
                    ],
                    'required' => ['forecast', 'actual'],
                ],
            ],
            'get_control_tower_snapshot' => [
                'name' => 'get_control_tower_snapshot',
                'description' => 'Unified Control Tower snapshot: KPIs, warnings, forecasts, recommendations, outcomes, learning, agent effectiveness. Read-only.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 30, 'default' => 12],
                    ],
                    'required' => [],
                ],
            ],
            'get_relevant_operational_context' => [
                'name' => 'get_relevant_operational_context',
                'description' => 'Tenant-scoped relevant operational memory for the current intent. Never full history. Historical items are labeled and not usable as current facts. LLM cannot write memory.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 20, 'default' => 12],
                    ],
                    'required' => [],
                ],
            ],
            'get_operational_memory' => [
                'name' => 'get_operational_memory',
                'description' => 'Control Tower operational context/memory section: recent decisions, actions, outcomes, patterns, unresolved issues, learning signals. Read-only evidence.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 20, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'plan_multi_step_workflow' => [
                'name' => 'plan_multi_step_workflow',
                'description' => 'Plan a multi-step operational workflow from intent using ActionPlanner. Never auto-executes writes. Returns steps, dependencies, confirmation scope.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'persist' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['message'],
                ],
            ],
            'get_active_workflows' => [
                'name' => 'get_active_workflows',
                'description' => 'List active tenant-scoped multi-step workflows for Control Tower. Read-only.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 12],
                    ],
                    'required' => [],
                ],
            ],
            'get_workflow_status' => [
                'name' => 'get_workflow_status',
                'description' => 'Get one multi-step workflow by id (tenant-scoped). Read-only evidence.',
                'permission' => 'dashboard.view',
                'module' => 'dashboard',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'workflow_id' => ['type' => 'string'],
                    ],
                    'required' => ['workflow_id'],
                ],
            ],
        ];
    }

    public static function isAllowed(string $toolName): bool
    {
        return isset(self::getTools()[$toolName]);
    }

    public static function getTool(string $toolName): ?array
    {
        return self::getTools()[$toolName] ?? null;
    }

    public static function hasSufficientParameters(string $toolName, array $arguments): bool
    {
        $tool = self::getTool($toolName);
        if ($tool === null) {
            return false;
        }
        foreach (($tool['parameters']['required'] ?? []) as $req) {
            $req = (string) $req;
            if ($req === '') {
                continue;
            }
            if (!array_key_exists($req, $arguments) || $arguments[$req] === '' || $arguments[$req] === null) {
                return false;
            }
        }
        if ($toolName === 'update_early_warning_status') {
            $st = strtoupper(trim((string) ($arguments['status'] ?? '')));
            if (!in_array($st, ['ACKNOWLEDGED', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED'], true)) {
                return false;
            }
        }
        if ($toolName === 'record_recommendation_feedback') {
            $fb = strtolower(trim((string) ($arguments['feedback'] ?? '')));
            if (!in_array($fb, ['useful', 'not_useful', 'action_taken', 'action_not_taken'], true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return list<array{type:string,function:array{name:string,description:string,parameters:array}}>
     */
    public static function toOpenAiTools(): array
    {
        $out = [];
        foreach (self::getTools() as $name => $config) {
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $config['description'],
                    'parameters' => $config['parameters'],
                ],
            ];
        }
        return $out;
    }
}
