<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmIntelligenceInsight;

/** Phase 9 — Executive insights center (cards, trends, risks, growth). */
final class CrmExecutiveInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function assemble(bool $persist = true): array
    {
        $layer = (new CrmIntelligenceLayerService())->analyze();
        $predictive = (new CrmPredictiveRulesEngineService())->evaluate(12);
        $cockpit = (new CrmExecutiveCockpitService())->assemble();
        $quality = [];
        try {
            $quality = (new CrmDataQualityEngineService())->dashboard(false);
        } catch (\Throwable $e) {
            $quality = [];
        }

        $cards = [];
        $cards[] = [
            'type' => 'growth',
            'severity' => ($layer['sales_trends']['direction'] ?? 'stable') === 'declining' ? 'warning' : 'info',
            'title' => 'Sales trend: ' . (string) ($layer['sales_trends']['direction'] ?? 'stable'),
            'body' => 'Historical CRM revenue trend direction (internal rules).',
            'score' => null,
        ];
        $cards[] = [
            'type' => 'forecast',
            'severity' => ((float) ($cockpit['forecast_confidence'] ?? 0) < 40) ? 'warning' : 'info',
            'title' => 'Forecast confidence ' . number_format((float) ($cockpit['forecast_confidence'] ?? 0), 1) . '%',
            'body' => 'Pipeline value ' . number_format((float) ($cockpit['pipeline_value'] ?? 0), 2),
            'score' => (float) ($cockpit['forecast_confidence'] ?? 0),
        ];
        $riskCount = count($layer['customer_risk_signals'] ?? []);
        $cards[] = [
            'type' => 'risk',
            'severity' => $riskCount > 0 ? 'high' : 'info',
            'title' => $riskCount . ' customer risk signals',
            'body' => 'Churn / health / engagement heuristics.',
            'score' => (float) $riskCount,
        ];
        $anomalyCount = count($layer['pipeline_anomalies'] ?? []);
        $cards[] = [
            'type' => 'anomaly',
            'severity' => $anomalyCount > 0 ? 'warning' : 'info',
            'title' => $anomalyCount . ' pipeline anomalies',
            'body' => 'Outliers, stale high-probability, score drops.',
            'score' => (float) $anomalyCount,
        ];
        $highProb = (int) ($predictive['counts']['high_probability'] ?? 0);
        $cards[] = [
            'type' => 'growth_opportunity',
            'severity' => 'info',
            'title' => $highProb . ' high-probability opportunities',
            'body' => 'Predictive rule: high_probability.',
            'score' => (float) $highProb,
        ];
        $cards[] = [
            'type' => 'quality',
            'severity' => ((float) ($quality['quality_score'] ?? 100) < 70) ? 'warning' : 'info',
            'title' => 'Data quality ' . (string) ($quality['quality_score'] ?? '—'),
            'body' => 'Completeness ' . (string) ($quality['completeness_score'] ?? '—'),
            'score' => isset($quality['quality_score']) ? (float) $quality['quality_score'] : null,
        ];

        if ($persist) {
            $this->persistCards($cards);
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.dashboard.access', 'crm_executive_insights', null, [
                'cards' => count($cards),
            ]);
        }

        return [
            'cards' => $cards,
            'trend_indicators' => [
                'sales_direction' => $layer['sales_trends']['direction'] ?? 'stable',
                'win_rate' => $cockpit['win_rate'] ?? 0,
                'sales_velocity' => $cockpit['sales_velocity'] ?? 0,
            ],
            'risk_alerts' => array_slice($layer['customer_risk_signals'] ?? [], 0, 10),
            'growth_opportunities' => $predictive['matches']['high_probability'] ?? [],
            'pipeline_anomalies' => array_slice($layer['pipeline_anomalies'] ?? [], 0, 10),
            'scoring_evolution' => array_slice($layer['scoring_evolution'] ?? [], 0, 15),
            'predictive_counts' => $predictive['counts'] ?? [],
            'stored' => $this->listOpenInsights(30),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listOpenInsights(int $limit = 40): array
    {
        $rows = (new CrmIntelligenceInsight())->query(
            "SELECT * FROM rateb_crm_intelligence_insights
             WHERE company_id = :cid AND status = 'open'
             ORDER BY FIELD(severity,'high','warning','info'), id DESC
             LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    public function dismiss(int $insightId): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmIntelligenceInsight())->queryOne(
            'SELECT id FROM rateb_crm_intelligence_insights WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $insightId, 'cid' => $companyId]
        );
        if ($row === null) {
            throw new \RuntimeException('insight_not_found');
        }
        (new CrmIntelligenceInsight())->update($insightId, [
            'status' => 'dismissed',
            'dismissed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $cards
     */
    private function persistCards(array $cards): void
    {
        $companyId = CrmSupport::requireCompanyId();
        foreach ($cards as $card) {
            $title = substr((string) ($card['title'] ?? 'Insight'), 0, 190);
            $existing = (new CrmIntelligenceInsight())->queryOne(
                "SELECT id FROM rateb_crm_intelligence_insights
                 WHERE company_id = :cid AND status = 'open' AND title = :t
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1",
                ['cid' => $companyId, 't' => $title]
            );
            if ($existing) {
                continue;
            }
            (new CrmIntelligenceInsight())->create([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'insight_type' => substr((string) ($card['type'] ?? 'info'), 0, 40),
                'severity' => substr((string) ($card['severity'] ?? 'info'), 0, 20),
                'title' => $title,
                'body' => isset($card['body']) ? substr((string) $card['body'], 0, 500) : null,
                'score' => $card['score'] ?? null,
                'status' => 'open',
                'meta_json' => json_encode($card, JSON_UNESCAPED_UNICODE),
                'created_by' => CrmSupport::userId(),
            ]);
        }
    }
}
