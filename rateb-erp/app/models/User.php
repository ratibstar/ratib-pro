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
        $login = trim($login);
        if ($login === '') {
            return null;
        }
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($login);
        }

        $norm = strtolower($login);
        $localEmail = $norm . '@local';

        $user = $this->findByEmail($localEmail);
        if ($user !== null) {
            return $user;
        }

        if ($norm === 'admin') {
            $user = $this->queryOne(
                "SELECT * FROM rateb_users
                 WHERE COALESCE(is_super_admin, 0) = 0
                   AND status = 'active'
                   AND (
                     email = :local
                     OR email LIKE 'admin+%'
                     OR LOWER(name) = 'admin'
                   )
                 ORDER BY
                   CASE
                     WHEN email = :local2 THEN 0
                     WHEN email LIKE 'admin+%@rateb.sa' THEN 1
                     WHEN email LIKE 'admin+%' THEN 2
                     WHEN LOWER(name) = 'admin' THEN 3
                     ELSE 9
                   END,
                   id ASC
                 LIMIT 1",
                ['local' => $localEmail, 'local2' => $localEmail]
            );
            if ($user !== null) {
                return $user;
            }
        }

        $user = $this->queryOne(
            'SELECT * FROM rateb_users
             WHERE COALESCE(is_super_admin, 0) = 0
               AND LOWER(name) = :name
             ORDER BY id ASC
             LIMIT 1',
            ['name' => $norm]
        );

        return $user ?: null;
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
