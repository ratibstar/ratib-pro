<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmStageTransition;
use Rateb\App\Models\CrmTask;
use Rateb\App\Models\Customer;

/** Phase 6 — Sales Execution Workspace (rep daily actions). */
final class CrmSalesWorkspaceService
{
    /**
     * @param array{user_id?:?int,team_id?:?int,territory_id?:?int} $filters
     * @return array<string, mixed>
     */
    public function assemble(array $filters = []): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $userId = CrmSupport::intOrNull($filters['user_id'] ?? null) ?? CrmSupport::userId();
        $teamId = CrmSupport::intOrNull($filters['team_id'] ?? null);
        $territoryId = CrmSupport::intOrNull($filters['territory_id'] ?? null);

        return [
            'filters' => [
                'user_id' => $userId,
                'team_id' => $teamId,
                'territory_id' => $territoryId,
            ],
            'my_leads' => $this->myLeads($companyId, $userId, $teamId),
            'my_opportunities' => $this->myOpportunities($companyId, $userId, $teamId),
            'my_tasks' => $this->myTasks($companyId, $userId),
            'follow_ups_due' => $this->followUpsDue($companyId, $userId),
            'pipeline_changes' => $this->pipelineChanges($companyId, $userId, $teamId),
            'daily_sales_actions' => $this->dailySalesActions($companyId, $userId, $teamId, $territoryId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function myLeads(int $companyId, ?int $userId, ?int $teamId): array
    {
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($userId !== null && $userId > 0) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        // team filter via customers linked later not on leads — soft ignore if no team column
        $rows = (new CrmLead())->query(
            "SELECT id, lead_no, title, workflow_status, estimated_value, owner_user_id, updated_at
             FROM rateb_crm_leads WHERE {$where}
             ORDER BY updated_at DESC LIMIT 40",
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function myOpportunities(int $companyId, ?int $userId, ?int $teamId): array
    {
        $params = ['cid' => $companyId];
        $where = "company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'";
        if ($userId !== null && $userId > 0) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($teamId !== null && $teamId > 0) {
            $where .= ' AND team_id = :tid';
            $params['tid'] = $teamId;
        }
        try {
            $rows = (new CrmOpportunity())->query(
                "SELECT id, opportunity_no, name, amount, probability_percent, intelligence_score, engagement_score,
                        risk_level, is_stale, stage_id, owner_user_id, team_id, updated_at
                 FROM rateb_crm_opportunities WHERE {$where}
                 ORDER BY updated_at DESC LIMIT 40",
                $params
            );
        } catch (\Throwable $e) {
            $rows = (new CrmOpportunity())->query(
                "SELECT id, opportunity_no, name, amount, probability_percent, stage_id, owner_user_id, team_id, updated_at
                 FROM rateb_crm_opportunities WHERE {$where}
                 ORDER BY updated_at DESC LIMIT 40",
                $params
            );
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function myTasks(int $companyId, ?int $userId): array
    {
        $params = ['cid' => $companyId];
        $where = "company_id = :cid AND deleted_at IS NULL AND status = 'open'";
        if ($userId !== null && $userId > 0) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        $rows = (new CrmTask())->query(
            "SELECT id, subject, due_at, reminder_at, priority, status, owner_user_id
             FROM rateb_crm_tasks WHERE {$where}
             ORDER BY COALESCE(due_at, created_at) ASC LIMIT 40",
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followUpsDue(int $companyId, ?int $userId): array
    {
        $params = ['cid' => $companyId, 'now' => date('Y-m-d H:i:s')];
        $where = "company_id = :cid AND deleted_at IS NULL AND status = 'open'
                  AND due_at IS NOT NULL AND due_at <= :now";
        if ($userId !== null && $userId > 0) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        $rows = (new CrmTask())->query(
            "SELECT id, subject, due_at, priority, owner_user_id
             FROM rateb_crm_tasks WHERE {$where}
             ORDER BY due_at ASC LIMIT 30",
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pipelineChanges(int $companyId, ?int $userId, ?int $teamId): array
    {
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        if ($userId !== null && $userId > 0) {
            $where .= ' AND owner_user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($teamId !== null && $teamId > 0) {
            $where .= ' AND team_id = :tid';
            $params['tid'] = $teamId;
        }
        $rows = (new CrmStageTransition())->query(
            "SELECT id, opportunity_id, from_stage_id, to_stage_id, duration_seconds, owner_user_id, created_at
             FROM rateb_crm_stage_transitions WHERE {$where}
             ORDER BY created_at DESC LIMIT 40",
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{type:string,label:string,entity_type:string,entity_id:int,priority:int}>
     */
    private function dailySalesActions(int $companyId, ?int $userId, ?int $teamId, ?int $territoryId): array
    {
        $actions = [];
        foreach ($this->followUpsDue($companyId, $userId) as $t) {
            $actions[] = [
                'type' => 'follow_up_due',
                'label' => (string) ($t['subject'] ?? ('Task #' . ($t['id'] ?? 0))),
                'entity_type' => 'task',
                'entity_id' => (int) ($t['id'] ?? 0),
                'priority' => 1,
            ];
        }
        try {
            $staleParams = ['cid' => $companyId];
            $staleWhere = "company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' AND is_stale = 1";
            if ($userId !== null && $userId > 0) {
                $staleWhere .= ' AND owner_user_id = :uid';
                $staleParams['uid'] = $userId;
            }
            if ($teamId !== null && $teamId > 0) {
                $staleWhere .= ' AND team_id = :tid';
                $staleParams['tid'] = $teamId;
            }
            $stale = (new CrmOpportunity())->query(
                "SELECT id, name FROM rateb_crm_opportunities WHERE {$staleWhere} ORDER BY updated_at ASC LIMIT 15",
                $staleParams
            );
            foreach (is_array($stale) ? $stale : [] as $o) {
                $actions[] = [
                    'type' => 'stale_opportunity',
                    'label' => (string) ($o['name'] ?? ('Opp #' . ($o['id'] ?? 0))),
                    'entity_type' => 'opportunity',
                    'entity_id' => (int) ($o['id'] ?? 0),
                    'priority' => 2,
                ];
            }
        } catch (\Throwable $e) {
            // is_stale may be absent pre-migrate
        }

        $custParams = ['cid' => $companyId];
        $custWhere = 'company_id = :cid AND crm_at_risk = 1';
        if ($userId !== null && $userId > 0) {
            $custWhere .= ' AND crm_owner_user_id = :uid';
            $custParams['uid'] = $userId;
        }
        if ($teamId !== null && $teamId > 0) {
            $custWhere .= ' AND crm_team_id = :tid';
            $custParams['tid'] = $teamId;
        }
        if ($territoryId !== null && $territoryId > 0) {
            $custWhere .= ' AND crm_territory_id = :ter';
            $custParams['ter'] = $territoryId;
        }
        try {
            $risk = (new Customer())->query(
                "SELECT id, name FROM rateb_customers WHERE {$custWhere} ORDER BY crm_activity_score ASC LIMIT 10",
                $custParams
            );
            foreach (is_array($risk) ? $risk : [] as $c) {
                $actions[] = [
                    'type' => 'at_risk_customer',
                    'label' => (string) ($c['name'] ?? ('Customer #' . ($c['id'] ?? 0))),
                    'entity_type' => 'customer',
                    'entity_id' => (int) ($c['id'] ?? 0),
                    'priority' => 3,
                ];
            }
        } catch (\Throwable $e) {
            // columns may be missing pre-migrate
        }

        usort($actions, static fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_slice($actions, 0, 40);
    }
}
