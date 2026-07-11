<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MfgAssignment;
use Rateb\App\Models\MfgAttachmentMeta;
use Rateb\App\Models\MfgBom;
use Rateb\App\Models\MfgBomLine;
use Rateb\App\Models\MfgBomVersion;
use Rateb\App\Models\MfgCapacityPlan;
use Rateb\App\Models\MfgComment;
use Rateb\App\Models\MfgEntityTag;
use Rateb\App\Models\MfgFinishedGoodsReceipt;
use Rateb\App\Models\MfgMachine;
use Rateb\App\Models\MfgMaterialConsumption;
use Rateb\App\Models\MfgMaterialReservation;
use Rateb\App\Models\MfgProduct;
use Rateb\App\Models\MfgProductionCalendar;
use Rateb\App\Models\MfgProductionCost;
use Rateb\App\Models\MfgProductionOrder;
use Rateb\App\Models\MfgProductVariant;
use Rateb\App\Models\MfgQualityCheck;
use Rateb\App\Models\MfgRouting;
use Rateb\App\Models\MfgRoutingOperation;
use Rateb\App\Models\MfgSchedule;
use Rateb\App\Models\MfgScrapRecord;
use Rateb\App\Models\MfgTag;
use Rateb\App\Models\MfgWorkCenter;
use Rateb\App\Models\MfgWorkOrder;

/**
 * Phase 22A — Enterprise Manufacturing (MRP) Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_mfg_* — workflow_status changes via ManufacturingWorkflowService only.
 * Stock / GL posting is deferred; consumption / receipt / scrap / cost are MFG ledger meta only.
 */

final class ManufacturingEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $out = [];
        $maps = [
            'production_order' => [
                'table' => 'rateb_mfg_production_orders',
                'model' => new MfgProductionOrder(),
            ],
            'work_order' => [
                'table' => 'rateb_mfg_work_orders',
                'model' => new MfgWorkOrder(),
            ],
            'bom' => [
                'table' => 'rateb_mfg_boms',
                'model' => new MfgBom(),
            ],
            'product' => [
                'table' => 'rateb_mfg_products',
                'model' => new MfgProduct(),
            ],
        ];
        foreach ($maps as $entityType => $cfg) {
            $counts = [];
            foreach (ManufacturingWorkflowService::statuses($entityType) as $st) {
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
}

final class MfgProductService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new MfgProduct())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_products WHERE ' . $where,
            $params
        );
        $items = (new MfgProduct())->query(
            'SELECT * FROM rateb_mfg_products WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return ManufacturingSupport::findProduct($id, ManufacturingSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_products', 'MFG-PROD', $companyId);
        }
        $productType = (string) ($input['product_type'] ?? 'finished');
        if (!in_array($productType, ['finished', 'semi_finished', 'raw', 'phantom'], true)) {
            $productType = 'finished';
        }
        $id = (new MfgProduct())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::intOrNull($input['branch_id'] ?? null)
                ?? ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ManufacturingSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => ManufacturingSupport::nullIfEmpty($input['description'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'uom' => substr(trim((string) ($input['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'product_type' => $productType,
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'product_created',
            'Product created: ' . $name,
            'product',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = ManufacturingSupport::assertProduct($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ManufacturingSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'uom', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'name') {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 190);
                } elseif ($f === 'uom') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'EA')), 0, 40) ?: 'EA';
                } else {
                    $patch[$f] = ManufacturingSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        if (array_key_exists('inventory_item_id', $input)) {
            $patch['inventory_item_id'] = ManufacturingSupport::intOrNull($input['inventory_item_id']);
        }
        if (array_key_exists('product_type', $input)
            && in_array($input['product_type'], ['finished', 'semi_finished', 'raw', 'phantom'], true)) {
            $patch['product_type'] = $input['product_type'];
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new MfgProduct())->update($id, $patch);
        (new ManufacturingTimelineService())->record(
            'product_updated',
            'Product updated',
            'product',
            $id
        );
    }

    public function softDelete(int $id): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProduct($id, $companyId);
        (new MfgProduct())->update($id, array_merge([
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived',
        ], ManufacturingSupport::actorFields(false)));
        (new ManufacturingTimelineService())->record(
            'product_deleted',
            'Product soft-deleted',
            'product',
            $id
        );
    }
}

final class MfgProductVariantService
{
    /** @return list<array<string, mixed>> */
    public function listByProduct(int $productId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProduct($productId, $companyId);
        $rows = (new MfgProductVariant())->query(
            'SELECT * FROM rateb_mfg_product_variants
             WHERE company_id = :cid AND product_id = :pid AND deleted_at IS NULL
             ORDER BY id ASC',
            ['cid' => $companyId, 'pid' => $productId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productId = (int) ($input['product_id'] ?? 0);
        ManufacturingSupport::assertProduct($productId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_product_variants', 'VAR', $companyId);
        }
        $attrsJson = null;
        if (isset($input['attributes_json'])) {
            if (is_array($input['attributes_json'])) {
                $encoded = json_encode($input['attributes_json'], JSON_UNESCAPED_UNICODE);
                $attrsJson = $encoded !== false ? $encoded : null;
            } else {
                $attrsJson = ManufacturingSupport::nullIfEmpty($input['attributes_json']);
            }
        }
        $id = (new MfgProductVariant())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'product_id' => $productId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'sku' => ManufacturingSupport::nullIfEmpty($input['sku'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'attributes_json' => $attrsJson,
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'variant_created',
            'Product variant created: ' . $name,
            'product',
            $productId,
            ['variant_id' => (int) $id]
        );

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BomService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new MfgBom())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_boms WHERE ' . $where,
            $params
        );
        $items = (new MfgBom())->query(
            'SELECT * FROM rateb_mfg_boms WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return ManufacturingSupport::findBom($id, ManufacturingSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productId = (int) ($input['product_id'] ?? 0);
        ManufacturingSupport::assertProduct($productId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_boms', 'BOM', $companyId);
        }
        $id = (new MfgBom())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ManufacturingSupport::nullIfEmpty($input['name_ar'] ?? null),
            'product_id' => $productId,
            'variant_id' => ManufacturingSupport::intOrNull($input['variant_id'] ?? null),
            'description' => ManufacturingSupport::nullIfEmpty($input['description'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'bom_created',
            'BOM created: ' . $name,
            'bom',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = ManufacturingSupport::assertBom($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ManufacturingSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ManufacturingSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('product_id', $input)) {
            $pid = (int) $input['product_id'];
            ManufacturingSupport::assertProduct($pid, $companyId);
            $patch['product_id'] = $pid;
        }
        if (array_key_exists('variant_id', $input)) {
            $patch['variant_id'] = ManufacturingSupport::intOrNull($input['variant_id']);
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new MfgBom())->update($id, $patch);
        (new ManufacturingTimelineService())->record('bom_updated', 'BOM updated', 'bom', $id);
    }
}

final class BomVersionService
{
    /** @return list<array<string, mixed>> */
    public function listByBom(int $bomId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertBom($bomId, $companyId);
        $rows = (new MfgBomVersion())->query(
            'SELECT * FROM rateb_mfg_bom_versions
             WHERE company_id = :cid AND bom_id = :bid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'bid' => $bomId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $bomId = (int) ($input['bom_id'] ?? 0);
        ManufacturingSupport::assertBom($bomId, $companyId);
        $versionLabel = trim((string) ($input['version_label'] ?? ''));
        if ($versionLabel === '') {
            throw new \InvalidArgumentException('version_label_required');
        }
        $isCurrent = !empty($input['is_current']) ? 1 : 0;
        if ($isCurrent === 1) {
            $currents = (new MfgBomVersion())->query(
                'SELECT id, version FROM rateb_mfg_bom_versions
                 WHERE company_id = :cid AND bom_id = :bid AND is_current = 1 AND deleted_at IS NULL',
                ['cid' => $companyId, 'bid' => $bomId]
            );
            if (is_array($currents)) {
                foreach ($currents as $cur) {
                    (new MfgBomVersion())->update((int) $cur['id'], array_merge([
                        'is_current' => 0,
                        'version' => (int) ($cur['version'] ?? 1) + 1,
                    ], ManufacturingSupport::actorFields(false)));
                }
            }
        }
        $id = (new MfgBomVersion())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'bom_id' => $bomId,
            'version_label' => substr($versionLabel, 0, 40),
            'is_current' => $isCurrent,
            'effective_from' => ManufacturingSupport::dateOrNull($input['effective_from'] ?? null),
            'effective_to' => ManufacturingSupport::dateOrNull($input['effective_to'] ?? null),
            'scrap_percent' => ManufacturingSupport::floatOrZero($input['scrap_percent'] ?? 0),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'bom_version_created',
            'BOM version created: ' . $versionLabel,
            'bom_version',
            (int) $id,
            ['bom_id' => $bomId]
        );

        return ['id' => (int) $id];
    }
}

final class BomLineService
{
    /** @return list<array<string, mixed>> */
    public function listByVersion(int $bomVersionId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $this->assertBomVersion($bomVersionId, $companyId);
        $rows = (new MfgBomLine())->query(
            'SELECT * FROM rateb_mfg_bom_lines
             WHERE company_id = :cid AND bom_version_id = :vid AND deleted_at IS NULL
             ORDER BY line_no ASC, id ASC',
            ['cid' => $companyId, 'vid' => $bomVersionId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $bomVersionId = (int) ($input['bom_version_id'] ?? 0);
        $this->assertBomVersion($bomVersionId, $companyId);
        $componentName = trim((string) ($input['component_name'] ?? ''));
        if ($componentName === '') {
            throw new \InvalidArgumentException('component_name_required');
        }
        if (!array_key_exists('qty_per', $input) || $input['qty_per'] === '' || $input['qty_per'] === null) {
            throw new \InvalidArgumentException('qty_per_required');
        }
        $qtyPer = (float) $input['qty_per'];
        $lineNo = (int) ($input['line_no'] ?? 0);
        if ($lineNo < 1) {
            $maxRow = (new MfgBomLine())->queryOne(
                'SELECT COALESCE(MAX(line_no),0) AS m FROM rateb_mfg_bom_lines
                 WHERE company_id = :cid AND bom_version_id = :vid AND deleted_at IS NULL',
                ['cid' => $companyId, 'vid' => $bomVersionId]
            );
            $lineNo = (int) ($maxRow['m'] ?? 0) + 1;
        }
        $id = (new MfgBomLine())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'bom_version_id' => $bomVersionId,
            'line_no' => $lineNo,
            'component_product_id' => ManufacturingSupport::intOrNull($input['component_product_id'] ?? null),
            'component_variant_id' => ManufacturingSupport::intOrNull($input['component_variant_id'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'component_code' => ManufacturingSupport::nullIfEmpty($input['component_code'] ?? null),
            'component_name' => substr($componentName, 0, 190),
            'qty_per' => $qtyPer,
            'uom' => substr(trim((string) ($input['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'scrap_percent' => ManufacturingSupport::floatOrZero($input['scrap_percent'] ?? 0),
            'is_optional' => !empty($input['is_optional']) ? 1 : 0,
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'bom_line_created',
            'BOM line created: ' . $componentName,
            'bom_version',
            $bomVersionId,
            ['bom_line_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @return array<string, mixed> */
    private function assertBomVersion(int $id, int $companyId): array
    {
        $row = (new MfgBomVersion())->queryOne(
            'SELECT * FROM rateb_mfg_bom_versions WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('bom_version_not_found');
        }

        return $row;
    }
}

final class WorkCenterService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new MfgWorkCenter())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_work_centers WHERE ' . $where,
            $params
        );
        $items = (new MfgWorkCenter())->query(
            'SELECT * FROM rateb_mfg_work_centers WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_work_centers', 'WC', $companyId);
        }
        $id = (new MfgWorkCenter())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => ManufacturingSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => ManufacturingSupport::nullIfEmpty($input['description'] ?? null),
            'capacity_hours_day' => ManufacturingSupport::floatOrZero($input['capacity_hours_day'] ?? 8),
            'cost_per_hour' => ManufacturingSupport::floatOrZero($input['cost_per_hour'] ?? 0),
            'warehouse_id' => ManufacturingSupport::intOrNull($input['warehouse_id'] ?? null),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'work_center_created',
            'Work center created: ' . $name,
            'work_center',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = $this->assertWorkCenter($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ManufacturingSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ManufacturingSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['capacity_hours_day', 'cost_per_hour'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ManufacturingSupport::floatOrZero($input[$f]);
            }
        }
        if (array_key_exists('warehouse_id', $input)) {
            $patch['warehouse_id'] = ManufacturingSupport::intOrNull($input['warehouse_id']);
        }
        if (array_key_exists('status', $input)
            && in_array($input['status'], ['active', 'inactive', 'archived'], true)) {
            $patch['status'] = $input['status'];
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new MfgWorkCenter())->update($id, $patch);
    }

    /** @return array<string, mixed> */
    private function assertWorkCenter(int $id, int $companyId): array
    {
        $row = (new MfgWorkCenter())->queryOne(
            'SELECT * FROM rateb_mfg_work_centers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('work_center_not_found');
        }

        return $row;
    }
}

final class MachineService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $workCenterId = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($workCenterId !== null && $workCenterId > 0) {
            $where .= ' AND work_center_id = :wc';
            $params['wc'] = $workCenterId;
        }
        $totalRow = (new MfgMachine())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_machines WHERE ' . $where,
            $params
        );
        $items = (new MfgMachine())->query(
            'SELECT * FROM rateb_mfg_machines WHERE ' . $where
            . ' ORDER BY name ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $workCenterId = (int) ($input['work_center_id'] ?? 0);
        $wc = (new MfgWorkCenter())->queryOne(
            'SELECT id FROM rateb_mfg_work_centers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $workCenterId, 'cid' => $companyId]
        );
        if (!is_array($wc)) {
            throw new \RuntimeException('work_center_not_found');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_machines', 'MCH', $companyId);
        }
        $id = (new MfgMachine())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'work_center_id' => $workCenterId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'eam_asset_id' => ManufacturingSupport::intOrNull($input['eam_asset_id'] ?? null),
            'capacity_hours_day' => ManufacturingSupport::floatOrZero($input['capacity_hours_day'] ?? 8),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'machine_created',
            'Machine created: ' . $name,
            'machine',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class RoutingService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new MfgRouting())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_routings WHERE ' . $where,
            $params
        );
        $items = (new MfgRouting())->query(
            'SELECT * FROM rateb_mfg_routings WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = (new MfgRouting())->queryOne(
            'SELECT * FROM rateb_mfg_routings WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
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
        $companyId = ManufacturingSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_routings', 'RT', $companyId);
        }
        $productId = ManufacturingSupport::intOrNull($input['product_id'] ?? null);
        if ($productId !== null) {
            ManufacturingSupport::assertProduct($productId, $companyId);
        }
        $bomId = ManufacturingSupport::intOrNull($input['bom_id'] ?? null);
        if ($bomId !== null) {
            ManufacturingSupport::assertBom($bomId, $companyId);
        }
        $id = (new MfgRouting())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'product_id' => $productId,
            'bom_id' => $bomId,
            'description' => ManufacturingSupport::nullIfEmpty($input['description'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'routing_created',
            'Routing created: ' . $name,
            'routing',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = (new MfgRouting())->queryOne(
            'SELECT * FROM rateb_mfg_routings WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('routing_not_found');
        }
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ManufacturingSupport::actorFields(false);
        foreach (['name', 'description', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'name'
                    ? substr(trim((string) $input[$f]), 0, 190)
                    : ManufacturingSupport::nullIfEmpty($input[$f]);
            }
        }
        if (array_key_exists('product_id', $input)) {
            $pid = ManufacturingSupport::intOrNull($input['product_id']);
            if ($pid !== null) {
                ManufacturingSupport::assertProduct($pid, $companyId);
            }
            $patch['product_id'] = $pid;
        }
        if (array_key_exists('bom_id', $input)) {
            $bid = ManufacturingSupport::intOrNull($input['bom_id']);
            if ($bid !== null) {
                ManufacturingSupport::assertBom($bid, $companyId);
            }
            $patch['bom_id'] = $bid;
        }
        if (isset($patch['name']) && $patch['name'] === '') {
            throw new \InvalidArgumentException('name_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new MfgRouting())->update($id, $patch);
        (new ManufacturingTimelineService())->record('routing_updated', 'Routing updated', 'routing', $id);
    }
}

final class RoutingOperationService
{
    /** @return list<array<string, mixed>> */
    public function listByRouting(int $routingId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $this->assertRouting($routingId, $companyId);
        $rows = (new MfgRoutingOperation())->query(
            'SELECT * FROM rateb_mfg_routing_operations
             WHERE company_id = :cid AND routing_id = :rid AND deleted_at IS NULL
             ORDER BY seq_no ASC, id ASC',
            ['cid' => $companyId, 'rid' => $routingId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $routingId = (int) ($input['routing_id'] ?? 0);
        $this->assertRouting($routingId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_routing_operations', 'OP', $companyId);
        }
        $seqNo = (int) ($input['seq_no'] ?? 10);
        if ($seqNo < 1) {
            $seqNo = 10;
        }
        $id = (new MfgRoutingOperation())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'routing_id' => $routingId,
            'seq_no' => $seqNo,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'work_center_id' => ManufacturingSupport::intOrNull($input['work_center_id'] ?? null),
            'machine_id' => ManufacturingSupport::intOrNull($input['machine_id'] ?? null),
            'setup_minutes' => ManufacturingSupport::floatOrZero($input['setup_minutes'] ?? 0),
            'run_minutes_per_unit' => ManufacturingSupport::floatOrZero($input['run_minutes_per_unit'] ?? 0),
            'queue_minutes' => ManufacturingSupport::floatOrZero($input['queue_minutes'] ?? 0),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'routing_operation_created',
            'Routing operation created: ' . $name,
            'routing',
            $routingId,
            ['operation_id' => (int) $id]
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @return array<string, mixed> */
    private function assertRouting(int $id, int $companyId): array
    {
        $row = (new MfgRouting())->queryOne(
            'SELECT * FROM rateb_mfg_routings WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('routing_not_found');
        }

        return $row;
    }
}

final class ProductionOrderService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
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
        $totalRow = (new MfgProductionOrder())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_production_orders WHERE ' . $where,
            $params
        );
        $items = (new MfgProductionOrder())->query(
            'SELECT * FROM rateb_mfg_production_orders WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return ManufacturingSupport::findProductionOrder($id, ManufacturingSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $productId = (int) ($input['product_id'] ?? 0);
        ManufacturingSupport::assertProduct($productId, $companyId);
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_production_orders', 'MO', $companyId);
        }
        $bomId = ManufacturingSupport::intOrNull($input['bom_id'] ?? null);
        if ($bomId !== null) {
            ManufacturingSupport::assertBom($bomId, $companyId);
        }
        $id = (new MfgProductionOrder())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'product_id' => $productId,
            'variant_id' => ManufacturingSupport::intOrNull($input['variant_id'] ?? null),
            'bom_id' => $bomId,
            'bom_version_id' => ManufacturingSupport::intOrNull($input['bom_version_id'] ?? null),
            'routing_id' => ManufacturingSupport::intOrNull($input['routing_id'] ?? null),
            'qty_planned' => ManufacturingSupport::floatOrZero($input['qty_planned'] ?? 1) ?: 1.0,
            'qty_completed' => 0,
            'qty_scrap' => 0,
            'uom' => substr(trim((string) ($input['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'warehouse_id' => ManufacturingSupport::intOrNull($input['warehouse_id'] ?? null),
            'project_id' => ManufacturingSupport::intOrNull($input['project_id'] ?? null),
            'planned_start' => ManufacturingSupport::dateOrNull($input['planned_start'] ?? null),
            'planned_end' => ManufacturingSupport::dateOrNull($input['planned_end'] ?? null),
            'priority' => (int) ($input['priority'] ?? 50),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'production_order_created',
            'Production order created: ' . $title,
            'production_order',
            (int) $id
        );

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $row = ManufacturingSupport::assertProductionOrder($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = ManufacturingSupport::actorFields(false);
        foreach (['title', 'uom', 'notes'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'title') {
                    $patch[$f] = substr(trim((string) $input[$f]), 0, 190);
                } elseif ($f === 'uom') {
                    $patch[$f] = substr(trim((string) ($input[$f] ?? 'EA')), 0, 40) ?: 'EA';
                } else {
                    $patch[$f] = ManufacturingSupport::nullIfEmpty($input[$f]);
                }
            }
        }
        if (array_key_exists('product_id', $input)) {
            $pid = (int) $input['product_id'];
            ManufacturingSupport::assertProduct($pid, $companyId);
            $patch['product_id'] = $pid;
        }
        foreach (['variant_id', 'bom_id', 'bom_version_id', 'routing_id', 'warehouse_id', 'project_id', 'priority'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = $f === 'priority'
                    ? (int) $input[$f]
                    : ManufacturingSupport::intOrNull($input[$f]);
            }
        }
        if (isset($patch['bom_id']) && $patch['bom_id'] !== null) {
            ManufacturingSupport::assertBom((int) $patch['bom_id'], $companyId);
        }
        foreach (['qty_planned'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ManufacturingSupport::floatOrZero($input[$f]);
            }
        }
        foreach (['planned_start', 'planned_end'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = ManufacturingSupport::dateOrNull($input[$f]);
            }
        }
        if (isset($patch['title']) && $patch['title'] === '') {
            throw new \InvalidArgumentException('title_required');
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new MfgProductionOrder())->update($id, $patch);
        (new ManufacturingTimelineService())->record(
            'production_order_updated',
            'Production order updated',
            'production_order',
            $id
        );
    }
}

final class MfgWorkOrderService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        $totalRow = (new MfgWorkOrder())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_work_orders WHERE ' . $where,
            $params
        );
        $items = (new MfgWorkOrder())->query(
            'SELECT * FROM rateb_mfg_work_orders WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgWorkOrder())->query(
            'SELECT * FROM rateb_mfg_work_orders
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY seq_no ASC, id ASC',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_work_orders', 'WO', $companyId);
        }
        $id = (new MfgWorkOrder())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'routing_operation_id' => ManufacturingSupport::intOrNull($input['routing_operation_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'work_center_id' => ManufacturingSupport::intOrNull($input['work_center_id'] ?? null),
            'machine_id' => ManufacturingSupport::intOrNull($input['machine_id'] ?? null),
            'seq_no' => (int) ($input['seq_no'] ?? 10),
            'qty_planned' => ManufacturingSupport::floatOrZero($input['qty_planned'] ?? 1) ?: 1.0,
            'qty_completed' => 0,
            'planned_start' => ManufacturingSupport::dateOrNull($input['planned_start'] ?? null),
            'planned_end' => ManufacturingSupport::dateOrNull($input['planned_end'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'work_order_created',
            'Work order created: ' . $title,
            'work_order',
            (int) $id,
            ['production_order_id' => $productionOrderId]
        );

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class CapacityPlanService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $workCenterId = null, ?string $from = null, ?string $to = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($workCenterId !== null && $workCenterId > 0) {
            $where .= ' AND work_center_id = :wc';
            $params['wc'] = $workCenterId;
        }
        if ($from !== null && $from !== '') {
            $where .= ' AND plan_date >= :df';
            $params['df'] = substr($from, 0, 32);
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND plan_date <= :dt';
            $params['dt'] = substr($to, 0, 32);
        }
        $totalRow = (new MfgCapacityPlan())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_capacity_plans WHERE ' . $where,
            $params
        );
        $items = (new MfgCapacityPlan())->query(
            'SELECT * FROM rateb_mfg_capacity_plans WHERE ' . $where
            . ' ORDER BY plan_date DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
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
        $companyId = ManufacturingSupport::requireCompanyId();
        $workCenterId = (int) ($input['work_center_id'] ?? 0);
        $wc = (new MfgWorkCenter())->queryOne(
            'SELECT id FROM rateb_mfg_work_centers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $workCenterId, 'cid' => $companyId]
        );
        if (!is_array($wc)) {
            throw new \RuntimeException('work_center_not_found');
        }
        $planDate = ManufacturingSupport::dateOrNull($input['plan_date'] ?? null);
        if ($planDate === null) {
            throw new \InvalidArgumentException('plan_date_required');
        }
        $id = (new MfgCapacityPlan())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'work_center_id' => $workCenterId,
            'plan_date' => $planDate,
            'available_hours' => ManufacturingSupport::floatOrZero($input['available_hours'] ?? 8),
            'booked_hours' => ManufacturingSupport::floatOrZero($input['booked_hours'] ?? 0),
            'status' => 'open',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'capacity_plan_created',
            'Capacity plan created for ' . $planDate,
            'capacity_plan',
            (int) $id
        );

        return ['id' => (int) $id];
    }
}

final class ProductionCalendarService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 50, int $offset = 0, ?string $from = null, ?string $to = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(200, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($from !== null && $from !== '') {
            $where .= ' AND event_date >= :df';
            $params['df'] = substr($from, 0, 32);
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND event_date <= :dt';
            $params['dt'] = substr($to, 0, 32);
        }
        $totalRow = (new MfgProductionCalendar())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_production_calendar WHERE ' . $where,
            $params
        );
        $items = (new MfgProductionCalendar())->query(
            'SELECT * FROM rateb_mfg_production_calendar WHERE ' . $where
            . ' ORDER BY event_date ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
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
        $companyId = ManufacturingSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $eventDate = ManufacturingSupport::dateOrNull($input['event_date'] ?? null);
        if ($eventDate === null) {
            throw new \InvalidArgumentException('event_date_required');
        }
        $id = (new MfgProductionCalendar())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'title' => substr($title, 0, 190),
            'event_type' => substr(trim((string) ($input['event_type'] ?? 'shift')), 0, 40) ?: 'shift',
            'event_date' => $eventDate,
            'start_time' => ManufacturingSupport::nullIfEmpty($input['start_time'] ?? null),
            'end_time' => ManufacturingSupport::nullIfEmpty($input['end_time'] ?? null),
            'work_center_id' => ManufacturingSupport::intOrNull($input['work_center_id'] ?? null),
            'is_holiday' => !empty($input['is_holiday']) ? 1 : 0,
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'calendar_event_created',
            'Production calendar event: ' . $title,
            'production_calendar',
            (int) $id
        );

        return ['id' => (int) $id];
    }
}

final class ScheduleService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $productionOrderId = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($productionOrderId !== null && $productionOrderId > 0) {
            $where .= ' AND production_order_id = :pid';
            $params['pid'] = $productionOrderId;
        }
        $totalRow = (new MfgSchedule())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_schedules WHERE ' . $where,
            $params
        );
        $items = (new MfgSchedule())->query(
            'SELECT * FROM rateb_mfg_schedules WHERE ' . $where
            . ' ORDER BY scheduled_start ASC, id ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
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
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $scheduledStart = ManufacturingSupport::dateOrNull($input['scheduled_start'] ?? null);
        $scheduledEnd = ManufacturingSupport::dateOrNull($input['scheduled_end'] ?? null);
        if ($scheduledStart === null || $scheduledEnd === null) {
            throw new \InvalidArgumentException('schedule_window_required');
        }
        $id = (new MfgSchedule())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'work_order_id' => ManufacturingSupport::intOrNull($input['work_order_id'] ?? null),
            'work_center_id' => ManufacturingSupport::intOrNull($input['work_center_id'] ?? null),
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'schedule_status' => substr(trim((string) ($input['schedule_status'] ?? 'planned')), 0, 40) ?: 'planned',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'schedule_created',
            'Schedule created',
            'production_order',
            $productionOrderId,
            ['schedule_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }
}

final class MaterialReservationService
{
    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgMaterialReservation())->query(
            'SELECT * FROM rateb_mfg_material_reservations
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY id ASC',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Meta-only reservation ledger — inventory stock posting deferred.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $componentName = trim((string) ($input['component_name'] ?? ''));
        if ($componentName === '') {
            throw new \InvalidArgumentException('component_name_required');
        }
        $id = (new MfgMaterialReservation())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'bom_line_id' => ManufacturingSupport::intOrNull($input['bom_line_id'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'component_name' => substr($componentName, 0, 190),
            'qty_reserved' => ManufacturingSupport::floatOrZero($input['qty_reserved'] ?? 0),
            'warehouse_id' => ManufacturingSupport::intOrNull($input['warehouse_id'] ?? null),
            'reservation_status' => substr(trim((string) ($input['reservation_status'] ?? 'reserved')), 0, 40) ?: 'reserved',
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'material_reserved',
            'Material reservation recorded (meta): ' . $componentName,
            'production_order',
            $productionOrderId,
            ['reservation_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }
}

final class MaterialConsumptionService
{
    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgMaterialConsumption())->query(
            'SELECT * FROM rateb_mfg_material_consumptions
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY consumed_at DESC, id DESC',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Meta-only consumption ledger — stock posting deferred.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $componentName = trim((string) ($input['component_name'] ?? ''));
        if ($componentName === '') {
            throw new \InvalidArgumentException('component_name_required');
        }
        $consumedAt = ManufacturingSupport::dateOrNull($input['consumed_at'] ?? null) ?? date('Y-m-d H:i:s');
        $id = (new MfgMaterialConsumption())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'work_order_id' => ManufacturingSupport::intOrNull($input['work_order_id'] ?? null),
            'reservation_id' => ManufacturingSupport::intOrNull($input['reservation_id'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'component_name' => substr($componentName, 0, 190),
            'qty_consumed' => ManufacturingSupport::floatOrZero($input['qty_consumed'] ?? 0),
            'uom' => substr(trim((string) ($input['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'warehouse_id' => ManufacturingSupport::intOrNull($input['warehouse_id'] ?? null),
            'batch_code' => ManufacturingSupport::nullIfEmpty($input['batch_code'] ?? null),
            'serial_code' => ManufacturingSupport::nullIfEmpty($input['serial_code'] ?? null),
            'consumed_at' => $consumedAt,
            'status' => 'posted',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'material_consumed',
            'Material consumption recorded (meta): ' . $componentName,
            'production_order',
            $productionOrderId,
            ['consumption_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }
}

final class FinishedGoodsReceiptService
{
    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgFinishedGoodsReceipt())->query(
            'SELECT * FROM rateb_mfg_finished_goods_receipts
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY received_at DESC, id DESC',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Meta-only FG receipt — inventory posting deferred. Updates production_order qty_completed.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        $po = ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $qty = ManufacturingSupport::floatOrZero($input['qty_received'] ?? 0);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('qty_received_required');
        }
        $productId = ManufacturingSupport::intOrNull($input['product_id'] ?? null) ?? (int) ($po['product_id'] ?? 0);
        if ($productId < 1) {
            throw new \InvalidArgumentException('product_id_required');
        }
        ManufacturingSupport::assertProduct($productId, $companyId);
        $receivedAt = ManufacturingSupport::dateOrNull($input['received_at'] ?? null) ?? date('Y-m-d H:i:s');
        $id = (new MfgFinishedGoodsReceipt())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'product_id' => $productId,
            'variant_id' => ManufacturingSupport::intOrNull($input['variant_id'] ?? null)
                ?? ManufacturingSupport::intOrNull($po['variant_id'] ?? null),
            'inventory_item_id' => ManufacturingSupport::intOrNull($input['inventory_item_id'] ?? null),
            'qty_received' => $qty,
            'uom' => substr(trim((string) ($input['uom'] ?? $po['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'warehouse_id' => ManufacturingSupport::intOrNull($input['warehouse_id'] ?? null)
                ?? ManufacturingSupport::intOrNull($po['warehouse_id'] ?? null),
            'batch_code' => ManufacturingSupport::nullIfEmpty($input['batch_code'] ?? null),
            'serial_code' => ManufacturingSupport::nullIfEmpty($input['serial_code'] ?? null),
            'received_at' => $receivedAt,
            'status' => 'posted',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new MfgProductionOrder())->update($productionOrderId, array_merge([
            'qty_completed' => (float) ($po['qty_completed'] ?? 0) + $qty,
            'version' => (int) ($po['version'] ?? 1) + 1,
        ], ManufacturingSupport::actorFields(false)));

        (new ManufacturingTimelineService())->record(
            'finished_goods_received',
            'Finished goods receipt recorded (meta): ' . $qty,
            'production_order',
            $productionOrderId,
            ['receipt_id' => (int) $id, 'qty' => $qty]
        );

        return ['id' => (int) $id];
    }
}

final class ScrapRecordingService
{
    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgScrapRecord())->query(
            'SELECT * FROM rateb_mfg_scrap_records
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 200',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Meta-only scrap ledger — stock posting deferred. Updates production_order qty_scrap.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        $po = ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $qty = ManufacturingSupport::floatOrZero($input['qty_scrap'] ?? 0);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('qty_scrap_required');
        }
        $scrapAt = ManufacturingSupport::dateOrNull($input['scrap_at'] ?? null) ?? date('Y-m-d H:i:s');
        $id = (new MfgScrapRecord())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'work_order_id' => ManufacturingSupport::intOrNull($input['work_order_id'] ?? null),
            'qty_scrap' => $qty,
            'uom' => substr(trim((string) ($input['uom'] ?? $po['uom'] ?? 'EA')), 0, 40) ?: 'EA',
            'reason_code' => ManufacturingSupport::nullIfEmpty($input['reason_code'] ?? null),
            'reason' => ManufacturingSupport::nullIfEmpty($input['reason'] ?? null),
            'scrap_at' => $scrapAt,
            'status' => 'posted',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new MfgProductionOrder())->update($productionOrderId, array_merge([
            'qty_scrap' => (float) ($po['qty_scrap'] ?? 0) + $qty,
            'version' => (int) ($po['version'] ?? 1) + 1,
        ], ManufacturingSupport::actorFields(false)));

        (new ManufacturingTimelineService())->record(
            'scrap_recorded',
            'Scrap recorded (meta): ' . $qty,
            'production_order',
            $productionOrderId,
            ['scrap_id' => (int) $id, 'qty' => $qty]
        );

        return ['id' => (int) $id];
    }
}

final class QualityCheckService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $productionOrderId = null): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($productionOrderId !== null && $productionOrderId > 0) {
            $where .= ' AND production_order_id = :pid';
            $params['pid'] = $productionOrderId;
        }
        $totalRow = (new MfgQualityCheck())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mfg_quality_checks WHERE ' . $where,
            $params
        );
        $items = (new MfgQualityCheck())->query(
            'SELECT * FROM rateb_mfg_quality_checks WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_quality_checks', 'QC', $companyId);
        }
        $checklistJson = null;
        if (isset($input['checklist_json'])) {
            if (is_array($input['checklist_json'])) {
                $encoded = json_encode($input['checklist_json'], JSON_UNESCAPED_UNICODE);
                $checklistJson = $encoded !== false ? $encoded : null;
            } else {
                $checklistJson = ManufacturingSupport::nullIfEmpty($input['checklist_json']);
            }
        }
        $id = (new MfgQualityCheck())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'work_order_id' => ManufacturingSupport::intOrNull($input['work_order_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'check_type' => substr(trim((string) ($input['check_type'] ?? 'in_process')), 0, 40) ?: 'in_process',
            'result_status' => substr(trim((string) ($input['result_status'] ?? 'pending')), 0, 40) ?: 'pending',
            'checklist_json' => $checklistJson,
            'checked_at' => ManufacturingSupport::dateOrNull($input['checked_at'] ?? null),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'quality_check_created',
            'Quality check created: ' . $title,
            'production_order',
            $productionOrderId,
            ['quality_check_id' => (int) $id]
        );

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class ProductionCostService
{
    /** @return list<array<string, mixed>> */
    public function listByProductionOrder(int $productionOrderId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $rows = (new MfgProductionCost())->query(
            'SELECT * FROM rateb_mfg_production_costs
             WHERE company_id = :cid AND production_order_id = :pid AND deleted_at IS NULL
             ORDER BY cost_date DESC, id DESC',
            ['cid' => $companyId, 'pid' => $productionOrderId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Meta-only cost ledger — GL posting deferred. accounting_ref is a string only.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $productionOrderId = (int) ($input['production_order_id'] ?? 0);
        ManufacturingSupport::assertProductionOrder($productionOrderId, $companyId);
        $amount = ManufacturingSupport::floatOrZero($input['amount'] ?? 0);
        $id = (new MfgProductionCost())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'production_order_id' => $productionOrderId,
            'cost_type' => substr(trim((string) ($input['cost_type'] ?? 'material')), 0, 40) ?: 'material',
            'amount' => $amount,
            'currency_code' => substr(trim((string) ($input['currency_code'] ?? 'SAR')), 0, 3) ?: 'SAR',
            'cost_center_id' => ManufacturingSupport::intOrNull($input['cost_center_id'] ?? null),
            'accounting_ref' => ManufacturingSupport::nullIfEmpty($input['accounting_ref'] ?? null),
            'cost_date' => ManufacturingSupport::dateOrNull($input['cost_date'] ?? null) ?? date('Y-m-d'),
            'status' => 'draft',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'production_cost_created',
            'Production cost recorded (meta): ' . $amount,
            'production_order',
            $productionOrderId,
            ['cost_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }
}

final class ManufacturingAssignmentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $assignee = (int) ($input['assignee_user_id'] ?? 0);
        if ($entityType === '' || $entityId < 1 || $assignee < 1) {
            throw new \InvalidArgumentException('assignment_fields_required');
        }
        $id = (new MfgAssignment())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'assignee_user_id' => $assignee,
            'role_label' => ManufacturingSupport::nullIfEmpty($input['role_label'] ?? null),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        (new ManufacturingTimelineService())->record(
            'assigned',
            'Assigned to user #' . $assignee,
            $entityType,
            $entityId,
            ['assignment_id' => (int) $id]
        );

        return ['id' => (int) $id];
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $rows = (new MfgAssignment())->query(
            'SELECT * FROM rateb_mfg_assignments
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }
}

final class ManufacturingCommentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $body = trim((string) ($input['body'] ?? ''));
        if ($entityType === '' || $entityId < 1 || $body === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        $id = (new MfgComment())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'body' => $body,
            'status' => 'active',
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new MfgComment())->query(
            'SELECT * FROM rateb_mfg_comments
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }
}

final class ManufacturingAttachmentMetaService
{
    /**
     * Metadata only — no binary upload.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $fileName = trim((string) ($input['file_name'] ?? ''));
        if ($entityType === '' || $entityId < 1 || $fileName === '') {
            throw new \InvalidArgumentException('attachment_meta_fields_required');
        }
        $id = (new MfgAttachmentMeta())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'file_name' => substr($fileName, 0, 190),
            'mime_type' => ManufacturingSupport::nullIfEmpty($input['mime_type'] ?? null),
            'file_size' => ManufacturingSupport::intOrNull($input['file_size'] ?? null),
            'storage_key' => ManufacturingSupport::nullIfEmpty($input['storage_key'] ?? null),
            'status' => 'active',
            'notes' => ManufacturingSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class ManufacturingTagService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = ManufacturingSupport::nextCode('rateb_mfg_tags', 'TAG', $companyId);
        }
        $id = (new MfgTag())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 120),
            'color' => ManufacturingSupport::nullIfEmpty($input['color'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function attach(array $input): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $tagId = (int) ($input['tag_id'] ?? 0);
        $entityType = substr(trim((string) ($input['entity_type'] ?? '')), 0, 40);
        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($tagId < 1 || $entityType === '' || $entityId < 1) {
            throw new \InvalidArgumentException('tag_attach_fields_required');
        }
        $tag = (new MfgTag())->queryOne(
            'SELECT id FROM rateb_mfg_tags WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $tagId, 'cid' => $companyId]
        );
        if (!is_array($tag)) {
            throw new \RuntimeException('tag_not_found');
        }
        $existing = (new MfgEntityTag())->queryOne(
            'SELECT id FROM rateb_mfg_entity_tags
             WHERE company_id = :cid AND tag_id = :tid AND entity_type = :et AND entity_id = :eid
             AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'tid' => $tagId, 'et' => $entityType, 'eid' => $entityId]
        );
        if (is_array($existing)) {
            return ['id' => (int) $existing['id']];
        }
        $id = (new MfgEntityTag())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'tag_id' => $tagId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
