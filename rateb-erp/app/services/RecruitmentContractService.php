<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentContract;

/** Recruitment employment contracts (distinct from supplier rateb_contracts). */
final class RecruitmentContractService
{
    /** @param array<string, mixed> $data */
    public function create(int $candidateId, array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('contract_title_required');
        }
        $no = trim((string) ($data['contract_no'] ?? ''));
        if ($no === '') {
            $no = RecruitmentSupport::nextContractNo($companyId);
        }
        $id = (new RecruitmentContract())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'contract_no' => $no,
            'title' => $title,
            'start_date' => $this->nullableDate($data['start_date'] ?? null),
            'end_date' => $this->nullableDate($data['end_date'] ?? null),
            'salary' => (float) ($data['salary'] ?? 0),
            'currency' => strtoupper(substr(trim((string) ($data['currency'] ?? 'SAR')), 0, 3)) ?: 'SAR',
            'status' => $this->normalizeStatus((string) ($data['status'] ?? 'draft')),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new RecruitmentTimelineService())->record($candidateId, 'contract', 'Contract created', $title, 'contract', $id);
        (new AuditService())->log('create', 'recruitment_contract', $id, ['candidate_id' => $candidateId, 'contract_no' => $no]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        $patch = RecruitmentSupport::actorFields(false);
        if (array_key_exists('title', $data)) {
            $t = trim((string) $data['title']);
            if ($t === '') {
                throw new \InvalidArgumentException('contract_title_required');
            }
            $patch['title'] = $t;
        }
        foreach (['notes'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (array_key_exists('start_date', $data)) {
            $patch['start_date'] = $this->nullableDate($data['start_date']);
        }
        if (array_key_exists('end_date', $data)) {
            $patch['end_date'] = $this->nullableDate($data['end_date']);
        }
        if (array_key_exists('salary', $data)) {
            $patch['salary'] = (float) $data['salary'];
        }
        if (array_key_exists('currency', $data)) {
            $patch['currency'] = strtoupper(substr(trim((string) $data['currency']), 0, 3)) ?: 'SAR';
        }
        if (array_key_exists('status', $data)) {
            $patch['status'] = $this->normalizeStatus((string) $data['status']);
        }
        (new RecruitmentContract())->update($id, $patch);
        (new RecruitmentTimelineService())->record((int) $row['candidate_id'], 'contract', 'Contract updated', null, 'contract', $id);
        (new AuditService())->log('update', 'recruitment_contract', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = $this->requireOwned($id, $companyId);
        (new RecruitmentContract())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'cancelled',
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_contract', $id, ['candidate_id' => $row['candidate_id']]);
    }

    /** @return array<string, mixed> */
    private function requireOwned(int $id, int $companyId): array
    {
        $row = (new RecruitmentContract())->queryOne(
            'SELECT * FROM rateb_recruitment_contracts WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('recruitment_contract_not_found');
        }

        return $row;
    }

    private function normalizeStatus(string $s): string
    {
        $s = strtolower(trim($s));

        return in_array($s, ['draft', 'pending', 'signed', 'cancelled', 'expired'], true) ? $s : 'draft';
    }

    private function nullableDate(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
