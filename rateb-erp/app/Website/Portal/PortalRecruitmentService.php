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

    /** @return array{items: list<array<string,mixed>>, total: int} */
    public function searchCandidates(string $search = '', int $limit = 25, int $offset = 0): array
    {
        TenantContext::setCompanyId($this->repo->companyId());
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
}
