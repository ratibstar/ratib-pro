<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\Customer;

/**
 * Phase 8 — Revenue Operations automation (NotificationService only; no new email provider).
 */
final class CrmRevOpsAutomationService
{
    private NotificationService $notifier;

    public function __construct(?NotificationService $notifier = null)
    {
        $this->notifier = $notifier ?? new NotificationService();
    }

    /**
     * @return array<string, int>
     */
    public function runAll(): array
    {
        $out = [
            'escalations' => $this->processEscalations(),
            'sla_breaches' => $this->processSlaBreaches(),
            'pipeline_risks' => $this->processPipelineRisks(),
            'forecast_alerts' => $this->processForecastAlerts(),
            'customer_risk_alerts' => $this->processCustomerRiskAlerts(),
            'legacy' => 0,
        ];
        try {
            $legacy = (new CrmAutomationService())->runAll();
            $out['legacy'] = is_array($legacy) ? 1 : 0;
        } catch (\Throwable $e) {
            $out['legacy'] = 0;
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.automation.run_all', 'crm_revops_automation', null, $out);
        }

        return $out;
    }

    public function processEscalations(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT id, name, owner_user_id, amount, stage_entered_at
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND workflow_status NOT IN ('won','lost')
               AND stage_entered_at IS NOT NULL
               AND TIMESTAMPDIFF(DAY, stage_entered_at, NOW()) >= 14
             ORDER BY amount DESC LIMIT 20",
            ['cid' => $companyId]
        );
        $n = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $uid = (int) ($row['owner_user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $this->notifier->notifyUser(
                $uid,
                $companyId,
                'CRM escalation',
                'Opportunity stale 14+ days: ' . (string) ($row['name'] ?? $row['id']),
                'warning',
                'crm.escalation',
                'crm_opportunity',
                (int) $row['id']
            );
            ++$n;
        }

        return $n;
    }

    public function processSlaBreaches(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $breaches = [];
        try {
            $breaches = (new CrmWorkflowGovernanceService())->slaBreaches(25);
        } catch (\Throwable $e) {
            $breaches = [];
        }
        $n = 0;
        foreach ($breaches as $row) {
            $uid = (int) ($row['owner_user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $this->notifier->notifyUser(
                $uid,
                $companyId,
                'CRM SLA breach',
                'Stage SLA exceeded: ' . (string) ($row['name'] ?? $row['id']),
                'warning',
                'crm.sla_breach',
                'crm_opportunity',
                (int) $row['id']
            );
            ++$n;
        }

        return $n;
    }

    public function processPipelineRisks(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $stale = [];
        try {
            $stale = (new CrmOpportunityIntelligenceService())->staleOpportunities(15);
        } catch (\Throwable $e) {
            $stale = [];
        }
        $n = 0;
        foreach ($stale as $row) {
            $uid = (int) ($row['owner_user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $this->notifier->notifyUser(
                $uid,
                $companyId,
                'CRM pipeline risk',
                'At-risk opportunity: ' . (string) ($row['name'] ?? $row['id']),
                'warning',
                'crm.pipeline_risk',
                'crm_opportunity',
                (int) $row['id']
            );
            ++$n;
        }

        return $n;
    }

    public function processForecastAlerts(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $settings = (new CrmGovernanceService())->setting('revops_alerts', ['forecast_confidence_min' => 40]);
        $min = (float) ($settings['forecast_confidence_min'] ?? 40);
        try {
            $forecast = (new CrmEnterpriseForecastService())->compute('month');
        } catch (\Throwable $e) {
            return 0;
        }
        $confidence = (float) ($forecast['confidence_score'] ?? 100);
        if ($confidence >= $min) {
            return 0;
        }
        $uid = CrmSupport::userId();
        if ($uid === null || $uid <= 0) {
            $this->notifier->notifyCompany(
                $companyId,
                'CRM forecast alert',
                'Forecast confidence low: ' . $confidence . '% (min ' . $min . '%)',
                'warning',
                'crm.forecast_alert',
                'crm_forecast',
                null
            );

            return 1;
        }
        $this->notifier->notifyUser(
            $uid,
            $companyId,
            'CRM forecast alert',
            'Forecast confidence low: ' . $confidence . '% (min ' . $min . '%)',
            'warning',
            'crm.forecast_alert',
            'crm_forecast',
            null
        );

        return 1;
    }

    public function processCustomerRiskAlerts(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $settings = (new CrmGovernanceService())->setting('revops_alerts', [
            'customer_risk_levels' => ['high', 'critical'],
        ]);
        $levels = $settings['customer_risk_levels'] ?? ['high', 'critical'];
        if (!is_array($levels)) {
            $levels = ['high', 'critical'];
        }
        $placeholders = [];
        $params = ['cid' => $companyId];
        foreach (array_values($levels) as $i => $level) {
            $k = 'r' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = (string) $level;
        }
        if ($placeholders === []) {
            return 0;
        }
        $sql = 'SELECT id, name, crm_owner_user_id, crm_renewal_risk, crm_health_score
                FROM rateb_customers
                WHERE company_id = :cid AND crm_renewal_risk IN (' . implode(',', $placeholders) . ')
                ORDER BY crm_health_score ASC LIMIT 20';
        $rows = (new Customer())->query($sql, $params);
        $n = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $uid = (int) ($row['crm_owner_user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $this->notifier->notifyUser(
                $uid,
                $companyId,
                'CRM customer risk',
                'Customer risk ' . (string) ($row['crm_renewal_risk'] ?? '') . ': ' . (string) ($row['name'] ?? $row['id']),
                'warning',
                'crm.customer_risk_alert',
                'customer',
                (int) $row['id']
            );
            ++$n;
        }

        return $n;
    }
}
