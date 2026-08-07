<?php

declare(strict_types=1);

namespace Rateb\App\Services;

/** Phase 8 — Unified RevOps Command Center (aggregates existing CRM services). */
final class CrmRevOpsCommandCenterService
{
    /**
     * @return array<string, mixed>
     */
    public function assemble(
        string $role = 'executive',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $teamId = null,
        ?int $pipelineId = null
    ): array {
        $role = strtolower(trim($role));
        if (!in_array($role, ['executive', 'manager', 'rep'], true)) {
            $role = 'executive';
        }
        $userId = $role === 'rep' ? CrmSupport::userId() : null;

        $revenue = [];
        $forecast = [];
        $performance = [];
        $health = [];
        $quality = [];
        $automation = [];
        $dashboard = [];

        try {
            $revenue = (new CrmRevenueIntelligenceService())->dashboard($pipelineId, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            $revenue = ['error' => $e->getMessage()];
        }
        try {
            $forecast = (new CrmEnterpriseForecastService())->compute('month', $pipelineId, $teamId, $userId, null);
        } catch (\Throwable $e) {
            $forecast = ['error' => $e->getMessage()];
        }
        try {
            $performance = (new CrmSalesPerformanceService())->dashboard($dateFrom, $dateTo);
        } catch (\Throwable $e) {
            $performance = ['error' => $e->getMessage()];
        }
        try {
            $retention = (new CrmRetentionService())->summary();
            $atRisk = (new CrmRetentionService())->atRiskCustomers(10);
            $health = [
                'retention' => $retention,
                'at_risk' => $atRisk,
                'pipeline_health' => (new CrmPipelineHealthService())->healthScore($pipelineId),
            ];
        } catch (\Throwable $e) {
            $health = ['error' => $e->getMessage()];
        }
        try {
            $quality = (new CrmDataQualityEngineService())->dashboard();
        } catch (\Throwable $e) {
            try {
                $quality = (new CrmGovernanceService())->healthDashboard();
            } catch (\Throwable $e2) {
                $quality = ['error' => $e2->getMessage()];
            }
        }
        try {
            $automation = [
                'governance' => (new CrmGovernanceService())->automationGovernanceCheck(),
                'history' => (new CrmAutomationRulesEngineService())->executionHistory(15),
            ];
        } catch (\Throwable $e) {
            $automation = ['error' => $e->getMessage()];
        }
        try {
            $dashboard = (new CrmAdvancedDashboardService())->forRole($role, $userId, $teamId, $pipelineId);
        } catch (\Throwable $e) {
            $dashboard = ['error' => $e->getMessage()];
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.dashboard.access', 'crm_revops_command_center', null, [
                'role' => $role,
                'team_id' => $teamId,
                'pipeline_id' => $pipelineId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
        }

        return [
            'role' => $role,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'team_id' => $teamId,
                'pipeline_id' => $pipelineId,
            ],
            'revenue_pipeline' => $revenue,
            'forecast' => $forecast,
            'sales_performance' => $performance,
            'customer_health' => $health,
            'data_quality' => $quality,
            'automation' => $automation,
            'role_dashboard' => $dashboard,
        ];
    }
}
