<?php

declare(strict_types=1);

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Services\PosSessionService;

/** Minimal DB fixture for POS V2 integration / E2E tests. */
final class PosV2IntegrationFixture
{
    public readonly int $companyId;
    public readonly int $branchId;
    public readonly int $warehouseId;
    public readonly int $userId;
    public readonly int $terminalId;
    public readonly int $shiftId;
    public readonly int $sessionId;
    public readonly int $inventoryId;

    private function __construct(
        int $companyId,
        int $branchId,
        int $warehouseId,
        int $userId,
        int $terminalId,
        int $shiftId,
        int $sessionId,
        int $inventoryId,
    ) {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->warehouseId = $warehouseId;
        $this->userId = $userId;
        $this->terminalId = $terminalId;
        $this->shiftId = $shiftId;
        $this->sessionId = $sessionId;
        $this->inventoryId = $inventoryId;
    }

    public static function isDatabaseAvailable(): bool
    {
        try {
            Database::connection()->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function loadOrNull(): ?self
    {
        if (!self::isDatabaseAvailable()) {
            return null;
        }

        $existingCompany = (int) (getenv('POS_V2_TEST_COMPANY_ID') ?: 0);
        $existingInventory = (int) (getenv('POS_V2_TEST_INVENTORY_ID') ?: 0);
        if ($existingCompany > 0 && $existingInventory > 0) {
            return self::fromEnvironment($existingCompany, $existingInventory);
        }

        if (getenv('POS_V2_INTEGRATION_SEED') !== '1') {
            return null;
        }

        try {
            return self::seed();
        } catch (\Throwable) {
            return null;
        }
    }

    public function bootstrapRuntime(): void
    {
        TenantContext::setCompanyId($this->companyId);
        $_SESSION['rateb_company_id'] = $this->companyId;
        $_SESSION['rateb_user_id'] = $this->userId;

        $session = new PosSessionService();
        $session->bindRegisterContext(
            $this->companyId,
            $this->userId,
            $this->terminalId,
            $this->shiftId,
            $this->branchId,
            $this->warehouseId,
        );
        $session->patch(['db_session_id' => $this->sessionId]);
    }

    /** @return array<string, mixed> */
    public function checkoutScope(string $idempotencyKey): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'warehouse_id' => $this->warehouseId,
            'terminal_id' => $this->terminalId,
            'shift_id' => $this->shiftId,
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'idempotency_key' => $idempotencyKey,
            'coupon_code' => '',
            'points_redeem' => 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function sampleCartLine(string $lineId = 'int-line-1'): array
    {
        return [[
            'id' => $lineId,
            'product_id' => $this->inventoryId,
            'item_name' => 'POS V2 Integration Item',
            'quantity' => 1.0,
            'unit_price' => 10.0,
            'price_source' => 'manual',
            'line_total' => 10.0,
        ]];
    }

    private static function fromEnvironment(int $companyId, int $inventoryId): ?self
    {
        $db = Database::connection();
        $inv = $db->prepare('SELECT company_id, warehouse_id, branch_id FROM rateb_inventory WHERE id = :id LIMIT 1');
        $inv->execute(['id' => $inventoryId]);
        $row = $inv->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $branchId = (int) ($row['branch_id'] ?? getenv('POS_V2_TEST_BRANCH_ID') ?: 0);
        $warehouseId = (int) ($row['warehouse_id'] ?? getenv('POS_V2_TEST_WAREHOUSE_ID') ?: 0);
        $userId = (int) (getenv('POS_V2_TEST_USER_ID') ?: 1);
        $terminalId = (int) (getenv('POS_V2_TEST_TERMINAL_ID') ?: 1);
        $shiftId = (int) (getenv('POS_V2_TEST_SHIFT_ID') ?: 1);
        $sessionId = (int) (getenv('POS_V2_TEST_SESSION_ID') ?: 1);

        return new self($companyId, $branchId, $warehouseId, $userId, $terminalId, $shiftId, $sessionId, $inventoryId);
    }

    private static function seed(): self
    {
        $db = Database::connection();
        $slug = 'pos-v2-integration-' . substr(sha1(RATEB_ROOT), 0, 8);

        $db->prepare(
            'INSERT INTO rateb_companies (name, slug, email, status, modules)
             VALUES (:name, :slug, :email, :status, :modules)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        )->execute([
            'name' => 'POS V2 Integration Co',
            'slug' => $slug,
            'email' => $slug . '@example.test',
            'status' => 'active',
            'modules' => json_encode(['pos' => true, 'inventory' => true, 'accounting' => true]),
        ]);

        $companyId = (int) $db->query("SELECT id FROM rateb_companies WHERE slug = " . $db->quote($slug) . ' LIMIT 1')->fetchColumn();
        if ($companyId < 1) {
            throw new \RuntimeException('Unable to seed company.');
        }

        $db->prepare(
            'INSERT INTO rateb_branches (company_id, name, code, status)
             VALUES (:cid, :name, :code, :status)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        )->execute([
            'cid' => $companyId,
            'name' => 'Integration Branch',
            'code' => 'INT-BR',
            'status' => 'active',
        ]);
        $branchId = (int) $db->query(
            'SELECT id FROM rateb_branches WHERE company_id = ' . $companyId . " AND code = 'INT-BR' LIMIT 1"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO rateb_warehouses (company_id, name, code, status)
             VALUES (:cid, :name, :code, :status)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        )->execute([
            'cid' => $companyId,
            'name' => 'Integration WH',
            'code' => 'INT-WH',
            'status' => 'active',
        ]);
        $warehouseId = (int) $db->query(
            'SELECT id FROM rateb_warehouses WHERE company_id = ' . $companyId . " AND code = 'INT-WH' LIMIT 1"
        )->fetchColumn();

        $email = $slug . '-user@example.test';
        $db->prepare(
            'INSERT INTO rateb_users (company_id, name, email, password, status)
             VALUES (:cid, :name, :email, :password, :status)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        )->execute([
            'cid' => $companyId,
            'name' => 'POS Integration User',
            'email' => $email,
            'password' => password_hash('test', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);
        $userId = (int) $db->query('SELECT id FROM rateb_users WHERE email = ' . $db->quote($email) . ' LIMIT 1')->fetchColumn();

        $db->prepare(
            'INSERT INTO rateb_inventory (company_id, warehouse_id, branch_id, item_name, sku, quantity, unit, unit_cost, sell_price, status)
             VALUES (:cid, :wid, :bid, :name, :sku, :qty, :unit, :cost, :sell, :status)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), sell_price = VALUES(sell_price)'
        )->execute([
            'cid' => $companyId,
            'wid' => $warehouseId,
            'bid' => $branchId,
            'name' => 'Integration Product',
            'sku' => 'INT-SKU-1',
            'qty' => 500,
            'unit' => 'ea',
            'cost' => 5.0,
            'sell' => 10.0,
            'status' => 'active',
        ]);
        $inventoryId = (int) $db->query(
            'SELECT id FROM rateb_inventory WHERE company_id = ' . $companyId . " AND sku = 'INT-SKU-1' LIMIT 1"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO rateb_pos_terminals (company_id, branch_id, warehouse_id, code, name, status)
             VALUES (:cid, :bid, :wid, :code, :name, :status)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        )->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'wid' => $warehouseId,
            'code' => 'INT-T1',
            'name' => 'Integration Terminal',
            'status' => 'active',
        ]);
        $terminalId = (int) $db->query(
            'SELECT id FROM rateb_pos_terminals WHERE company_id = ' . $companyId . " AND code = 'INT-T1' LIMIT 1"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO rateb_pos_shifts (company_id, branch_id, terminal_id, user_id, shift_no, status)
             VALUES (:cid, :bid, :tid, :uid, :sno, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        )->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'tid' => $terminalId,
            'uid' => $userId,
            'sno' => 'INT-SHIFT-1',
            'status' => 'open',
        ]);
        $shiftId = (int) $db->query(
            'SELECT id FROM rateb_pos_shifts WHERE company_id = ' . $companyId . " AND shift_no = 'INT-SHIFT-1' LIMIT 1"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO rateb_pos_sessions (company_id, branch_id, terminal_id, user_id, shift_id, status)
             VALUES (:cid, :bid, :tid, :uid, :sid, :status)'
        )->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'tid' => $terminalId,
            'uid' => $userId,
            'sid' => $shiftId,
            'status' => 'active',
        ]);
        $sessionId = (int) $db->lastInsertId();
        if ($sessionId < 1) {
            $sessionId = (int) $db->query(
                'SELECT id FROM rateb_pos_sessions WHERE company_id = ' . $companyId . ' ORDER BY id DESC LIMIT 1'
            )->fetchColumn();
        }

        return new self($companyId, $branchId, $warehouseId, $userId, $terminalId, $shiftId, $sessionId, $inventoryId);
    }
}
