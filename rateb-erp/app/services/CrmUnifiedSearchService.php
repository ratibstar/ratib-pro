<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmCompany;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;

/** Phase 8 — Unified CRM search with RBAC-aware entity inclusion. */
final class CrmUnifiedSearchService
{
    /**
     * @return array{q:string,results:array<string, list<array<string, mixed>>>,total:int}
     */
    public function search(string $q, int $limitPerType = 8): array
    {
        $q = trim($q);
        $out = [
            'leads' => [],
            'contacts' => [],
            'companies' => [],
            'opportunities' => [],
            'quotations' => [],
            'activities' => [],
        ];
        if ($q === '' || mb_strlen($q) < 2) {
            return ['q' => $q, 'results' => $out, 'total' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $like = '%' . $q . '%';
        $limit = max(1, min(25, $limitPerType));

        if ($this->canViewCrm()) {
            $out['leads'] = $this->rows((new CrmLead())->query(
                "SELECT id, lead_no, title, email, phone, workflow_status
                 FROM rateb_crm_leads
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (title LIKE :q OR email LIKE :q OR phone LIKE :q OR lead_no LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
            $out['contacts'] = $this->rows((new CrmContact())->query(
                "SELECT id, full_name, email, phone
                 FROM rateb_crm_contacts
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (full_name LIKE :q OR email LIKE :q OR phone LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
            $out['companies'] = $this->rows((new CrmCompany())->query(
                "SELECT id, name, email, phone
                 FROM rateb_crm_companies
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (name LIKE :q OR email LIKE :q OR phone LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
            $out['opportunities'] = $this->rows((new CrmOpportunity())->query(
                "SELECT id, opportunity_no, name, amount, workflow_status
                 FROM rateb_crm_opportunities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (name LIKE :q OR opportunity_no LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
            $out['quotations'] = $this->rows((new CrmQuotation())->query(
                "SELECT id, quotation_no, title, status, total_amount
                 FROM rateb_crm_quotations
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (title LIKE :q OR quotation_no LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
        }
        if ($this->canViewActivities()) {
            $out['activities'] = $this->rows((new CrmActivity())->query(
                "SELECT id, activity_type, subject, status, activity_at, due_at
                 FROM rateb_crm_activities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (subject LIKE :q OR body LIKE :q)
                 ORDER BY id DESC LIMIT {$limit}",
                ['cid' => $companyId, 'q' => $like]
            ));
        }

        $total = 0;
        foreach ($out as $list) {
            $total += count($list);
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.search.usage', 'crm_unified_search', null, [
                'q_len' => mb_strlen($q),
                'total' => $total,
            ]);
        }

        return ['q' => $q, 'results' => $out, 'total' => $total];
    }

    private function canViewCrm(): bool
    {
        return !function_exists('rateb_can')
            || rateb_can('crm.view')
            || rateb_can('crm.manage')
            || rateb_can('crm.admin')
            || rateb_can('crm.search.view');
    }

    private function canViewActivities(): bool
    {
        return !function_exists('rateb_can')
            || rateb_can('crm.activities')
            || rateb_can('crm.activities.view')
            || rateb_can('crm.manage')
            || rateb_can('crm.admin')
            || rateb_can('crm.search.view');
    }

    /**
     * @param mixed $rows
     * @return list<array<string, mixed>>
     */
    private function rows($rows): array
    {
        return is_array($rows) ? $rows : [];
    }
}
