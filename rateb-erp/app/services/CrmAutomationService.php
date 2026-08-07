<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAutomationLog;
use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\CrmTask;

/**
 * Phase 3 — CRM automation events via existing NotificationService.
 * No new notification subsystem.
 */
final class CrmAutomationService
{
    private NotificationService $notifier;

    public function __construct(?NotificationService $notifier = null)
    {
        $this->notifier = $notifier ?? new NotificationService();
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
        $companyId = CrmSupport::requireCompanyId();
        $horizon = date('Y-m-d', strtotime('+3 days'));
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
