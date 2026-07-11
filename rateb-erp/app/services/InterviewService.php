<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentInterview;
use Rateb\App\Models\RecruitmentPassport;

final class InterviewService
{
    /** @param array<string, mixed> $data */
    public function create(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $id = (new RecruitmentInterview())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'candidate_id' => $candidateId,
            'interviewer_user_id' => isset($data['interviewer_user_id']) && (int) $data['interviewer_user_id'] > 0
                ? (int) $data['interviewer_user_id'] : null,
            'scheduled_at' => $this->nullableDateTime($data['scheduled_at'] ?? null),
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'mode' => $this->normalizeMode((string) ($data['mode'] ?? 'in_person')),
            'result' => $this->normalizeResult((string) ($data['result'] ?? 'pending')),
            'score' => isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'interview', 'Interview scheduled', null, 'interview', $id);
        (new AuditService())->log('create', 'recruitment_interview', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        $patch = RecruitmentSupport::actorFields(false);
        foreach (['location', 'notes'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (array_key_exists('scheduled_at', $data)) {
            $patch['scheduled_at'] = $this->nullableDateTime($data['scheduled_at']);
        }
        if (array_key_exists('mode', $data)) {
            $patch['mode'] = $this->normalizeMode((string) $data['mode']);
        }
        if (array_key_exists('result', $data)) {
            $patch['result'] = $this->normalizeResult((string) $data['result']);
        }
        if (array_key_exists('score', $data)) {
            $patch['score'] = $data['score'] !== '' && $data['score'] !== null ? (float) $data['score'] : null;
        }
        if (array_key_exists('interviewer_user_id', $data)) {
            $patch['interviewer_user_id'] = (int) $data['interviewer_user_id'] > 0 ? (int) $data['interviewer_user_id'] : null;
        }
        (new RecruitmentInterview())->update($id, $patch);
        (new RecruitmentTimelineService())->record((int) $row['candidate_id'], 'interview', 'Interview updated', null, 'interview', $id);
        (new AuditService())->log('update', 'recruitment_interview', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        (new RecruitmentInterview())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'result' => 'cancelled',
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_interview', $id, ['candidate_id' => $row['candidate_id']]);
    }

    /** @return array<string, mixed> */
    private function requireOwned(int $id, int $companyId): array
    {
        $row = (new RecruitmentInterview())->queryOne(
            'SELECT * FROM rateb_recruitment_interviews WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('interview_not_found');
        }

        return $row;
    }

    private function normalizeMode(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['in_person', 'phone', 'video', 'other'], true) ? $s : 'in_person';
    }

    private function normalizeResult(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['pending', 'passed', 'failed', 'no_show', 'cancelled'], true) ? $s : 'pending';
    }

    private function nullableDateTime(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $s)) {
            return str_replace('T', ' ', substr($s, 0, 19));
        }

        return null;
    }
}

/** Passport records under candidates. */
final class PassportService
{
    /** @param array<string, mixed> $data */
    public function create(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $no = trim((string) ($data['passport_no'] ?? ''));
        if ($no === '') {
            throw new \InvalidArgumentException('passport_no_required');
        }
        $id = (new RecruitmentPassport())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'passport_no' => $no,
            'nationality' => strtoupper(substr(trim((string) ($data['nationality'] ?? '')), 0, 2)) ?: null,
            'issue_date' => $this->nullableDate($data['issue_date'] ?? null),
            'expiry_date' => $this->nullableDate($data['expiry_date'] ?? null),
            'issue_place' => trim((string) ($data['issue_place'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? 'pending'), ['valid', 'expired', 'pending', 'cancelled'], true)
                ? $data['status'] : 'pending',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'passport', 'Passport added', $no, 'passport', $id);
        (new AuditService())->log('create', 'recruitment_passport', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
