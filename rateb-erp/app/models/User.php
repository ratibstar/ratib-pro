<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class User extends Model
{
    protected string $table = 'rateb_users';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'name', 'email', 'password', 'phone', 'avatar_path',
        'is_super_admin', 'status', 'two_factor_secret', 'two_factor_enabled', 'locale', 'login_barcode',
    ];

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rateb_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rateb_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Email or short username (e.g. admin → admin@local or admin+slug@rateb.sa). */
    public function findByLogin(string $login): ?array
    {
        $candidates = $this->loginCandidates($login);

        return $candidates[0] ?? null;
    }

    /**
     * Resolve login to an active user row when password matches.
     * Tries every candidate (fixes stale admin@local vs admin+slug@rateb.sa).
     */
    public function authenticate(string $login, string $password): ?array
    {
        $password = (string) $password;
        if ($password === '') {
            return null;
        }

        foreach ($this->loginCandidates($login) as $user) {
            if ((string) ($user['status'] ?? '') !== 'active') {
                continue;
            }
            if (!self::passwordMatches($user, $password)) {
                continue;
            }

            return $user;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loginCandidates(string $login): array
    {
        $login = trim($login);
        if ($login === '') {
            return [];
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = $this->findByEmail($login);

            return $user ? [$user] : [];
        }

        $norm = strtolower($login);
        $localEmail = $norm . '@local';

        if ($norm === 'admin') {
            $rows = $this->query(
                "SELECT * FROM rateb_users
                 WHERE COALESCE(is_super_admin, 0) = 0
                   AND status = 'active'
                   AND (
                     email LIKE 'admin+%'
                     OR email = :local
                     OR LOWER(name) = 'admin'
                   )
                 ORDER BY
                   CASE
                     WHEN email LIKE 'admin+%@rateb.sa' THEN 0
                     WHEN email LIKE 'admin+%' THEN 1
                     WHEN LOWER(name) = 'admin' THEN 2
                     WHEN email = :local2 THEN 3
                     ELSE 9
                   END,
                   id ASC",
                ['local' => $localEmail, 'local2' => $localEmail]
            );
            if ($rows !== []) {
                return $rows;
            }

            $fallback = $this->queryOne(
                "SELECT u.* FROM rateb_users u
                 INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
                 INNER JOIN rateb_roles r ON r.id = ur.role_id AND r.slug = 'company-full-access'
                 WHERE COALESCE(u.is_super_admin, 0) = 0 AND u.status = 'active'
                 ORDER BY u.id ASC
                 LIMIT 1"
            );

            return $fallback ? [$fallback] : [];
        }

        $user = $this->queryOne(
            'SELECT * FROM rateb_users
             WHERE COALESCE(is_super_admin, 0) = 0
               AND LOWER(name) = :name
             ORDER BY id ASC
             LIMIT 1',
            ['name' => $norm]
        );

        return $user ? [$user] : [];
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function passwordMatches(array $user, string $password): bool
    {
        foreach (['password', 'password_hash'] as $column) {
            if (!array_key_exists($column, $user)) {
                continue;
            }
            $hash = (string) ($user[$column] ?? '');
            if ($hash !== '' && password_verify($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE rateb_users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function forCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM rateb_users WHERE company_id = :cid ORDER BY id DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':cid', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
