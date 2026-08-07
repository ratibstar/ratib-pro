<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmCompany;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;

/**
 * Phase 8/9 — Unified CRM search with relevance ranking + permission-aware results.
 */
final class CrmUnifiedSearchService
{
    /**
     * @return array{
     *   q:string,
     *   results:array<string, list<array<string, mixed>>>,
     *   ranked:list<array<string, mixed>>,
     *   total:int
     * }
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
            return ['q' => $q, 'results' => $out, 'ranked' => [], 'total' => 0];
        }
        $companyId = CrmSupport::requireCompanyId();
        $like = '%' . $q . '%';
        $fetchLimit = max(1, min(40, $limitPerType * 2));
        $limit = max(1, min(25, $limitPerType));

        if ($this->canViewCrm()) {
            $out['leads'] = $this->rankSlice($this->rows((new CrmLead())->query(
                "SELECT id, lead_no, title, email, phone, workflow_status, updated_at
                 FROM rateb_crm_leads
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (title LIKE :q OR email LIKE :q OR phone LIKE :q OR lead_no LIKE :q)
                 ORDER BY updated_at DESC, id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['lead_no', 'email', 'phone', 'title'], $limit);

            $out['contacts'] = $this->rankSlice($this->rows((new CrmContact())->query(
                "SELECT id, full_name, email, phone
                 FROM rateb_crm_contacts
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (full_name LIKE :q OR email LIKE :q OR phone LIKE :q)
                 ORDER BY id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['email', 'phone', 'full_name'], $limit);

            $out['companies'] = $this->rankSlice($this->rows((new CrmCompany())->query(
                "SELECT id, name, email, phone
                 FROM rateb_crm_companies
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (name LIKE :q OR email LIKE :q OR phone LIKE :q)
                 ORDER BY id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['name', 'email', 'phone'], $limit);

            $out['opportunities'] = $this->rankSlice($this->rows((new CrmOpportunity())->query(
                "SELECT id, opportunity_no, name, amount, workflow_status, updated_at
                 FROM rateb_crm_opportunities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (name LIKE :q OR opportunity_no LIKE :q)
                 ORDER BY updated_at DESC, id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['opportunity_no', 'name'], $limit);

            $out['quotations'] = $this->rankSlice($this->rows((new CrmQuotation())->query(
                "SELECT id, quotation_no, title, status, total_amount
                 FROM rateb_crm_quotations
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (title LIKE :q OR quotation_no LIKE :q)
                 ORDER BY id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['quotation_no', 'title'], $limit);
        }
        if ($this->canViewActivities()) {
            $out['activities'] = $this->rankSlice($this->rows((new CrmActivity())->query(
                "SELECT id, activity_type, subject, status, activity_at, due_at
                 FROM rateb_crm_activities
                 WHERE company_id = :cid AND deleted_at IS NULL
                   AND (subject LIKE :q OR body LIKE :q)
                 ORDER BY id DESC
                 LIMIT {$fetchLimit}",
                ['cid' => $companyId, 'q' => $like]
            )), $q, ['subject'], $limit);
        }

        $ranked = [];
        foreach ($out as $type => $list) {
            foreach ($list as $row) {
                $ranked[] = array_merge($row, ['entity_type' => $type]);
            }
        }
        usort($ranked, static function (array $a, array $b): int {
            return ((int) ($b['relevance'] ?? 0)) <=> ((int) ($a['relevance'] ?? 0));
        });

        $total = 0;
        foreach ($out as $list) {
            $total += count($list);
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.search.usage', 'crm_unified_search', null, [
                'q_len' => mb_strlen($q),
                'total' => $total,
                'ranked' => true,
            ]);
        }

        return ['q' => $q, 'results' => $out, 'ranked' => array_slice($ranked, 0, $limit * 3), 'total' => $total];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private function rankSlice(array $rows, string $q, array $fields, int $limit): array
    {
        $qLower = mb_strtolower($q);
        $scored = [];
        foreach ($rows as $row) {
            $score = 10;
            foreach ($fields as $field) {
                $val = mb_strtolower(trim((string) ($row[$field] ?? '')));
                if ($val === '') {
                    continue;
                }
                if ($val === $qLower) {
                    $score = max($score, 100);
                } elseif (str_starts_with($val, $qLower)) {
                    $score = max($score, 80);
                } elseif (str_contains($val, $qLower)) {
                    $score = max($score, 50);
                }
            }
            $row['relevance'] = $score;
            $scored[] = $row;
        }
        usort($scored, static function (array $a, array $b): int {
            return ((int) ($b['relevance'] ?? 0)) <=> ((int) ($a['relevance'] ?? 0));
        });

        return array_slice($scored, 0, $limit);
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
