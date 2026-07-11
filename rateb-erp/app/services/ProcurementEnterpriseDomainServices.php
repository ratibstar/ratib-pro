<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EprocApprovalLink;
use Rateb\App\Models\EprocAssignment;
use Rateb\App\Models\EprocAudit;
use Rateb\App\Models\EprocBidComparison;
use Rateb\App\Models\EprocCalendarEvent;
use Rateb\App\Models\EprocCollaboration;
use Rateb\App\Models\EprocComment;
use Rateb\App\Models\EprocContract;
use Rateb\App\Models\EprocDocumentMeta;
use Rateb\App\Models\EprocEntityTag;
use Rateb\App\Models\EprocPortalInvite;
use Rateb\App\Models\EprocRfqTemplate;
use Rateb\App\Models\EprocSpendSnapshot;
use Rateb\App\Models\EprocSupplierBlacklist;
use Rateb\App\Models\EprocSupplierCategory;
use Rateb\App\Models\EprocSupplierCertification;
use Rateb\App\Models\EprocSupplierContact;
use Rateb\App\Models\EprocSupplierPerformance;
use Rateb\App\Models\EprocSupplierProfile;
use Rateb\App\Models\EprocSupplierQualification;
use Rateb\App\Models\EprocSupplierRisk;
use Rateb\App\Models\EprocSupplierScorecard;
use Rateb\App\Models\EprocSupplierSla;
use Rateb\App\Models\EprocTag;
use Rateb\App\Models\EprocTender;
use Rateb\App\Models\EprocTenderBid;

/**
 * Phase 21A — Enterprise Procurement Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_eproc_* — distinct from legacy ProcurementService / Offline.
 */

final class ProcurementEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $out = [];
        $maps = [
            'supplier_profile' => [
                'table' => 'rateb_eproc_supplier_profiles',
                'model' => new EprocSupplierProfile(),
            ],
            'tender' => [
                'table' => 'rateb_eproc_tenders',
                'model' => new EprocTender(),
            ],
            'contract' => [
                'table' => 'rateb_eproc_contracts',
                'model' => new EprocContract(),
            ],
            'qualification' => [
                'table' => 'rateb_eproc_supplier_qualification',
                'model' => new EprocSupplierQualification(),
            ],
            'collaboration' => [
                'table' => 'rateb_eproc_collaboration',
                'model' => new EprocCollaboration(),
            ],
        ];
        foreach ($maps as $entityType => $cfg) {
            $counts = [];
            foreach (ProcurementWorkflowService::statuses($entityType) as $st) {
                $row = $cfg['model']->queryOne(
                    'SELECT COUNT(*) AS c FROM ' . $cfg['table']
                    . ' WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                    ['cid' => $companyId, 'st' => $st]
                );
                $counts[$st] = (int) ($row['c'] ?? 0);
            }
            $out[$entityType] = $counts;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function spendSummary(): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $summary = [
            'company_id' => $companyId,
            'analytics' => null,
            'snapshots_total' => 0.0,
            'snapshots_by_period' => [],
        ];
        if (class_exists(ErpAnalyticsService::class)) {
            $summary['analytics'] = (new ErpAnalyticsService())->procurementDashboard($companyId);
        }
        $rows = (new EprocSpendSnapshot())->query(
            'SELECT period_label, COALESCE(SUM(amount),0) AS total
             FROM rateb_eproc_spend_snapshots
             WHERE company_id = :cid AND deleted_at IS NULL AND status = \'active\'
             GROUP BY period_label ORDER BY period_label DESC LIMIT 24',
            ['cid' => $companyId]
        );
        $summary['snapshots_by_period'] = is_array($rows) ? $rows : [];
        $totalRow = (new EprocSpendSnapshot())->queryOne(
            'SELECT COALESCE(SUM(amount),0) AS t FROM rateb_eproc_spend_snapshots
             WHERE company_id = :cid AND deleted_at IS NULL AND status = \'active\'',
            ['cid' => $companyId]
        );
        $summary['snapshots_total'] = (float) ($totalRow['t'] ?? 0);

        return $summary;
    }
}

final class SupplierCategoryService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocSupplierCategory())->query(
            'SELECT * FROM rateb_eproc_supplier_categories
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_supplier_categories', 'SCAT', $companyId);
        }
        $id = (new EprocSupplierCategory())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ProcurementEnterpriseSupport::nullIfEmpty($input['name_ar'] ?? null),
            'parent_id' => ProcurementEnterpriseSupport::intOrNull($input['parent_id'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierCategory())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_categories WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('category_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['name', 'name_ar', 'code'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name' || $f === 'code'
                    ? substr(trim((string) $input[$f]), 0, $f === 'code' ? 40 : 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('parent_id', $input)) {
            $patch['parent_id'] = ProcurementEnterpriseSupport::intOrNull($input['parent_id']);
        }
        if (array_key_exists('status', $input) && in_array($input['status'], ['active', 'inactive', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierCategory())->update($id, $patch);
    }
}

final class SupplierProfileService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR email LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new EprocSupplierProfile())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_supplier_profiles WHERE ' . $where,
            $params
        );
        $items = (new EprocSupplierProfile())->query(
            'SELECT * FROM rateb_eproc_supplier_profiles WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ProcurementEnterpriseSupport::findProfile($id, ProcurementEnterpriseSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_supplier_profiles', 'SUP', $companyId);
        }
        $risk = (string) ($input['risk_level'] ?? 'medium');
        if (!in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            $risk = 'medium';
        }
        $id = (new EprocSupplierProfile())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::intOrNull($input['branch_id'] ?? null)
                ?? ProcurementEnterpriseSupport::branchId(),
            'legacy_supplier_id' => ProcurementEnterpriseSupport::intOrNull($input['legacy_supplier_id'] ?? null),
            'category_id' => ProcurementEnterpriseSupport::intOrNull($input['category_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ProcurementEnterpriseSupport::nullIfEmpty($input['name_ar'] ?? null),
            'legal_name' => ProcurementEnterpriseSupport::nullIfEmpty($input['legal_name'] ?? null),
            'tax_number' => ProcurementEnterpriseSupport::nullIfEmpty($input['tax_number'] ?? null),
            'country_code' => ProcurementEnterpriseSupport::nullIfEmpty($input['country_code'] ?? null),
            'city' => ProcurementEnterpriseSupport::nullIfEmpty($input['city'] ?? null),
            'email' => ProcurementEnterpriseSupport::nullIfEmpty($input['email'] ?? null),
            'phone' => ProcurementEnterpriseSupport::nullIfEmpty($input['phone'] ?? null),
            'website' => ProcurementEnterpriseSupport::nullIfEmpty($input['website'] ?? null),
            'risk_level' => $risk,
            'qualification_status' => ProcurementEnterpriseSupport::nullIfEmpty($input['qualification_status'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'supplier_created',
            'Supplier profile created: ' . $name,
            'supplier_profile',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = ProcurementEnterpriseSupport::assertProfile($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['name', 'name_ar', 'legal_name', 'tax_number', 'country_code', 'city', 'email', 'phone',
            'website', 'qualification_status', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['category_id', 'legacy_supplier_id', 'branch_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('risk_level', $input)
            && in_array($input['risk_level'], ['low', 'medium', 'high', 'critical'], true)) {
            $patch['risk_level'] = $input['risk_level'];
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierProfile())->update($id, $patch);
        (new ProcurementTimelineService())->record(
            'supplier_updated',
            'Supplier profile updated',
            'supplier_profile',
            $id
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($id, $companyId);
        (new EprocSupplierProfile())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], ProcurementEnterpriseSupport::actorFields(false)));
    }
}

final class SupplierContactService
{
    /** @return list<array<string, mixed>> */
    public function listForProfile(int $profileId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $rows = (new EprocSupplierContact())->query(
            'SELECT * FROM rateb_eproc_supplier_contacts
             WHERE company_id = :cid AND profile_id = :pid AND deleted_at IS NULL
             ORDER BY is_primary DESC, id ASC',
            ['cid' => $companyId, 'pid' => $profileId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $id = (new EprocSupplierContact())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'name' => substr($name, 0, 160),
            'title' => ProcurementEnterpriseSupport::nullIfEmpty($input['title'] ?? null),
            'email' => ProcurementEnterpriseSupport::nullIfEmpty($input['email'] ?? null),
            'phone' => ProcurementEnterpriseSupport::nullIfEmpty($input['phone'] ?? null),
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierContact())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_contacts WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('contact_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['name', 'title', 'email', 'phone'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 160)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('is_primary', $input)) {
            $patch['is_primary'] = !empty($input['is_primary']) ? 1 : 0;
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierContact())->update($id, $patch);
    }
}

final class SupplierCertificationService
{
    /** @return list<array<string, mixed>> */
    public function listForProfile(int $profileId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $rows = (new EprocSupplierCertification())->query(
            'SELECT * FROM rateb_eproc_supplier_certifications
             WHERE company_id = :cid AND profile_id = :pid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'pid' => $profileId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $certType = trim((string) ($input['cert_type'] ?? ''));
        if ($certType === '') {
            throw new \InvalidArgumentException('cert_type_required');
        }
        $status = (string) ($input['status'] ?? 'pending');
        if (!in_array($status, ['valid', 'expired', 'revoked', 'pending'], true)) {
            $status = 'pending';
        }
        $id = (new EprocSupplierCertification())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'cert_type' => substr($certType, 0, 80),
            'cert_number' => ProcurementEnterpriseSupport::nullIfEmpty($input['cert_number'] ?? null),
            'issued_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['issued_at'] ?? null),
            'expires_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['expires_at'] ?? null),
            'issuer' => ProcurementEnterpriseSupport::nullIfEmpty($input['issuer'] ?? null),
            'status' => $status,
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierCertification())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_certifications WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('certification_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['cert_type', 'cert_number', 'issued_at', 'expires_at', 'issuer'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'cert_type'
                    ? substr(trim((string) $input[$f]), 0, 80)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['valid', 'expired', 'revoked', 'pending'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierCertification())->update($id, $patch);
    }
}

final class SupplierSlaService
{
    /** @return list<array<string, mixed>> */
    public function listForProfile(int $profileId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $rows = (new EprocSupplierSla())->query(
            'SELECT * FROM rateb_eproc_supplier_sla
             WHERE company_id = :cid AND profile_id = :pid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'pid' => $profileId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_supplier_sla', 'SLA', $companyId);
        }
        $id = (new EprocSupplierSla())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'metric_key' => substr(trim((string) ($input['metric_key'] ?? 'on_time_delivery')), 0, 60) ?: 'on_time_delivery',
            'target_value' => ProcurementEnterpriseSupport::floatOrNull($input['target_value'] ?? null) ?? 0.0,
            'unit' => ProcurementEnterpriseSupport::nullIfEmpty($input['unit'] ?? null),
            'period_days' => max(1, (int) ($input['period_days'] ?? 30)),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierSla())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_sla WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('sla_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['name', 'metric_key', 'unit'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('target_value', $input)) {
            $patch['target_value'] = ProcurementEnterpriseSupport::floatOrNull($input['target_value']) ?? 0.0;
        }
        if (array_key_exists('period_days', $input)) {
            $patch['period_days'] = max(1, (int) $input['period_days']);
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierSla())->update($id, $patch);
    }
}

final class SupplierScorecardService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $profileId = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($profileId !== null && $profileId > 0) {
            $where .= ' AND profile_id = :pid';
            $params['pid'] = $profileId;
        }
        $totalRow = (new EprocSupplierScorecard())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_supplier_scorecards WHERE ' . $where,
            $params
        );
        $items = (new EprocSupplierScorecard())->query(
            'SELECT * FROM rateb_eproc_supplier_scorecards WHERE ' . $where
            . ' ORDER BY id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $period = trim((string) ($input['period_label'] ?? ''));
        if ($period === '') {
            throw new \InvalidArgumentException('period_label_required');
        }
        $quality = (float) ($input['quality_score'] ?? 0);
        $delivery = (float) ($input['delivery_score'] ?? 0);
        $price = (float) ($input['price_score'] ?? 0);
        $service = (float) ($input['service_score'] ?? 0);
        $overall = array_key_exists('overall_score', $input)
            ? (float) $input['overall_score']
            : round(($quality + $delivery + $price + $service) / 4, 2);
        $id = (new EprocSupplierScorecard())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'period_label' => substr($period, 0, 40),
            'quality_score' => $quality,
            'delivery_score' => $delivery,
            'price_score' => $price,
            'service_score' => $service,
            'overall_score' => $overall,
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => 'draft',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierScorecard())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_scorecards WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('scorecard_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['period_label', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'period_label'
                    ? substr(trim((string) $input[$f]), 0, 40)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['quality_score', 'delivery_score', 'price_score', 'service_score', 'overall_score'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = (float) $input[$f];
            }
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['draft', 'published', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierScorecard())->update($id, $patch);
    }
}

final class SupplierPerformanceService
{
    /** @return list<array<string, mixed>> */
    public function listForProfile(int $profileId, int $limit = 50): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new EprocSupplierPerformance())->query(
            'SELECT * FROM rateb_eproc_supplier_performance
             WHERE company_id = :cid AND profile_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'pid' => $profileId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $metricKey = trim((string) ($input['metric_key'] ?? ''));
        if ($metricKey === '') {
            throw new \InvalidArgumentException('metric_key_required');
        }
        $id = (new EprocSupplierPerformance())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'metric_key' => substr($metricKey, 0, 60),
            'metric_value' => ProcurementEnterpriseSupport::floatOrNull($input['metric_value'] ?? null) ?? 0.0,
            'period_start' => ProcurementEnterpriseSupport::nullIfEmpty($input['period_start'] ?? null),
            'period_end' => ProcurementEnterpriseSupport::nullIfEmpty($input['period_end'] ?? null),
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierPerformance())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_performance WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('performance_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['metric_key', 'period_start', 'period_end', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('metric_value', $input)) {
            $patch['metric_value'] = ProcurementEnterpriseSupport::floatOrNull($input['metric_value']) ?? 0.0;
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierPerformance())->update($id, $patch);
    }
}

final class SupplierRiskService
{
    /** @return list<array<string, mixed>> */
    public function listForProfile(int $profileId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $rows = (new EprocSupplierRisk())->query(
            'SELECT * FROM rateb_eproc_supplier_risk
             WHERE company_id = :cid AND profile_id = :pid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'pid' => $profileId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $riskCode = trim((string) ($input['risk_code'] ?? ''));
        if ($riskCode === '') {
            $riskCode = ProcurementEnterpriseSupport::nextCode('rateb_eproc_supplier_risk', 'RSK', $companyId);
        }
        $level = (string) ($input['risk_level'] ?? 'medium');
        if (!in_array($level, ['low', 'medium', 'high', 'critical'], true)) {
            $level = 'medium';
        }
        $id = (new EprocSupplierRisk())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'risk_code' => substr($riskCode, 0, 40),
            'risk_level' => $level,
            'title' => substr($title, 0, 190),
            'description' => ProcurementEnterpriseSupport::nullIfEmpty($input['description'] ?? null),
            'mitigation' => ProcurementEnterpriseSupport::nullIfEmpty($input['mitigation'] ?? null),
            'status' => 'open',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierRisk())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_risk WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('risk_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['title', 'description', 'mitigation'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('risk_level', $input)
            && in_array($input['risk_level'], ['low', 'medium', 'high', 'critical'], true)) {
            $patch['risk_level'] = $input['risk_level'];
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['open', 'mitigated', 'accepted', 'closed'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierRisk())->update($id, $patch);
    }
}

final class SupplierBlacklistService
{
    /** @return list<array<string, mixed>> */
    public function list(int $limit = 50, ?int $profileId = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($profileId !== null && $profileId > 0) {
            $where .= ' AND profile_id = :pid';
            $params['pid'] = $profileId;
        }
        $rows = (new EprocSupplierBlacklist())->query(
            'SELECT * FROM rateb_eproc_supplier_blacklist WHERE ' . $where
            . ' ORDER BY id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new \InvalidArgumentException('reason_required');
        }
        $from = trim((string) ($input['effective_from'] ?? ''));
        if ($from === '') {
            $from = date('Y-m-d');
        }
        $id = (new EprocSupplierBlacklist())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'reason' => substr($reason, 0, 255),
            'effective_from' => $from,
            'effective_to' => ProcurementEnterpriseSupport::nullIfEmpty($input['effective_to'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierBlacklist())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_blacklist WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('blacklist_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['reason', 'effective_from', 'effective_to'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'reason'
                    ? substr(trim((string) $input[$f]), 0, 255)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'lifted', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierBlacklist())->update($id, $patch);
    }
}

final class SupplierQualificationService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null, ?int $profileId = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($profileId !== null && $profileId > 0) {
            $where .= ' AND profile_id = :pid';
            $params['pid'] = $profileId;
        }
        $totalRow = (new EprocSupplierQualification())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_supplier_qualification WHERE ' . $where,
            $params
        );
        $items = (new EprocSupplierQualification())->query(
            'SELECT * FROM rateb_eproc_supplier_qualification WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSupplierQualification())->queryOne(
            'SELECT * FROM rateb_eproc_supplier_qualification
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_supplier_qualification', 'QUAL', $companyId);
        }
        $checklist = $input['checklist_json'] ?? null;
        if (is_array($checklist)) {
            $encoded = json_encode($checklist, JSON_UNESCAPED_UNICODE);
            $checklist = $encoded !== false ? $encoded : null;
        }
        $id = (new EprocSupplierQualification())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'checklist_json' => $checklist,
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'qualification_created',
            'Qualification created: ' . $title,
            'qualification',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = $this->find($id);
        if ($row === null) {
            throw new \RuntimeException('qualification_not_found');
        }
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['title', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('checklist_json', $input)) {
            $checklist = $input['checklist_json'];
            if (is_array($checklist)) {
                $encoded = json_encode($checklist, JSON_UNESCAPED_UNICODE);
                $checklist = $encoded !== false ? $encoded : null;
            }
            $patch['checklist_json'] = $checklist;
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSupplierQualification())->update($id, $patch);
    }
}

/**
 * Document metadata only. Binary uploads remain ONLINE via DocumentService.
 */
final class SupplierDocumentMetaService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $entityType, int $entityId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocDocumentMeta())->query(
            'SELECT * FROM rateb_eproc_document_meta
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($entityType === '' || $entityId < 1) {
            throw new \InvalidArgumentException('entity_required');
        }
        $id = (new EprocDocumentMeta())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'document_id' => ProcurementEnterpriseSupport::intOrNull($input['document_id'] ?? null),
            'file_name' => ProcurementEnterpriseSupport::nullIfEmpty($input['file_name'] ?? null),
            'mime_type' => ProcurementEnterpriseSupport::nullIfEmpty($input['mime_type'] ?? null),
            'title' => ProcurementEnterpriseSupport::nullIfEmpty($input['title'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocDocumentMeta())->queryOne(
            'SELECT * FROM rateb_eproc_document_meta WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('document_meta_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['file_name', 'mime_type', 'title'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('document_id', $input)) {
            $patch['document_id'] = ProcurementEnterpriseSupport::intOrNull($input['document_id']);
        }
        if (array_key_exists('status', $input) && in_array($input['status'], ['active', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocDocumentMeta())->update($id, $patch);
    }
}

final class SupplierPortalService
{
    /** @return list<array<string, mixed>> */
    public function listInvites(int $limit = 50, ?int $profileId = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($profileId !== null && $profileId > 0) {
            $where .= ' AND profile_id = :pid';
            $params['pid'] = $profileId;
        }
        $rows = (new EprocPortalInvite())->query(
            'SELECT * FROM rateb_eproc_portal_invites WHERE ' . $where
            . ' ORDER BY id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, invite_token: string}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $profileId = (int) ($input['profile_id'] ?? 0);
        ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('email_required');
        }
        $token = bin2hex(random_bytes(32));
        $expires = ProcurementEnterpriseSupport::nullIfEmpty($input['expires_at'] ?? null);
        if ($expires === null) {
            $expires = date('Y-m-d H:i:s', strtotime('+14 days'));
        }
        $id = (new EprocPortalInvite())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'email' => substr($email, 0, 190),
            'invite_token' => $token,
            'expires_at' => $expires,
            'status' => 'pending',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'portal_invite',
            'Portal invite sent to ' . $email,
            'supplier_profile',
            $profileId,
            ['invite_id' => (int) $id]
        );

        return ['id' => (int) $id, 'invite_token' => $token];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocPortalInvite())->queryOne(
            'SELECT * FROM rateb_eproc_portal_invites WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('portal_invite_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['pending', 'accepted', 'revoked', 'expired'], true)) {
            $patch['status'] = $input['status'];
            if ($input['status'] === 'accepted') {
                $patch['accepted_at'] = date('Y-m-d H:i:s');
            }
        }
        if (array_key_exists('expires_at', $input)) {
            $patch['expires_at'] = ProcurementEnterpriseSupport::nullIfEmpty($input['expires_at']);
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocPortalInvite())->update($id, $patch);
    }
}

final class VendorCollaborationService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null, ?int $profileId = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($profileId !== null && $profileId > 0) {
            $where .= ' AND profile_id = :pid';
            $params['pid'] = $profileId;
        }
        $totalRow = (new EprocCollaboration())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_collaboration WHERE ' . $where,
            $params
        );
        $items = (new EprocCollaboration())->query(
            'SELECT * FROM rateb_eproc_collaboration WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocCollaboration())->queryOne(
            'SELECT * FROM rateb_eproc_collaboration
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('subject_required');
        }
        $profileId = ProcurementEnterpriseSupport::intOrNull($input['profile_id'] ?? null);
        if ($profileId !== null) {
            ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        }
        $id = (new EprocCollaboration())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'profile_id' => $profileId,
            'related_type' => ProcurementEnterpriseSupport::nullIfEmpty($input['related_type'] ?? null),
            'related_id' => ProcurementEnterpriseSupport::intOrNull($input['related_id'] ?? null),
            'subject' => substr($subject, 0, 190),
            'body' => ProcurementEnterpriseSupport::nullIfEmpty($input['body'] ?? null),
            'workflow_status' => 'open',
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'collaboration_opened',
            'Collaboration: ' . $subject,
            'collaboration',
            (int) $id
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = $this->find($id);
        if ($row === null) {
            throw new \RuntimeException('collaboration_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['subject', 'body', 'related_type'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'subject'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['profile_id', 'related_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::intOrNull($input[$f]);
            }
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocCollaboration())->update($id, $patch);
    }
}

final class RfqTemplateService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocRfqTemplate())->query(
            'SELECT * FROM rateb_eproc_rfq_templates
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_rfq_templates', 'RFQT', $companyId);
        }
        $id = (new EprocRfqTemplate())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ProcurementEnterpriseSupport::nullIfEmpty($input['name_ar'] ?? null),
            'body_template' => ProcurementEnterpriseSupport::nullIfEmpty($input['body_template'] ?? null),
            'default_days' => max(1, (int) ($input['default_days'] ?? 14)),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocRfqTemplate())->queryOne(
            'SELECT * FROM rateb_eproc_rfq_templates WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('rfq_template_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['name', 'name_ar', 'body_template'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('default_days', $input)) {
            $patch['default_days'] = max(1, (int) $input['default_days']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'inactive', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocRfqTemplate())->update($id, $patch);
    }
}

final class EnterpriseTenderService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new EprocTender())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_tenders WHERE ' . $where,
            $params
        );
        $items = (new EprocTender())->query(
            'SELECT * FROM rateb_eproc_tenders WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ProcurementEnterpriseSupport::findTender($id, ProcurementEnterpriseSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_tenders', 'TND', $companyId);
        }
        $id = (new EprocTender())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'legacy_tender_id' => ProcurementEnterpriseSupport::intOrNull($input['legacy_tender_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'description' => ProcurementEnterpriseSupport::nullIfEmpty($input['description'] ?? null),
            'opens_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['opens_at'] ?? null),
            'closes_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['closes_at'] ?? null),
            'budget_amount' => ProcurementEnterpriseSupport::floatOrNull($input['budget_amount'] ?? null),
            'currency_code' => ProcurementEnterpriseSupport::nullIfEmpty($input['currency_code'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'tender_created',
            'Tender created: ' . $title,
            'tender',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = ProcurementEnterpriseSupport::assertTender($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['title', 'description', 'opens_at', 'closes_at', 'currency_code'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('budget_amount', $input)) {
            $patch['budget_amount'] = ProcurementEnterpriseSupport::floatOrNull($input['budget_amount']);
        }
        if (array_key_exists('legacy_tender_id', $input)) {
            $patch['legacy_tender_id'] = ProcurementEnterpriseSupport::intOrNull($input['legacy_tender_id']);
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocTender())->update($id, $patch);
    }
}

final class TenderBidService
{
    /** @return list<array<string, mixed>> */
    public function listForTender(int $tenderId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertTender($tenderId, $companyId);
        $rows = (new EprocTenderBid())->query(
            'SELECT * FROM rateb_eproc_tender_bids
             WHERE company_id = :cid AND tender_id = :tid AND deleted_at IS NULL
             ORDER BY bid_amount ASC, id ASC',
            ['cid' => $companyId, 'tid' => $tenderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $tenderId = (int) ($input['tender_id'] ?? 0);
        ProcurementEnterpriseSupport::assertTender($tenderId, $companyId);
        $profileId = ProcurementEnterpriseSupport::intOrNull($input['profile_id'] ?? null);
        if ($profileId !== null) {
            ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        }
        $status = (string) ($input['status'] ?? 'submitted');
        if (!in_array($status, ['submitted', 'shortlisted', 'accepted', 'rejected', 'withdrawn'], true)) {
            $status = 'submitted';
        }
        $id = (new EprocTenderBid())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'tender_id' => $tenderId,
            'profile_id' => $profileId,
            'bid_amount' => ProcurementEnterpriseSupport::floatOrNull($input['bid_amount'] ?? null) ?? 0.0,
            'currency_code' => ProcurementEnterpriseSupport::nullIfEmpty($input['currency_code'] ?? null),
            'score' => ProcurementEnterpriseSupport::floatOrNull($input['score'] ?? null),
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => $status,
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocTenderBid())->queryOne(
            'SELECT * FROM rateb_eproc_tender_bids WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('bid_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['currency_code', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['bid_amount', 'score'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::floatOrNull($input[$f]);
            }
        }
        if (array_key_exists('profile_id', $input)) {
            $patch['profile_id'] = ProcurementEnterpriseSupport::intOrNull($input['profile_id']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['submitted', 'shortlisted', 'accepted', 'rejected', 'withdrawn'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocTenderBid())->update($id, $patch);
    }
}

final class BidComparisonService
{
    /** @return list<array<string, mixed>> */
    public function listForTender(int $tenderId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        ProcurementEnterpriseSupport::assertTender($tenderId, $companyId);
        $rows = (new EprocBidComparison())->query(
            'SELECT * FROM rateb_eproc_bid_comparisons
             WHERE company_id = :cid AND tender_id = :tid AND deleted_at IS NULL ORDER BY id DESC',
            ['cid' => $companyId, 'tid' => $tenderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $tenderId = (int) ($input['tender_id'] ?? 0);
        ProcurementEnterpriseSupport::assertTender($tenderId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $comparison = $input['comparison_json'] ?? null;
        if (is_array($comparison)) {
            $encoded = json_encode($comparison, JSON_UNESCAPED_UNICODE);
            $comparison = $encoded !== false ? $encoded : null;
        }
        $id = (new EprocBidComparison())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'tender_id' => $tenderId,
            'title' => substr($title, 0, 190),
            'comparison_json' => $comparison,
            'recommended_bid_id' => ProcurementEnterpriseSupport::intOrNull($input['recommended_bid_id'] ?? null),
            'status' => 'draft',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocBidComparison())->queryOne(
            'SELECT * FROM rateb_eproc_bid_comparisons WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('comparison_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        if (array_key_exists('title', $input)) {
            $patch['title'] = substr(trim((string) $input['title']), 0, 190);
        }
        if (array_key_exists('comparison_json', $input)) {
            $comparison = $input['comparison_json'];
            if (is_array($comparison)) {
                $encoded = json_encode($comparison, JSON_UNESCAPED_UNICODE);
                $comparison = $encoded !== false ? $encoded : null;
            }
            $patch['comparison_json'] = $comparison;
        }
        if (array_key_exists('recommended_bid_id', $input)) {
            $patch['recommended_bid_id'] = ProcurementEnterpriseSupport::intOrNull($input['recommended_bid_id']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['draft', 'finalized', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocBidComparison())->update($id, $patch);
    }
}

final class EnterpriseContractService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new EprocContract())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_contracts WHERE ' . $where,
            $params
        );
        $items = (new EprocContract())->query(
            'SELECT * FROM rateb_eproc_contracts WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return ProcurementEnterpriseSupport::findContract($id, ProcurementEnterpriseSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_contracts', 'CTR', $companyId);
        }
        $profileId = ProcurementEnterpriseSupport::intOrNull($input['profile_id'] ?? null);
        if ($profileId !== null) {
            ProcurementEnterpriseSupport::assertProfile($profileId, $companyId);
        }
        $id = (new EprocContract())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'legacy_contract_id' => ProcurementEnterpriseSupport::intOrNull($input['legacy_contract_id'] ?? null),
            'profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'starts_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['starts_at'] ?? null),
            'ends_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['ends_at'] ?? null),
            'value_amount' => ProcurementEnterpriseSupport::floatOrNull($input['value_amount'] ?? null),
            'currency_code' => ProcurementEnterpriseSupport::nullIfEmpty($input['currency_code'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ProcurementEnterpriseSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'contract_created',
            'Contract created: ' . $title,
            'contract',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = ProcurementEnterpriseSupport::assertContract($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['title', 'starts_at', 'ends_at', 'currency_code', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['profile_id', 'legacy_contract_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('value_amount', $input)) {
            $patch['value_amount'] = ProcurementEnterpriseSupport::floatOrNull($input['value_amount']);
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocContract())->update($id, $patch);
    }
}

final class ProcurementCalendarService
{
    /** @return list<array<string, mixed>> */
    public function list(int $limit = 50, ?string $from = null, ?string $to = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($from !== null && $from !== '') {
            $where .= ' AND starts_at >= :from';
            $params['from'] = $from;
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND starts_at <= :to';
            $params['to'] = $to;
        }
        $rows = (new EprocCalendarEvent())->query(
            'SELECT * FROM rateb_eproc_calendar_events WHERE ' . $where
            . ' ORDER BY starts_at ASC, id ASC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $startsAt = trim((string) ($input['starts_at'] ?? ''));
        if ($startsAt === '') {
            throw new \InvalidArgumentException('starts_at_required');
        }
        $id = (new EprocCalendarEvent())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'event_type' => substr(trim((string) ($input['event_type'] ?? 'general')), 0, 40) ?: 'general',
            'title' => substr($title, 0, 190),
            'starts_at' => $startsAt,
            'ends_at' => ProcurementEnterpriseSupport::nullIfEmpty($input['ends_at'] ?? null),
            'related_type' => ProcurementEnterpriseSupport::nullIfEmpty($input['related_type'] ?? null),
            'related_id' => ProcurementEnterpriseSupport::intOrNull($input['related_id'] ?? null),
            'status' => 'scheduled',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocCalendarEvent())->queryOne(
            'SELECT * FROM rateb_eproc_calendar_events WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('calendar_event_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['event_type', 'title', 'starts_at', 'ends_at', 'related_type'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'title'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('related_id', $input)) {
            $patch['related_id'] = ProcurementEnterpriseSupport::intOrNull($input['related_id']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['scheduled', 'done', 'cancelled'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocCalendarEvent())->update($id, $patch);
    }
}

final class SpendAnalysisService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $period = null): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($period !== null && $period !== '') {
            $where .= ' AND period_label = :period';
            $params['period'] = $period;
        }
        $totalRow = (new EprocSpendSnapshot())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eproc_spend_snapshots WHERE ' . $where,
            $params
        );
        $items = (new EprocSpendSnapshot())->query(
            'SELECT * FROM rateb_eproc_spend_snapshots WHERE ' . $where
            . ' ORDER BY period_label DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $period = trim((string) ($input['period_label'] ?? ''));
        if ($period === '') {
            throw new \InvalidArgumentException('period_label_required');
        }
        $meta = $input['meta_json'] ?? null;
        if (is_array($meta)) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $meta = $encoded !== false ? $encoded : null;
        }
        $id = (new EprocSpendSnapshot())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'period_label' => substr($period, 0, 40),
            'category_key' => ProcurementEnterpriseSupport::nullIfEmpty($input['category_key'] ?? null),
            'amount' => ProcurementEnterpriseSupport::floatOrNull($input['amount'] ?? null) ?? 0.0,
            'currency_code' => ProcurementEnterpriseSupport::nullIfEmpty($input['currency_code'] ?? null),
            'meta_json' => $meta,
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocSpendSnapshot())->queryOne(
            'SELECT * FROM rateb_eproc_spend_snapshots WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('spend_snapshot_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['period_label', 'category_key', 'currency_code'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('amount', $input)) {
            $patch['amount'] = ProcurementEnterpriseSupport::floatOrNull($input['amount']) ?? 0.0;
        }
        if (array_key_exists('meta_json', $input)) {
            $meta = $input['meta_json'];
            if (is_array($meta)) {
                $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
                $meta = $encoded !== false ? $encoded : null;
            }
            $patch['meta_json'] = $meta;
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocSpendSnapshot())->update($id, $patch);
    }
}

final class ProcurementApprovalLinkService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $entityType, int $entityId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocApprovalLink())->query(
            'SELECT * FROM rateb_eproc_approval_links
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, eap_request_id: int|null}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($entityType === '' || $entityId < 1) {
            throw new \InvalidArgumentException('entity_required');
        }

        $eapRequestId = ProcurementEnterpriseSupport::intOrNull($input['eap_request_id'] ?? null);
        if ($eapRequestId === null && class_exists(ApprovalRequestService::class)) {
            try {
                $created = (new ApprovalRequestService())->create([
                    'title' => (string) ($input['title'] ?? ('Procurement ' . $entityType . ' #' . $entityId)),
                    'related_module' => 'procurement',
                    'related_type' => $entityType,
                    'related_id' => $entityId,
                    'amount' => $input['amount'] ?? null,
                    'currency_code' => $input['currency_code'] ?? null,
                    'notes' => $input['notes'] ?? null,
                ]);
                $eapRequestId = (int) ($created['id'] ?? 0) ?: null;
            } catch (\Throwable) {
                $eapRequestId = null;
            }
        }

        $id = (new EprocApprovalLink())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'eap_request_id' => $eapRequestId,
            'legacy_instance_id' => ProcurementEnterpriseSupport::intOrNull($input['legacy_instance_id'] ?? null),
            'link_status' => $eapRequestId !== null ? 'linked' : 'pending',
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id, 'eap_request_id' => $eapRequestId];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocApprovalLink())->queryOne(
            'SELECT * FROM rateb_eproc_approval_links WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('approval_link_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        foreach (['eap_request_id', 'legacy_instance_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ProcurementEnterpriseSupport::intOrNull($input[$f]);
            }
        }
        if (array_key_exists('link_status', $input)
            && in_array($input['link_status'], ['pending', 'linked', 'closed'], true)) {
            $patch['link_status'] = $input['link_status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocApprovalLink())->update($id, $patch);
    }
}

final class ProcurementAssignmentService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $entityType, int $entityId): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocAssignment())->query(
            'SELECT * FROM rateb_eproc_assignments
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($entityType === '' || $entityId < 1 || $assignee < 1) {
            throw new \InvalidArgumentException('assignment_fields_required');
        }
        $id = (new EprocAssignment())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'assignee_user_id' => $assignee,
            'role_label' => ProcurementEnterpriseSupport::nullIfEmpty($input['role_label'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        (new ProcurementTimelineService())->record(
            'assigned',
            'Assigned to user #' . $assignee,
            $entityType,
            $entityId,
            ['assignment_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $row = (new EprocAssignment())->queryOne(
            'SELECT * FROM rateb_eproc_assignments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('assignment_not_found');
        }
        $patch = ProcurementEnterpriseSupport::actorFields(false);
        if (array_key_exists('assignee_user_id', $input)) {
            $patch['assignee_user_id'] = (int) $input['assignee_user_id'];
        }
        if (array_key_exists('role_label', $input)) {
            $patch['role_label'] = ProcurementEnterpriseSupport::nullIfEmpty($input['role_label']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'completed', 'cancelled'], true)) {
            $patch['status'] = $input['status'];
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new EprocAssignment())->update($id, $patch);
    }
}

final class ProcurementCommentService
{
    /** @return list<array<string, mixed>> */
    public function listFor(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EprocComment())->query(
            'SELECT * FROM rateb_eproc_comments
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $body = trim((string) ($input['body'] ?? ''));
        if ($entityType === '' || $entityId < 1 || $body === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        $id = (new EprocComment())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'body' => $body,
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ProcurementTagService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $rows = (new EprocTag())->query(
            'SELECT * FROM rateb_eproc_tags WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ProcurementEnterpriseSupport::nextCode('rateb_eproc_tags', 'TAG', $companyId);
        }
        $id = (new EprocTag())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 120),
            'color' => ProcurementEnterpriseSupport::nullIfEmpty($input['color'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function attach(array $input): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $tagId = (int) ($input['tag_id'] ?? 0);
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($tagId < 1 || $entityType === '' || $entityId < 1) {
            throw new \InvalidArgumentException('tag_attach_fields_required');
        }
        $existing = (new EprocEntityTag())->queryOne(
            'SELECT id FROM rateb_eproc_entity_tags
             WHERE company_id = :cid AND tag_id = :tid AND entity_type = :et AND entity_id = :eid
             AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'tid' => $tagId, 'et' => $entityType, 'eid' => $entityId]
        );
        if (is_array($existing)) {
            return ['id' => (int) $existing['id']];
        }
        $id = (new EprocEntityTag())->create(array_merge([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'tag_id' => $tagId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'version' => 1,
        ], ProcurementEnterpriseSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ProcurementAuditService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function log(
        string $action,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null
    ): int {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new EprocAudit())->create([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => substr($action, 0, 60),
            'message' => substr($message, 0, 255),
            'meta_json' => $metaJson,
            'created_by' => ProcurementEnterpriseSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 50): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EprocAudit())->query(
            'SELECT * FROM rateb_eproc_audit WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }
}
