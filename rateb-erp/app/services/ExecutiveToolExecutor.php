<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Executive Tool Executor — tenant-scoped executive intelligence over live domain evidence.
 * No WRITE. No ExecutiveAgent.
 */
final class ExecutiveToolExecutor
{
    /**
     * @param array<string, mixed> $arguments
     * @return array{success: bool, data: mixed, error: string|null}
     */
    public static function execute(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        if ((int) $ctx->companyId < 1) {
            return self::fail('tenant_mismatch');
        }
        try {
            $limit = max(1, min(30, (int) ($arguments['limit'] ?? 15)));
            switch ($toolName) {
                case 'analyze_executive_intelligence':
                    $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
                    return ['success' => true, 'data' => $pack, 'error' => null];
                case 'get_executive_kpis':
                    $domains = ErpExecutiveIntelligenceLayer::entitledDomains($ctx);
                    $kpis = ErpExecutiveIntelligenceLayer::collectKpis($ctx, $domains, $limit);
                    return ['success' => true, 'data' => $kpis, 'error' => null];
                case 'get_executive_summary':
                    $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'executive_summary' => $pack['executive_summary'] ?? [],
                            'text' => ErpExecutiveIntelligenceLayer::formatSummary($pack, $ctx),
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_executive_forecast':
                    $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
                    return ['success' => true, 'data' => $pack['forecasts'] ?? [], 'error' => null];
                case 'get_executive_priorities':
                    $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
                    $recs = is_array($pack['recommended_actions'] ?? null) ? $pack['recommended_actions'] : [];
                    $recs = ErpOperationalLearningLayer::optimizeRecommendationOrder((int) $ctx->companyId, $recs);
                    $pri = is_array($pack['priorities'] ?? null) ? $pack['priorities'] : [];
                    $pri = ErpOperationalLearningLayer::optimizeRecommendationOrder((int) $ctx->companyId, $pri);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'priorities' => $pri,
                            'risks' => $pack['risks'] ?? [],
                            'recommended_actions' => $recs,
                            'optimized' => true,
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_executive_action_bridge':
                    $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
                    if (!empty($pack['priorities']) && is_array($pack['priorities'])) {
                        $pack['priorities'] = ErpOperationalLearningLayer::optimizeRecommendationOrder(
                            (int) $ctx->companyId,
                            $pack['priorities']
                        );
                    }
                    $bridge = ErpExecutiveIntelligenceLayer::actionBridge($pack, $ctx);
                    return ['success' => true, 'data' => $bridge, 'error' => null];
                case 'scan_early_warnings':
                    $scan = ErpProactiveEarlyWarningLayer::scan($ctx, [
                        'trigger' => 'on_demand',
                        'persist' => true,
                        'notify' => !empty($arguments['notify']),
                        'limit' => $limit,
                    ]);
                    if (!empty($scan['recommendations']) && is_array($scan['recommendations'])) {
                        $scan['recommendations'] = ErpOperationalLearningLayer::optimizeRecommendationOrder(
                            (int) $ctx->companyId,
                            $scan['recommendations']
                        );
                    }
                    return ['success' => true, 'data' => $scan, 'error' => null];
                case 'get_early_warnings':
                    $includeClosed = !empty($arguments['include_closed']);
                    $list = ErpProactiveEarlyWarningLayer::listWarnings((int) $ctx->companyId, $includeClosed);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'company_id' => (int) $ctx->companyId,
                            'items' => array_slice($list, 0, max(1, min(50, (int) ($arguments['limit'] ?? 20)))),
                            'count' => count($list),
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_early_warning_digest':
                    $scan = ErpProactiveEarlyWarningLayer::scan($ctx, [
                        'trigger' => 'digest',
                        'persist' => true,
                        'notify' => false,
                        'limit' => $limit,
                    ]);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'text' => ErpProactiveEarlyWarningLayer::formatDigest($scan, $ctx),
                            'warnings' => $scan['warnings'] ?? [],
                            'opportunities' => $scan['opportunities'] ?? [],
                            'recommendations' => $scan['recommendations'] ?? [],
                            'observability' => $scan['observability'] ?? [],
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'update_early_warning_status':
                    $upd = ErpProactiveEarlyWarningLayer::updateStatus(
                        (int) $ctx->companyId,
                        (string) ($arguments['warning_id'] ?? ''),
                        (string) ($arguments['status'] ?? '')
                    );
                    return [
                        'success' => !empty($upd['success']),
                        'data' => $upd,
                        'error' => empty($upd['success']) ? (string) ($upd['error'] ?? 'update_failed') : null,
                    ];
                case 'revalidate_early_warning':
                    $re = ErpProactiveEarlyWarningLayer::revalidateWarning($ctx, (string) ($arguments['warning_id'] ?? ''));
                    return ['success' => true, 'data' => $re, 'error' => null];
                case 'get_early_warning_action_bridge':
                    $bridge = ErpProactiveEarlyWarningLayer::actionBridge($ctx, (string) ($arguments['warning_id'] ?? ''));
                    return [
                        'success' => !empty($bridge['success']),
                        'data' => $bridge,
                        'error' => empty($bridge['success']) ? (string) ($bridge['reason'] ?? 'bridge_failed') : null,
                    ];
                case 'analyze_operational_learning':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return ['success' => true, 'data' => $pack, 'error' => null];
                case 'get_action_outcomes':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'items' => $pack['outcomes'] ?? [],
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_learning_signals':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'items' => $pack['signals'] ?? [],
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_recommendation_effectiveness':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'items' => $pack['recommendation_effectiveness'] ?? [],
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_warning_effectiveness':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'items' => $pack['warning_effectiveness'] ?? [],
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_forecast_feedback':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'items' => $pack['forecast_feedback'] ?? [],
                            'model_mutated' => false,
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'get_optimization_insights':
                    $pack = ErpOperationalLearningLayer::analyze($ctx, $limit);
                    return [
                        'success' => true,
                        'data' => [
                            'data_source' => 'live_tenant',
                            'optimization' => $pack['optimization'] ?? [],
                            'patterns' => $pack['patterns'] ?? [],
                            'root_cause_support' => $pack['root_cause_support'] ?? [],
                            'learning_audit' => $pack['learning_audit'] ?? [],
                            'no_self_modification' => ErpOperationalLearningLayer::assertNoSelfModification(),
                            'auto_execute' => false,
                        ],
                        'error' => null,
                    ];
                case 'record_recommendation_feedback':
                    $fb = ErpOperationalLearningLayer::recordUserFeedback(
                        $ctx,
                        (string) ($arguments['target_type'] ?? 'recommendation'),
                        (string) ($arguments['target_id'] ?? ''),
                        (string) ($arguments['feedback'] ?? '')
                    );
                    return [
                        'success' => !empty($fb['success']),
                        'data' => $fb,
                        'error' => empty($fb['success']) ? (string) ($fb['error'] ?? 'feedback_failed') : null,
                    ];
                case 'measure_action_outcome':
                    $m = ErpOperationalLearningLayer::measureBusinessOutcome(
                        $ctx,
                        (string) ($arguments['outcome_id'] ?? ''),
                        [
                            'warning_id' => $arguments['warning_id'] ?? null,
                            'kpi_before' => $arguments['kpi_before'] ?? null,
                            'kpi_after' => $arguments['kpi_after'] ?? null,
                            'improve_direction' => $arguments['improve_direction'] ?? 'up',
                        ]
                    );
                    return [
                        'success' => !empty($m['success']),
                        'data' => $m,
                        'error' => empty($m['success']) ? (string) ($m['error'] ?? 'measure_failed') : null,
                    ];
                case 'record_forecast_feedback':
                    $fc = ErpOperationalLearningLayer::recordForecastFeedback(
                        $ctx,
                        is_array($arguments['forecast'] ?? null) ? $arguments['forecast'] : [],
                        (float) ($arguments['actual'] ?? 0),
                        isset($arguments['period']) ? (string) $arguments['period'] : null
                    );
                    return [
                        'success' => !empty($fc['success']),
                        'data' => $fc,
                        'error' => empty($fc['success']) ? (string) ($fc['error'] ?? 'forecast_feedback_failed') : null,
                    ];
                case 'get_control_tower_snapshot':
                    $snap = ErpControlTowerLayer::snapshot($ctx, max(5, min(30, (int) ($arguments['limit'] ?? 12))));
                    return ['success' => true, 'data' => $snap, 'error' => null];
                case 'get_relevant_operational_context':
                    $mem = ErpOperationalMemoryLayer::buildRelevantContext(
                        $ctx,
                        ['intent_kind' => 'executive', 'memory' => true],
                        [],
                        max(5, min(20, (int) ($arguments['limit'] ?? 12)))
                    );
                    return ['success' => true, 'data' => $mem, 'error' => null];
                case 'get_operational_memory':
                    $towerMem = ErpOperationalMemoryLayer::towerSection(
                        $ctx,
                        max(5, min(20, (int) ($arguments['limit'] ?? 10)))
                    );
                    return ['success' => true, 'data' => $towerMem, 'error' => null];
                default:
                    return self::fail('tool_not_implemented');
            }
        } catch (\Throwable $e) {
            return self::fail('tool_exception');
        }
    }

    /**
     * @return array{success: false, data: null, error: string, error_code: string, error_message: string}
     */
    private static function fail(string $code): array
    {
        $key = 'ai_tool_err_' . $code;
        $translated = __($key);
        $message = (is_string($translated) && $translated !== '' && $translated !== $key) ? $translated : $code;
        return [
            'success' => false,
            'data' => null,
            'error' => $code,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
