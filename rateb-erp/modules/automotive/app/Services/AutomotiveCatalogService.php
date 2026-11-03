<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Catalog writes for automotive parts. Never inserts into rateb_inventory.
 */
final class AutomotiveCatalogService
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }

    /** @return list<array<string, mixed>> */
    public function listParts(int $companyId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT p.*, i.item_name, i.sku, i.item_code
             FROM rateb_auto_parts p
             INNER JOIN rateb_inventory i ON i.id = p.inventory_id AND i.company_id = p.company_id
             WHERE p.company_id = :cid
             ORDER BY p.id DESC'
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function inventoryChoices(int $companyId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT i.id, i.item_name, i.sku, i.item_code
             FROM rateb_inventory i
             LEFT JOIN rateb_auto_parts p ON p.inventory_id = i.id
             WHERE i.company_id = :cid AND p.id IS NULL
             ORDER BY i.item_name ASC
             LIMIT 500'
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    public function partDetail(int $companyId, int $partId): array
    {
        $part = $this->requirePart($companyId, $partId);
        $part['numbers'] = $this->rows(
            'SELECT * FROM rateb_auto_part_numbers WHERE company_id = :cid AND part_id = :pid ORDER BY id',
            ['cid' => $companyId, 'pid' => $partId]
        );
        $part['barcodes'] = $this->rows(
            'SELECT * FROM rateb_auto_barcodes WHERE company_id = :cid AND part_id = :pid ORDER BY id',
            ['cid' => $companyId, 'pid' => $partId]
        );
        $part['fitment'] = $this->rows(
            'SELECT f.id, v.make, v.model, v.model_year, v.engine, v.trim
             FROM rateb_auto_fitment f
             INNER JOIN rateb_auto_vehicles v ON v.id = f.vehicle_id AND v.company_id = f.company_id
             WHERE f.company_id = :cid AND f.part_id = :pid
             ORDER BY v.make, v.model, v.model_year',
            ['cid' => $companyId, 'pid' => $partId]
        );
        $part['interchange'] = $this->rows(
            'SELECT x.id, x.alternate_inventory_id, x.relation_type, i.item_name, i.sku
             FROM rateb_auto_interchange x
             INNER JOIN rateb_inventory i ON i.id = x.alternate_inventory_id AND i.company_id = x.company_id
             WHERE x.company_id = :cid AND x.part_id = :pid
             ORDER BY x.id',
            ['cid' => $companyId, 'pid' => $partId]
        );
        return $part;
    }

    /** @return list<array<string, mixed>> */
    public function inventoryExcept(int $companyId, int $exceptInventoryId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, item_name, sku, item_code
             FROM rateb_inventory
             WHERE company_id = :cid AND id <> :except
             ORDER BY item_name ASC
             LIMIT 500'
        );
        $stmt->execute(['cid' => $companyId, 'except' => $exceptInventoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listVehicles(int $companyId): array
    {
        return $this->rows(
            'SELECT * FROM rateb_auto_vehicles WHERE company_id = :cid ORDER BY make, model, model_year',
            ['cid' => $companyId]
        );
    }

    public function createPart(
        int $companyId,
        int $inventoryId,
        string $manufacturer,
        string $brand,
        string $oemNumber,
        string $status
    ): int {
        $this->assertCompany($companyId);
        if (!$this->inventoryInCompany($companyId, $inventoryId)) {
            throw new \InvalidArgumentException('Inventory item is not in this company.');
        }
        if ($this->partIdForInventory($inventoryId) !== null) {
            throw new \InvalidArgumentException('This inventory item is already linked to an automotive part.');
        }
        $oem = $this->normalizeCode($oemNumber);
        $status = $this->normalizeStatus($status);
        if ($oem !== null) {
            $this->assertCodeAvailable($companyId, $oem);
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_parts (company_id, inventory_id, manufacturer, brand, oem_number, status)
             VALUES (:cid, :inv, :mfr, :brand, :oem, :status)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'inv' => $inventoryId,
            'mfr' => $this->blankToNull($manufacturer),
            'brand' => $this->blankToNull($brand),
            'oem' => $oem,
            'status' => $status,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function addNumber(int $companyId, int $partId, string $code, string $codeType): int
    {
        $this->requirePart($companyId, $partId);
        $normalized = $this->requireCode($code);
        $type = $this->normalizeNumberType($codeType);
        $this->assertCodeAvailable($companyId, $normalized);
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_part_numbers (company_id, part_id, code, code_type)
             VALUES (:cid, :pid, :code, :type)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'pid' => $partId,
            'code' => $normalized,
            'type' => $type,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function addBarcode(int $companyId, int $partId, string $code): int
    {
        $this->requirePart($companyId, $partId);
        $normalized = $this->requireCode($code);
        $this->assertCodeAvailable($companyId, $normalized);
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_barcodes (company_id, part_id, code) VALUES (:cid, :pid, :code)'
        );
        $stmt->execute(['cid' => $companyId, 'pid' => $partId, 'code' => $normalized]);
        return (int) $this->db()->lastInsertId();
    }

    public function createVehicle(
        int $companyId,
        string $make,
        string $model,
        int $year,
        string $engine,
        string $trim
    ): int {
        $this->assertCompany($companyId);
        $make = trim($make);
        $model = trim($model);
        if ($make === '' || $model === '' || $year < 1900 || $year > 2100) {
            throw new \InvalidArgumentException('Vehicle make, model, and year are required.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_vehicles (company_id, make, model, model_year, engine, trim)
             VALUES (:cid, :make, :model, :year, :engine, :trim)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'engine' => trim($engine),
            'trim' => trim($trim),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function addFitment(int $companyId, int $partId, int $vehicleId): int
    {
        $this->requirePart($companyId, $partId);
        $vehicle = $this->db()->prepare(
            'SELECT id FROM rateb_auto_vehicles WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $vehicle->execute(['id' => $vehicleId, 'cid' => $companyId]);
        if (!$vehicle->fetch()) {
            throw new \InvalidArgumentException('Vehicle is not in this company.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_fitment (company_id, part_id, vehicle_id) VALUES (:cid, :pid, :vid)'
        );
        $stmt->execute(['cid' => $companyId, 'pid' => $partId, 'vid' => $vehicleId]);
        return (int) $this->db()->lastInsertId();
    }

    public function addInterchange(int $companyId, int $partId, int $alternateInventoryId): int
    {
        $part = $this->requirePart($companyId, $partId);
        if ($alternateInventoryId === (int) $part['inventory_id']) {
            throw new \InvalidArgumentException('A part cannot interchange with itself.');
        }
        if (!$this->inventoryInCompany($companyId, $alternateInventoryId)) {
            throw new \InvalidArgumentException('Alternate inventory item is not in this company.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_interchange (company_id, part_id, alternate_inventory_id, relation_type)
             VALUES (:cid, :pid, :alt, :rel)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'pid' => $partId,
            'alt' => $alternateInventoryId,
            'rel' => 'interchange',
        ]);
        return (int) $this->db()->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function requirePart(int $companyId, int $partId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT p.*, i.item_name, i.sku, i.item_code
             FROM rateb_auto_parts p
             INNER JOIN rateb_inventory i ON i.id = p.inventory_id AND i.company_id = p.company_id
             WHERE p.id = :id AND p.company_id = :cid
             LIMIT 1'
        );
        $stmt->execute(['id' => $partId, 'cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('Automotive part was not found in this company.');
        }
        return $row;
    }

    private function inventoryInCompany(int $companyId, int $inventoryId): bool
    {
        if ($inventoryId < 1) {
            return false;
        }
        $stmt = $this->db()->prepare(
            'SELECT id FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        return (bool) $stmt->fetch();
    }

    private function partIdForInventory(int $inventoryId): ?int
    {
        $stmt = $this->db()->prepare('SELECT id FROM rateb_auto_parts WHERE inventory_id = :id LIMIT 1');
        $stmt->execute(['id' => $inventoryId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function assertCodeAvailable(int $companyId, string $code): void
    {
        $queries = [
            'SELECT id FROM rateb_auto_parts WHERE company_id = :cid AND oem_number = :code LIMIT 1',
            'SELECT id FROM rateb_auto_part_numbers WHERE company_id = :cid AND code = :code LIMIT 1',
            'SELECT id FROM rateb_auto_barcodes WHERE company_id = :cid AND code = :code LIMIT 1',
        ];
        foreach ($queries as $sql) {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['cid' => $companyId, 'code' => $code]);
            if ($stmt->fetch()) {
                throw new \InvalidArgumentException('This OEM number, alternate number, or barcode is already used in the company.');
            }
        }
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId < 1) {
            throw new \InvalidArgumentException('Company is required.');
        }
    }

    private function requireCode(string $code): string
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Code is required.');
        }
        return $normalized;
    }

    private function normalizeCode(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        if (strlen($code) > 80) {
            throw new \InvalidArgumentException('Code is too long.');
        }
        return $code;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'superseded', 'obsolete'], true)) {
            return 'active';
        }
        return $status;
    }

    private function normalizeNumberType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['oem', 'aftermarket', 'supplier'], true)) {
            throw new \InvalidArgumentException('Number type must be OEM, aftermarket, or supplier.');
        }
        return $type;
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
