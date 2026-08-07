<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;

/** Phase 6 — Executive / Manager / Rep dashboards. */
final class CrmAdvancedDashboardService
{
    /**
     * @return array{role:string,kpis:array<string,mixed>,extra:array<string,mixed>}
     */
    public function forRole(string $role, ?int $userId = null, ?int $teamId = null, ?int $pipelineId = null): array
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['executive', 'manager', 'rep'], true)) {
            $role = 'rep';
        }
        $base = $this->coreKpis($pipelineId, $userId, $teamId);
        $extra = [];
        if ($role === 'executive') {
            $extra = [
                'forecast_confidence' => $this->forecastConfidence(),
                'team_performance' => (new CrmAnalyticsService())->repPerformance(),
                'pipeline_health' => (new CrmPipelineHealthService())->healthScore($pipelineId),
                'retention' => (new CrmRetentionService())->summary(),
            ];
        } elseif ($role === 'manager') {
            $extra = [
                'team_performance' => (new CrmAnalyticsService())->repPerformance(),
                'bottlenecks' => (new CrmPipelineHealthService())->bottleneckAnalysis($pipelineId),
                'stale' => (new CrmOpportunityIntelligenceService())->staleOpportunities(20),
                'activity' => (new CrmActivityIntelligenceService())->analyze(null),
            ];
        } else {
            $uid = $userId ?? CrmSupport::userId();
            $extra = [
                'workspace' => (new CrmSalesWorkspaceService())->assemble([
                    'user_id' => $uid,
                    'team_id' => $teamId,
                ]),
                'my_stale' => array_values(array_filter(
                    (new CrmOpportunityIntelligenceService())->staleOpportunities(20),
                    static fn ($r) => $uid === null || (int) ($r['owner_user_id'] ?? 0) === (int) $uid
                )),
                'activity' => (new CrmActivityIntelligenceService())->analyze($uid),
            ];
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.dashboard.access', 'crm_dashboard', null, [
                'role' => $role,
                'user_id' => $userId,
                'team_id' => $teamId,
            ]);
        }

        return ['role' => $role, 'kpis' => $base, 'extra' => $extra];
    }

    /**
     * @return array{
     *   pipeline_value:float,
     *   weighted_pipeline:float,
     *   win_rate:float,
     *   sales_velocity:float,
     *   forecast_confidence:float,
     *   open_count:int,
     *   won_count:int
     * }
     */
    public function coreKpis(?int $pipelineId = null, ?int $userId = null, ?int $teamId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $filters = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $filters .= ' AND pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        if ($userId !== null && $userId > 0) {
            $filters .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($teamId !== null && $teamId > 0) {
            $filters .= ' AND team_id = :tid';
            $params['tid'] = $teamId;
        }
        $open = (new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS v,
                    COALESCE(SUM(amount * probability_percent / 100),0) AS w
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$filters}",
            $params
        );
        $won = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'
               AND updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) {$filters}",
            $params
        )['c'] ?? 0));
        $lost = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'lost'
               AND updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) {$filters}",
            $params
        )['c'] ?? 0));
        $closed = $won + $lost;
        $winRate = $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0;
        $velocity = (new CrmAnalyticsService())->pipelineVelocity($pipelineId);
        $confidence = $this->forecastConfidence();

        return [
            'pipeline_value' => (float) ($open['v'] ?? 0),
            'weighted_pipeline' => (float) ($open['w'] ?? 0),
            'win_rate' => $winRate,
            'sales_velocity' => (float) ($velocity['velocity_score'] ?? 0),
            'forecast_confidence' => $confidence,
            'open_count' => (int) ($open['c'] ?? 0),
            'won_count' => $won,
        ];
    }

    private function forecastConfidence(): float
    {
        $accuracy = (new CrmForecastEngineService())->accuracyReport(6);
        if ($accuracy === []) {
            return 50.0;
        }
        $sum = 0.0;
        foreach ($accuracy as $row) {
            $sum += (float) ($row['accuracy_pct'] ?? 0);
        }

        return round(min(100, max(0, $sum / count($accuracy))), 1);
    }
}
