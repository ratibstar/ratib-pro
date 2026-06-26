<?php
declare(strict_types=1);

use Rateb\App\Core\Database;

/**
 * Realistic staging volumes with deterministic pseudo-random data (not pure random).
 */
final class EnterpriseSeeder
{
    private const TARGET_COMPANIES = 10;
    private const TARGET_BRANCHES = 50;
    private const TARGET_USERS = 500;
    private const TARGET_EMPLOYEES = 1000;
    private const TARGET_CUSTOMERS = 10000;
    private const TARGET_INVOICES = 50000;
    private const TARGET_JOURNALS = 100000;
    private const TARGET_STOCK_MOVES = 250000;
    private const TARGET_WAREHOUSES = 100;
    private const TARGET_ASSETS = 500;
    private const TARGET_CONTRACTS = 500;

    private \PDO $db;
    /** @var array<int,int> */
    private array $companyIds = [];
    /** @var array<int,int> */
    private array $branchIds = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Idempotent GL + platform role backfill when migrations 128/132 were not applied. */
    public function backfillPrerequisites(): void
    {
        $this->ensureInterBranchGlAccounts();
        $this->ensurePlatformEnterpriseRoles();
    }

    public function seedCompanies(): void
    {
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_companies')->fetchColumn();
        $need = max(0, self::TARGET_COMPANIES - $existing);
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $slug = 'ent-co-' . $n;
            $stmt = $this->db->prepare(
                'INSERT INTO rateb_companies (name, slug, status, locale) VALUES (:name, :slug, :st, :loc)'
            );
            $stmt->execute([
                'name' => 'Enterprise Co ' . $n,
                'slug' => $slug,
                'st' => 'active',
                'loc' => $n % 2 === 0 ? 'ar' : 'en',
            ]);
        }
        $this->companyIds = array_map('intval', $this->db->query(
            'SELECT id FROM rateb_companies ORDER BY id ASC LIMIT ' . self::TARGET_COMPANIES
        )->fetchAll(\PDO::FETCH_COLUMN));
        $this->ensureInterBranchGlAccounts();
        echo '  companies: ' . count($this->companyIds) . "\n";
    }

    private function ensureInterBranchGlAccounts(): void
    {
        if (!$this->tableExists('rateb_chart_of_accounts')) {
            return;
        }
        $this->db->exec(
            "INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
             SELECT c.id, '1350', 'Due From Branches', 'Due From Branches', 'asset', p.id, 1
             FROM rateb_companies c
             LEFT JOIN rateb_chart_of_accounts p ON p.company_id = c.id AND p.code = '1000'
             WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts x WHERE x.company_id = c.id AND x.code = '1350')"
        );
        $this->db->exec(
            "INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
             SELECT c.id, '2150', 'Due To Branches', 'Due To Branches', 'liability', p.id, 1
             FROM rateb_companies c
             LEFT JOIN rateb_chart_of_accounts p ON p.company_id = c.id AND p.code = '2000'
             WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts x WHERE x.company_id = c.id AND x.code = '2150')"
        );
    }

    /** Insert HO/branch roles once per slug (rateb_roles.slug is globally unique). */
    private function ensurePlatformEnterpriseRoles(): void
    {
        if (!$this->tableExists('rateb_roles')) {
            return;
        }
        $companyId = (int) $this->db->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($companyId < 1) {
            return;
        }
        $roles = [
            ['HQ Admin', 'hq_admin', 'Head office — all branches'],
            ['HQ Manager', 'hq_manager', 'Head office manager — all branches read/compare'],
            ['Branch Manager', 'branch_manager', 'Single-branch manager'],
        ];
        $insert = $this->db->prepare(
            'INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
             SELECT :cid, :name, :slug, :desc, 1
             WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.slug = :slug_chk)'
        );
        foreach ($roles as [$name, $slug, $desc]) {
            $insert->execute([
                'cid' => $companyId,
                'name' => $name,
                'slug' => $slug,
                'desc' => $desc,
                'slug_chk' => $slug,
            ]);
        }
    }

    public function seedBranches(): void
    {
        $this->loadCompanies();
        $cities = ['Riyadh', 'Jeddah', 'Dammam', 'Makkah', 'Madinah', 'Khobar', 'Tabuk', 'Abha'];
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_branches')->fetchColumn();
        $need = max(0, self::TARGET_BRANCHES - $existing);
        $idx = 0;
        while ($need > 0 && $idx < 5000) {
            $cid = $this->companyIds[$idx % max(1, count($this->companyIds))];
            $code = 'B' . str_pad((string) ($existing + $idx + 1), 3, '0', STR_PAD_LEFT);
            $city = $cities[$idx % count($cities)];
            try {
                $this->db->prepare(
                    'INSERT INTO rateb_branches (company_id, name, code, status) VALUES (:cid, :name, :code, :st)'
                )->execute([
                    'cid' => $cid,
                    'name' => $city . ' Branch ' . ($idx + 1),
                    'code' => $code,
                    'st' => 'active',
                ]);
                $need--;
            } catch (\Throwable $e) {
                // duplicate code — skip
            }
            $idx++;
        }
        $this->branchIds = array_map('intval', $this->db->query(
            'SELECT id FROM rateb_branches ORDER BY id ASC LIMIT ' . self::TARGET_BRANCHES
        )->fetchAll(\PDO::FETCH_COLUMN));
        echo '  branches: ' . count($this->branchIds) . "\n";
    }

    public function seedUsers(): void
    {
        $this->loadCompanies();
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_users WHERE is_super_admin = 0')->fetchColumn();
        $need = max(0, self::TARGET_USERS - $existing);
        $hash = password_hash('Enterprise@2026', PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO rateb_users (company_id, name, email, password, status, locale)
             VALUES (:cid, :name, :email, :pass, :st, :loc)'
        );
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $stmt->execute([
                'cid' => $cid,
                'name' => 'User ' . $n,
                'email' => 'ent.user' . $n . '@staging.rateb.test',
                'pass' => $hash,
                'st' => 'active',
                'loc' => 'ar',
            ]);
        }
        echo "  users target: " . self::TARGET_USERS . " (added {$need})\n";
    }

    public function seedEmployees(): void
    {
        $this->loadCompanies();
        $this->loadBranches();
        if (!$this->columnExists('rateb_employees', 'branch_id')) {
            echo "  skip employees — no branch_id\n";
            return;
        }
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_employees')->fetchColumn();
        $need = max(0, self::TARGET_EMPLOYEES - $existing);
        $stmt = $this->db->prepare(
            'INSERT INTO rateb_employees (company_id, employee_code, name, branch_id, salary_base, status)
             VALUES (:cid, :code, :name, :bid, :sal, :st)'
        );
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $bid = $this->branchIds[$n % max(1, count($this->branchIds))];
            $stmt->execute([
                'cid' => $cid,
                'code' => 'EMP-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                'name' => 'Employee ' . $n,
                'bid' => $bid,
                'sal' => 3000 + ($n % 120) * 100,
                'st' => 'active',
            ]);
        }
        echo "  employees target: " . self::TARGET_EMPLOYEES . " (added {$need})\n";
    }

    public function seedCustomers(): void
    {
        $this->loadCompanies();
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_customers')->fetchColumn();
        $need = max(0, self::TARGET_CUSTOMERS - $existing);
        $hasBranch = $this->columnExists('rateb_customers', 'branch_id');
        $this->loadBranches();
        $sql = $hasBranch
            ? 'INSERT INTO rateb_customers (company_id, code, name, branch_id, status) VALUES (:cid, :code, :name, :bid, :st)'
            : 'INSERT INTO rateb_customers (company_id, code, name, status) VALUES (:cid, :code, :name, :st)';
        $stmt = $this->db->prepare($sql);
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $params = [
                'cid' => $cid,
                'code' => 'CUST-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'name' => 'Customer ' . $n,
                'st' => 'active',
            ];
            if ($hasBranch) {
                $params['bid'] = $this->branchIds[$n % max(1, count($this->branchIds))];
            }
            $stmt->execute($params);
            if ($i > 0 && $i % 2000 === 0) {
                echo "    customers {$i}/{$need}\n";
            }
        }
        echo "  customers target: " . self::TARGET_CUSTOMERS . " (added {$need})\n";
    }

    public function seedWarehouses(): void
    {
        if (!$this->tableExists('rateb_warehouses')) {
            return;
        }
        $this->loadCompanies();
        $this->loadBranches();
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_warehouses')->fetchColumn();
        $need = max(0, self::TARGET_WAREHOUSES - $existing);
        $hasBranch = $this->columnExists('rateb_warehouses', 'branch_id');
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $bid = $this->branchIds[$n % max(1, count($this->branchIds))];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_warehouses (company_id, code, name, branch_id, status) VALUES (:cid, :code, :name, :bid, :st)'
                )->execute(['cid' => $cid, 'code' => 'WH' . $n, 'name' => 'Warehouse ' . $n, 'bid' => $bid, 'st' => 'active']);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_warehouses (company_id, code, name, status) VALUES (:cid, :code, :name, :st)'
                )->execute(['cid' => $cid, 'code' => 'WH' . $n, 'name' => 'Warehouse ' . $n, 'st' => 'active']);
            }
        }
        echo "  warehouses target: " . self::TARGET_WAREHOUSES . " (added {$need})\n";
    }

    public function seedInventory(): void
    {
        if (!$this->tableExists('rateb_inventory')) {
            return;
        }
        $count = (int) $this->db->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
        if ($count >= 5000) {
            echo "  inventory rows sufficient: {$count}\n";
            return;
        }
        $this->loadCompanies();
        $this->loadBranches();
        $need = 5000 - $count;
        $hasBranch = $this->columnExists('rateb_inventory', 'branch_id');
        for ($i = 0; $i < $need; $i++) {
            $n = $count + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $data = [
                'cid' => $cid,
                'code' => 'SKU-' . $n,
                'name' => 'Item ' . $n,
                'qty' => 50 + ($n % 200),
                'cost' => 10 + ($n % 50),
                'st' => 'active',
            ];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_inventory (company_id, item_code, item_name, branch_id, quantity, unit_cost, status)
                     VALUES (:cid, :code, :name, :bid, :qty, :cost, :st)'
                )->execute($data + ['bid' => $this->branchIds[$n % max(1, count($this->branchIds))]]);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_inventory (company_id, item_code, item_name, quantity, unit_cost, status)
                     VALUES (:cid, :code, :name, :qty, :cost, :st)'
                )->execute($data);
            }
        }
        echo "  inventory added: {$need}\n";
    }

    public function seedStockMovements(): void
    {
        if (!$this->tableExists('rateb_stock_movements')) {
            return;
        }
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_stock_movements')->fetchColumn();
        $need = max(0, min(5000, self::TARGET_STOCK_MOVES - $existing));
        if ($need < 1) {
            echo "  stock movements sufficient\n";
            return;
        }
        $items = $this->db->query('SELECT id, company_id FROM rateb_inventory ORDER BY id ASC LIMIT 500')->fetchAll(\PDO::FETCH_ASSOC);
        if ($items === []) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO rateb_stock_movements (company_id, movement_no, inventory_id, movement_type, quantity, created_at)
             VALUES (:cid, :no, :iid, :type, :qty, :at)'
        );
        for ($i = 0; $i < $need; $i++) {
            $item = $items[$i % count($items)];
            $stmt->execute([
                'cid' => (int) $item['company_id'],
                'no' => 'MV-' . ($existing + $i + 1),
                'iid' => (int) $item['id'],
                'type' => $i % 2 === 0 ? 'in' : 'out',
                'qty' => 1 + ($i % 10),
                'at' => date('Y-m-d H:i:s', strtotime('-' . ($i % 365) . ' days')),
            ]);
        }
        echo "  stock movements batch added: {$need} (run again for more toward " . self::TARGET_STOCK_MOVES . ")\n";
    }

    public function seedAssets(): void
    {
        if (!$this->tableExists('rateb_assets')) {
            return;
        }
        $this->loadCompanies();
        $this->loadBranches();
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_assets')->fetchColumn();
        $need = max(0, self::TARGET_ASSETS - $existing);
        $hasBranch = $this->columnExists('rateb_assets', 'branch_id');
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_assets (company_id, asset_tag, name, branch_id, purchase_cost, current_value, status)
                     VALUES (:cid, :tag, :name, :bid, :pc, :cv, :st)'
                )->execute([
                    'cid' => $cid,
                    'tag' => 'AST-' . $n,
                    'name' => 'Asset ' . $n,
                    'bid' => $this->branchIds[$n % max(1, count($this->branchIds))],
                    'pc' => 5000 + ($n % 100) * 500,
                    'cv' => 4000 + ($n % 100) * 400,
                    'st' => 'active',
                ]);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_assets (company_id, asset_tag, name, purchase_cost, current_value, status)
                     VALUES (:cid, :tag, :name, :pc, :cv, :st)'
                )->execute([
                    'cid' => $cid,
                    'tag' => 'AST-' . $n,
                    'name' => 'Asset ' . $n,
                    'pc' => 5000,
                    'cv' => 4000,
                    'st' => 'active',
                ]);
            }
        }
        echo "  assets target: " . self::TARGET_ASSETS . " (added {$need})\n";
    }

    public function seedContracts(): void
    {
        if (!$this->tableExists('rateb_contracts')) {
            return;
        }
        $this->loadCompanies();
        $this->loadBranches();
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_contracts')->fetchColumn();
        $need = max(0, self::TARGET_CONTRACTS - $existing);
        $hasBranch = $this->columnExists('rateb_contracts', 'branch_id');
        for ($i = 0; $i < $need; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $params = [
                'cid' => $cid,
                'no' => 'CNT-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                'title' => 'Contract ' . $n,
                'val' => 10000 + ($n % 50) * 1000,
                'st' => 'active',
                'sd' => date('Y-m-d', strtotime('-' . ($n % 400) . ' days')),
                'ed' => date('Y-m-d', strtotime('+' . (365 - ($n % 365)) . ' days')),
            ];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_contracts (company_id, contract_no, title, branch_id, value, status, start_date, end_date)
                     VALUES (:cid, :no, :title, :bid, :val, :st, :sd, :ed)'
                )->execute($params + ['bid' => $this->branchIds[$n % max(1, count($this->branchIds))]]);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_contracts (company_id, contract_no, title, value, status, start_date, end_date)
                     VALUES (:cid, :no, :title, :val, :st, :sd, :ed)'
                )->execute($params);
            }
        }
        echo "  contracts target: " . self::TARGET_CONTRACTS . " (added {$need})\n";
    }

    public function seedJournalEntries(): void
    {
        if (!$this->tableExists('rateb_journal_entries')) {
            return;
        }
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_journal_entries')->fetchColumn();
        $batch = min(2000, max(0, self::TARGET_JOURNALS - $existing));
        if ($batch < 1) {
            echo "  journal entries sufficient\n";
            return;
        }
        $this->loadCompanies();
        $this->loadBranches();
        $accounts = $this->db->query(
            'SELECT id, company_id FROM rateb_chart_of_accounts WHERE code IN (\'1000\',\'1300\',\'4000\',\'5100\') LIMIT 200'
        )->fetchAll(\PDO::FETCH_ASSOC);
        if (count($accounts) < 2) {
            echo "  skip journals — need chart of accounts\n";
            return;
        }
        $hasBranch = $this->columnExists('rateb_journal_entries', 'branch_id');
        for ($i = 0; $i < $batch; $i++) {
            $n = $existing + $i + 1;
            $cid = $this->companyIds[$n % max(1, count($this->companyIds))];
            $a1 = $accounts[$n % count($accounts)];
            $a2 = $accounts[($n + 1) % count($accounts)];
            if ((int) $a1['company_id'] !== $cid) {
                continue;
            }
            $entry = [
                'cid' => $cid,
                'no' => 'JE-SEED-' . $n,
                'dt' => date('Y-m-d', strtotime('-' . ($n % 300) . ' days')),
                'desc' => 'Seed entry ' . $n,
                'st' => 'posted',
            ];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_journal_entries (company_id, entry_no, entry_date, description, source_type, status, branch_id, posted_at)
                     VALUES (:cid, :no, :dt, :desc, \'manual\', :st, :bid, NOW())'
                )->execute($entry + ['bid' => $this->branchIds[$n % max(1, count($this->branchIds))]]);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_journal_entries (company_id, entry_no, entry_date, description, source_type, status, posted_at)
                     VALUES (:cid, :no, :dt, :desc, \'manual\', :st, NOW())'
                )->execute($entry);
            }
            $eid = (int) $this->db->lastInsertId();
            $amt = 100 + ($n % 50) * 10;
            $this->db->prepare(
                'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (:eid, :a1, :dr, 0, :m1)'
            )->execute(['eid' => $eid, 'a1' => (int) $a1['id'], 'dr' => $amt, 'm1' => 'Dr']);
            $this->db->prepare(
                'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (:eid, :a2, 0, :cr, :m2)'
            )->execute(['eid' => $eid, 'a2' => (int) $a2['id'], 'cr' => $amt, 'm2' => 'Cr']);
        }
        echo "  journal entries batch: {$batch} (re-run toward " . self::TARGET_JOURNALS . ")\n";
    }

    public function seedInvoices(): void
    {
        if (!$this->tableExists('rateb_invoices')) {
            return;
        }
        $existing = (int) $this->db->query('SELECT COUNT(*) FROM rateb_invoices')->fetchColumn();
        $batch = min(2000, max(0, self::TARGET_INVOICES - $existing));
        if ($batch < 1) {
            echo "  invoices sufficient\n";
            return;
        }
        $this->loadCompanies();
        $customers = $this->db->query('SELECT id, company_id FROM rateb_customers ORDER BY id ASC LIMIT 500')->fetchAll(\PDO::FETCH_ASSOC);
        if ($customers === []) {
            echo "  skip invoices — no customers\n";
            return;
        }
        $hasBranch = $this->columnExists('rateb_invoices', 'branch_id');
        $this->loadBranches();
        for ($i = 0; $i < $batch; $i++) {
            $n = $existing + $i + 1;
            $cust = $customers[$n % count($customers)];
            $cid = (int) $cust['company_id'];
            $params = [
                'cid' => $cid,
                'no' => 'INV-SEED-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'cust' => (int) $cust['id'],
                'amt' => 500 + ($n % 100) * 25,
                'dt' => date('Y-m-d', strtotime('-' . ($n % 400) . ' days')),
                'st' => $n % 5 === 0 ? 'draft' : 'posted',
            ];
            if ($hasBranch) {
                $this->db->prepare(
                    'INSERT INTO rateb_invoices (company_id, invoice_no, customer_id, branch_id, total_amount, invoice_date, status)
                     VALUES (:cid, :no, :cust, :bid, :amt, :dt, :st)'
                )->execute($params + ['bid' => $this->branchIds[$n % max(1, count($this->branchIds))]]);
            } else {
                $this->db->prepare(
                    'INSERT INTO rateb_invoices (company_id, invoice_no, customer_id, total_amount, invoice_date, status)
                     VALUES (:cid, :no, :cust, :amt, :dt, :st)'
                )->execute($params);
            }
        }
        echo "  invoices batch: {$batch} (re-run toward " . self::TARGET_INVOICES . ")\n";
    }

    private function loadCompanies(): void
    {
        if ($this->companyIds !== []) {
            return;
        }
        $this->companyIds = array_map('intval', $this->db->query(
            'SELECT id FROM rateb_companies ORDER BY id ASC LIMIT ' . self::TARGET_COMPANIES
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function loadBranches(): void
    {
        if ($this->branchIds !== []) {
            return;
        }
        $this->branchIds = array_map('intval', $this->db->query(
            'SELECT id FROM rateb_branches ORDER BY id ASC LIMIT ' . self::TARGET_BRANCHES
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($table));
        return $stmt !== false && $stmt->fetch() !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
