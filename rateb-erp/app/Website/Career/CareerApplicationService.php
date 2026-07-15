<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Core\TenantContext;
use Rateb\App\Services\CandidateService;
use Rateb\App\Services\CmsService;
use Rateb\App\Services\RecruitmentWorkflowService;
use Rateb\App\Website\TenantMediaService;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-06 — Submit applications via CandidateService (ATS source of truth).
 */
final class CareerApplicationService
{
    private TenantWebsiteRepository $repo;
    private CareerJobService $jobs;
    private CareerPortalAuthService $auth;

    public function __construct(
        ?TenantWebsiteRepository $repo = null,
        ?CareerJobService $jobs = null,
        ?CareerPortalAuthService $auth = null
    ) {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->jobs = $jobs ?? new CareerJobService($this->repo);
        $this->auth = $auth ?? new CareerPortalAuthService($this->repo);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $resumeFile $_FILES entry
     * @return array{ok: bool, application_id?: int, candidate_id?: int, error?: string}
     */
    public function submit(string $careerSlug, array $data, ?array $resumeFile = null): array
    {
        $job = $this->jobs->findBySlug($careerSlug);
        if ($job === null || (string) ($job['status'] ?? '') !== 'open') {
            return ['ok' => false, 'error' => 'job_not_found'];
        }
        $this->repo->assertRowCompany($job, 'career');

        $portalUser = $this->auth->currentUser();
        $fullName = trim((string) ($data['full_name'] ?? ($portalUser['full_name'] ?? '')));
        $email = trim((string) ($data['email'] ?? ($portalUser['email'] ?? '')));
        if ($fullName === '') {
            return ['ok' => false, 'error' => 'full_name_required'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }

        $careerId = (int) $job['id'];
        $portalUserId = $portalUser !== null ? (int) $portalUser['id'] : null;
        if ($portalUserId !== null && $this->hasActiveApplication($careerId, $portalUserId)) {
            return ['ok' => false, 'error' => 'already_applied'];
        }

        TenantContext::setCompanyId($this->repo->companyId());
        try {
            $candidate = (new CandidateService())->create([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => trim((string) ($data['phone'] ?? ($portalUser['phone'] ?? ''))) ?: null,
                'nationality' => $data['nationality'] ?? ($portalUser['nationality'] ?? null),
                'job_title_target' => CmsService::pickLocale($job, 'title'),
                'source' => 'website_career',
                'recruiter_user_id' => (int) ($job['recruiter_user_id'] ?? 0) > 0 ? (int) $job['recruiter_user_id'] : null,
                'notes' => $this->buildCandidateNotes($data, $job),
            ]);
            $candidateId = (int) $candidate['id'];

            try {
                (new RecruitmentWorkflowService())->transition(
                    $candidateId,
                    RecruitmentWorkflowService::STATUS_REGISTERED,
                    'Website career application'
                );
            } catch (\Throwable $e) {
                error_log('CareerApplication workflow: ' . $e->getMessage());
            }

            $this->attachProfileChildren($candidateId, $data);

            $resume = $this->storeResume($resumeFile, $portalUser);
            $meta = $this->buildMeta($data);

            $this->repo->execute(
                'INSERT INTO rateb_website_career_applications
                 (company_id, career_id, portal_user_id, recruitment_candidate_id, cover_letter,
                  expected_salary, availability_date, status, meta_json, resume_media_id, resume_path)
                 VALUES (:cid, :jid, :pid, :cand, :cover, :salary, :avail, :st, :meta, :mid, :path)',
                [
                    'cid' => $this->repo->companyId(),
                    'jid' => $careerId,
                    'pid' => $portalUserId,
                    'cand' => $candidateId,
                    'cover' => trim((string) ($data['cover_letter'] ?? '')) ?: null,
                    'salary' => $this->decimalOrNull($data['expected_salary'] ?? null),
                    'avail' => $this->dateOrNull($data['availability_date'] ?? null),
                    'st' => 'submitted',
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'mid' => $resume['media_id'] ?? null,
                    'path' => $resume['path'] ?? null,
                ]
            );
            $appId = (int) $this->repo->lastInsertId();

            $this->repo->execute(
                'UPDATE rateb_cms_careers SET application_count = application_count + 1 WHERE id = :id',
                ['id' => $careerId]
            );
            CareerJobService::clearCache();

            (new CareerNotificationService($this->repo))->notifyApplication($appId, $job, $candidate, $data);

            return ['ok' => true, 'application_id' => $appId, 'candidate_id' => $candidateId];
        } catch (\Throwable $e) {
            error_log('CareerApplicationService: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'application_failed'];
        }
    }

    public function withdraw(int $applicationId, int $portalUserId): bool
    {
        [$where, $params] = $this->repo->companyWhere('a');
        $params['id'] = $applicationId;
        $params['pid'] = $portalUserId;
        $row = $this->repo->fetchOne(
            "SELECT a.* FROM rateb_website_career_applications a
             WHERE {$where} AND a.id = :id AND a.portal_user_id = :pid AND a.status = 'submitted' LIMIT 1",
            $params
        );
        if ($row === null) {
            return false;
        }
        $this->repo->execute(
            "UPDATE rateb_website_career_applications SET status = 'withdrawn' WHERE id = :id",
            ['id' => $applicationId]
        );

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function applicationsForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere('a');
        $params['pid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT a.*, c.slug, c.title_en, c.title_ar, c.department_en, c.department_ar
             FROM rateb_website_career_applications a
             INNER JOIN rateb_cms_careers c ON c.id = a.career_id
             WHERE {$where} AND a.portal_user_id = :pid
             ORDER BY a.created_at DESC",
            $params
        );
    }

    public function saveJob(int $portalUserId, int $careerId): bool
    {
        $job = $this->repo->fetchOne(
            'SELECT id, company_id, status FROM rateb_cms_careers WHERE id = :id LIMIT 1',
            ['id' => $careerId]
        );
        if ($job === null || (string) ($job['status'] ?? '') !== 'open') {
            return false;
        }
        $this->repo->assertRowCompany($job, 'career');
        try {
            $this->repo->execute(
                'INSERT INTO rateb_website_career_saved_jobs (company_id, portal_user_id, career_id)
                 VALUES (:cid, :pid, :jid)',
                ['cid' => $this->repo->companyId(), 'pid' => $portalUserId, 'jid' => $careerId]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function unsaveJob(int $portalUserId, int $careerId): void
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['pid'] = $portalUserId;
        $params['jid'] = $careerId;
        $this->repo->execute(
            "DELETE FROM rateb_website_career_saved_jobs WHERE {$where} AND portal_user_id = :pid AND career_id = :jid",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function savedJobsForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere('s');
        $params['pid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT s.id AS saved_id, c.*
             FROM rateb_website_career_saved_jobs s
             INNER JOIN rateb_cms_careers c ON c.id = s.career_id
             WHERE {$where} AND s.portal_user_id = :pid AND c.status = 'open'
             ORDER BY s.created_at DESC",
            $params
        );
    }

    private function hasActiveApplication(int $careerId, int $portalUserId): bool
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['jid'] = $careerId;
        $params['pid'] = $portalUserId;
        $row = $this->repo->fetchOne(
            "SELECT id FROM rateb_website_career_applications
             WHERE {$where} AND career_id = :jid AND portal_user_id = :pid
             AND status IN ('submitted','reviewing') LIMIT 1",
            $params
        );

        return $row !== null;
    }

    /** @param array<string, mixed> $data */
    private function buildCandidateNotes(array $data, array $job): ?string
    {
        $parts = ['Career portal application for: ' . CmsService::pickLocale($job, 'title')];
        if (trim((string) ($data['cover_letter'] ?? '')) !== '') {
            $parts[] = 'Cover letter: ' . trim((string) $data['cover_letter']);
        }
        if (trim((string) ($data['linkedin'] ?? '')) !== '') {
            $parts[] = 'LinkedIn: ' . trim((string) $data['linkedin']);
        }
        if (trim((string) ($data['portfolio'] ?? '')) !== '') {
            $parts[] = 'Portfolio: ' . trim((string) $data['portfolio']);
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function buildMeta(array $data): array
    {
        return [
            'education' => trim((string) ($data['education'] ?? '')),
            'experience' => trim((string) ($data['experience'] ?? '')),
            'skills' => trim((string) ($data['skills'] ?? '')),
            'languages' => trim((string) ($data['languages'] ?? '')),
            'linkedin' => trim((string) ($data['linkedin'] ?? '')),
            'portfolio' => trim((string) ($data['portfolio'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'country' => trim((string) ($data['country'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $data */
    private function attachProfileChildren(int $candidateId, array $data): void
    {
        $svc = new CandidateService();
        $employer = trim((string) ($data['experience_employer'] ?? ''));
        if ($employer !== '') {
            try {
                $svc->addExperience($candidateId, [
                    'employer_name' => $employer,
                    'job_title' => $data['experience_title'] ?? null,
                    'description' => $data['experience'] ?? null,
                ]);
            } catch (\Throwable $e) {
                error_log('Career experience attach: ' . $e->getMessage());
            }
        }
        $inst = trim((string) ($data['education_institution'] ?? ($data['education'] ?? '')));
        if ($inst !== '') {
            try {
                $svc->addEducation($candidateId, [
                    'institution' => $inst,
                    'degree' => $data['education_degree'] ?? null,
                    'field_of_study' => $data['education_field'] ?? null,
                ]);
            } catch (\Throwable $e) {
                error_log('Career education attach: ' . $e->getMessage());
            }
        }
        $cover = trim((string) ($data['cover_letter'] ?? ''));
        if ($cover !== '') {
            try {
                $svc->addNote($candidateId, $cover, 'shared');
            } catch (\Throwable $e) {
                error_log('Career note attach: ' . $e->getMessage());
            }
        }
    }

    /** @param array<string, mixed>|null $file @return array{media_id?:int,path?:string} */
    private function storeResume(?array $file, ?array $portalUser): array
    {
        if ($file !== null && !empty($file['tmp_name'])) {
            $upload = (new TenantMediaService($this->repo))->upload($file);
            if (($upload['ok'] ?? false) === true) {
                return [
                    'media_id' => (int) ($upload['id'] ?? 0) ?: null,
                    'path' => (string) ($upload['path'] ?? ''),
                ];
            }
        }
        if ($portalUser !== null && trim((string) ($portalUser['resume_path'] ?? '')) !== '') {
            return [
                'media_id' => (int) ($portalUser['resume_media_id'] ?? 0) ?: null,
                'path' => (string) $portalUser['resume_path'],
            ];
        }

        return [];
    }

    private function decimalOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (float) $v;

        return $n > 0 ? $n : null;
    }

    private function dateOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
