<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentAgency;

final class RecruitmentAgencyService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('agency_name_required');
        }
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = RecruitmentSupport::nextAgencyCode($companyId);
        }
        $id = (new RecruitmentAgency())->create(array_merge([
            'public_uuid' => RecruitmentSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'code' => $code,
            'name' => $name,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'country_code' => strtoupper(substr(trim((string) ($data['country_code'] ?? '')), 0, 2)) ?: null,
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], RecruitmentSupport::actorFields(true)));
        (new AuditService())->log('create', 'recruitment_agency', $id, ['code' => $code]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = (new RecruitmentAgency())->queryOne(
            'SELECT id FROM rateb_recruitment_agencies WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('agency_not_found');
        }
        $patch = RecruitmentSupport::actorFields(false);
        foreach (['name', 'contact_name', 'email', 'phone', 'notes', 'code'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (isset($patch['name']) && $patch['name'] === null) {
            throw new \InvalidArgumentException('agency_name_required');
        }
        if (array_key_exists('status', $data) && in_array($data['status'], ['active', 'inactive'], true)) {
            $patch['status'] = $data['status'];
        }
        if (array_key_exists('country_code', $data)) {
            $c = strtoupper(substr(trim((string) $data['country_code']), 0, 2));
            $patch['country_code'] = $c !== '' ? $c : null;
        }
        (new RecruitmentAgency())->update($id, $patch);
        (new AuditService())->log('update', 'recruitment_agency', $id, $patch);
    }

    public function softDelete(int $id): void
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $row = (new RecruitmentAgency())->queryOne(
            'SELECT id FROM rateb_recruitment_agencies WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$row) {
            throw new \RuntimeException('agency_not_found');
        }
        (new RecruitmentAgency())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'inactive',
        ], RecruitmentSupport::actorFields(false)));
        (new AuditService())->log('delete', 'recruitment_agency', $id);
    }

    /** @return list<array<string, mixed>> */
    public function listActive(): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();

        return (new RecruitmentAgency())->query(
            'SELECT id, code, name, status FROM rateb_recruitment_agencies
             WHERE company_id = :cid AND deleted_at IS NULL AND status = \'active\'
             ORDER BY name ASC',
            ['cid' => $companyId]
        );
    }
}
