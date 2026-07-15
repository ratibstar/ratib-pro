<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Employer ATS bridge (CandidateService / RecruitmentSupport only).
 */
final class PortalRecruitmentService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * Employer ATS search (company-scoped CandidateService + assertCandidate on shortlist).
     * Customers must use pipelineSummary(), not this search.
     *
     * @param array<string, mixed>|null $portalUser Unused for employer search; reserved for future scoping.
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function searchCandidates(string $search = '', int $limit = 25, int $offset = 0, ?array $portalUser = null): array
    {
        TenantContext::setCompanyId($this->repo->companyId());
        // Employer needs company-wide search to shortlist; customers use scoped pipelineSummary only.
        unset($portalUser);
        if (!class_exists(\Rateb\App\Services\CandidateService::class)) {
            return ['items' => [], 'total' => 0];
        }
        try {
            return (new \Rateb\App\Services\CandidateService())->list($limit, $offset, $search);
        } catch (\Throwable $e) {
            error_log('PortalRecruitmentService search: ' . $e->getMessage());

            return ['items' => [], 'total' => 0];
        }
    }

    public function shortlist(array $portalUser, int $candidateId, ?int $careerId = null, string $notes = ''): bool
    {
        TenantContext::setCompanyId($this->repo->companyId());
        if (class_exists(\Rateb\App\Services\RecruitmentSupport::class)) {
            try {
                \Rateb\App\Services\RecruitmentSupport::assertCandidate($candidateId, $this->repo->companyId());
            } catch (\Throwable $e) {
                return false;
            }
        }
        try {
            $this->repo->execute(
                'INSERT INTO rateb_website_portal_shortlists
                 (company_id, portal_user_id, recruitment_candidate_id, career_id, status, notes)
                 VALUES (:cid, :uid, :cand, :career, :st, :notes)',
                [
                    'cid' => $this->repo->companyId(),
                    'uid' => (int) $portalUser['id'],
                    'cand' => $candidateId,
                    'career' => $careerId,
                    'st' => 'shortlisted',
                    'notes' => $notes !== '' ? $notes : null,
                ]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function decide(array $portalUser, int $shortlistId, string $decision, string $notes = ''): bool
    {
        $allowed = ['approved', 'rejected', 'replacement_requested'];
        if (!in_array($decision, $allowed, true)) {
            return false;
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $shortlistId;
        $params['uid'] = (int) $portalUser['id'];
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_portal_shortlists WHERE {$where} AND id = :id AND portal_user_id = :uid LIMIT 1",
            $params
        );
        if ($row === null) {
            return false;
        }
        $this->repo->execute(
            'UPDATE rateb_website_portal_shortlists SET status = :st, notes = COALESCE(:notes, notes)
             WHERE id = :id AND company_id = :cid',
            [
                'st' => $decision,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $shortlistId,
                'cid' => $this->repo->companyId(),
            ]
        );

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function shortlistsForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere('s');
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT s.*, c.full_name, c.candidate_no, c.workflow_status, c.email, c.phone
             FROM rateb_website_portal_shortlists s
             LEFT JOIN rateb_recruitment_candidates c ON c.id = s.recruitment_candidate_id
             WHERE {$where} AND s.portal_user_id = :uid
             ORDER BY s.id DESC LIMIT 100",
            $params
        );
    }

    /**
     * Read-only ATS pipeline stages for a portal user only (shortlists + request links).
     * Never returns company-wide candidates.
     *
     * @param array<string, mixed>|null $portalUser
     * @return array{total: int, stages: array<string, list<array<string,mixed>>>}
     */
    public function pipelineSummary(int $limitPerStage = 20, ?array $portalUser = null): array
    {
        $empty = [
            'total' => 0,
            'stages' => [
                'shortlisted' => [],
                'interview' => [],
                'medical' => [],
                'visa' => [],
                'ready' => [],
                'deployed' => [],
            ],
        ];
        if ($portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return $empty;
        }
        TenantContext::setCompanyId($this->repo->companyId());
        $stages = $empty['stages'];
        $map = [
            'interview' => 'interview',
            'medical' => 'medical',
            'visa' => 'visa',
            'ready' => 'ready',
            'deployed' => 'deployed',
            'registered' => 'shortlisted',
            'documents_pending' => 'shortlisted',
            'draft' => 'shortlisted',
            'contract' => 'ready',
            'shortlisted' => 'shortlisted',
        ];
        $limitPerStage = max(1, min(50, $limitPerStage));
        $uid = (int) $portalUser['id'];
        $cid = $this->repo->companyId();
        try {
            $rows = $this->repo->fetchAll(
                "SELECT DISTINCT c.id, c.full_name, c.candidate_no, c.workflow_status, c.email, c.job_title_target
                 FROM rateb_recruitment_candidates c
                 WHERE c.company_id = :cid
                   AND (c.deleted_at IS NULL)
                   AND (
                        c.id IN (
                            SELECT s.recruitment_candidate_id
                            FROM rateb_website_portal_shortlists s
                            WHERE s.company_id = :cid2 AND s.portal_user_id = :uid
                        )
                     OR c.id IN (
                            SELECT r.recruitment_candidate_id
                            FROM rateb_website_portal_requests r
                            WHERE r.company_id = :cid3 AND r.portal_user_id = :uid2
                              AND r.recruitment_candidate_id IS NOT NULL
                              AND r.recruitment_candidate_id > 0
                        )
                   )
                 ORDER BY c.id DESC LIMIT 200",
                [
                    'cid' => $cid,
                    'cid2' => $cid,
                    'uid' => $uid,
                    'cid3' => $cid,
                    'uid2' => $uid,
                ]
            );
            foreach ($rows as $row) {
                $ws = (string) ($row['workflow_status'] ?? 'draft');
                $bucket = $map[$ws] ?? 'shortlisted';
                if (!isset($stages[$bucket])) {
                    $bucket = 'shortlisted';
                }
                if (count($stages[$bucket]) >= $limitPerStage) {
                    continue;
                }
                $stages[$bucket][] = $row;
            }
        } catch (\Throwable $e) {
            // Fallback without deleted_at / recruitment_candidate_id column differences.
            try {
                $rows = $this->repo->fetchAll(
                    "SELECT DISTINCT c.id, c.full_name, c.candidate_no, c.workflow_status, c.email, c.job_title_target
                     FROM rateb_recruitment_candidates c
                     INNER JOIN rateb_website_portal_shortlists s
                        ON s.recruitment_candidate_id = c.id AND s.company_id = c.company_id
                     WHERE c.company_id = :cid AND s.portal_user_id = :uid
                     ORDER BY c.id DESC LIMIT 200",
                    ['cid' => $cid, 'uid' => $uid]
                );
                foreach ($rows as $row) {
                    $ws = (string) ($row['workflow_status'] ?? 'draft');
                    $bucket = $map[$ws] ?? 'shortlisted';
                    if (!isset($stages[$bucket])) {
                        $bucket = 'shortlisted';
                    }
                    if (count($stages[$bucket]) >= $limitPerStage) {
                        continue;
                    }
                    $stages[$bucket][] = $row;
                }
            } catch (\Throwable $e2) {
                error_log('PortalRecruitmentService pipeline: ' . $e2->getMessage());
            }
        }
        $total = 0;
        foreach ($stages as $list) {
            $total += count($list);
        }

        return ['total' => $total, 'stages' => $stages];
    }
}
