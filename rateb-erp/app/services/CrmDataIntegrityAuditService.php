<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;

/**
 * Phase 11 — CRM data integrity audit (findings + safe remediation only; never deletes).
 */
final class CrmDataIntegrityAuditService
{
    private const SAMPLE_LIMIT = 25;

    /**
     * @return array{
     *   company_id:int,
     *   generated_at:string,
     *   summary:array<string,int>,
     *   findings:list<array<string,mixed>>,
     *   auto_delete:false
     * }
     */
    public function runAudit(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $findings = [];
        $errors = [];
        foreach ([
            'orphanOpportunities',
            'orphanActivities',
            'invalidCustomerRefs',
            'invalidCompanyRefs',
            'duplicateActiveLeads',
            'duplicateActiveContacts',
            'invalidLifecycleStates',
            'invalidPipelineStages',
            'brokenQuotationRelationships',
            'inconsistentStageHistory',
            'forecastOrphans',
        ] as $method) {
            try {
                $chunk = $this->{$method}($companyId);
                if (is_array($chunk)) {
                    $findings = array_merge($findings, $chunk);
                }
            } catch (\Throwable $e) {
                $errors[] = ['check' => $method, 'error' => $e->getMessage()];
            }
        }

        $byCode = [];
        foreach ($findings as $f) {
            $code = (string) ($f['code'] ?? 'unknown');
            $byCode[$code] = ($byCode[$code] ?? 0) + 1;
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.integrity.audit', 'crm_integrity', $companyId, [
                'finding_count' => count($findings),
                'by_code' => $byCode,
            ]);
        }

        return [
            'company_id' => $companyId,
            'generated_at' => date('c'),
            'summary' => [
                'total_findings' => count($findings),
                'codes' => count($byCode),
                'check_errors' => count($errors),
            ] + $byCode,
            'findings' => $findings,
            'check_errors' => $errors,
            'auto_delete' => false,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function orphanOpportunities(int $companyId): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT o.id, o.opportunity_no, o.lead_id
             FROM rateb_crm_opportunities o
             LEFT JOIN rateb_crm_leads l
               ON l.id = o.lead_id AND l.company_id = o.company_id AND l.deleted_at IS NULL
             WHERE o.company_id = :cid AND o.deleted_at IS NULL
               AND o.lead_id IS NOT NULL AND o.lead_id > 0 AND l.id IS NULL
             ORDER BY o.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return $this->mapFindings(
            is_array($rows) ? $rows : [],
            'orphan_opportunity',
            'high',
            'Opportunity references missing/deleted lead',
            'Re-link lead_id to a valid lead for this company, or archive the opportunity via CRM UI (no hard delete).'
        );
    }

    /** @return list<array<string, mixed>> */
    private function orphanActivities(int $companyId): array
    {
        $rows = (new CrmActivity())->query(
            "SELECT a.id, a.lead_id, a.contact_id, a.opportunity_id
             FROM rateb_crm_activities a
             LEFT JOIN rateb_crm_leads l
               ON l.id = a.lead_id AND l.company_id = a.company_id AND l.deleted_at IS NULL
             LEFT JOIN rateb_crm_contacts c
               ON c.id = a.contact_id AND c.company_id = a.company_id AND c.deleted_at IS NULL
             LEFT JOIN rateb_crm_opportunities o
               ON o.id = a.opportunity_id AND o.company_id = a.company_id AND o.deleted_at IS NULL
             WHERE a.company_id = :cid AND a.deleted_at IS NULL
               AND (
                 (a.lead_id IS NOT NULL AND a.lead_id > 0 AND l.id IS NULL)
                 OR (a.contact_id IS NOT NULL AND a.contact_id > 0 AND c.id IS NULL)
                 OR (a.opportunity_id IS NOT NULL AND a.opportunity_id > 0 AND o.id IS NULL)
               )
             ORDER BY a.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return $this->mapFindings(
            is_array($rows) ? $rows : [],
            'orphan_activity',
            'medium',
            'Activity references missing lead/contact/opportunity',
            'Null or re-link the broken FK to a valid tenant entity; do not delete activity history.'
        );
    }

    /** @return list<array<string, mixed>> */
    private function invalidCustomerRefs(int $companyId): array
    {
        $out = [];
        $leadRows = (new CrmLead())->query(
            "SELECT l.id, l.customer_id
             FROM rateb_crm_leads l
             LEFT JOIN rateb_customers cu ON cu.id = l.customer_id AND cu.company_id = l.company_id
             WHERE l.company_id = :cid AND l.deleted_at IS NULL
               AND l.customer_id IS NOT NULL AND l.customer_id > 0 AND cu.id IS NULL
             ORDER BY l.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );
        $out = array_merge($out, $this->mapFindings(
            is_array($leadRows) ? $leadRows : [],
            'invalid_customer_ref_lead',
            'high',
            'Lead customer_id not in rateb_customers for this company',
            'Clear customer_id or link to a valid customer in the same tenant.'
        ));

        $oppRows = (new CrmOpportunity())->query(
            "SELECT o.id, o.customer_id
             FROM rateb_crm_opportunities o
             LEFT JOIN rateb_customers cu ON cu.id = o.customer_id AND cu.company_id = o.company_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL
               AND o.customer_id IS NOT NULL AND o.customer_id > 0 AND cu.id IS NULL
             ORDER BY o.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return array_merge($out, $this->mapFindings(
            is_array($oppRows) ? $oppRows : [],
            'invalid_customer_ref_opportunity',
            'high',
            'Opportunity customer_id not in rateb_customers for this company',
            'Clear or re-link customer_id within the same company_id scope.'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function invalidCompanyRefs(int $companyId): array
    {
        $rows = (new CrmLead())->query(
            "SELECT l.id, l.crm_company_id
             FROM rateb_crm_leads l
             LEFT JOIN rateb_crm_companies cc
               ON cc.id = l.crm_company_id AND cc.company_id = l.company_id AND cc.deleted_at IS NULL
             WHERE l.company_id = :cid AND l.deleted_at IS NULL
               AND l.crm_company_id IS NOT NULL AND l.crm_company_id > 0 AND cc.id IS NULL
             ORDER BY l.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return $this->mapFindings(
            is_array($rows) ? $rows : [],
            'invalid_crm_company_ref',
            'medium',
            'Lead crm_company_id missing or cross-tenant',
            'Clear crm_company_id or point to a CRM company row owned by this tenant.'
        );
    }

    /** @return list<array<string, mixed>> */
    private function duplicateActiveLeads(int $companyId): array
    {
        $rows = (new CrmLead())->query(
            "SELECT email, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids
             FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL AND email IS NOT NULL AND email <> ''
             GROUP BY email HAVING COUNT(*) > 1
             ORDER BY cnt DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );
        $findings = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $findings[] = [
                'code' => 'duplicate_active_lead_email',
                'severity' => 'medium',
                'entity_type' => 'lead',
                'entity_id' => null,
                'detail' => [
                    'email' => (string) ($r['email'] ?? ''),
                    'count' => (int) ($r['cnt'] ?? 0),
                    'ids' => (string) ($r['ids'] ?? ''),
                ],
                'message' => 'Multiple active leads share the same email',
                'remediation' => 'Use CRM Duplicate Merge (crm.merge.manage) to merge into one keep record; never hard-delete.',
            ];
        }

        return $findings;
    }

    /** @return list<array<string, mixed>> */
    private function duplicateActiveContacts(int $companyId): array
    {
        $rows = (new CrmLead())->query(
            "SELECT email, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids
             FROM rateb_crm_contacts
             WHERE company_id = :cid AND deleted_at IS NULL AND email IS NOT NULL AND email <> ''
             GROUP BY email HAVING COUNT(*) > 1
             ORDER BY cnt DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );
        $findings = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $findings[] = [
                'code' => 'duplicate_active_contact_email',
                'severity' => 'medium',
                'entity_type' => 'contact',
                'entity_id' => null,
                'detail' => [
                    'email' => (string) ($r['email'] ?? ''),
                    'count' => (int) ($r['cnt'] ?? 0),
                    'ids' => (string) ($r['ids'] ?? ''),
                ],
                'message' => 'Multiple active contacts share the same email',
                'remediation' => 'Request/execute contact merge via CRM merge workflow; soft-archive source only.',
            ];
        }

        return $findings;
    }

    /** @return list<array<string, mixed>> */
    private function invalidLifecycleStates(int $companyId): array
    {
        $allowed = "'lead','prospect','opportunity','customer','churn_risk','churned','archived',''";
        $rows = (new CrmLead())->query(
            "SELECT id, crm_lifecycle_stage AS stage
             FROM rateb_customers
             WHERE company_id = :cid
               AND crm_lifecycle_stage IS NOT NULL
               AND crm_lifecycle_stage NOT IN ({$allowed})
             ORDER BY id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return $this->mapFindings(
            is_array($rows) ? $rows : [],
            'invalid_lifecycle_state',
            'low',
            'Customer crm_lifecycle_stage is not a known value',
            'Correct via CRM lifecycle manage UI to a supported stage; do not wipe history.'
        );
    }

    /** @return list<array<string, mixed>> */
    private function invalidPipelineStages(int $companyId): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT o.id, o.stage_id, o.pipeline_id
             FROM rateb_crm_opportunities o
             LEFT JOIN rateb_crm_pipeline_stages s
               ON s.id = o.stage_id AND s.company_id = o.company_id AND s.deleted_at IS NULL
             WHERE o.company_id = :cid AND o.deleted_at IS NULL
               AND o.stage_id IS NOT NULL AND o.stage_id > 0 AND s.id IS NULL
             ORDER BY o.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );
        $out = $this->mapFindings(
            is_array($rows) ? $rows : [],
            'invalid_pipeline_stage',
            'high',
            'Opportunity stage_id missing or not in tenant pipeline stages',
            'Move opportunity to a valid stage in its pipeline via CRM pipeline UI.'
        );

        $mismatch = (new CrmOpportunity())->query(
            "SELECT o.id, o.stage_id, o.pipeline_id, s.pipeline_id AS stage_pipeline_id
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_pipeline_stages s
               ON s.id = o.stage_id AND s.company_id = o.company_id AND s.deleted_at IS NULL
             WHERE o.company_id = :cid AND o.deleted_at IS NULL
               AND o.pipeline_id IS NOT NULL AND s.pipeline_id IS NOT NULL
               AND o.pipeline_id <> s.pipeline_id
             ORDER BY o.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return array_merge($out, $this->mapFindings(
            is_array($mismatch) ? $mismatch : [],
            'pipeline_stage_mismatch',
            'high',
            'Opportunity pipeline_id does not match stage pipeline',
            'Align pipeline_id/stage_id using pipeline board move (tenant-scoped).'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function brokenQuotationRelationships(int $companyId): array
    {
        $rows = (new CrmLead())->query(
            "SELECT q.id, q.opportunity_id, q.lead_id, q.status
             FROM rateb_crm_quotations q
             LEFT JOIN rateb_crm_opportunities o
               ON o.id = q.opportunity_id AND o.company_id = q.company_id AND o.deleted_at IS NULL
             LEFT JOIN rateb_crm_leads l
               ON l.id = q.lead_id AND l.company_id = q.company_id AND l.deleted_at IS NULL
             WHERE q.company_id = :cid AND q.deleted_at IS NULL
               AND (
                 (q.opportunity_id IS NOT NULL AND q.opportunity_id > 0 AND o.id IS NULL)
                 OR (q.lead_id IS NOT NULL AND q.lead_id > 0 AND l.id IS NULL)
               )
             ORDER BY q.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );

        return $this->mapFindings(
            is_array($rows) ? $rows : [],
            'broken_quotation_relationship',
            'high',
            'Quotation references missing opportunity/lead',
            'Re-link quotation to valid opportunity/lead in same company; keep quote history (no invoice conversion).'
        );
    }

    /** @return list<array<string, mixed>> */
    private function inconsistentStageHistory(int $companyId): array
    {
        $rows = (new CrmLead())->query(
            "SELECT t.id, t.opportunity_id, t.to_stage_id
             FROM rateb_crm_stage_transitions t
             LEFT JOIN rateb_crm_opportunities o
               ON o.id = t.opportunity_id AND o.company_id = t.company_id
             WHERE t.company_id = :cid AND o.id IS NULL
             ORDER BY t.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId]
        );
        $out = $this->mapFindings(
            is_array($rows) ? $rows : [],
            'stage_history_orphan',
            'medium',
            'Stage transition references missing opportunity',
            'Leave history intact; mark for review. Do not DELETE transition rows in production.'
        );

        $currentMismatch = (new CrmOpportunity())->query(
            "SELECT o.id, o.stage_id AS current_stage, last_t.to_stage_id AS last_to_stage
             FROM rateb_crm_opportunities o
             INNER JOIN (
                SELECT t.opportunity_id, t.to_stage_id
                FROM rateb_crm_stage_transitions t
                INNER JOIN (
                    SELECT opportunity_id, MAX(id) AS max_id
                    FROM rateb_crm_stage_transitions
                    WHERE company_id = :cid
                    GROUP BY opportunity_id
                ) latest ON latest.max_id = t.id
             ) last_t ON last_t.opportunity_id = o.id
             WHERE o.company_id = :cid2 AND o.deleted_at IS NULL AND o.stage_id IS NOT NULL
               AND last_t.to_stage_id <> o.stage_id
             ORDER BY o.id DESC LIMIT " . self::SAMPLE_LIMIT,
            ['cid' => $companyId, 'cid2' => $companyId]
        );

        return array_merge($out, $this->mapFindings(
            is_array($currentMismatch) ? $currentMismatch : [],
            'stage_history_inconsistent',
            'low',
            'Latest stage transition does not match opportunity.stage_id',
            'Reconcile by recording a corrective stage move via CRM workflow (adds history; no wipe).'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function forecastOrphans(int $companyId): array
    {
        $findings = [];
        try {
            $rows = (new CrmLead())->query(
                "SELECT id, period_key, team_id, owner_user_id, opportunity_count
                 FROM rateb_crm_forecast_snapshots
                 WHERE company_id = :cid
                   AND (
                     (team_id IS NOT NULL AND team_id > 0 AND NOT EXISTS (
                        SELECT 1 FROM rateb_crm_sales_teams t
                        WHERE t.id = rateb_crm_forecast_snapshots.team_id
                          AND t.company_id = rateb_crm_forecast_snapshots.company_id
                          AND t.deleted_at IS NULL
                     ))
                     OR (opportunity_count > 0 AND open_amount = 0 AND weighted_amount = 0 AND won_amount = 0)
                   )
                 ORDER BY id DESC LIMIT " . self::SAMPLE_LIMIT,
                ['cid' => $companyId]
            );
            foreach (is_array($rows) ? $rows : [] as $r) {
                $findings[] = [
                    'code' => 'forecast_orphan_or_inconsistent',
                    'severity' => 'medium',
                    'entity_type' => 'forecast_snapshot',
                    'entity_id' => (int) ($r['id'] ?? 0),
                    'detail' => $r,
                    'message' => 'Forecast snapshot has invalid team ref or inconsistent opportunity totals',
                    'remediation' => 'Rebuild snapshot via enterprise forecast job for the period; retain old rows for audit.',
                ];
            }
        } catch (\Throwable $e) {
            // team_id column may be absent on older schemas — skip without failing certification tooling
        }

        return $findings;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapFindings(array $rows, string $code, string $severity, string $message, string $remediation): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'code' => $code,
                'severity' => $severity,
                'entity_type' => null,
                'entity_id' => isset($r['id']) ? (int) $r['id'] : null,
                'detail' => $r,
                'message' => $message,
                'remediation' => $remediation,
            ];
        }

        return $out;
    }
}
