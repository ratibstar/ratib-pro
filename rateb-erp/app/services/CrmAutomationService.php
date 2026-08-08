<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAutomationLog;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\CrmTask;
use Rateb\App\Models\Customer;

/**
 * Phase 3 — CRM automation events via existing NotificationService.
 * No new notification subsystem.
 */
final class CrmAutomationService
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

    public function onLeadAssigned(int $leadId, int $assigneeUserId, ?string $leadTitle = null): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $title = 'CRM: Lead assigned';
        $message = 'Lead #' . $leadId . ($leadTitle ? ' — ' . $leadTitle : '') . ' was assigned to you.';
        $this->notifier->notifyUser(
            $assigneeUserId,
            $companyId,
            $title,
            $message,
            'info',
            'crm.lead_assignment',
            'crm_lead',
            $leadId
        );
        $this->log('lead_assignment', 'lead', $leadId, $assigneeUserId, [
            'title' => $leadTitle,
        ]);
        $this->audit('crm.automation.lead_assignment', 'crm_lead', $leadId, [
            'assignee_user_id' => $assigneeUserId,
        ]);
    }

    public function onOpportunityStageChanged(
        int $opportunityId,
        string $stageName,
        ?int $ownerUserId,
        ?string $workflowStatus = null
    ): void {
        if (!(new CrmAdminConfigService())->isRuleEnabled('stage_change')) {
            return;
        }
        $companyId = CrmSupport::requireCompanyId();
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $this->notifier->notifyUser(
                $ownerUserId,
                $companyId,
                'CRM: Opportunity stage changed',
                'Opportunity #' . $opportunityId . ' moved to ' . $stageName
                    . ($workflowStatus ? ' (' . $workflowStatus . ')' : ''),
                'info',
                'crm.opportunity_stage',
                'crm_opportunity',
                $opportunityId
            );
        } else {
            $this->notifier->notifyCompany(
                $companyId,
                'CRM: Opportunity stage changed',
                'Opportunity #' . $opportunityId . ' moved to ' . $stageName,
                'info',
                'crm.opportunity_stage',
                'crm_opportunity',
                $opportunityId
            );
        }
        $this->log('opportunity_stage', 'opportunity', $opportunityId, $ownerUserId, [
            'stage' => $stageName,
            'workflow_status' => $workflowStatus,
        ]);
        $this->audit('crm.automation.opportunity_stage', 'crm_opportunity', $opportunityId, [
            'stage' => $stageName,
            'workflow_status' => $workflowStatus,
        ]);
    }

    /**
     * Scan open tasks with due/reminder windows and notify owners.
     *
     * @return array{reminders: int, overdue: int}
     */
    public function processFollowUpReminders(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('follow_up_overdue')) {
            return ['reminders' => 0, 'overdue' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $now = date('Y-m-d H:i:s');
        $reminders = 0;
        $overdue = 0;

        $dueSoon = (new CrmTask())->query(
            "SELECT * FROM rateb_crm_tasks
             WHERE company_id = :cid AND deleted_at IS NULL AND status = 'open'
               AND (
                    (reminder_at IS NOT NULL AND reminder_at <= :now1)
                    OR (due_at IS NOT NULL AND due_at <= :now2)
               )
             ORDER BY COALESCE(reminder_at, due_at) ASC LIMIT 50",
            ['cid' => $companyId, 'now1' => $now, 'now2' => $now]
        );
        if (!is_array($dueSoon)) {
            $dueSoon = [];
        }

        foreach ($dueSoon as $task) {
            $owner = (int) ($task['owner_user_id'] ?? 0);
            if ($owner < 1) {
                continue;
            }
            $taskId = (int) ($task['id'] ?? 0);
            if (!$this->allowNotify('follow_up_reminder', 'task', $taskId)) {
                continue;
            }
            $isOverdue = !empty($task['due_at']) && (string) $task['due_at'] <= $now;
            $title = $isOverdue ? 'CRM: Task overdue' : 'CRM: Follow-up reminder';
            $message = (string) ($task['subject'] ?? ('Task #' . $taskId));
            $this->notifier->notifyUser(
                $owner,
                $companyId,
                $title,
                $message,
                $isOverdue ? 'warning' : 'info',
                'crm.follow_up_reminder',
                'crm_task',
                $taskId
            );
            $this->log('follow_up_reminder', 'task', $taskId, $owner, [
                'overdue' => $isOverdue,
            ]);
            if ($isOverdue) {
                ++$overdue;
            } else {
                ++$reminders;
            }
        }

        $this->audit('crm.automation.follow_up_scan', 'crm_task', null, [
            'reminders' => $reminders,
            'overdue' => $overdue,
        ]);

        return ['reminders' => $reminders, 'overdue' => $overdue];
    }

    /**
     * Notify owners of quotations approaching / past valid_until while draft|sent.
     *
     * @return array{alerts: int}
     */
    public function processQuoteExpiryAlerts(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('quote_expiry')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $cfg = (new CrmAdminConfigService())->ruleConfig('quote_expiry');
        $daysAhead = max(0, (int) ($cfg['days_ahead'] ?? 3));
        $horizon = date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
        $today = date('Y-m-d');
        $rows = (new CrmQuotation())->query(
            "SELECT * FROM rateb_crm_quotations
             WHERE company_id = :cid AND deleted_at IS NULL
               AND status IN ('draft','sent')
               AND valid_until IS NOT NULL
               AND valid_until <= :horizon
             ORDER BY valid_until ASC LIMIT 50",
            ['cid' => $companyId, 'horizon' => $horizon]
        );
        if (!is_array($rows)) {
            $rows = [];
        }
        $alerts = 0;
        foreach ($rows as $quote) {
            $owner = (int) ($quote['owner_user_id'] ?? 0);
            $qid = (int) ($quote['id'] ?? 0);
            if (!$this->allowNotify('quote_expiry', 'quotation', $qid)) {
                continue;
            }
            $valid = (string) ($quote['valid_until'] ?? '');
            $expired = $valid !== '' && $valid < $today;
            $title = $expired ? 'CRM: Quotation expired' : 'CRM: Quotation expiring soon';
            $message = (string) ($quote['quotation_no'] ?? ('#' . $qid)) . ' valid until ' . $valid;
            if ($owner > 0) {
                $this->notifier->notifyUser(
                    $owner,
                    $companyId,
                    $title,
                    $message,
                    $expired ? 'warning' : 'info',
                    'crm.quote_expiry',
                    'crm_quotation',
                    $qid
                );
            } else {
                $this->notifier->notifyCompany(
                    $companyId,
                    $title,
                    $message,
                    $expired ? 'warning' : 'info',
                    'crm.quote_expiry',
                    'crm_quotation',
                    $qid
                );
            }
            $this->log('quote_expiry', 'quotation', $qid, $owner > 0 ? $owner : null, [
                'valid_until' => $valid,
                'expired' => $expired,
            ]);
            ++$alerts;
        }
        $this->audit('crm.automation.quote_expiry_scan', 'crm_quotation', null, ['alerts' => $alerts]);

        return ['alerts' => $alerts];
    }

    /**
     * Alert owners of open opportunities with no updates for N days.
     *
     * @return array{alerts: int}
     */
    public function processOpportunityInactivity(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('opportunity_inactivity')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $cfg = (new CrmAdminConfigService())->ruleConfig('opportunity_inactivity');
        $days = max(1, (int) ($cfg['days'] ?? 14));
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $rows = (new CrmOpportunity())->query(
            "SELECT id, name, owner_user_id, updated_at FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
               AND updated_at IS NOT NULL AND updated_at <= :cutoff
             ORDER BY updated_at ASC LIMIT 50",
            ['cid' => $companyId, 'cutoff' => $cutoff]
        );
        $alerts = 0;
        foreach (is_array($rows) ? $rows : [] as $opp) {
            $owner = (int) ($opp['owner_user_id'] ?? 0);
            $oid = (int) ($opp['id'] ?? 0);
            if (!$this->allowNotify('opportunity_inactivity', 'opportunity', $oid)) {
                continue;
            }
            $msg = 'Opportunity #' . $oid . ' inactive for ' . $days . ' days: ' . (string) ($opp['name'] ?? '');
            if ($owner > 0) {
                $this->notifier->notifyUser(
                    $owner,
                    $companyId,
                    'CRM: Opportunity inactivity',
                    $msg,
                    'warning',
                    'crm.opportunity_inactivity',
                    'crm_opportunity',
                    $oid
                );
            } else {
                $this->notifier->notifyCompany(
                    $companyId,
                    'CRM: Opportunity inactivity',
                    $msg,
                    'warning',
                    'crm.opportunity_inactivity',
                    'crm_opportunity',
                    $oid
                );
            }
            $this->log('opportunity_inactivity', 'opportunity', $oid, $owner > 0 ? $owner : null, [
                'days' => $days,
            ]);
            ++$alerts;
        }
        $this->audit('crm.automation.opportunity_inactivity_scan', 'crm_opportunity', null, [
            'alerts' => $alerts,
            'days' => $days,
        ]);

        return ['alerts' => $alerts];
    }

    /**
     * Customers / leads with no CRM activity for N days.
     *
     * @return array{alerts: int}
     */
    public function processNoActivityReminders(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('no_activity')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $cfg = (new CrmAdminConfigService())->ruleConfig('no_activity');
        $days = max(1, (int) ($cfg['days'] ?? 21));
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $rows = (new Customer())->query(
            "SELECT id, name, crm_owner_user_id, crm_last_interaction_at
             FROM rateb_customers
             WHERE company_id = :cid
               AND (
                    crm_last_interaction_at IS NULL
                    OR crm_last_interaction_at <= :cutoff
               )
             ORDER BY COALESCE(crm_last_interaction_at, '1970-01-01') ASC
             LIMIT 40",
            ['cid' => $companyId, 'cutoff' => $cutoff]
        );
        $alerts = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $owner = (int) ($row['crm_owner_user_id'] ?? 0);
            if (!$this->allowNotify('no_activity', 'customer', $cid)) {
                continue;
            }
            $msg = 'Customer #' . $cid . ' has no activity for ' . $days . ' days: ' . (string) ($row['name'] ?? '');
            if ($owner > 0) {
                $this->notifier->notifyUser($owner, $companyId, 'CRM: No activity', $msg, 'warning', 'crm.no_activity', 'customer', $cid);
            } else {
                $this->notifier->notifyCompany($companyId, 'CRM: No activity', $msg, 'warning', 'crm.no_activity', 'customer', $cid);
            }
            $this->log('no_activity', 'customer', $cid, $owner > 0 ? $owner : null, ['days' => $days]);
            ++$alerts;
        }
        $this->audit('crm.automation.no_activity_scan', 'customer', null, ['alerts' => $alerts, 'days' => $days]);

        return ['alerts' => $alerts];
    }

    /**
     * Renewal due reminders (CRM tracking only — no Subscription/Accounting).
     *
     * @return array{alerts: int}
     */
    public function processRenewalReminders(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('renewal_reminder')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $cfg = (new CrmAdminConfigService())->ruleConfig('renewal_reminder');
        $daysAhead = max(0, (int) ($cfg['days_ahead'] ?? 30));
        $rows = (new CrmRetentionService())->renewalsDue($daysAhead, 40);
        $alerts = 0;
        foreach ($rows as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $owner = (int) ($row['crm_owner_user_id'] ?? 0);
            $due = (string) ($row['crm_renewal_due_at'] ?? '');
            if (!$this->allowNotify('renewal_reminder', 'customer', $cid)) {
                continue;
            }
            $msg = 'Customer #' . $cid . ' renewal due ' . $due . ': ' . (string) ($row['name'] ?? '');
            if ($owner > 0) {
                $this->notifier->notifyUser($owner, $companyId, 'CRM: Renewal reminder', $msg, 'info', 'crm.renewal_reminder', 'customer', $cid);
            } else {
                $this->notifier->notifyCompany($companyId, 'CRM: Renewal reminder', $msg, 'info', 'crm.renewal_reminder', 'customer', $cid);
            }
            $this->log('renewal_reminder', 'customer', $cid, $owner > 0 ? $owner : null, ['due' => $due]);
            ++$alerts;
        }
        $this->audit('crm.automation.renewal_scan', 'customer', null, ['alerts' => $alerts]);

        return ['alerts' => $alerts];
    }

    /**
     * Open opportunities exceeding stage expected_duration_days.
     *
     * @return array{alerts: int}
     */
    public function processStaleOpportunities(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('stale_opportunity')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT o.id, o.name, o.owner_user_id, o.stage_entered_at, s.name AS stage_name, s.expected_duration_days
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_pipeline_stages s ON s.id = o.stage_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open'
               AND s.expected_duration_days IS NOT NULL AND s.expected_duration_days > 0
               AND o.stage_entered_at IS NOT NULL
               AND o.stage_entered_at < DATE_SUB(NOW(), INTERVAL s.expected_duration_days DAY)
             ORDER BY o.stage_entered_at ASC
             LIMIT 50",
            ['cid' => $companyId]
        );
        $alerts = 0;
        foreach (is_array($rows) ? $rows : [] as $opp) {
            $oid = (int) ($opp['id'] ?? 0);
            $owner = (int) ($opp['owner_user_id'] ?? 0);
            if (!$this->allowNotify('stale_opportunity', 'opportunity', $oid)) {
                continue;
            }
            $msg = 'Opportunity #' . $oid . ' stale in stage ' . (string) ($opp['stage_name'] ?? '')
                . ' (SLA ' . (int) ($opp['expected_duration_days'] ?? 0) . 'd)';
            if ($owner > 0) {
                $this->notifier->notifyUser($owner, $companyId, 'CRM: Stale opportunity', $msg, 'warning', 'crm.stale_opportunity', 'crm_opportunity', $oid);
            } else {
                $this->notifier->notifyCompany($companyId, 'CRM: Stale opportunity', $msg, 'warning', 'crm.stale_opportunity', 'crm_opportunity', $oid);
            }
            $this->log('stale_opportunity', 'opportunity', $oid, $owner > 0 ? $owner : null, [
                'expected_duration_days' => (int) ($opp['expected_duration_days'] ?? 0),
            ]);
            ++$alerts;
        }
        $this->audit('crm.automation.stale_opportunity_scan', 'crm_opportunity', null, ['alerts' => $alerts]);

        return ['alerts' => $alerts];
    }

    /**
     * Active customers needing follow-up based on last interaction.
     *
     * @return array{alerts: int}
     */
    public function processCustomerFollowUps(): array
    {
        if (!(new CrmAdminConfigService())->isRuleEnabled('customer_follow_up')) {
            return ['alerts' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $cfg = (new CrmAdminConfigService())->ruleConfig('customer_follow_up');
        $days = max(1, (int) ($cfg['days'] ?? 14));
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $rows = (new Customer())->query(
            "SELECT id, name, crm_owner_user_id, crm_lifecycle_stage, crm_last_interaction_at
             FROM rateb_customers
             WHERE company_id = :cid
               AND crm_lifecycle_stage IN ('customer','active_customer','retention','renewal')
               AND (
                    crm_last_interaction_at IS NULL
                    OR crm_last_interaction_at <= :cutoff
               )
             ORDER BY COALESCE(crm_last_interaction_at, '1970-01-01') ASC
             LIMIT 40",
            ['cid' => $companyId, 'cutoff' => $cutoff]
        );
        $alerts = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $owner = (int) ($row['crm_owner_user_id'] ?? 0);
            if (!$this->allowNotify('customer_follow_up', 'customer', $cid)) {
                continue;
            }
            $msg = 'Follow up customer #' . $cid . ' (' . (string) ($row['crm_lifecycle_stage'] ?? '') . '): '
                . (string) ($row['name'] ?? '');
            if ($owner > 0) {
                $this->notifier->notifyUser($owner, $companyId, 'CRM: Customer follow-up', $msg, 'info', 'crm.customer_follow_up', 'customer', $cid);
            } else {
                $this->notifier->notifyCompany($companyId, 'CRM: Customer follow-up', $msg, 'info', 'crm.customer_follow_up', 'customer', $cid);
            }
            $this->log('customer_follow_up', 'customer', $cid, $owner > 0 ? $owner : null, ['days' => $days]);
            ++$alerts;
        }
        $this->audit('crm.automation.customer_follow_up_scan', 'customer', null, ['alerts' => $alerts]);

        return ['alerts' => $alerts];
    }

    /**
     * @return array{
     *   follow_up: array{reminders:int,overdue:int},
     *   quote_expiry: array{alerts:int},
     *   inactivity: array{alerts:int},
     *   expired_quotes: int,
     *   no_activity: array{alerts:int},
     *   renewal: array{alerts:int},
     *   stale: array{alerts:int},
     *   customer_follow_up: array{alerts:int}
     * }
     */
    public function runAll(): array
    {
        $timed = CrmObservability::timed('crm.automation.run_all', function () {
            if (!$this->safety->acquireRunLock('automation_run_all')) {
                return [
                    'skipped' => 'run_lock',
                    'follow_up' => ['reminders' => 0, 'overdue' => 0],
                    'quote_expiry' => ['alerts' => 0],
                    'inactivity' => ['alerts' => 0],
                    'expired_quotes' => 0,
                    'no_activity' => ['alerts' => 0],
                    'renewal' => ['alerts' => 0],
                    'stale' => ['alerts' => 0],
                    'customer_follow_up' => ['alerts' => 0],
                    'rules_engine' => ['matched' => 0, 'executed' => 0, 'history' => []],
                    'intelligence_scored' => 0,
                    'notify_budget_remaining' => $this->notifyBudget,
                ];
            }
            $this->notifyBudget = $this->safety->maxNotifiesPerRun();
            $follow = $this->processFollowUpReminders();
            $quotes = $this->processQuoteExpiryAlerts();
            $inactive = $this->processOpportunityInactivity();
            $expired = (new CrmQuotationService())->expireOverdue();
            $noActivity = $this->processNoActivityReminders();
            $renewal = $this->processRenewalReminders();
            $stale = $this->processStaleOpportunities();
            $custFollow = $this->processCustomerFollowUps();
            $rules = ['matched' => 0, 'executed' => 0, 'history' => []];
            $scored = 0;
            try {
                $scored = count((new CrmOpportunityIntelligenceService())->refreshOpen(25));
                $rules = (new CrmAutomationRulesEngineService())->evaluate([
                    'entity_type' => 'crm',
                    'entity_id' => 0,
                    'days_inactive' => 21,
                ]);
            } catch (\Throwable $e) {
                CrmObservability::logFailure('crm.automation.rules_or_intel', $e);
            }
            $payload = [
                'follow_up' => $follow,
                'quote_expiry' => $quotes,
                'inactivity' => $inactive,
                'expired_quotes' => $expired,
                'no_activity' => $noActivity,
                'renewal' => $renewal,
                'stale' => $stale,
                'customer_follow_up' => $custFollow,
                'rules_engine' => $rules,
                'intelligence_scored' => $scored,
                'notify_budget_remaining' => $this->notifyBudget,
            ];
            $this->audit('crm.automation.run_all', 'crm', null, $payload);

            return $payload;
        });

        return is_array($timed['result'] ?? null) ? $timed['result'] : [];
    }

    /** Phase 10 — cooldown + notify budget gate. */
    private function allowNotify(string $eventType, ?string $entityType, ?int $entityId): bool
    {
        $gate = $this->safety->allowNotify($eventType, $entityType, $entityId, $this->notifyBudget);

        return !empty($gate['allowed']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(
        string $eventType,
        ?string $entityType,
        ?int $entityId,
        ?int $userId,
        array $payload = []
    ): void {
        (new CrmAutomationLog())->create([
            'company_id' => CrmSupport::requireCompanyId(),
            'event_type' => substr($eventType, 0, 60),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function audit(string $action, ?string $entityType, ?int $entityId, ?array $payload): void
    {
        if (class_exists(AuditService::class)) {
            (new AuditService())->log($action, $entityType, $entityId, $payload);
        }
    }
}
