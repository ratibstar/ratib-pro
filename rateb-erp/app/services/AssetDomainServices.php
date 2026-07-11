<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EamAsset;
use Rateb\App\Models\EamAssetAssignment;
use Rateb\App\Models\EamAssetCategory;
use Rateb\App\Models\EamAssetModel;
use Rateb\App\Models\EamAssetTransfer;
use Rateb\App\Models\EamLocation;
use Rateb\App\Models\EamManufacturer;
use Rateb\App\Models\EamVendor;

/**
 * Phase 19A — EAM core domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_eam_* — distinct from legacy Asset → rateb_assets.
 */

final class AssetService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR asset_no LIKE :q2 OR serial_no LIKE :q3 OR barcode LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        $totalRow = (new EamAsset())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_eam_assets WHERE ' . $where,
            $params
        );
        $items = (new EamAsset())->query(
            'SELECT * FROM rateb_eam_assets WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return AssetSupport::findAsset($id, AssetSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, asset_no: string}
     */
    public function create(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $assetNo = trim((string) ($input['asset_no'] ?? ''));
        if ($assetNo === '') {
            $assetNo = AssetSupport::nextAssetNo($companyId);
        }
        $id = (new EamAsset())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::intOrNull($input['branch_id'] ?? null) ?? AssetSupport::branchId(),
            'asset_no' => substr($assetNo, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'category_id' => AssetSupport::intOrNull($input['category_id'] ?? null),
            'model_id' => AssetSupport::intOrNull($input['model_id'] ?? null),
            'manufacturer_id' => AssetSupport::intOrNull($input['manufacturer_id'] ?? null),
            'vendor_id' => AssetSupport::intOrNull($input['vendor_id'] ?? null),
            'location_id' => AssetSupport::intOrNull($input['location_id'] ?? null),
            'legacy_asset_id' => AssetSupport::intOrNull($input['legacy_asset_id'] ?? null),
            'serial_no' => AssetSupport::nullIfEmpty($input['serial_no'] ?? null),
            'barcode' => AssetSupport::nullIfEmpty($input['barcode'] ?? null),
            'workflow_status' => AssetWorkflowService::ASSET_DRAFT,
            'priority' => in_array(($input['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $input['priority'] : 'normal',
            'purchase_date' => AssetSupport::nullIfEmpty($input['purchase_date'] ?? null),
            'purchase_cost' => AssetSupport::floatOrNull($input['purchase_cost'] ?? null),
            'current_value' => AssetSupport::floatOrNull($input['current_value'] ?? null),
            'currency_code' => AssetSupport::nullIfEmpty($input['currency_code'] ?? null),
            'useful_life_months' => AssetSupport::intOrNull($input['useful_life_months'] ?? null),
            'salvage_value' => AssetSupport::floatOrNull($input['salvage_value'] ?? null),
            'depreciation_method' => AssetSupport::nullIfEmpty($input['depreciation_method'] ?? null),
            'placed_in_service_date' => AssetSupport::nullIfEmpty($input['placed_in_service_date'] ?? null),
            'owner_user_id' => AssetSupport::intOrNull($input['owner_user_id'] ?? null) ?? AssetSupport::userId(),
            'custodian_user_id' => AssetSupport::intOrNull($input['custodian_user_id'] ?? null),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new AssetTimelineService())->record(
            'asset_created',
            'Asset created: ' . $name,
            null,
            (int) $id,
            'asset',
            (int) $id
        );

        return ['id' => (int) $id, 'asset_no' => $assetNo];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = AssetSupport::requireCompanyId();
        $asset = AssetSupport::assertAsset($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($asset['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = AssetSupport::actorFields(false);
        foreach (['name', 'name_ar', 'serial_no', 'barcode', 'notes', 'currency_code', 'purchase_date',
            'placed_in_service_date', 'depreciation_method', 'priority'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : AssetSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['category_id', 'model_id', 'manufacturer_id', 'vendor_id', 'location_id', 'branch_id',
            'legacy_asset_id', 'owner_user_id', 'custodian_user_id', 'useful_life_months'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = AssetSupport::intOrNull($input[$f]);
            }
        }
        foreach (['purchase_cost', 'current_value', 'salvage_value'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = AssetSupport::floatOrNull($input[$f]);
            }
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($asset['version'] ?? 1) + 1;
        (new EamAsset())->update($id, $patch);
        (new AssetTimelineService())->record('asset_updated', 'Asset updated', null, $id, 'asset', $id);
    }

    public function softDelete(int $id): void
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($id, $companyId);
        (new EamAsset())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], AssetSupport::actorFields(false)));
        (new AssetTimelineService())->record('asset_deleted', 'Asset soft-deleted', null, $id, 'asset', $id);
    }

    /** @return array<string, int> */
    public function boardCounts(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $out = [];
        foreach (AssetWorkflowService::assetStatuses() as $st) {
            $row = (new EamAsset())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_eam_assets
                 WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $out[$st] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }
}

final class AssetCategoryService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamAssetCategory())->query(
            'SELECT * FROM rateb_eam_asset_categories WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
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
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_asset_categories', 'CAT', $companyId);
        }
        $id = (new EamAssetCategory())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'parent_id' => AssetSupport::intOrNull($input['parent_id'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetLocationService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamLocation())->query(
            'SELECT * FROM rateb_eam_locations WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
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
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_locations', 'LOC', $companyId);
        }
        $id = (new EamLocation())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'parent_id' => AssetSupport::intOrNull($input['parent_id'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetManufacturerService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamManufacturer())->query(
            'SELECT * FROM rateb_eam_manufacturers WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
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
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_manufacturers', 'MFR', $companyId);
        }
        $id = (new EamManufacturer())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetVendorService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamVendor())->query(
            'SELECT * FROM rateb_eam_vendors WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
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
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_vendors', 'VND', $companyId);
        }
        $id = (new EamVendor())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'supplier_id' => AssetSupport::intOrNull($input['supplier_id'] ?? null),
            'phone' => AssetSupport::nullIfEmpty($input['phone'] ?? null),
            'email' => AssetSupport::nullIfEmpty($input['email'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetModelService
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $rows = (new EamAssetModel())->query(
            'SELECT * FROM rateb_eam_asset_models WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name ASC',
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
        $companyId = AssetSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = AssetSupport::nextCode('rateb_eam_asset_models', 'MDL', $companyId);
        }
        $id = (new EamAssetModel())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'manufacturer_id' => AssetSupport::intOrNull($input['manufacturer_id'] ?? null),
            'category_id' => AssetSupport::intOrNull($input['category_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => AssetSupport::nullIfEmpty($input['name_ar'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class AssetAssignmentService
{
    /** @return list<array<string, mixed>> */
    public function list(?int $assetId = null, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($assetId !== null && $assetId > 0) {
            AssetSupport::assertAsset($assetId, $companyId);
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $rows = (new EamAssetAssignment())->query(
            'SELECT * FROM rateb_eam_asset_assignments WHERE ' . $where
            . ' ORDER BY assigned_at DESC, id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function assign(array $input): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        AssetSupport::assertAsset($assetId, $companyId);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($assignee < 1) {
            throw new \InvalidArgumentException('assignee_required');
        }
        $id = (new EamAssetAssignment())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'assignee_user_id' => $assignee,
            'role_label' => AssetSupport::nullIfEmpty($input['role_label'] ?? null),
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        (new EamAsset())->update($assetId, array_merge([
            'custodian_user_id' => $assignee,
            'version' => (int) (AssetSupport::assertAsset($assetId, $companyId)['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false)));

        (new AssetTimelineService())->record(
            'asset_assigned',
            'Asset assigned',
            null,
            $assetId,
            'assignment',
            (int) $id
        );

        return ['id' => (int) $id];
    }

    public function release(int $assignmentId): void
    {
        $companyId = AssetSupport::requireCompanyId();
        $row = (new EamAssetAssignment())->queryOne(
            'SELECT * FROM rateb_eam_asset_assignments WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $assignmentId, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('assignment_not_found');
        }
        (new EamAssetAssignment())->update($assignmentId, array_merge([
            'status' => 'released',
            'released_at' => date('Y-m-d H:i:s'),
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false)));
    }
}

final class AssetTransferService
{
    /** @return list<array<string, mixed>> */
    public function list(?int $assetId = null, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($assetId !== null && $assetId > 0) {
            $where .= ' AND asset_id = :aid';
            $params['aid'] = $assetId;
        }
        $rows = (new EamAssetTransfer())->query(
            'SELECT * FROM rateb_eam_asset_transfers WHERE ' . $where
            . ' ORDER BY transfer_at DESC, id DESC LIMIT ' . $safe,
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
        $companyId = AssetSupport::requireCompanyId();
        $assetId = (int) ($input['asset_id'] ?? 0);
        $asset = AssetSupport::assertAsset($assetId, $companyId);
        $toLocation = AssetSupport::intOrNull($input['to_location_id'] ?? null);
        if ($toLocation === null) {
            throw new \InvalidArgumentException('to_location_required');
        }
        $id = (new EamAssetTransfer())->create(array_merge([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'from_location_id' => AssetSupport::intOrNull($input['from_location_id'] ?? null)
                ?? AssetSupport::intOrNull($asset['location_id'] ?? null),
            'to_location_id' => $toLocation,
            'from_branch_id' => AssetSupport::intOrNull($input['from_branch_id'] ?? null)
                ?? AssetSupport::intOrNull($asset['branch_id'] ?? null),
            'to_branch_id' => AssetSupport::intOrNull($input['to_branch_id'] ?? null),
            'transfer_at' => AssetSupport::nullIfEmpty($input['transfer_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'status' => 'draft',
            'notes' => AssetSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], AssetSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    public function complete(int $transferId): void
    {
        $companyId = AssetSupport::requireCompanyId();
        $row = (new EamAssetTransfer())->queryOne(
            'SELECT * FROM rateb_eam_asset_transfers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $transferId, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('transfer_not_found');
        }
        if (($row['status'] ?? '') === 'completed') {
            return;
        }
        $assetId = (int) $row['asset_id'];
        $asset = AssetSupport::assertAsset($assetId, $companyId);
        (new EamAssetTransfer())->update($transferId, array_merge([
            'status' => 'completed',
            'version' => (int) ($row['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false)));
        $patch = array_merge([
            'location_id' => AssetSupport::intOrNull($row['to_location_id'] ?? null),
            'version' => (int) ($asset['version'] ?? 1) + 1,
        ], AssetSupport::actorFields(false));
        if (!empty($row['to_branch_id'])) {
            $patch['branch_id'] = (int) $row['to_branch_id'];
        }
        (new EamAsset())->update($assetId, $patch);
        (new AssetTimelineService())->record(
            'asset_transferred',
            'Asset transferred',
            null,
            $assetId,
            'transfer',
            $transferId
        );
    }
}
