<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use RuntimeException;

/**
 * Phase B Branch cold-start seed (SQLite-safe SQL only).
 * Additive Core installer — does not modify Controllers/Services/Models.
 */
final class BranchSeedService
{
    public const DEFAULT_LOGIN = 'admin';
    /** Must pass FILTER_VALIDATE_EMAIL (PHP rejects *.local). */
    public const DEFAULT_EMAIL = 'admin@branch.test';
    public const DEFAULT_PASSWORD = '123456';
    public const DEFAULT_COMPANY = 'Branch Appliance';

    /**
     * Seed minimal tenant so company login works offline.
     *
     * @return array{company_id:int,user_id:int,branch_id:int,email:string,password:string}
     */
    public static function seedMinimalTenant(PDO $pdo, string $companyName = self::DEFAULT_COMPANY): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_BCRYPT);

        // Plan
        $planId = self::scalarInt($pdo, "SELECT id FROM rateb_plans WHERE slug = 'professional' LIMIT 1");
        if ($planId < 1) {
            $pdo->prepare(
                'INSERT INTO rateb_plans (name, slug, description, price_monthly, price_yearly, max_users, max_storage_mb, modules, is_active, created_at)
                 VALUES (:n, :s, :d, 0, 0, 50, 10240, :m, 1, :t)'
            )->execute([
                'n' => 'Professional',
                's' => 'professional',
                'd' => 'Branch appliance plan',
                'm' => json_encode(['pos' => true, 'inventory' => true, 'hr' => true, 'accounting' => true], JSON_UNESCAPED_UNICODE),
                't' => $now,
            ]);
            $planId = (int) $pdo->lastInsertId();
        }

        // Company
        $companyId = self::scalarInt($pdo, "SELECT id FROM rateb_companies WHERE slug = 'branch-appliance' LIMIT 1");
        if ($companyId < 1) {
            $pdo->prepare(
                'INSERT INTO rateb_companies (name, slug, email, status, plan_id, storage_limit_mb, user_limit, modules, created_at)
                 VALUES (:n, :s, :e, \'active\', :p, 10240, 50, :m, :t)'
            )->execute([
                'n' => $companyName,
                's' => 'branch-appliance',
                'e' => 'branch@local',
                'p' => $planId,
                'm' => json_encode(['pos' => true, 'inventory' => true], JSON_UNESCAPED_UNICODE),
                't' => $now,
            ]);
            $companyId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE rateb_companies SET status = \'active\', name = :n WHERE id = :id')
                ->execute(['n' => $companyName, 'id' => $companyId]);
        }

        // Subscription
        $subId = self::scalarInt(
            $pdo,
            'SELECT id FROM rateb_subscriptions WHERE company_id = ' . $companyId . ' ORDER BY id DESC LIMIT 1'
        );
        if ($subId < 1) {
            $pdo->prepare(
                'INSERT INTO rateb_subscriptions (company_id, plan_id, status, billing_cycle, amount, starts_at, ends_at, auto_renew, created_at)
                 VALUES (:c, :p, \'active\', \'yearly\', 0, :s, :e, 1, :t)'
            )->execute([
                'c' => $companyId,
                'p' => $planId,
                's' => gmdate('Y-m-d'),
                'e' => gmdate('Y-m-d', strtotime('+10 years')),
                't' => $now,
            ]);
        }

        // Main branch
        $branchId = self::scalarInt(
            $pdo,
            'SELECT id FROM rateb_branches WHERE company_id = ' . $companyId . ' ORDER BY id ASC LIMIT 1'
        );
        if ($branchId < 1) {
            // Detect required columns via pragma
            $cols = self::tableColumns($pdo, 'rateb_branches');
            $fields = ['company_id', 'name', 'code'];
            $values = [':c', ':n', ':code'];
            $params = ['c' => $companyId, 'n' => 'Main', 'code' => 'MAIN'];
            if (isset($cols['is_main'])) {
                $fields[] = 'is_main';
                $values[] = '1';
            }
            if (isset($cols['status'])) {
                $fields[] = 'status';
                $values[] = "'active'";
            }
            if (isset($cols['created_at'])) {
                $fields[] = 'created_at';
                $values[] = ':t';
                $params['t'] = $now;
            }
            $sql = 'INSERT INTO rateb_branches (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $pdo->prepare($sql)->execute($params);
            $branchId = (int) $pdo->lastInsertId();
        }

        // Admin user (company portal — not super admin)
        $userId = self::scalarInt(
            $pdo,
            "SELECT id FROM rateb_users WHERE email = '" . self::DEFAULT_EMAIL . "' LIMIT 1"
        );
        if ($userId < 1) {
            $pdo->prepare(
                'INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale, created_at)
                 VALUES (:c, :n, :e, :p, 0, \'active\', \'ar\', :t)'
            )->execute([
                'c' => $companyId,
                'n' => 'admin',
                'e' => self::DEFAULT_EMAIL,
                'p' => $hash,
                't' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE rateb_users SET company_id = :c, password = :p, status = \'active\', is_super_admin = 0 WHERE id = :id'
            )->execute(['c' => $companyId, 'p' => $hash, 'id' => $userId]);
        }

        // Role + all permissions (full access)
        $roleId = self::scalarInt(
            $pdo,
            'SELECT id FROM rateb_roles WHERE slug = \'company-full-access\' AND (company_id IS NULL OR company_id = '
            . $companyId . ') ORDER BY id ASC LIMIT 1'
        );
        if ($roleId < 1) {
            $pdo->prepare(
                'INSERT INTO rateb_roles (company_id, name, slug, description, is_system, created_at)
                 VALUES (:c, :n, :s, :d, 1, :t)'
            )->execute([
                'c' => $companyId,
                'n' => 'Company Full Access',
                's' => 'company-full-access',
                'd' => 'Branch appliance full access',
                't' => $now,
            ]);
            $roleId = (int) $pdo->lastInsertId();
        }

        // Ensure at least a few core permissions exist and attach all permissions to role
        self::ensureCorePermissions($pdo, $now);
        $pdo->exec(
            'INSERT OR IGNORE INTO rateb_role_permissions (role_id, permission_id)
             SELECT ' . (int) $roleId . ', id FROM rateb_permissions'
        );
        $pdo->prepare(
            'INSERT OR IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (:u, :r)'
        )->execute(['u' => $userId, 'r' => $roleId]);

        // Bind user to branch if table supports it
        if (self::tableExists($pdo, 'rateb_user_branches')) {
            $pdo->prepare(
                'INSERT OR IGNORE INTO rateb_user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :t)'
            )->execute(['u' => $userId, 'b' => $branchId, 't' => $now]);
        }

        SqliteSchemaBootstrap::upsertMeta($pdo, 'branch_seeded', '1');
        SqliteSchemaBootstrap::upsertMeta($pdo, 'branch_admin_email', self::DEFAULT_EMAIL);

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'email' => self::DEFAULT_EMAIL,
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    private static function ensureCorePermissions(PDO $pdo, string $now): void
    {
        $count = self::scalarInt($pdo, 'SELECT COUNT(*) FROM rateb_permissions');
        if ($count > 0) {
            return;
        }
        $core = [
            ['Dashboard', 'dashboard.view', 'core'],
            ['POS', 'pos.view', 'pos'],
            ['POS Manage', 'pos.manage', 'pos'],
            ['Inventory', 'inventory.view', 'inventory'],
            ['Settings', 'settings.manage', 'settings'],
        ];
        $ins = $pdo->prepare(
            'INSERT INTO rateb_permissions (name, slug, module, description, created_at)
             VALUES (:n, :s, :m, :d, :t)'
        );
        foreach ($core as [$name, $slug, $module]) {
            $ins->execute(['n' => $name, 's' => $slug, 'm' => $module, 'd' => $name, 't' => $now]);
        }
    }

    private static function scalarInt(PDO $pdo, string $sql): int
    {
        $v = $pdo->query($sql)->fetchColumn();

        return (int) ($v ?: 0);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        return SqliteSchemaBootstrap::tableExists($pdo, $table);
    }

    /** @return array<string, true> */
    private static function tableColumns(PDO $pdo, string $table): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $out = [];
        $st = $pdo->query('PRAGMA table_info(' . $safe . ')');
        if ($st === false) {
            return $out;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) ($row['name'] ?? '')] = true;
        }

        return $out;
    }
}
