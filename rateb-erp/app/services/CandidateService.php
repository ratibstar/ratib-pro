<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentAssignment;
use Rateb\App\Models\RecruitmentCandidate;
use Rateb\App\Models\RecruitmentEducation;
use Rateb\App\Models\RecruitmentExperience;
use Rateb\App\Models\RecruitmentNote;

/**
 * Candidate domain service — create/update/soft-delete + profile children.
 * Controllers and future Offline Replay MUST call this service.
 */
final class CandidateService
{
    /**
     * @param array<string, mixed> $data
     * @return array{id: int, public_uuid: string, candidate_no: string}
     */
    public function create(array $data): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $name = trim((string) ($data['full_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('full_name_required');
        }
        $uuid = RecruitmentSupport::uuidV4();
        $no = trim((string) ($data['candidate_no'] ?? ''));
        if ($no === '') {
            $no = RecruitmentSupport::nextCandidateNo($companyId);
        }
        $payload = array_merge([
            'public_uuid' => $uuid,
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'agency_id' => isset($data['agency_id']) && (int) $data['agency_id'] > 0 ? (int) $data['agency_id'] : null,
            'candidate_no' => $no,
            'full_name' => $name,
            'full_name_ar' => trim((string) ($data['full_name_ar'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'nationality' => strtoupper(substr(trim((string) ($data['nationality'] ?? '')), 0, 2)) ?: null,
            'gender' => $this->normalizeGender((string) ($data['gender'] ?? 'unspecified')),
            'date_of_birth' => $this->nullableDate($data['date_of_birth'] ?? null),
            'national_id' => trim((string) ($data['national_id'] ?? '')) ?: null,
            'job_title_target' => trim((string) ($data['job_title_target'] ?? '')) ?: null,
            'source' => trim((string) ($data['source'] ?? '')) ?: null,
            'recruiter_user_id' => isset($data['recruiter_user_id']) && (int) $data['recruiter_user_id'] > 0
                ? (int) $data['recruiter_user_id'] : null,
            'workflow_status' => RecruitmentWorkflowService::STATUS_DRAFT,
            'status' => 'active',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true));

        $id = (new RecruitmentCandidate())->create($payload);
        (new RecruitmentTimelineService())->record($id, 'candidate', 'Candidate created', $name, 'candidate', $id);
        (new AuditService())->log('create', 'recruitment_candidate', $id, ['candidate_no' => $no]);

        return ['id' => $id, 'public_uuid' => $uuid, 'candidate_no' => $no];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($id, $companyId);
        $patch = RecruitmentSupport::actorFields(false);
        foreach ([
            'full_name', 'full_name_ar', 'email', 'phone', 'national_id', 'job_title_target', 'source', 'notes',
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (isset($data['full_name']) && trim((string) $data['full_name']) === '') {
            throw new \InvalidArgumentException('full_name_required');
        }
        if (array_key_exists('nationality', $data)) {
            $n = strtoupper(substr(trim((string) $data['nationality']), 0, 2));
            $patch['nationality'] = $n !== '' ? $n : null;
        }
        if (array_key_exists('gender', $data)) {
            $patch['gender'] = $this->normalizeGender((string) $data['gender']);
        }
        if (array_key_exists('date_of_birth', $data)) {
            $patch['date_of_birth'] = $this->nullableDate($data['date_of_birth']);
        }
        if (array_key_exists('branch_id', $data)) {
            $patch['branch_id'] = (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null;
        }
        if (array_key_exists('agency_id', $data)) {
            $patch['agency_id'] = (int) $data['agency_id'] > 0 ? (int) $data['agency_id'] : null;
        }
        if (array_key_exists('recruiter_user_id', $data)) {
            $patch['recruiter_user_id'] = (int) $data['recruiter_user_id'] > 0 ? (int) $data['recruiter_user_id'] : null;
        }
        (new RecruitmentCandidate())->update($id, $patch);
        (new RecruitmentTimelineService())->record($id, 'candidate', 'Candidate updated', null, 'candidate', $id);
        (new AuditService())->log('update', 'recruitment_candidate', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($id, $companyId);
        (new RecruitmentCandidate())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
            'workflow_status' => RecruitmentWorkflowService::STATUS_ARCHIVED,
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_candidate', $id);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return RecruitmentSupport::findCandidate($id, RecruitmentSupport::requireCompanyId());
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (full_name LIKE :q OR candidate_no LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $model = new RecruitmentCandidate();
        $totalRow = $model->queryOne("SELECT COUNT(*) AS c FROM rateb_recruitment_candidates WHERE {$where}", $params);
        $items = $model->query(
            "SELECT * FROM rateb_recruitment_candidates WHERE {$where}
             ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['items' => $items, 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @param array<string, mixed> $data */
    public function addExperience(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $employer = trim((string) ($data['employer_name'] ?? ''));
        if ($employer === '') {
            throw new \InvalidArgumentException('employer_name_required');
        }
        $id = (new RecruitmentExperience())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'employer_name' => $employer,
            'job_title' => trim((string) ($data['job_title'] ?? '')) ?: null,
            'country_code' => strtoupper(substr(trim((string) ($data['country_code'] ?? '')), 0, 2)) ?: null,
            'start_date' => $this->nullableDate($data['start_date'] ?? null),
            'end_date' => $this->nullableDate($data['end_date'] ?? null),
            'is_current' => !empty($data['is_current']) ? 1 : 0,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'created_by' => RecruitmentSupport::userId(),
        ]);
        (new AuditService())->log('create', 'recruitment_experience', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function addEducation(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $inst = trim((string) ($data['institution'] ?? ''));
        if ($inst === '') {
            throw new \InvalidArgumentException('institution_required');
        }
        $id = (new RecruitmentEducation())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'institution' => $inst,
            'degree' => trim((string) ($data['degree'] ?? '')) ?: null,
            'field_of_study' => trim((string) ($data['field_of_study'] ?? '')) ?: null,
            'country_code' => strtoupper(substr(trim((string) ($data['country_code'] ?? '')), 0, 2)) ?: null,
            'start_year' => isset($data['start_year']) && (int) $data['start_year'] > 0 ? (int) $data['start_year'] : null,
            'end_year' => isset($data['end_year']) && (int) $data['end_year'] > 0 ? (int) $data['end_year'] : null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => RecruitmentSupport::userId(),
        ]);
        (new AuditService())->log('create', 'recruitment_education', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    public function addNote(int $candidateId, string $body, string $visibility = 'internal'): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('note_body_required');
        }
        $id = (new RecruitmentNote())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'body' => $body,
            'visibility' => $visibility === 'shared' ? 'shared' : 'internal',
            'created_by' => RecruitmentSupport::userId(),
        ]);
        (new AuditService())->log('create', 'recruitment_note', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    private function normalizeGender(string $g): string
    {
        $g = strtolower(trim($g));

        return in_array($g, ['male', 'female', 'other', 'unspecified'], true) ? $g : 'unspecified';
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }

        return $s;
    }
}
