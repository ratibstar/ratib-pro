<?php
declare(strict_types=1);

/**
 * Emergency restore: platform super-admin accounts only (no business data).
 * Idempotent — skips existing emails; restores role mappings when missing.
 */
final class SuperAdminRestoreRunner
{
    /** Bcrypt for the standard dev/bootstrap password ("password") — change after login. */
    public const DEFAULT_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /** @var list<array{email:string,name:string,locale:string}> */
    private const SUPER_ADMINS = [
        ['email' => 'admin@rateb.sa', 'name' => 'Super Admin', 'locale' => 'ar'],
        ['email' => 'ahmedashrafabdalmonem77@gmail.com', 'name' => 'Ahmed Ashraf', 'locale' => 'ar'],
    ];

    private \PDO $db;

    /** @var array<string, mixed> */
    private array $report = [
        'mode' => 'forensic',
        'database' => null,
        'forensic' => [],
        'actions' => [],
        'restored_users' => 0,
        'updated_users' => 0,
        'role_mappings_restored' => 0,
        'password_hashes_reset' => 0,
        'errors' => [],
    ];

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? \Rateb\App\Core\Database::connection();
        if (method_exists(\Rateb\App\Core\Database::class, 'resolvedDatabaseName')) {
            $this->report['database'] = \Rateb\App\Core\Database::resolvedDatabaseName();
        }
    }

    /** @return array<string, mixed> */
    public function forensic(): array
    {
        $this->report['mode'] = 'forensic';
        $this->report['forensic'] = $this->collectForensic();
        return $this->report;
    }

    /** @return array<string, mixed> */
    public function restore(bool $resetPasswordHashes = true): array
    {
        $this->report['mode'] = 'restore';
        $this->report['forensic'] = $this->collectForensic();

        try {
            $this->db->beginTransaction();
            $roleId = $this->ensureSuperAdminRole();
            foreach (self::SUPER_ADMINS as $spec) {
                $this->ensureSuperAdminUser($spec, $roleId, $resetPasswordHashes);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->report['errors'][] = $e->getMessage();
            throw $e;
        }

        $this->report['forensic_after'] = $this->collectForensic();
        return $this->report;
    }

    /** @return array<string, mixed> */
    private function collectForensic(): array
    {
        $totalUsers = (int) $this->db->query('SELECT COUNT(*) FROM rateb_users')->fetchColumn();
        $superCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM rateb_users WHERE is_super_admin = 1'
        )->fetchColumn();
        $roleMapCount = (int) $this->db->query('SELECT COUNT(*) FROM rateb_user_roles')->fetchColumn();

        $superRole = $this->db->query(
            "SELECT id, slug FROM rateb_roles WHERE slug = 'super-admin' LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);

        $accounts = [];
        foreach (self::SUPER_ADMINS as $spec) {
            $email = $spec['email'];
            $stmt = $this->db->prepare(
                'SELECT id, email, name, is_super_admin, status, locale FROM rateb_users WHERE email = :e LIMIT 1'
            );
            $stmt->execute(['e' => $email]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            $roleLinked = false;
            if ($row && $superRole) {
                $chk = $this->db->prepare(
                    'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1'
                );
                $chk->execute(['uid' => (int) $row['id'], 'rid' => (int) $superRole['id']]);
                $roleLinked = (bool) $chk->fetchColumn();
            }
            $accounts[$email] = [
                'exists' => $row !== null,
                'id' => $row ? (int) $row['id'] : null,
                'is_super_admin' => $row ? (int) $row['is_super_admin'] === 1 : false,
                'status' => $row['status'] ?? null,
                'role_linked' => $roleLinked,
            ];
        }

        return [
            'rateb_users_total' => $totalUsers,
            'super_admin_count' => $superCount,
            'rateb_user_roles_total' => $roleMapCount,
            'super_admin_role' => $superRole ?: null,
            'accounts' => $accounts,
        ];
    }

    private function ensureSuperAdminRole(): int
    {
        $row = $this->db->query(
            "SELECT id FROM rateb_roles WHERE slug = 'super-admin' LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->exec(
            "INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
             VALUES (NULL, 'Super Admin', 'super-admin', 'Platform super administrator', 1)"
        );
        $id = (int) $this->db->lastInsertId();
        $this->report['actions'][] = 'inserted role super-admin id=' . $id;
        return $id;
    }

    /** @param array{email:string,name:string,locale:string} $spec */
    private function ensureSuperAdminUser(array $spec, int $roleId, bool $resetPasswordHashes): void
    {
        $email = $spec['email'];
        $stmt = $this->db->prepare('SELECT id, is_super_admin, status FROM rateb_users WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$existing) {
            $ins = $this->db->prepare(
                'INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
                 VALUES (NULL, :name, :email, :pass, 1, :status, :locale)'
            );
            $ins->execute([
                'name' => $spec['name'],
                'email' => $email,
                'pass' => self::DEFAULT_PASSWORD_HASH,
                'status' => 'active',
                'locale' => $spec['locale'],
            ]);
            $userId = (int) $this->db->lastInsertId();
            $this->report['restored_users']++;
            $this->report['actions'][] = 'inserted user id=' . $userId . ' email=' . $email;
        } else {
            $userId = (int) $existing['id'];
            $needsUpdate = (int) ($existing['is_super_admin'] ?? 0) !== 1
                || (string) ($existing['status'] ?? '') !== 'active';
            if ($needsUpdate) {
                $upd = $this->db->prepare(
                    'UPDATE rateb_users SET is_super_admin = 1, status = :status, name = :name, locale = :locale
                     WHERE id = :id'
                );
                $upd->execute([
                    'status' => 'active',
                    'name' => $spec['name'],
                    'locale' => $spec['locale'],
                    'id' => $userId,
                ]);
                $this->report['updated_users']++;
                $this->report['actions'][] = 'updated user flags id=' . $userId . ' email=' . $email;
            }
            if ($resetPasswordHashes) {
                $pw = $this->db->prepare('UPDATE rateb_users SET password = :pass WHERE id = :id');
                $pw->execute(['pass' => self::DEFAULT_PASSWORD_HASH, 'id' => $userId]);
                $this->report['password_hashes_reset']++;
                $this->report['actions'][] = 'reset password hash id=' . $userId . ' email=' . $email;
            }
        }

        $chk = $this->db->prepare(
            'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1'
        );
        $chk->execute(['uid' => $userId, 'rid' => $roleId]);
        if (!$chk->fetchColumn()) {
            $map = $this->db->prepare(
                'INSERT INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)'
            );
            $map->execute(['uid' => $userId, 'rid' => $roleId]);
            $this->report['role_mappings_restored']++;
            $this->report['actions'][] = 'linked user_id=' . $userId . ' role_id=' . $roleId;
        }
    }
}
