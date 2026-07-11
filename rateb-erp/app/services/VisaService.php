<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentVisa;

final class VisaService
{
    /** @param array<string, mixed> $data */
    public function create(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $id = (new RecruitmentVisa())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'visa_no' => trim((string) ($data['visa_no'] ?? '')) ?: null,
            'visa_type' => trim((string) ($data['visa_type'] ?? '')) ?: null,
            'destination_country' => strtoupper(substr(trim((string) ($data['destination_country'] ?? '')), 0, 2)) ?: null,
            'issue_date' => $this->nullableDate($data['issue_date'] ?? null),
            'expiry_date' => $this->nullableDate($data['expiry_date'] ?? null),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? 'draft')),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'visa', 'Visa record created', null, 'visa', $id);
        (new AuditService())->log('create', 'recruitment_visa', $id, ['candidate_id' => $candidateId]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        $patch = RecruitmentSupport::actorFields(false);
        foreach (['visa_no', 'visa_type', 'notes'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (array_key_exists('destination_country', $data)) {
            $c = strtoupper(substr(trim((string) $data['destination_country']), 0, 2));
            $patch['destination_country'] = $c !== '' ? $c : null;
        }
        if (array_key_exists('issue_date', $data)) {
            $patch['issue_date'] = $this->nullableDate($data['issue_date']);
        }
        if (array_key_exists('expiry_date', $data)) {
            $patch['expiry_date'] = $this->nullableDate($data['expiry_date']);
        }
        if (array_key_exists('status', $data)) {
            $patch['status'] = $this->normalizeStatus((string) $data['status']);
        }
        (new RecruitmentVisa())->update($id, $patch);
        (new RecruitmentTimelineService())->record((int) $row['candidate_id'], 'visa', 'Visa updated', null, 'visa', $id);
        (new AuditService())->log('update', 'recruitment_visa', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        (new RecruitmentVisa())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_visa', $id, ['candidate_id' => $row['candidate_id']]);
    }

    /** @return array<string, mixed> */
    private function requireOwned(int $id, int $companyId): array
    {
        $row = (new RecruitmentVisa())->queryOne(
            'SELECT * FROM rateb_recruitment_visas WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('visa_not_found');
        }

        return $row;
    }

    private function normalizeStatus(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['draft', 'applied', 'issued', 'rejected', 'expired', 'cancelled'], true) ? $s : 'draft';
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
