<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\Customer;

/**
 * Phase 8/10 — Revenue Operations automation with cooldown + run lock (NotificationService only).
 */
final class CrmRevOpsAutomationService
{
    private NotificationService $notifier;
    private CrmAutomationSafetyService $safety;
    private int $notifyBudget = 100;

    public function __construct(?NotificationService $notifier = null, ?CrmAutomationSafetyService $safety = null)
    {
        $this->notifier = $notifier ?? new NotificationService();
        $this->safety = $safety ?? new CrmAutomationSafetyService();
        $this->notifyBudget = $this->safety->maxNotifiesPerRun();
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function runAll(bool $includeLegacy = false): array
    {
        $timed = CrmObservability::timed('crm.revops.automation.run_all', function () use ($includeLegacy) {
            if (!$this->safety->acquireRunLock('revops_run_all')) {
                return [
                    'skipped' => 'run_lock',
                    'escalations' => 0,
                    'sla_breaches' => 0,
                    'pipeline_risks' => 0,
                    'forecast_alerts' => 0,
                    'customer_risk_alerts' => 0,
                    'legacy' => 0,
                ];
            }
            $settings = $this->safety->settings();
            $includeLegacy = $includeLegacy || !empty($settings['include_legacy_in_revops']);
            $this->notifyBudget = $this->safety->maxNotifiesPerRun();

            $out = [
                'escalations' => $this->processEscalations(),
                'sla_breaches' => $this->processSlaBreaches(),
                'pipeline_risks' => $this->processPipelineRisks(),
                'forecast_alerts' => $this->processForecastAlerts(),
                'customer_risk_alerts' => $this->processCustomerRiskAlerts(),
                'legacy' => 0,
                'notify_budget_remaining' => $this->notifyBudget,
            ];
            if ($includeLegacy) {
                try {
                    $legacy = (new CrmAutomationService())->runAll();
                    $out['legacy'] = is_array($legacy) ? 1 : 0;
                    $out['legacy_detail'] = isset($legacy['skipped']) ? (string) $legacy['skipped'] : 'ran';
                } catch (\Throwable $e) {
                    CrmObservability::logFailure('crm.revops.legacy_automation', $e);
                    $out['legacy'] = 0;
                }
            }
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.automation.run_all', 'crm_revops_automation', null, $out);
            }

            return $out;
        });

        return is_array($timed['result'] ?? null) ? $timed['result'] : [];
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
            $oid = (int) ($row['id'] ?? 0);
            if ($uid <= 0 || !$this->allow('revops_escalation', 'opportunity', $oid)) {
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
                $oid
            );
            $this->safety->record('revops_escalation', 'opportunity', $oid, ['days' => 14], $uid);
            ++$n;
        }

        return $n;
    }

    public function processSlaBreaches(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        try {
            $breaches = (new CrmWorkflowGovernanceService())->slaBreaches(25);
        } catch (\Throwable $e) {
            $breaches = [];
        }
        $n = 0;
        foreach ($breaches as $row) {
            $uid = (int) ($row['owner_user_id'] ?? 0);
            $oid = (int) ($row['id'] ?? 0);
            if ($uid <= 0 || !$this->allow('revops_sla_breach', 'opportunity', $oid)) {
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
                $oid
            );
            $this->safety->record('revops_sla_breach', 'opportunity', $oid, [], $uid);
            ++$n;
        }

        return $n;
    }

    public function processPipelineRisks(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        try {
            $stale = (new CrmOpportunityIntelligenceService())->staleOpportunities(15);
        } catch (\Throwable $e) {
            $stale = [];
        }
        $n = 0;
        foreach ($stale as $row) {
            $uid = (int) ($row['owner_user_id'] ?? 0);
            $oid = (int) ($row['id'] ?? 0);
            // Skip if already escalated/stale-notified recently (cross-event storm protection).
            if ($uid <= 0
                || !$this->allow('revops_pipeline_risk', 'opportunity', $oid)
                || $this->safety->recentlyFired('revops_escalation', 'opportunity', $oid)
                || $this->safety->recentlyFired('stale_opportunity', 'opportunity', $oid)
            ) {
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
                $oid
            );
            $this->safety->record('revops_pipeline_risk', 'opportunity', $oid, [], $uid);
            ++$n;
        }

        return $n;
    }

    public function processForecastAlerts(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        if (!$this->allow('revops_forecast_alert', 'crm_forecast', 1)) {
            return 0;
        }
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
        $msg = 'Forecast confidence low: ' . $confidence . '% (min ' . $min . '%)';
        $uid = CrmSupport::userId();
        if ($uid === null || $uid <= 0) {
            $this->notifier->notifyCompany($companyId, 'CRM forecast alert', $msg, 'warning', 'crm.forecast_alert', 'crm_forecast', null);
        } else {
            $this->notifier->notifyUser($uid, $companyId, 'CRM forecast alert', $msg, 'warning', 'crm.forecast_alert', 'crm_forecast', null);
        }
        $this->safety->record('revops_forecast_alert', 'crm_forecast', 1, ['confidence' => $confidence]);

        return 1;
    }

    public function processCustomerRiskAlerts(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $settings = (new CrmGovernanceService())->setting('revops_alerts', [
            'customer_risk_levels' => ['high', 'critical'],
        ]);
        $levels = $settings['customer_risk_levels'] ?? ['high', 'critical'];
        if (!is_array($levels) || $levels === []) {
            $levels = ['high', 'critical'];
        }
        $placeholders = [];
        $params = ['cid' => $companyId];
        foreach (array_values($levels) as $i => $level) {
            $k = 'r' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = (string) $level;
        }
        $sql = 'SELECT id, name, crm_owner_user_id, crm_renewal_risk, crm_health_score
                FROM rateb_customers
                WHERE company_id = :cid AND crm_renewal_risk IN (' . implode(',', $placeholders) . ')
                ORDER BY crm_health_score ASC LIMIT 20';
        $rows = (new Customer())->query($sql, $params);
        $n = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $uid = (int) ($row['crm_owner_user_id'] ?? 0);
            $cid = (int) ($row['id'] ?? 0);
            if ($uid <= 0 || !$this->allow('revops_customer_risk', 'customer', $cid)) {
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
                $cid
            );
            $this->safety->record('revops_customer_risk', 'customer', $cid, [], $uid);
            ++$n;
        }

        return $n;
    }

    private function allow(string $eventType, ?string $entityType, ?int $entityId): bool
    {
        $gate = $this->safety->allowNotify($eventType, $entityType, $entityId, $this->notifyBudget);

        return !empty($gate['allowed']);
    }
}
