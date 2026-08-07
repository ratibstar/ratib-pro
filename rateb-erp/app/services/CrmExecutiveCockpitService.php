<?php

declare(strict_types=1);

namespace Rateb\App\Services;

/** Phase 8 — Executive CRM Cockpit KPIs. */
final class CrmExecutiveCockpitService
{
    /**
     * @return array<string, mixed>
     */
    public function assemble(?int $teamId = null, ?int $pipelineId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $kpis = (new CrmAdvancedDashboardService())->coreKpis($pipelineId, null, $teamId);
        $forecast = [];
        $winLoss = [];
        $cycle = [];
        $trends = [];
        $risk = [];

        try {
            $forecast = (new CrmEnterpriseForecastService())->compute('month', $pipelineId, $teamId, null, null);
        } catch (\Throwable $e) {
            $forecast = [];
        }
        try {
            $winLoss = (new CrmRevenueIntelligenceService())->winLossIntelligence($pipelineId);
        } catch (\Throwable $e) {
            $winLoss = [];
        }
        try {
            $cycle = (new CrmRevenueIntelligenceService())->salesCycleAnalytics();
        } catch (\Throwable $e) {
            $cycle = [];
        }
        try {
            $trends = (new CrmRevenueIntelligenceService())->historicalTrendAnalysis($dateFrom, $dateTo);
        } catch (\Throwable $e) {
            $trends = [];
        }
        try {
            $risk = [
                'at_risk' => (new CrmRetentionService())->atRiskCustomers(15),
                'summary' => (new CrmRetentionService())->summary(),
            ];
        } catch (\Throwable $e) {
            $risk = [];
        }

        $confidence = (float) ($forecast['confidence_score'] ?? $kpis['forecast_confidence'] ?? 0);
        $winRate = (float) ($kpis['win_rate'] ?? ($winLoss['win_rate'] ?? 0));
        $velocity = (float) ($kpis['sales_velocity'] ?? ($cycle['avg_days'] ?? 0));

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.dashboard.access', 'crm_executive_cockpit', null, [
                'team_id' => $teamId,
                'pipeline_id' => $pipelineId,
            ]);
        }

        return [
            'pipeline_value' => (float) ($kpis['pipeline_value'] ?? 0),
            'weighted_pipeline' => (float) ($kpis['weighted_pipeline'] ?? 0),
            'forecast_confidence' => $confidence,
            'forecast' => $forecast,
            'win_rate' => $winRate,
            'sales_velocity' => $velocity,
            'customer_risk' => $risk,
            'growth_trends' => $trends,
            'win_loss' => $winLoss,
            'sales_cycle' => $cycle,
            'open_count' => (int) ($kpis['open_count'] ?? 0),
            'won_count' => (int) ($kpis['won_count'] ?? 0),
        ];
    }
}
