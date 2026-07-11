<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentMedical;

final class MedicalService
{
    /** @param array<string, mixed> $data */
    public function create(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $id = (new RecruitmentMedical())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'clinic_name' => trim((string) ($data['clinic_name'] ?? '')) ?: null,
            'exam_date' => $this->nullableDate($data['exam_date'] ?? null),
            'result' => $this->normalizeResult((string) ($data['result'] ?? 'pending')),
            'expiry_date' => $this->nullableDate($data['expiry_date'] ?? null),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? 'scheduled')),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'medical', 'Medical record created', null, 'medical', $id);
        (new AuditService())->log('create', 'recruitment_medical', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        $patch = RecruitmentSupport::actorFields(false);
        if (array_key_exists('clinic_name', $data)) {
            $v = trim((string) $data['clinic_name']);
            $patch['clinic_name'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $patch['notes'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('exam_date', $data)) {
            $patch['exam_date'] = $this->nullableDate($data['exam_date']);
        }
        if (array_key_exists('expiry_date', $data)) {
            $patch['expiry_date'] = $this->nullableDate($data['expiry_date']);
        }
        if (array_key_exists('result', $data)) {
            $patch['result'] = $this->normalizeResult((string) $data['result']);
        }
        if (array_key_exists('status', $data)) {
            $patch['status'] = $this->normalizeStatus((string) $data['status']);
        }
        (new RecruitmentMedical())->update($id, $patch);
        (new RecruitmentTimelineService())->record((int) $row['candidate_id'], 'medical', 'Medical updated', null, 'medical', $id);
        (new AuditService())->log('update', 'recruitment_medical', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        (new RecruitmentMedical())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_medical', $id, ['candidate_id' => $row['candidate_id']]);
    }

    /** @return array<string, mixed> */
    private function requireOwned(int $id, int $companyId): array
    {
        $row = (new RecruitmentMedical())->queryOne(
            'SELECT * FROM rateb_recruitment_medicals WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('medical_not_found');
        }

        return $row;
    }

    private function normalizeResult(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['pending', 'fit', 'unfit', 'conditional'], true) ? $s : 'pending';
    }

    private function normalizeStatus(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['scheduled', 'completed', 'cancelled'], true) ? $s : 'scheduled';
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
